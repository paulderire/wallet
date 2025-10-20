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

// Exchange rate: 1 USD = 1500 RWF
define('USD_TO_RWF', 1500);

// Get today's date
$today = date('Y-m-d');

// 1. GET EMPLOYEE DAILY BALANCES (Today's Sales per Employee)
$employee_balances = [];
try {
  $stmt = $conn->prepare("SELECT 
    e.id,
    CONCAT(e.first_name, ' ', e.last_name) as name,
    e.email,
    e.role,
    COALESCE(SUM(CASE WHEN et.task_date = ? AND et.transaction_type = 'sale' THEN et.total_amount ELSE 0 END), 0) as today_sales,
    COALESCE(SUM(CASE WHEN et.task_date = ? AND et.payment_method = 'cash' THEN et.total_amount ELSE 0 END), 0) as today_cash,
    COALESCE(SUM(CASE WHEN et.task_date = ? AND et.payment_method = 'mobile_money' THEN et.total_amount ELSE 0 END), 0) as today_mobile_money,
    COALESCE(SUM(CASE WHEN et.task_date = ? AND et.transaction_type = 'sale' THEN 1 ELSE 0 END), 0) as transaction_count
    FROM employees e
    LEFT JOIN employee_tasks et ON e.id = et.employee_id AND et.user_id = ?
    WHERE e.user_id = ? AND e.status = 'active'
    GROUP BY e.id, e.first_name, e.last_name, e.email, e.role
    ORDER BY today_sales DESC");
  
  $stmt->execute([$today, $today, $today, $today, $user_id, $user_id]);
  $employee_balances = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  // Handle error
}

// 2. GET INVENTORY ALERTS FROM EMPLOYEES
$inventory_alerts = [];
try {
  $stmt = $conn->prepare("SELECT 
    ia.*,
    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
    e.role as employee_role
    FROM inventory_alerts ia
    JOIN employees e ON ia.employee_id = e.id
    WHERE ia.user_id = ? AND ia.status IN ('pending', 'acknowledged')
    ORDER BY 
      CASE ia.urgency 
        WHEN 'critical' THEN 1
        WHEN 'high' THEN 2
        WHEN 'medium' THEN 3
        WHEN 'low' THEN 4
      END,
      ia.alert_date DESC,
      ia.alert_time DESC
    LIMIT 20");
  
  $stmt->execute([$user_id]);
  $inventory_alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// 3. OVERALL BUSINESS STATISTICS
$total_employees = 0;
$active_employees = 0;
$total_today_sales = 0;
$total_today_cash = 0;
$pending_alerts = 0;
$critical_alerts = 0;

try {
  $stmt = $conn->prepare("SELECT 
    COUNT(*) as total, 
    SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active 
    FROM employees WHERE user_id=?");
  $stmt->execute([$user_id]);
  $emp_stats = $stmt->fetch(PDO::FETCH_ASSOC);
  $total_employees = $emp_stats['total'] ?? 0;
  $active_employees = $emp_stats['active'] ?? 0;
} catch (Exception $e) {}

try {
  $stmt = $conn->prepare("SELECT 
    COALESCE(SUM(total_amount), 0) as total_sales,
    COALESCE(SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END), 0) as total_cash
    FROM employee_tasks 
    WHERE user_id = ? AND task_date = ? AND transaction_type = 'sale'");
  $stmt->execute([$user_id, $today]);
  $sales_stats = $stmt->fetch(PDO::FETCH_ASSOC);
  $total_today_sales = $sales_stats['total_sales'] ?? 0;
  $total_today_cash = $sales_stats['total_cash'] ?? 0;
} catch (Exception $e) {}

try {
  $stmt = $conn->prepare("SELECT 
    COUNT(*) as pending,
    SUM(CASE WHEN urgency = 'critical' THEN 1 ELSE 0 END) as critical
    FROM inventory_alerts 
    WHERE user_id = ? AND status = 'pending'");
  $stmt->execute([$user_id]);
  $alert_stats = $stmt->fetch(PDO::FETCH_ASSOC);
  $pending_alerts = $alert_stats['pending'] ?? 0;
  $critical_alerts = $alert_stats['critical'] ?? 0;
} catch (Exception $e) {}

// 4. WEEK STATISTICS
$week_sales = [];
try {
  $stmt = $conn->prepare("SELECT 
    task_date,
    COALESCE(SUM(total_amount), 0) as daily_total,
    COUNT(*) as transaction_count
    FROM employee_tasks 
    WHERE user_id = ? 
    AND task_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    AND transaction_type = 'sale'
    GROUP BY task_date
    ORDER BY task_date ASC");
  $stmt->execute([$user_id]);
  $week_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

$week_total = array_sum(array_column($week_sales, 'daily_total'));

include __DIR__ . '/../includes/header.php';
?>

<style>
* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
}

.business-container {
  max-width: 1600px;
  margin: 0 auto;
  padding: 24px;
}

.business-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 32px;
  border-radius: 16px;
  margin-bottom: 24px;
  color: white;
  box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
}

.business-header h1 {
  font-size: 32px;
  font-weight: 800;
  margin-bottom: 8px;
}

.business-header p {
  opacity: 0.9;
  font-size: 14px;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}

.stat-card {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  border-left: 4px solid #667eea;
}

.stat-card.warning {
  border-left-color: #f59e0b;
}

.stat-card.danger {
  border-left-color: #ef4444;
}

.stat-card.success {
  border-left-color: #10b981;
}

.stat-icon {
  font-size: 32px;
  margin-bottom: 8px;
}

.stat-label {
  font-size: 12px;
  color: #718096;
  font-weight: 600;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 28px;
  font-weight: 900;
  color: #1a202c;
}

.stat-subtext {
  font-size: 12px;
  color: #a0aec0;
  margin-top: 4px;
}

.content-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}

.card {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.card-title {
  font-size: 18px;
  font-weight: 700;
  color: #1a202c;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.employee-balance-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.employee-balance-item {
  padding: 16px;
  background: #f7fafc;
  border-radius: 10px;
  border-left: 4px solid #667eea;
  transition: all 0.3s;
}

.employee-balance-item:hover {
  background: #edf2f7;
  transform: translateX(4px);
}

.employee-balance-item.top-performer {
  border-left-color: #10b981;
  background: linear-gradient(135deg, #d4f4dd 0%, #f0fdf4 100%);
}

.employee-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.employee-name {
  font-weight: 700;
  color: #1a202c;
  font-size: 15px;
}

.employee-amount {
  font-weight: 900;
  color: #10b981;
  font-size: 18px;
}

.employee-meta {
  display: flex;
  gap: 16px;
  font-size: 12px;
  color: #718096;
}

.employee-role {
  background: #edf2f7;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 600;
}

.alert-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
  max-height: 600px;
  overflow-y: auto;
}

.alert-item {
  padding: 16px;
  background: #fef2f2;
  border-radius: 10px;
  border-left: 4px solid #ef4444;
}

.alert-item.low {
  background: #fef9c3;
  border-left-color: #eab308;
}

.alert-item.medium {
  background: #fed7aa;
  border-left-color: #f97316;
}

.alert-item.high {
  background: #fecaca;
  border-left-color: #dc2626;
}

.alert-item.critical {
  background: #fee2e2;
  border-left-color: #991b1b;
}

.alert-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.alert-title {
  font-weight: 700;
  color: #1a202c;
  font-size: 14px;
}

.alert-urgency {
  padding: 4px 10px;
  border-radius: 12px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
}

.urgency-critical {
  background: #991b1b;
  color: white;
}

.urgency-high {
  background: #dc2626;
  color: white;
}

.urgency-medium {
  background: #f97316;
  color: white;
}

.urgency-low {
  background: #eab308;
  color: white;
}

.alert-meta {
  font-size: 12px;
  color: #718096;
  margin-top: 8px;
}

.alert-type {
  display: inline-block;
  background: white;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: 600;
  margin-right: 8px;
}

.empty-state {
  text-align: center;
  padding: 40px;
  color: #a0aec0;
}

.empty-icon {
  font-size: 64px;
  margin-bottom: 16px;
}

.btn {
  display: inline-block;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  text-decoration: none;
  font-size: 14px;
  transition: all 0.3s;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.full-width {
  grid-column: 1 / -1;
}

@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="business-container">
  <!-- Header -->
  <div class="business-header">
    <h1>🏪 Business Manager Dashboard</h1>
    <p>Real-time employee sales tracking and inventory alerts • <?php echo date('l, F j, Y'); ?></p>
  </div>

  <!-- Statistics Overview -->
  <div class="stats-grid">
    <div class="stat-card success">
      <div class="stat-icon">💰</div>
      <div class="stat-label">Today's Total Sales</div>
      <div class="stat-value">RWF <?php echo number_format($total_today_sales, 0); ?></div>
      <div class="stat-subtext">$<?php echo number_format($total_today_sales / USD_TO_RWF, 2); ?> USD • From all employees</div>
    </div>

    <div class="stat-card success">
      <div class="stat-icon">💵</div>
      <div class="stat-label">Today's Cash</div>
      <div class="stat-value">RWF <?php echo number_format($total_today_cash, 0); ?></div>
      <div class="stat-subtext">$<?php echo number_format($total_today_cash / USD_TO_RWF, 2); ?> USD • Cash payments</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">👥</div>
      <div class="stat-label">Active Employees</div>
      <div class="stat-value"><?php echo $active_employees; ?></div>
      <div class="stat-subtext"><?php echo $total_employees; ?> total</div>
    </div>

    <div class="stat-card <?php echo $pending_alerts > 0 ? 'warning' : ''; ?>">
      <div class="stat-icon">⚠️</div>
      <div class="stat-label">Pending Alerts</div>
      <div class="stat-value"><?php echo $pending_alerts; ?></div>
      <div class="stat-subtext"><?php echo $critical_alerts; ?> critical</div>
    </div>

    <div class="stat-card">
      <div class="stat-icon">📅</div>
      <div class="stat-label">Week's Total Sales</div>
      <div class="stat-value">RWF <?php echo number_format($week_total, 0); ?></div>
      <div class="stat-subtext">$<?php echo number_format($week_total / USD_TO_RWF, 2); ?> USD • Last 7 days</div>
    </div>
  </div>

  <!-- Employee Balances & Alerts Grid -->
  <div class="content-grid">
    <!-- Employee Daily Balances -->
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 class="card-title" style="margin-bottom: 0;">
          <span>💼</span>
          Employee Sales Today
        </h2>
        <a href="/MY CASH/business/employee_sales.php" style="font-size: 13px; color: #667eea; font-weight: 600; text-decoration: none;">
          View All Sales →
        </a>
      </div>

      <?php if (empty($employee_balances)): ?>
        <div class="empty-state">
          <div class="empty-icon">📊</div>
          <p>No employee sales data yet</p>
        </div>
      <?php else: ?>
        <div class="employee-balance-list">
          <?php foreach ($employee_balances as $index => $emp): ?>
            <div class="employee-balance-item <?php echo $index === 0 && $emp['today_sales'] > 0 ? 'top-performer' : ''; ?>">
              <div class="employee-header">
                <div>
                  <div class="employee-name">
                    <?php if ($index === 0 && $emp['today_sales'] > 0): ?>🏆 <?php endif; ?>
                    <?php echo htmlspecialchars($emp['name']); ?>
                  </div>
                  <div class="employee-meta">
                    <span class="employee-role"><?php echo htmlspecialchars($emp['role']); ?></span>
                    <span><?php echo $emp['transaction_count']; ?> transactions</span>
                  </div>
                </div>
                <div>
                  <div class="employee-amount">
                    RWF <?php echo number_format($emp['today_sales'], 0); ?>
                  </div>
                  <div style="font-size: 13px; color: #10b981; font-weight: 600; margin-top: 2px;">
                    $<?php echo number_format($emp['today_sales'] / USD_TO_RWF, 2); ?>
                  </div>
                </div>
              </div>
              
              <div class="employee-meta" style="margin-top: 8px; padding-top: 8px; border-top: 1px solid #e2e8f0;">
                <span>💵 Cash: RWF <?php echo number_format($emp['today_cash'], 0); ?> ($<?php echo number_format($emp['today_cash'] / USD_TO_RWF, 2); ?>)</span>
                <span>📱 Mobile: RWF <?php echo number_format($emp['today_mobile_money'], 0); ?> ($<?php echo number_format($emp['today_mobile_money'] / USD_TO_RWF, 2); ?>)</span>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- Inventory Alerts from Employees -->
    <div class="card">
      <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 class="card-title" style="margin-bottom: 0;">
          <span>⚠️</span>
          Inventory Alerts
          <?php if ($pending_alerts > 0): ?>
            <span style="background: #ef4444; color: white; padding: 2px 8px; border-radius: 12px; font-size: 11px; margin-left: 8px;">
              <?php echo $pending_alerts; ?> pending
            </span>
          <?php endif; ?>
        </h2>
        <a href="/MY CASH/business/inventory_alerts.php" style="font-size: 13px; color: #ef4444; font-weight: 600; text-decoration: none;">
          View All Alerts →
        </a>
      </div>

      <?php if (empty($inventory_alerts)): ?>
        <div class="empty-state">
          <div class="empty-icon">✅</div>
          <p>No pending inventory alerts</p>
        </div>
      <?php else: ?>
        <div class="alert-list">
          <?php foreach ($inventory_alerts as $alert): ?>
            <div class="alert-item <?php echo strtolower($alert['urgency']); ?>">
              <div class="alert-header">
                <div class="alert-title">
                  <?php echo htmlspecialchars($alert['item_name']); ?>
                </div>
                <span class="alert-urgency urgency-<?php echo strtolower($alert['urgency']); ?>">
                  <?php echo strtoupper($alert['urgency']); ?>
                </span>
              </div>
              
              <div style="font-size: 13px; color: #4a5568; margin: 8px 0;">
                <?php echo htmlspecialchars($alert['notes'] ?? 'No additional notes'); ?>
              </div>
              
              <div class="alert-meta">
                <span class="alert-type"><?php echo str_replace('_', ' ', ucwords($alert['alert_type'])); ?></span>
                <span>Qty: <?php echo $alert['current_quantity']; ?></span>
                <span>By: <?php echo htmlspecialchars($alert['employee_name']); ?></span>
                <span><?php echo date('M j, g:i A', strtotime($alert['alert_date'] . ' ' . $alert['alert_time'])); ?></span>
              </div>
              
              <?php if ($alert['status'] === 'pending'): ?>
                <div style="margin-top: 12px; display: flex; gap: 8px;">
                  <a href="/MY CASH/business/inventory_alerts.php?action=acknowledge&id=<?php echo $alert['id']; ?>" 
                     class="btn btn-primary" style="font-size: 12px; padding: 6px 12px;">
                    ✅ Acknowledge
                  </a>
                  <a href="/MY CASH/business/inventory_alerts.php?action=resolve&id=<?php echo $alert['id']; ?>" 
                     class="btn btn-primary" style="font-size: 12px; padding: 6px 12px; background: #10b981;">
                    ✓ Resolve
                  </a>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      
      <div style="margin-top: 16px; text-align: center;">
        <a href="/MY CASH/business/inventory_alerts.php" class="btn btn-primary">
          View All Alerts →
        </a>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
