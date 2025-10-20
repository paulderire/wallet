<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION['user_id'])){ header("Location: /MY CASH/pages/login.php"); exit; }
include __DIR__ . '/../includes/db.php';

// Check if user is admin
$is_admin = false;
try {
  $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
  $stmt->execute([$_SESSION['user_id']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  $is_admin = !empty($user['is_admin']);
} catch (Exception $e) {}

if (!$is_admin) {
  header("Location: /MY CASH/pages/dashboard.php");
  exit;
}

$user_id = $_SESSION['user_id'];
$success_msg = '';
$error_msg = '';

// Handle status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  try {
    $alert_id = intval($_POST['alert_id'] ?? 0);
    $action = $_POST['action'];
    
    if ($alert_id <= 0) {
      throw new Exception("Invalid alert ID");
    }
    
    if ($action === 'acknowledge') {
      $stmt = $conn->prepare("UPDATE inventory_alerts SET status = 'acknowledged', acknowledged_at = NOW() WHERE id = ?");
      $stmt->execute([$alert_id]);
      $success_msg = "Alert acknowledged successfully!";
    } elseif ($action === 'resolve') {
      $stmt = $conn->prepare("UPDATE inventory_alerts SET status = 'resolved', resolved_at = NOW() WHERE id = ?");
      $stmt->execute([$alert_id]);
      $success_msg = "Alert marked as resolved!";
    } elseif ($action === 'reopen') {
      $stmt = $conn->prepare("UPDATE inventory_alerts SET status = 'pending', acknowledged_at = NULL, resolved_at = NULL WHERE id = ?");
      $stmt->execute([$alert_id]);
      $success_msg = "Alert reopened successfully!";
    }
  } catch (Exception $e) {
    $error_msg = "Error: " . $e->getMessage();
  }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_urgency = $_GET['urgency'] ?? 'all';
$filter_type = $_GET['type'] ?? 'all';

// Build query
$query = "SELECT ia.*, e.first_name, e.last_name, e.employee_id 
          FROM inventory_alerts ia 
          LEFT JOIN employees e ON ia.employee_id = e.id 
          WHERE ia.user_id = ?";

$params = [$user_id];

if ($filter_status !== 'all') {
  $query .= " AND ia.status = ?";
  $params[] = $filter_status;
}

if ($filter_urgency !== 'all') {
  $query .= " AND ia.urgency = ?";
  $params[] = $filter_urgency;
}

if ($filter_type !== 'all') {
  $query .= " AND ia.alert_type = ?";
  $params[] = $filter_type;
}

$query .= " ORDER BY 
            CASE ia.urgency 
              WHEN 'critical' THEN 1 
              WHEN 'high' THEN 2 
              WHEN 'medium' THEN 3 
              WHEN 'low' THEN 4 
            END,
            ia.alert_date DESC, ia.alert_time DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$stats_query = "SELECT 
  COUNT(*) as total_alerts,
  SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
  SUM(CASE WHEN status = 'acknowledged' THEN 1 ELSE 0 END) as acknowledged,
  SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
  SUM(CASE WHEN urgency = 'critical' THEN 1 ELSE 0 END) as critical,
  SUM(CASE WHEN urgency = 'high' THEN 1 ELSE 0 END) as high
  FROM inventory_alerts";

$stats_stmt = $conn->query($stats_query);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<style>
  /* Dark Mode Variables */
  :root {
    --inv-bg-gradient-start: #667eea;
    --inv-bg-gradient-end: #764ba2;
    --inv-card-bg: rgba(255, 255, 255, 0.98);
    --inv-text-primary: #1e293b;
    --inv-text-secondary: #64748b;
    --inv-border: #e2e8f0;
    --inv-hover-bg: rgba(248, 250, 252, 0.8);
    --inv-input-bg: #ffffff;
  }

  [data-theme="dark"] {
    --inv-bg-gradient-start: #1a1a2e;
    --inv-bg-gradient-end: #16213e;
    --inv-card-bg: rgba(30, 30, 46, 0.95);
    --inv-text-primary: #e2e8f0;
    --inv-text-secondary: #94a3b8;
    --inv-border: #334155;
    --inv-hover-bg: rgba(148, 163, 184, 0.1);
    --inv-input-bg: rgba(30, 30, 46, 0.8);
  }

  /* Override container width for this page */
  #app-main.container {
    max-width: 100% !important;
    width: 100% !important;
    padding: 0 !important;
  }

  #app-main {
    background: linear-gradient(135deg, var(--inv-bg-gradient-start) 0%, var(--inv-bg-gradient-end) 100%);
    min-height: calc(100vh - 64px);
  }

  .inventory-wrapper {
    max-width: 1400px;
    margin: 0 auto;
    padding: 32px;
  }

  .header {
    background: var(--inv-card-bg);
    backdrop-filter: blur(20px);
    padding: 28px 36px;
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    margin-bottom: 28px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border: 1px solid var(--inv-border);
  }

  .header-title {
    font-size: 2rem;
    font-weight: 900;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    display: flex;
    align-items: center;
    gap: 12px;
  }

  [data-theme="dark"] .header-title {
    background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
  }

  .header-subtitle {
    color: var(--inv-text-secondary);
    font-size: 0.95rem;
    margin-top: 6px;
  }

  .btn {
    padding: 12px 24px;
    border-radius: 12px;
    font-weight: 600;
    text-decoration: none;
    font-size: 0.95rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
  }

  .btn-secondary {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
    border: 2px solid rgba(102, 126, 234, 0.2);
  }

  [data-theme="dark"] .btn-secondary {
    background: rgba(148, 163, 184, 0.15);
    color: #a78bfa;
    border: 2px solid rgba(148, 163, 184, 0.3);
  }

  .btn-secondary:hover {
    background: rgba(102, 126, 234, 0.2);
    transform: translateY(-2px);
  }

  [data-theme="dark"] .btn-secondary:hover {
    background: rgba(148, 163, 184, 0.25);
  }

  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
  }

  .stat-card {
    background: var(--inv-card-bg);
    backdrop-filter: blur(20px);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
    border: 1px solid var(--inv-border);
  }

  .stat-label {
    font-size: 0.8rem;
    color: var(--inv-text-secondary);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 10px;
  }

  .stat-value {
    font-size: 2.25rem;
    font-weight: 900;
    color: var(--inv-text-primary);
  }

  .stat-card.critical {
    border-left: 4px solid #dc2626;
  }

  .stat-card.high {
    border-left: 4px solid #f97316;
  }

  .stat-card.pending {
    border-left: 4px solid #f59e0b;
  }

  .card {
    background: var(--inv-card-bg);
    backdrop-filter: blur(20px);
    padding: 28px;
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
    margin-bottom: 28px;
    border: 1px solid var(--inv-border);
  }

  .card-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: var(--inv-text-primary);
    margin-bottom: 20px;
  }

  .alert {
    padding: 16px 20px;
    border-radius: 14px;
    margin-bottom: 20px;
    font-size: 0.95rem;
    font-weight: 600;
  }

  .alert-success {
    background: #d4f4dd;
    color: #22543d;
    border-left: 4px solid #10b981;
  }

  [data-theme="dark"] .alert-success {
    background: rgba(16, 185, 129, 0.2);
    color: #6ee7b7;
  }

  .alert-error {
    background: #fecaca;
    color: #7f1d1d;
    border-left: 4px solid #ef4444;
  }

  [data-theme="dark"] .alert-error {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
  }

  .filters {
    display: flex;
    gap: 14px;
    margin-bottom: 24px;
    flex-wrap: wrap;
  }

  .filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
  }
  .filter-label {
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--inv-text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .filter-select {
    padding: 10px 14px;
    border: 2px solid var(--inv-border);
    border-radius: 10px;
    font-family: 'Inter', sans-serif;
    font-size: 0.95rem;
    font-weight: 600;
    cursor: pointer;
    background: var(--inv-input-bg);
    color: var(--inv-text-primary);
    transition: all 0.3s ease;
  }

  .filter-select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }

  .table-container {
    overflow-x: auto;
    border-radius: 16px;
  }

  .alerts-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.95rem;
  }

  .alerts-table thead {
    background: var(--inv-hover-bg);
  }

  [data-theme="dark"] .alerts-table thead {
    background: rgba(15, 23, 42, 0.5);
  }

  .alerts-table th {
    padding: 14px 18px;
    text-align: left;
    font-weight: 700;
    color: var(--inv-text-primary);
    border-bottom: 2px solid var(--inv-border);
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
  }

  .alerts-table td {
    padding: 16px 18px;
    border-bottom: 1px solid var(--inv-border);
    color: var(--inv-text-primary);
  }

  .alerts-table tr:hover {
    background: var(--inv-hover-bg);
  }

  .urgency-badge {
    padding: 5px 14px;
    border-radius: 14px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-block;
    letter-spacing: 0.3px;
  }

  .urgency-critical {
    background: #dc2626;
    color: white;
    box-shadow: 0 2px 8px rgba(220, 38, 38, 0.3);
  }

  .urgency-high {
    background: #f97316;
    color: white;
    box-shadow: 0 2px 8px rgba(249, 115, 22, 0.3);
  }

  .urgency-medium {
    background: #f59e0b;
    color: white;
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.3);
  }

  .urgency-low {
    background: #3b82f6;
    color: white;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
  }
    
  .status-badge {
    padding: 5px 14px;
    border-radius: 14px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    display: inline-block;
    letter-spacing: 0.3px;
  }
  
  .status-pending {
    background: rgba(254, 243, 199, 1);
    color: #78350f;
  }

  [data-theme="dark"] .status-pending {
    background: rgba(120, 53, 15, 0.3);
    color: #fef3c7;
  }
  
  .status-acknowledged {
    background: rgba(219, 234, 254, 1);
    color: #1e40af;
  }

  [data-theme="dark"] .status-acknowledged {
    background: rgba(30, 64, 175, 0.3);
    color: #dbeafe;
  }
  
  .status-resolved {
    background: rgba(212, 244, 221, 1);
    color: #22543d;
  }

  [data-theme="dark"] .status-resolved {
    background: rgba(34, 84, 61, 0.3);
    color: #d4f4dd;
  }
  
  .action-buttons {
    display: flex;
    gap: 10px;
  }
  
  .btn-small {
    padding: 8px 16px;
    font-size: 0.8rem;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
  }
  
  .btn-success {
    background: #10b981;
    color: white;
  }
  
  .btn-success:hover {
    background: #059669;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  }
  
  .btn-info {
    background: #3b82f6;
    color: white;
  }
  
  .btn-info:hover {
    background: #2563eb;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
  }
  
  .btn-warning {
    background: #f59e0b;
    color: white;
  }
  
  .btn-warning:hover {
    background: #d97706;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
  }
  
  .empty-state {
    text-align: center;
    padding: 64px 24px;
    color: var(--inv-text-secondary);
  }
  
  @media (max-width: 768px) {
      .inventory-wrapper {
        padding: 16px;
      }
      
      .header {
        flex-direction: column;
        gap: 16px;
      }
      
      .filters {
        flex-direction: column;
      }
      
      .filter-group {
        width: 100%;
      }
      
      .table-container {
        font-size: 12px;
      }
      
      .alerts-table th,
      .alerts-table td {
        padding: 8px;
      }
    }
  </style>

  <div class="inventory-wrapper">
    <!-- Header -->
    <div class="header">
      <div>
        <h1 class="header-title">🚨 Inventory Alerts Manager</h1>
        <p class="header-subtitle">Monitor and respond to stock alerts from employees</p>
      </div>
      <a href="/MY CASH/business/dashboard.php" class="btn btn-secondary">
        ← Back to Dashboard
      </a>
    </div>
    
    <!-- Statistics -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-label">Total Alerts</div>
        <div class="stat-value"><?php echo $stats['total_alerts']; ?></div>
      </div>
      
      <div class="stat-card pending">
        <div class="stat-label">⏳ Pending</div>
        <div class="stat-value"><?php echo $stats['pending']; ?></div>
      </div>
      
      <div class="stat-card high">
        <div class="stat-label">🔴 Critical</div>
        <div class="stat-value"><?php echo $stats['critical']; ?></div>
      </div>
      
      <div class="stat-card high">
        <div class="stat-label">🟠 High Priority</div>
        <div class="stat-value"><?php echo $stats['high']; ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-label">👁️ Acknowledged</div>
        <div class="stat-value"><?php echo $stats['acknowledged']; ?></div>
      </div>
      
      <div class="stat-card">
        <div class="stat-label">✅ Resolved</div>
        <div class="stat-value"><?php echo $stats['resolved']; ?></div>
      </div>
    </div>
    
    <!-- Alerts Card -->
    <div class="card">
      <?php if ($success_msg): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($success_msg); ?></div>
      <?php endif; ?>
      
      <?php if ($error_msg): ?>
        <div class="alert alert-error">❌ <?php echo htmlspecialchars($error_msg); ?></div>
      <?php endif; ?>
      
      <h2 class="card-title">📋 All Alerts</h2>
      
      <!-- Filters -->
      <form method="GET" action="">
        <div class="filters">
          <div class="filter-group">
            <label class="filter-label">Status</label>
            <select name="status" class="filter-select" onchange="this.form.submit()">
              <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
              <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
              <option value="acknowledged" <?php echo $filter_status === 'acknowledged' ? 'selected' : ''; ?>>Acknowledged</option>
              <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Urgency</label>
            <select name="urgency" class="filter-select" onchange="this.form.submit()">
              <option value="all" <?php echo $filter_urgency === 'all' ? 'selected' : ''; ?>>All Urgency</option>
              <option value="critical" <?php echo $filter_urgency === 'critical' ? 'selected' : ''; ?>>Critical</option>
              <option value="high" <?php echo $filter_urgency === 'high' ? 'selected' : ''; ?>>High</option>
              <option value="medium" <?php echo $filter_urgency === 'medium' ? 'selected' : ''; ?>>Medium</option>
              <option value="low" <?php echo $filter_urgency === 'low' ? 'selected' : ''; ?>>Low</option>
            </select>
          </div>
          
          <div class="filter-group">
            <label class="filter-label">Alert Type</label>
            <select name="type" class="filter-select" onchange="this.form.submit()">
              <option value="all" <?php echo $filter_type === 'all' ? 'selected' : ''; ?>>All Types</option>
              <option value="out_of_stock" <?php echo $filter_type === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
              <option value="low_stock" <?php echo $filter_type === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
              <option value="damaged" <?php echo $filter_type === 'damaged' ? 'selected' : ''; ?>>Damaged</option>
              <option value="expired" <?php echo $filter_type === 'expired' ? 'selected' : ''; ?>>Expired</option>
            </select>
          </div>
        </div>
      </form>
      
      <!-- Table -->
      <?php if (empty($alerts)): ?>
        <div class="empty-state">
          <div style="font-size: 64px; margin-bottom: 16px;">✅</div>
          <h3 style="margin-bottom: 8px;">No Alerts Found</h3>
          <p>All clear! No inventory alerts match your current filters.</p>
        </div>
      <?php else: ?>
        <div class="table-container">
          <table class="alerts-table">
            <thead>
              <tr>
                <th>Item Name</th>
                <th>Employee</th>
                <th>Alert Type</th>
                <th>Qty</th>
                <th>Urgency</th>
                <th>Status</th>
                <th>Date & Time</th>
                <th>Notes</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($alerts as $alert): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($alert['item_name']); ?></strong></td>
                  <td>
                    <?php echo htmlspecialchars($alert['first_name'] . ' ' . $alert['last_name']); ?>
                    <br><small style="color: #718096;"><?php echo htmlspecialchars($alert['employee_id']); ?></small>
                  </td>
                  <td><?php echo ucfirst(str_replace('_', ' ', $alert['alert_type'])); ?></td>
                  <td><?php echo $alert['current_quantity']; ?></td>
                  <td>
                    <span class="urgency-badge urgency-<?php echo $alert['urgency']; ?>">
                      <?php echo strtoupper($alert['urgency']); ?>
                    </span>
                  </td>
                  <td>
                    <span class="status-badge status-<?php echo $alert['status']; ?>">
                      <?php echo $alert['status']; ?>
                    </span>
                  </td>
                  <td>
                    <?php echo date('M j, Y', strtotime($alert['alert_date'])); ?>
                    <br><small style="color: #718096;"><?php echo date('g:i A', strtotime($alert['alert_time'])); ?></small>
                  </td>
                  <td>
                    <?php if ($alert['notes']): ?>
                      <small><?php echo htmlspecialchars(substr($alert['notes'], 0, 50)); ?><?php echo strlen($alert['notes']) > 50 ? '...' : ''; ?></small>
                    <?php else: ?>
                      <small style="color: #a0aec0;">-</small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div class="action-buttons">
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                        <input type="hidden" name="action" value="acknowledge">
                        <button type="submit" class="btn btn-info btn-small">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                          </svg>
                          Ack
                        </button>
                      </form>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                        <input type="hidden" name="action" value="resolve">
                        <button type="submit" class="btn btn-success btn-small">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;">
                            <polyline points="20 6 9 17 4 12"></polyline>
                          </svg>
                          Resolve
                        </button>
                      </form>
                                        </td>
                  <td>
                    <div class="action-buttons">
                      <?php if ($alert['status'] === 'pending'): ?>
                        <form method="POST" style="display: inline;">
                          <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                          <input type="hidden" name="action" value="acknowledge">
                          <button type="submit" class="btn btn-info btn-small">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;">
                              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                              <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            Ack
                          </button>
                        </form>
                        <form method="POST" style="display: inline;">
                          <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                          <input type="hidden" name="action" value="resolve">
                          <button type="submit" class="btn btn-success btn-small">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;">
                              <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Resolve
                          </button>
                        </form>
                      <?php elseif ($alert['status'] === 'acknowledged'): ?>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                        <input type="hidden" name="action" value="resolve">
                        <button type="submit" class="btn btn-success btn-small">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;">
                            <polyline points="20 6 9 17 4 12"></polyline>
                          </svg>
                          Resolve
                        </button>
                      </form>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                        <input type="hidden" name="action" value="reopen">
                        <button type="submit" class="btn btn-warning btn-small">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;">
                            <polyline points="9 14 4 9 9 4"></polyline>
                            <path d="M20 20v-7a4 4 0 0 0-4-4H4"></path>
                          </svg>
                          Reopen
                        </button>
                      </form>
                    <?php else: ?>
                      <form method="POST" style="display: inline;">
                        <input type="hidden" name="alert_id" value="<?php echo $alert['id']; ?>">
                        <input type="hidden" name="action" value="reopen">
                        <button type="submit" class="btn btn-warning btn-small">
                          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align: middle; margin-right: 4px;">
                            <polyline points="9 14 4 9 9 4"></polyline>
                            <path d="M20 20v-7a4 4 0 0 0-4-4H4"></path>
                          </svg>
                          Reopen
                        </button>
                      </form>
                    <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
