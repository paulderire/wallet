<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if employee is logged in
if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/currency.php';

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';
$employee_role = $_SESSION['employee_role'] ?? '';
$user_id = $_SESSION['employee_user_id'] ?? 0;

// Get today's date
$today = date('Y-m-d');

// Fetch today's sales summary
$today_sales = [
  'total_amount' => 0,
  'total_transactions' => 0,
  'cash' => 0,
  'mobile_money' => 0,
  'bank_transfer' => 0,
  'credit' => 0
];

try {
  $stmt = $conn->prepare("SELECT 
    COUNT(*) as total_transactions,
    COALESCE(SUM(total_amount), 0) as total_amount,
    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as cash,
    COALESCE(SUM(CASE WHEN payment_method = 'mobile_money' THEN total_amount ELSE 0 END), 0) as mobile_money,
    COALESCE(SUM(CASE WHEN payment_method = 'bank_transfer' THEN total_amount ELSE 0 END), 0) as bank_transfer,
    COALESCE(SUM(CASE WHEN payment_method = 'credit' THEN total_amount ELSE 0 END), 0) as credit
    FROM employee_tasks 
    WHERE employee_id = ? AND task_date = ? AND transaction_type = 'sale'");
  $stmt->execute([$employee_id, $today]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  if ($result) {
    $today_sales = $result;
  }
} catch (Exception $e) {}

// Fetch today's transactions
$today_transactions = [];
try {
  $stmt = $conn->prepare("SELECT * FROM employee_tasks 
    WHERE employee_id = ? AND task_date = ? 
    ORDER BY task_time DESC LIMIT 10");
  $stmt->execute([$employee_id, $today]);
  $today_transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch pending stock alerts
$pending_alerts = [];
try {
  $stmt = $conn->prepare("SELECT * FROM inventory_alerts 
    WHERE employee_id = ? AND status = 'pending' 
    ORDER BY urgency DESC, alert_date DESC LIMIT 5");
  $stmt->execute([$employee_id]);
  $pending_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get week statistics
$week_sales = [];
try {
  $stmt = $conn->prepare("SELECT 
    task_date,
    COALESCE(SUM(total_amount), 0) as daily_total,
    COUNT(*) as transaction_count
    FROM employee_tasks 
    WHERE employee_id = ? 
    AND task_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND transaction_type = 'sale'
    GROUP BY task_date
    ORDER BY task_date ASC");
  $stmt->execute([$employee_id]);
  $week_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$week_total = array_sum(array_column($week_sales, 'daily_total'));

// Get my assigned tasks from projects
$my_tasks = [];
try {
  $stmt = $conn->prepare("
    SELECT pt.*, p.project_name, p.project_code
    FROM project_tasks pt
    JOIN projects p ON pt.project_id = p.id
    WHERE pt.assigned_to_employee = ? AND pt.status != 'completed'
    ORDER BY pt.due_date ASC
    LIMIT 5
  ");
  $stmt->execute([$employee_id]);
  $my_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Dashboard - MY CASH</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  
  <style>
    :root {
      --emp-primary: #10b981;
      --emp-primary-dark: #059669;
      --emp-bg: #f8fafc;
      --emp-card-bg: #ffffff;
      --emp-text: #1e293b;
      --emp-text-muted: #64748b;
      --emp-border: #e2e8f0;
      --emp-sidebar-bg: #ffffff;
      --emp-sidebar-text: #475569;
      --emp-sidebar-hover: #f1f5f9;
      --emp-sidebar-active: #10b981;
      --emp-shadow: 0 1px 3px rgba(0,0,0,0.1);
      --emp-shadow-lg: 0 10px 25px rgba(0,0,0,0.08);
    }

    [data-theme="dark"] {
      --emp-primary: #10b981;
      --emp-primary-dark: #34d399;
      --emp-bg: #0f172a;
      --emp-card-bg: #1e293b;
      --emp-text: #f1f5f9;
      --emp-text-muted: #94a3b8;
      --emp-border: #334155;
      --emp-sidebar-bg: #1e293b;
      --emp-sidebar-text: #cbd5e1;
      --emp-sidebar-hover: #334155;
      --emp-sidebar-active: #10b981;
      --emp-shadow: 0 1px 3px rgba(0,0,0,0.3);
      --emp-shadow-lg: 0 10px 25px rgba(0,0,0,0.5);
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: var(--emp-bg);
      color: var(--emp-text);
      min-height: 100vh;
      display: flex;
      transition: background-color 0.3s, color 0.3s;
    }

    /* Sidebar */
    .sidebar {
      width: 280px;
      background: var(--emp-sidebar-bg);
      border-right: 1px solid var(--emp-border);
      height: 100vh;
      position: fixed;
      left: 0;
      top: 0;
      overflow-y: auto;
      z-index: 1000;
      box-shadow: var(--emp-shadow-lg);
      transition: all 0.3s;
    }

    .sidebar-header {
      padding: 24px 20px;
      border-bottom: 1px solid var(--emp-border);
    }

    .sidebar-logo {
      font-size: 1.5rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--emp-primary), var(--emp-primary-dark));
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 8px;
    }

    .sidebar-user {
      font-size: 0.9rem;
      color: var(--emp-text);
      font-weight: 600;
      margin-bottom: 4px;
    }

    .sidebar-role {
      font-size: 0.75rem;
      color: var(--emp-text-muted);
    }

    .sidebar-nav {
      padding: 20px 0;
    }

    .nav-section-title {
      padding: 8px 20px;
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--emp-text-muted);
      margin-top: 16px;
    }

    .nav-item {
      display: flex;
      align-items: center;
      padding: 12px 20px;
      color: var(--emp-sidebar-text);
      text-decoration: none;
      transition: all 0.2s;
      font-size: 0.9rem;
      font-weight: 500;
    }

    .nav-item:hover {
      background: var(--emp-sidebar-hover);
      color: var(--emp-text);
    }

    .nav-item.active {
      background: linear-gradient(90deg, rgba(16,185,129,0.1), transparent);
      color: var(--emp-sidebar-active);
      border-left: 3px solid var(--emp-sidebar-active);
      font-weight: 600;
    }

    .nav-icon {
      margin-right: 12px;
      font-size: 1.2rem;
    }

    .theme-toggle {
      padding: 12px 20px;
      margin: 0 20px 20px;
      background: var(--emp-sidebar-hover);
      border: 1px solid var(--emp-border);
      border-radius: 12px;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--emp-text);
      transition: all 0.2s;
    }

    .theme-toggle:hover {
      background: var(--emp-border);
    }

    /* Main Content */
    .main-content {
      margin-left: 280px;
      flex: 1;
      padding: 32px;
      width: calc(100% - 280px);
    }

    .page-header {
      margin-bottom: 32px;
    }

    .page-title {
      font-size: 2rem;
      font-weight: 800;
      margin-bottom: 8px;
      color: var(--emp-text);
    }

    .page-subtitle {
      color: var(--emp-text-muted);
      font-size: 0.95rem;
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 32px;
    }

    .stat-card {
      background: var(--emp-card-bg);
      border: 1px solid var(--emp-border);
      border-radius: 16px;
      padding: 24px;
      box-shadow: var(--emp-shadow);
      transition: all 0.3s;
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--emp-primary), var(--emp-primary-dark));
    }

    .stat-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--emp-shadow-lg);
    }

    .stat-icon {
      font-size: 2rem;
      margin-bottom: 12px;
    }

    .stat-label {
      font-size: 0.85rem;
      color: var(--emp-text-muted);
      margin-bottom: 8px;
      font-weight: 500;
    }

    .stat-value {
      font-size: 2rem;
      font-weight: 700;
      color: var(--emp-text);
      margin-bottom: 8px;
    }

    .stat-change {
      font-size: 0.75rem;
      color: var(--emp-primary);
      font-weight: 600;
    }

    /* Card */
    .card {
      background: var(--emp-card-bg);
      border: 1px solid var(--emp-border);
      border-radius: 16px;
      padding: 24px;
      box-shadow: var(--emp-shadow);
      margin-bottom: 24px;
    }

    .card-title {
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: 20px;
      color: var(--emp-text);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Transaction List */
    .transaction-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .transaction-item {
      padding: 16px;
      background: var(--emp-bg);
      border: 1px solid var(--emp-border);
      border-radius: 12px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      transition: all 0.2s;
    }

    .transaction-item:hover {
      border-color: var(--emp-primary);
      background: var(--emp-card-bg);
    }

    .transaction-info {
      flex: 1;
    }

    .transaction-type {
      font-weight: 600;
      color: var(--emp-text);
      margin-bottom: 4px;
      font-size: 0.95rem;
    }

    .transaction-meta {
      font-size: 0.8rem;
      color: var(--emp-text-muted);
    }

    .transaction-amount {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--emp-primary);
    }

    /* Alert Item */
    .alert-item {
      padding: 16px;
      background: var(--emp-bg);
      border: 1px solid var(--emp-border);
      border-left: 4px solid #f59e0b;
      border-radius: 12px;
      margin-bottom: 12px;
      transition: all 0.2s;
    }

    .alert-item:hover {
      background: var(--emp-card-bg);
      box-shadow: var(--emp-shadow);
    }

    .alert-item.high {
      border-left-color: #ef4444;
    }

    .alert-item.medium {
      border-left-color: #f59e0b;
    }

    .alert-item.low {
      border-left-color: #10b981;
    }

    .alert-header {
      display: flex;
      justify-content: space-between;
      align-items: start;
      margin-bottom: 8px;
    }

    .alert-title {
      font-weight: 600;
      color: var(--emp-text);
      font-size: 0.95rem;
    }

    .alert-urgency {
      padding: 4px 12px;
      border-radius: 12px;
      font-size: 0.7rem;
      font-weight: 700;
      text-transform: uppercase;
    }

    .urgency-high {
      background: rgba(239,68,68,0.15);
      color: #ef4444;
    }

    .urgency-medium {
      background: rgba(245,158,11,0.15);
      color: #f59e0b;
    }

    .urgency-low {
      background: rgba(16,185,129,0.15);
      color: #10b981;
    }

    .alert-meta {
      font-size: 0.85rem;
      color: var(--emp-text-muted);
      line-height: 1.5;
    }

    /* Task Item */
    .task-item {
      padding: 14px;
      background: var(--emp-bg);
      border: 1px solid var(--emp-border);
      border-left: 4px solid var(--emp-primary);
      border-radius: 10px;
      margin-bottom: 10px;
      transition: all 0.2s;
    }

    .task-item:hover {
      background: var(--emp-card-bg);
      box-shadow: var(--emp-shadow);
    }

    .task-title {
      font-weight: 600;
      color: var(--emp-text);
      margin-bottom: 6px;
      font-size: 0.9rem;
    }

    .task-project {
      font-size: 0.75rem;
      color: var(--emp-text-muted);
      font-family: monospace;
      margin-bottom: 6px;
    }

    .task-footer {
      display: flex;
      gap: 12px;
      font-size: 0.75rem;
      color: var(--emp-text-muted);
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 48px 24px;
    }

    .empty-icon {
      font-size: 3rem;
      margin-bottom: 16px;
      opacity: 0.3;
    }

    .empty-state p {
      color: var(--emp-text-muted);
    }

    /* Responsive */
    @media (max-width: 768px) {
      .sidebar {
        transform: translateX(-100%);
      }

      .sidebar.open {
        transform: translateX(0);
      }

      .main-content {
        margin-left: 0;
        width: 100%;
      }

      .stats-grid {
        grid-template-columns: 1fr;
      }
    }

    /* Logout Button */
    .logout-btn {
      margin: 20px;
      padding: 12px 20px;
      background: linear-gradient(135deg, #ef4444, #dc2626);
      color: white;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
      width: calc(100% - 40px);
      font-size: 0.9rem;
      transition: all 0.2s;
    }

    .logout-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(239,68,68,0.3);
    }
  </style>
</head>
<body>
  
  <!-- Sidebar -->
  <div class="sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">💰 MY CASH</div>
      <div class="sidebar-user"><?= htmlspecialchars($employee_name) ?></div>
      <div class="sidebar-role"><?= htmlspecialchars($employee_role ?: 'Employee') ?></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-title">MAIN</div>
      <a href="/MY CASH/employee/dashboard.php" class="nav-item active">
        <span class="nav-icon">📊</span>
        <span>Dashboard</span>
      </a>
      <a href="/MY CASH/employee/tasks.php" class="nav-item">
        <span class="nav-icon">✅</span>
        <span>My Tasks</span>
      </a>
      <a href="/MY CASH/employee/sales.php" class="nav-item">
        <span class="nav-icon">💰</span>
        <span>Sales</span>
      </a>
      <a href="/MY CASH/employee/stock.php" class="nav-item">
        <span class="nav-icon">📦</span>
        <span>Stock Alerts</span>
      </a>

      <div class="nav-section-title">ACCOUNT</div>
      <a href="/MY CASH/employee/profile.php" class="nav-item">
        <span class="nav-icon">👤</span>
        <span>My Profile</span>
      </a>
      <a href="/MY CASH/employee/attendance.php" class="nav-item">
        <span class="nav-icon">🕒</span>
        <span>Attendance</span>
      </a>
      <a href="/MY CASH/employee/chat.php" class="nav-item">
        <span class="nav-icon">💬</span>
        <span>Team Chat</span>
      </a>
    </nav>

    <button class="theme-toggle" onclick="toggleTheme()">
      <span id="theme-text">🌙 Dark Mode</span>
      <span id="theme-icon">🌙</span>
    </button>

    <button class="logout-btn" onclick="window.location.href='/MY CASH/employee_login.php?logout=1'">
      🚪 Logout
    </button>
  </div>

  <!-- Main Content -->
  <div class="main-content">
    <div class="page-header">
      <h1 class="page-title">👋 Welcome back, <?= htmlspecialchars(explode(' ', $employee_name)[0]) ?>!</h1>
      <p class="page-subtitle">Here's what's happening with your work today</p>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">💵</div>
        <div class="stat-label">Today's Sales</div>
        <div class="stat-value">RWF <?= number_format($today_sales['total_amount']) ?></div>
        <div class="stat-change"><?= $today_sales['total_transactions'] ?> transactions</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">📅</div>
        <div class="stat-label">Week's Sales</div>
        <div class="stat-value">RWF <?= number_format($week_total) ?></div>
        <div class="stat-change"><?= count($week_sales) ?> days recorded</div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">⚠️</div>
        <div class="stat-label">Stock Alerts</div>
        <div class="stat-value"><?= count($pending_alerts) ?></div>
        <div class="stat-change"><?= count($pending_alerts) > 0 ? 'Needs attention' : 'All good!' ?></div>
      </div>

      <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-label">My Tasks</div>
        <div class="stat-value"><?= count($my_tasks) ?></div>
        <div class="stat-change">Pending completion</div>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card">
      <h2 class="card-title">
        <span>💰</span>
        Today's Transactions
      </h2>

      <?php if (empty($today_transactions)): ?>
        <div class="empty-state">
          <div class="empty-icon">📝</div>
          <p>No transactions recorded today</p>
          <p style="font-size:0.85rem;margin-top:8px">Start recording your sales and activities</p>
        </div>
      <?php else: ?>
        <div class="transaction-list">
          <?php foreach ($today_transactions as $trans): ?>
            <div class="transaction-item">
              <div class="transaction-info">
                <div class="transaction-type">
                  <?= ucfirst(str_replace('_', ' ', $trans['transaction_type'])) ?>
                  <?php if ($trans['customer_name']): ?>
                    • <?= htmlspecialchars($trans['customer_name']) ?>
                  <?php endif; ?>
                </div>
                <div class="transaction-meta">
                  <?= date('g:i A', strtotime($trans['task_time'])) ?> • 
                  <?= ucfirst($trans['payment_method']) ?>
                </div>
              </div>
              <div class="transaction-amount">
                RWF <?= number_format($trans['total_amount']) ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <!-- My Project Tasks -->
      <div class="card">
        <h2 class="card-title">
          <span>✅</span>
          My Project Tasks
        </h2>

        <?php if (empty($my_tasks)): ?>
          <div class="empty-state" style="padding:32px 16px">
            <div class="empty-icon">🎯</div>
            <p>No tasks assigned</p>
          </div>
        <?php else: ?>
          <?php foreach ($my_tasks as $task): ?>
            <div class="task-item">
              <div class="task-title"><?= htmlspecialchars($task['task_name']) ?></div>
              <div class="task-project">
                <?= htmlspecialchars($task['project_name']) ?> • <?= htmlspecialchars($task['project_code']) ?>
              </div>
              <div class="task-footer">
                <?php if ($task['due_date']): ?>
                  <span>📅 Due: <?= date('M j', strtotime($task['due_date'])) ?></span>
                <?php endif; ?>
                <span style="text-transform:uppercase;font-weight:600;color:var(--emp-primary)">
                  <?= str_replace('_', ' ', $task['status']) ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Stock Alerts -->
      <div class="card">
        <h2 class="card-title">
          <span>📦</span>
          Stock Alerts
        </h2>

        <?php if (empty($pending_alerts)): ?>
          <div class="empty-state" style="padding:32px 16px">
            <div class="empty-icon">✅</div>
            <p>All stock levels are good!</p>
          </div>
        <?php else: ?>
          <?php foreach ($pending_alerts as $alert): ?>
            <div class="alert-item <?= $alert['urgency'] ?>">
              <div class="alert-header">
                <div class="alert-title"><?= htmlspecialchars($alert['item_name']) ?></div>
                <span class="alert-urgency urgency-<?= $alert['urgency'] ?>">
                  <?= strtoupper($alert['urgency']) ?>
                </span>
              </div>
              <div class="alert-meta">
                <strong><?= ucfirst(str_replace('_', ' ', $alert['alert_type'])) ?></strong>
                <?php if ($alert['current_quantity'] > 0): ?>
                  • <?= $alert['current_quantity'] ?> left
                <?php endif; ?>
                <br>
                <span style="font-size:0.75rem">
                  📅 <?= date('M j, g:i A', strtotime($alert['alert_date'] . ' ' . $alert['alert_time'])) ?>
                </span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    // Theme Toggle
    function toggleTheme() {
      const html = document.documentElement;
      const currentTheme = html.getAttribute('data-theme');
      const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
      
      html.setAttribute('data-theme', newTheme);
      localStorage.setItem('employee-theme', newTheme);
      
      const themeText = document.getElementById('theme-text');
      const themeIcon = document.getElementById('theme-icon');
      
      if (newTheme === 'dark') {
        themeText.textContent = '☀️ Light Mode';
        themeIcon.textContent = '☀️';
      } else {
        themeText.textContent = '🌙 Dark Mode';
        themeIcon.textContent = '🌙';
      }
    }

    // Load saved theme
    window.addEventListener('DOMContentLoaded', () => {
      const savedTheme = localStorage.getItem('employee-theme') || 'light';
      document.documentElement.setAttribute('data-theme', savedTheme);
      
      const themeText = document.getElementById('theme-text');
      const themeIcon = document.getElementById('theme-icon');
      
      if (savedTheme === 'dark') {
        themeText.textContent = '☀️ Light Mode';
        themeIcon.textContent = '☀️';
      }
    });
  </script>
</body>
</html>
