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

include __DIR__ . '/../includes/header.php';

// Get statistics
$user_id = $_SESSION['user_id'];
$stats = [
  'total_employees' => 0,
  'active_employees' => 0,
  'pending_payments' => 0,
  'low_stock_alerts' => 0
];

try {
  // Total and active employees
  $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active FROM employees WHERE user_id = ?");
  $stmt->execute([$user_id]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $stats['total_employees'] = $result['total'] ?? 0;
  $stats['active_employees'] = $result['active'] ?? 0;

  // Pending payments
  $stmt = $conn->prepare("SELECT COUNT(*) as total FROM employee_payments WHERE user_id = ? AND status = 'pending'");
  $stmt->execute([$user_id]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $stats['pending_payments'] = $result['total'] ?? 0;

  // Low stock alerts
  $stmt = $conn->prepare("SELECT COUNT(*) as total FROM inventory_alerts WHERE user_id = ? AND status = 'pending'");
  $stmt->execute([$user_id]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $stats['low_stock_alerts'] = $result['total'] ?? 0;
} catch (Exception $e) {
  error_log("Error fetching employee stats: " . $e->getMessage());
}
?>

<style>
:root {
  --emp-bg: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  --emp-card-bg: rgba(255, 255, 255, 0.98);
  --emp-text-primary: #1e293b;
  --emp-text-secondary: #64748b;
  --emp-border: rgba(226, 232, 240, 0.8);
  --emp-shadow: rgba(0, 0, 0, 0.1);
  --emp-shadow-hover: rgba(0, 0, 0, 0.2);
  --emp-card-hover: rgba(102, 126, 234, 0.05);
}

[data-theme="dark"] {
  --emp-bg: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
  --emp-card-bg: rgba(30, 30, 46, 0.95);
  --emp-text-primary: #e2e8f0;
  --emp-text-secondary: #94a3b8;
  --emp-border: rgba(71, 85, 105, 0.3);
  --emp-shadow: rgba(0, 0, 0, 0.3);
  --emp-shadow-hover: rgba(0, 0, 0, 0.5);
  --emp-card-hover: rgba(102, 126, 234, 0.1);
}

.emp-container {
  background: var(--emp-bg);
  min-height: 100vh;
  padding: 2rem;
  margin: -2rem;
  border-radius: 0;
}

.emp-header {
  text-align: center;
  margin-bottom: 3rem;
  color: white;
}

.emp-header h1 {
  font-size: 2.5rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 1rem;
}

.emp-header p {
  font-size: 1.1rem;
  opacity: 0.9;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 1.5rem;
  margin-bottom: 3rem;
  max-width: 1200px;
  margin-left: auto;
  margin-right: auto;
}

.stat-card {
  background: var(--emp-card-bg);
  border: 1px solid var(--emp-border);
  border-radius: 16px;
  padding: 1.5rem;
  box-shadow: 0 4px 6px var(--emp-shadow);
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px var(--emp-shadow-hover);
}

.stat-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon.blue { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
.stat-icon.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-icon.orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.stat-icon.red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

.stat-icon svg {
  width: 24px;
  height: 24px;
  stroke: white;
}

.stat-info h3 {
  font-size: 0.875rem;
  color: var(--emp-text-secondary);
  margin: 0;
  font-weight: 500;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--emp-text-primary);
  margin: 0;
}

.modules-section h2 {
  text-align: center;
  color: white;
  font-size: 2rem;
  margin-bottom: 2rem;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.75rem;
}

.modules-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 1.5rem;
  max-width: 1200px;
  margin: 0 auto;
}

.module-card {
  background: var(--emp-card-bg);
  border: 1px solid var(--emp-border);
  border-radius: 16px;
  padding: 1.5rem;
  text-decoration: none;
  color: inherit;
  display: block;
  transition: all 0.3s ease;
  box-shadow: 0 4px 6px var(--emp-shadow);
  position: relative;
}

.module-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px var(--emp-shadow-hover);
  background: var(--emp-card-hover);
}

.module-header {
  display: flex;
  align-items: center;
  gap: 1rem;
  margin-bottom: 1rem;
}

.module-icon {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.module-icon.purple { background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%); }
.module-icon.green { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.module-icon.blue { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); }
.module-icon.indigo { background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); }
.module-icon.orange { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.module-icon.red { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
.module-icon.yellow { background: linear-gradient(135deg, #eab308 0%, #ca8a04 100%); }

.module-icon svg {
  width: 28px;
  height: 28px;
  stroke: white;
}

.module-title {
  font-size: 1.25rem;
  font-weight: 600;
  color: var(--emp-text-primary);
  margin: 0;
}

.module-desc {
  color: var(--emp-text-secondary);
  font-size: 0.875rem;
  line-height: 1.5;
  margin: 0;
}

.module-badge {
  position: absolute;
  top: 1rem;
  right: 1rem;
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  font-size: 0.75rem;
  font-weight: 600;
  padding: 0.25rem 0.75rem;
  border-radius: 9999px;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
}

@media (max-width: 768px) {
  .emp-container {
    padding: 1rem;
    margin: -1rem;
  }
  
  .emp-header h1 {
    font-size: 1.75rem;
  }
  
  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  }
  
  .modules-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="emp-container">
<div class="emp-header">
  <h1>
    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
      <circle cx="9" cy="7" r="4"></circle>
      <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
      <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </svg>
    Employee Management Hub
  </h1>
  <p>Manage your team, payroll, attendance, and inventory all in one place</p>
</div>

<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon blue">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
          <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
        </svg>
      </div>
      <div class="stat-info">
        <h3>Total Employees</h3>
        <div class="stat-value"><?= $stats['total_employees'] ?></div>
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon green">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
          <circle cx="9" cy="7" r="4"></circle>
          <polyline points="16 11 18 13 22 9"></polyline>
        </svg>
      </div>
      <div class="stat-info">
        <h3>Active Staff</h3>
        <div class="stat-value"><?= $stats['active_employees'] ?></div>
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon orange">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
          <line x1="1" y1="10" x2="23" y2="10"></line>
        </svg>
      </div>
      <div class="stat-info">
        <h3>Pending Payments</h3>
        <div class="stat-value"><?= $stats['pending_payments'] ?></div>
      </div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-header">
      <div class="stat-icon red">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <circle cx="12" cy="12" r="10"></circle>
          <line x1="12" y1="8" x2="12" y2="12"></line>
          <line x1="12" y1="16" x2="12.01" y2="16"></line>
        </svg>
      </div>
      <div class="stat-info">
        <h3>Stock Alerts</h3>
        <div class="stat-value"><?= $stats['low_stock_alerts'] ?></div>
      </div>
    </div>
  </div>
</div>

<div class="modules-section">
  <h2>
    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="3" width="7" height="7"></rect>
      <rect x="14" y="3" width="7" height="7"></rect>
      <rect x="14" y="14" width="7" height="7"></rect>
      <rect x="3" y="14" width="7" height="7"></rect>
    </svg>
    Management Modules
  </h2>

  <div class="modules-grid">
    <a href="/MY CASH/pages/employee_profile.php" class="module-card">
      <?php if ($stats['total_employees'] > 0): ?>
        <span class="module-badge"><?= $stats['total_employees'] ?></span>
      <?php endif; ?>
      <div class="module-header">
        <div class="module-icon purple">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
          </svg>
        </div>
        <h3 class="module-title">Employee Profiles</h3>
      </div>
      <p class="module-desc">View and manage employee information, roles, and personal details</p>
    </a>

    <a href="/MY CASH/business/payroll.php" class="module-card">
      <div class="module-header">
        <div class="module-icon green">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
        </div>
        <h3 class="module-title">Payroll Management</h3>
      </div>
      <p class="module-desc">Process salaries, bonuses, deductions, and generate payslips</p>
    </a>

    <a href="/MY CASH/pages/employee_payments.php" class="module-card">
      <?php if ($stats['pending_payments'] > 0): ?>
        <span class="module-badge"><?= $stats['pending_payments'] ?></span>
      <?php endif; ?>
      <div class="module-header">
        <div class="module-icon blue">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
            <line x1="1" y1="10" x2="23" y2="10"></line>
          </svg>
        </div>
        <h3 class="module-title">Payment Records</h3>
      </div>
      <p class="module-desc">Track payment history and manage pending employee payments</p>
    </a>

    <a href="/MY CASH/pages/employee_attendance.php" class="module-card">
      <div class="module-header">
        <div class="module-icon indigo">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
            <line x1="16" y1="2" x2="16" y2="6"></line>
            <line x1="8" y1="2" x2="8" y2="6"></line>
            <line x1="3" y1="10" x2="21" y2="10"></line>
          </svg>
        </div>
        <h3 class="module-title">Attendance Tracking</h3>
      </div>
      <p class="module-desc">Monitor employee attendance, leaves, and working hours</p>
    </a>

    <a href="/MY CASH/pages/employee_financial.php" class="module-card">
      <div class="module-header">
        <div class="module-icon orange">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="12" y1="1" x2="12" y2="23"></line>
            <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path>
          </svg>
        </div>
        <h3 class="module-title">Financial Overview</h3>
      </div>
      <p class="module-desc">View comprehensive employee financial reports and budget analysis</p>
    </a>

    <a href="/MY CASH/business/inventory_alerts.php" class="module-card">
      <?php if ($stats['low_stock_alerts'] > 0): ?>
        <span class="module-badge"><?= $stats['low_stock_alerts'] ?></span>
      <?php endif; ?>
      <div class="module-header">
        <div class="module-icon red">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"></circle>
            <line x1="12" y1="8" x2="12" y2="12"></line>
            <line x1="12" y1="16" x2="12.01" y2="16"></line>
          </svg>
        </div>
        <h3 class="module-title">Low Stock Alerts</h3>
      </div>
      <p class="module-desc">Monitor inventory levels and receive low stock notifications</p>
    </a>

    <a href="/MY CASH/business/manage_products.php" class="module-card">
      <div class="module-header">
        <div class="module-icon yellow">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
            <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
            <line x1="12" y1="22.08" x2="12" y2="12"></line>
          </svg>
        </div>
        <h3 class="module-title">Enhanced Stock Manager</h3>
      </div>
      <p class="module-desc">Add new products, update inventory quantities, and manage stock levels</p>
    </a>

    <a href="/MY CASH/business/add_employee.php" class="module-card">
      <div class="module-header">
        <div class="module-icon green">
          <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
            <circle cx="8.5" cy="7" r="4"></circle>
            <line x1="20" y1="8" x2="20" y2="14"></line>
            <line x1="23" y1="11" x2="17" y2="11"></line>
          </svg>
        </div>
        <h3 class="module-title">Add New Employee</h3>
      </div>
      <p class="module-desc">Register new team members and set up their profiles</p>
    </a>
  </div>
</div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

