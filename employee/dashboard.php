<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Check if employee is logged in
if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/currency.php';
include __DIR__ . '/../includes/header.php';

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
    ORDER BY urgency DESC, alert_date DESC LIMIT 10");
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

// Calculate week total
$week_total = array_sum(array_column($week_sales, 'daily_total'));
?>

<style>
/* ==========================================
   EMPLOYEE DASHBOARD - LIGHT & DARK MODE
   ========================================== */

.employee-header {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  padding: 40px;
  border-radius: 20px;
  margin-bottom: 32px;
  color: white;
  position: relative;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(16, 185, 129, 0.2);
  transition: box-shadow 0.3s ease;
}

/* Dark mode header enhancement */
[data-theme="dark"] .employee-header {
  background: linear-gradient(135deg, #059669 0%, #047857 100%);
  box-shadow: 0 8px 32px rgba(5, 150, 105, 0.4);
}

.employee-header::before {
  content: '📊';
  position: absolute;
  right: 40px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 8rem;
  opacity: 0.1;
  filter: brightness(1.2);
}

[data-theme="dark"] .employee-header::before {
  opacity: 0.15;
  filter: brightness(1.5);
}

.employee-header h1 {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0 0 12px 0;
  color: white;
  text-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.employee-header p {
  font-size: 1.1rem;
  opacity: 0.9;
  margin: 0;
  color: rgba(255, 255, 255, 0.95);
}

[data-theme="dark"] .employee-header p {
  opacity: 1;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.stat-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 28px;
  position: relative;
  overflow: hidden;
  transition: all 0.3s;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 4px;
}

.stat-card:hover {
  transform: translateY(-6px);
  box-shadow: 0 16px 40px rgba(var(--card-text-rgb), 0.15);
}

/* Dark mode card enhancement */
[data-theme="dark"] .stat-card {
  background: var(--card-bg);
  border-color: var(--border-medium);
}

[data-theme="dark"] .stat-card:hover {
  box-shadow: 0 16px 40px rgba(var(--card-text-rgb), 0.25);
  border-color: var(--border-strong);
}

.stat-card.sales::before { background: linear-gradient(90deg, #10b981, #059669); }
.stat-card.cash::before { background: linear-gradient(90deg, #22c55e, #16a34a); }
.stat-card.mobile::before { background: linear-gradient(90deg, #667eea, #764ba2); }
.stat-card.alerts::before { background: linear-gradient(90deg, #f59e0b, #ea580c); }
.stat-card.week::before { background: linear-gradient(90deg, #3b82f6, #2563eb); }

.stat-icon {
  font-size: 2.5rem;
  opacity: 0.2;
  position: absolute;
  right: 20px;
  top: 20px;
  transition: opacity 0.3s ease;
}

[data-theme="dark"] .stat-icon {
  opacity: 0.15;
}

.stat-label {
  font-size: 0.9rem;
  color: var(--muted);
  text-transform: uppercase;
  font-weight: 600;
  margin-bottom: 12px;
}

.stat-value {
  font-size: 2.4rem;
  font-weight: 800;
  color: var(--card-text);
  margin-bottom: 8px;
}

.stat-sub {
  font-size: 0.9rem;
  color: var(--muted);
}

.action-buttons {
  display: flex;
  gap: 12px;
  margin-bottom: 32px;
  flex-wrap: wrap;
}

.quick-actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 32px;
}

.quick-action-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 12px;
  padding: 24px;
  text-align: center;
  text-decoration: none;
  transition: all 0.3s;
  cursor: pointer;
}

.quick-action-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(var(--card-text-rgb), 0.12);
}

[data-theme="dark"] .quick-action-card {
  border-color: var(--border-medium);
}

[data-theme="dark"] .quick-action-card:hover {
  box-shadow: 0 12px 32px rgba(var(--card-text-rgb), 0.2);
  border-color: var(--border-strong);
}
.quick-action-icon {
  font-size: 2.5rem;
  margin-bottom: 12px;
  opacity: 0.9;
  transition: opacity 0.3s ease;
}

[data-theme="dark"] .quick-action-icon {
  opacity: 0.95;
  filter: brightness(1.1);
}

.quick-action-label {
  font-size: 0.95rem;
  font-weight: 700;
  color: var(--card-text);
  margin-bottom: 6px;
}

.quick-action-desc {
  font-size: 0.8rem;
  color: var(--muted);
}

.content-grid {
  display: grid;
  grid-template-columns: 2fr 1fr;
  gap: 24px;
  margin-bottom: 24px;
}

.card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
  transition: all 0.3s ease;
}

[data-theme="dark"] .card {
  border-color: var(--border-medium);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
}

.card h2 {
  margin: 0 0 20px 0;
  font-size: 1.4rem;
  font-weight: 700;
  color: var(--card-text);
  display: flex;
  align-items: center;
  gap: 8px;
}

.transaction-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.transaction-item {
  padding: 16px;
  background: rgba(var(--card-text-rgb), 0.03);
  border-radius: 12px;
  border-left: 4px solid #10b981;
  transition: all 0.3s;
}

.transaction-item:hover {
  background: rgba(var(--card-text-rgb), 0.06);
  transform: translateX(4px);
}

[data-theme="dark"] .transaction-item {
  background: rgba(var(--card-text-rgb), 0.05);
  border-left-color: #059669;
}

[data-theme="dark"] .transaction-item:hover {
  background: rgba(var(--card-text-rgb), 0.1);
}

.transaction-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 8px;
}

.transaction-title {
  font-weight: 600;
  color: var(--card-text);
  font-size: 14px;
}

.transaction-customer {
  font-size: 12px;
  color: var(--muted);
  margin-top: 4px;
}

.transaction-amount {
  font-weight: 700;
  color: #10b981;
  font-size: 16px;
  text-align: right;
}

[data-theme="dark"] .transaction-amount {
  color: #34d399;
}

.transaction-usd {
  font-size: 12px;
  color: #059669;
  font-weight: 600;
  text-align: right;
}

[data-theme="dark"] .transaction-usd {
  color: #10b981;
}
.transaction-meta {
  display: flex;
  gap: 12px;
  flex-wrap: wrap;
  font-size: 12px;
  color: var(--muted);
}

.badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 11px;
  font-weight: 600;
  text-transform: capitalize;
  transition: all 0.3s ease;
}

.badge-cash { background: rgba(34, 197, 94, 0.15); color: #16a34a; }
.badge-mobile_money { background: rgba(102, 126, 234, 0.15); color: #667eea; }
.badge-bank_transfer { background: rgba(59, 130, 246, 0.15); color: #2563eb; }
.badge-credit { background: rgba(245, 158, 11, 0.15); color: #d97706; }

/* Dark mode badge enhancements */
[data-theme="dark"] .badge-cash { 
  background: rgba(34, 197, 94, 0.25); 
  color: #4ade80; 
}
[data-theme="dark"] .badge-mobile_money { 
  background: rgba(102, 126, 234, 0.25); 
  color: #818cf8; 
}
[data-theme="dark"] .badge-bank_transfer { 
  background: rgba(59, 130, 246, 0.25); 
  color: #60a5fa; 
}
[data-theme="dark"] .badge-credit { 
  background: rgba(245, 158, 11, 0.25); 
  color: #fbbf24; 
}

.alert-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.alert-item {
  padding: 16px;
  border-radius: 12px;
  border-left: 4px solid;
  transition: all 0.3s ease;
}

.alert-item.low { background: rgba(59, 130, 246, 0.1); border-left-color: #3b82f6; }
.alert-item.medium { background: rgba(245, 158, 11, 0.1); border-left-color: #f59e0b; }
.alert-item.high { background: rgba(249, 115, 22, 0.1); border-left-color: #f97316; }
.alert-item.critical { background: rgba(239, 68, 68, 0.1); border-left-color: #ef4444; }

/* Dark mode alert enhancements */
[data-theme="dark"] .alert-item.low { 
  background: rgba(59, 130, 246, 0.2); 
  border-left-color: #60a5fa; 
}
[data-theme="dark"] .alert-item.medium { 
  background: rgba(245, 158, 11, 0.2); 
  border-left-color: #fbbf24; 
}
[data-theme="dark"] .alert-item.high { 
  background: rgba(249, 115, 22, 0.2); 
  border-left-color: #fb923c; 
}
[data-theme="dark"] .alert-item.critical { 
  background: rgba(239, 68, 68, 0.2); 
  border-left-color: #f87171; 
}

.alert-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.alert-title {
  font-weight: 600;
  color: var(--card-text);
  font-size: 14px;
}

.alert-urgency {
  padding: 2px 8px;
  border-radius: 8px;
  font-size: 10px;
  font-weight: 700;
  text-transform: uppercase;
  color: white;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.urgency-low { background: #3b82f6; }
.urgency-medium { background: #f59e0b; }
.urgency-high { background: #f97316; }
.urgency-critical { background: #ef4444; }

/* Dark mode urgency badges - brighter */
[data-theme="dark"] .urgency-low { background: #60a5fa; }
[data-theme="dark"] .urgency-medium { background: #fbbf24; }
[data-theme="dark"] .urgency-high { background: #fb923c; }
[data-theme="dark"] .urgency-critical { background: #f87171; }

.alert-meta {
  font-size: 12px;
  color: var(--muted);
}

.empty-state {
  text-align: center;
  padding: 48px 24px;
  color: var(--muted);
}

.empty-icon {
  font-size: 4rem;
  margin-bottom: 16px;
  opacity: 0.5;
  transition: opacity 0.3s ease;
}

[data-theme="dark"] .empty-icon {
  opacity: 0.3;
}
@media (max-width: 1024px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
}
@media (max-width: 768px) {
  .employee-header h1 {
    font-size: 2rem;
  }
  .employee-header::before {
    font-size: 5rem;
  }
  .stats-grid {
    grid-template-columns: 1fr;
  }
  .action-buttons {
    flex-direction: column;
  }
  .quick-actions-grid {
    grid-template-columns: 1fr;
  }
}

/* ==========================================
   SIDEBAR LAYOUT & STYLES
   ========================================== */

.emp-dashboard-layout {
  display: flex;
  gap: 24px;
  max-width: 1600px;
  margin: 0 auto;
  padding: 0;
}

.emp-sidebar {
  width: 320px;
  flex: 0 0 320px;
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
  min-height: calc(100vh - 60px);
  max-height: calc(100vh - 60px);
  position: sticky;
  top: 20px;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 16px rgba(var(--card-text-rgb), 0.08);
  transition: all 0.3s ease;
  overflow-y: auto;
}

[data-theme="dark"] .emp-sidebar {
  background: var(--card-bg);
  border-color: var(--border-medium);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.3);
}

.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: 20px;
  border-bottom: 2px solid var(--border-weak);
  margin-bottom: 20px;
}

[data-theme="dark"] .sidebar-header {
  border-bottom-color: var(--border-medium);
}

.sidebar-logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 800;
  font-size: 1.1rem;
  color: var(--card-text);
}

.sidebar-logo-icon {
  font-size: 1.8rem;
}

.theme-toggle-btn {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border: none;
  border-radius: 10px;
  padding: 8px 12px;
  cursor: pointer;
  font-size: 1.2rem;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
}

.theme-toggle-btn:hover {
  transform: scale(1.1);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

[data-theme="dark"] .theme-toggle-btn {
  background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
  box-shadow: 0 2px 8px rgba(251, 191, 36, 0.3);
}

[data-theme="dark"] .theme-toggle-btn:hover {
  box-shadow: 0 4px 12px rgba(251, 191, 36, 0.4);
}

.sidebar-nav {
  flex: 1;
  overflow-y: auto;
  padding-right: 4px;
}

.sidebar-nav-section {
  margin-bottom: 24px;
}

.sidebar-nav-title {
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 8px;
  padding: 0 12px;
  letter-spacing: 0.5px;
}

.sidebar-nav-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 10px;
  text-decoration: none;
  color: var(--card-text);
  font-weight: 600;
  font-size: 0.95rem;
  transition: all 0.3s ease;
  margin-bottom: 4px;
  border: 1px solid transparent;
}

.sidebar-nav-item:hover {
  background: rgba(var(--card-text-rgb), 0.06);
  transform: translateX(4px);
}

[data-theme="dark"] .sidebar-nav-item:hover {
  background: rgba(var(--card-text-rgb), 0.1);
}

.sidebar-nav-item.active {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.1));
  color: #10b981;
  border-color: rgba(16, 185, 129, 0.3);
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
}

[data-theme="dark"] .sidebar-nav-item.active {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.25), rgba(5, 150, 105, 0.15));
  color: #34d399;
  border-color: rgba(52, 211, 153, 0.3);
  box-shadow: 0 2px 8px rgba(52, 211, 153, 0.3);
}

.sidebar-nav-icon {
  font-size: 1.3rem;
  width: 24px;
  text-align: center;
}

.sidebar-logout {
  margin-top: auto;
  padding: 14px;
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  border: none;
  border-radius: 12px;
  font-weight: 700;
  font-size: 0.95rem;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
  text-decoration: none;
  width: 100%;
}

.sidebar-logout:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4);
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
}

[data-theme="dark"] .sidebar-logout {
  background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
  box-shadow: 0 4px 12px rgba(248, 113, 113, 0.4);
}

[data-theme="dark"] .sidebar-logout:hover {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  box-shadow: 0 6px 16px rgba(239, 68, 68, 0.5);
}

.sidebar-footer {
  padding-top: 16px;
  margin-top: auto;
  border-top: 2px solid var(--border-weak);
  text-align: center;
}

[data-theme="dark"] .sidebar-footer {
  border-top-color: var(--border-medium);
}

.sidebar-user {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.05));
  border-radius: 12px;
  padding: 12px;
  margin-bottom: 12px;
}

[data-theme="dark"] .sidebar-user {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(5, 150, 105, 0.1));
}

.sidebar-user-name {
  font-weight: 700;
  color: var(--card-text);
  font-size: 0.9rem;
  margin-bottom: 2px;
}

.sidebar-user-role {
  font-size: 0.75rem;
  color: var(--muted);
  text-transform: capitalize;
}

.sidebar-version {
  font-size: 0.7rem;
  color: var(--muted);
  font-weight: 600;
}

/* Sidebar scrollbar styling */
.emp-sidebar::-webkit-scrollbar {
  width: 6px;
}

.emp-sidebar::-webkit-scrollbar-track {
  background: transparent;
}

.emp-sidebar::-webkit-scrollbar-thumb {
  background: var(--border-medium);
  border-radius: 3px;
}

.emp-sidebar::-webkit-scrollbar-thumb:hover {
  background: var(--border-strong);
}

[data-theme="dark"] .emp-sidebar::-webkit-scrollbar-thumb {
  background: var(--border-medium);
}

[data-theme="dark"] .emp-sidebar::-webkit-scrollbar-thumb:hover {
  background: var(--border-strong);
}

.emp-main-content {
  flex: 1;
  min-width: 0;
}

/* Mobile Sidebar */
@media (max-width: 1024px) {
  .emp-dashboard-layout {
    flex-direction: column;
  }
  
  .emp-sidebar {
    width: 100%;
    flex: none;
    height: auto;
    position: relative;
    top: 0;
    order: -1;
  }
  
  .sidebar-nav {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 8px;
    max-height: none;
    overflow: visible;
  }
  
  .sidebar-nav-section {
    margin-bottom: 0;
  }
  
  .sidebar-nav-title {
    display: none;
  }
  
  .sidebar-nav-item {
    flex-direction: column;
    text-align: center;
    padding: 10px 8px;
    gap: 6px;
  }
  
  .sidebar-nav-icon {
    font-size: 1.5rem;
  }
  
  .sidebar-footer {
    display: none;
  }
}

@media (max-width: 640px) {
  .sidebar-nav {
    grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  }
  
  .sidebar-nav-item {
    font-size: 0.8rem;
    padding: 8px 6px;
  }
  
  .sidebar-logo-icon {
    display: none;
  }
}
</style>

<!-- Sidebar + Main Layout -->
<div class="emp-dashboard-layout">
  
  <!-- Sidebar Navigation -->
  <aside class="emp-sidebar">
    <div class="sidebar-header">
      <div class="sidebar-logo">
        <span class="sidebar-logo-icon">📊</span>
        <span>Employee Hub</span>
      </div>
      <button id="theme-toggle" class="theme-toggle-btn" aria-label="Toggle theme">🌙</button>
    </div>
    
    <nav class="sidebar-nav">
      <div class="sidebar-nav-section">
        <div class="sidebar-nav-title">Dashboard</div>
        <a href="/MY CASH/employee/dashboard.php" class="sidebar-nav-item active">
          <span class="sidebar-nav-icon">🏠</span>
          <span>Home</span>
        </a>
        <a href="/MY CASH/employee/my_profile.php" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">👤</span>
          <span>My Profile</span>
        </a>
      </div>
      
      <div class="sidebar-nav-section">
        <div class="sidebar-nav-title">Operations</div>
        <a href="/MY CASH/employee/record_transaction.php" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">💰</span>
          <span>Record Sale</span>
        </a>
        <a href="/MY CASH/employee/manage_inventory.php" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">📦</span>
          <span>Inventory</span>
        </a>
        <a href="/MY CASH/employee/report_stock.php" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">⚠️</span>
          <span>Stock Issues</span>
        </a>
      </div>
      
      <div class="sidebar-nav-section">
        <div class="sidebar-nav-title">My Account</div>
        <a href="/MY CASH/employee/my_attendance.php" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">🕐</span>
          <span>Attendance</span>
        </a>
        <a href="/MY CASH/employee/my_payments.php" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">💳</span>
          <span>Payments</span>
        </a>
        <a href="/MY CASH/employee/my_corrections.php" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">🔧</span>
          <span>Corrections</span>
        </a>
        <a href="/MY CASH/employee/chat.php" class="sidebar-nav-item">
          <span class="sidebar-nav-icon">💬</span>
          <span>Team Chat</span>
        </a>
      </div>
    </nav>
    
    <div class="sidebar-footer">
      <a href="/MY CASH/employee/logout.php" class="sidebar-logout">
        <span>🚪</span>
        <span>Logout</span>
      </a>
      
      <div class="sidebar-user">
        <div class="sidebar-user-name"><?php echo htmlspecialchars($employee_name); ?></div>
        <div class="sidebar-user-role"><?php echo htmlspecialchars($employee_role); ?></div>
      </div>
      <div class="sidebar-version">v1.0 Employee Portal</div>
    </div>
  </aside>
  
  <!-- Main Content Area -->
  <main class="emp-main-content">

<div class="employee-header">
  <h1>📊 Employee Dashboard</h1>
  <p>Welcome back, <?php echo htmlspecialchars($employee_name); ?> • <?php echo htmlspecialchars($employee_role); ?> • <?php echo date('l, F j, Y'); ?></p>
</div>

<div class="action-buttons">
  <a href="/MY CASH/employee/record_transaction.php" class="button primary action-link">💰 Record Sale</a>
  <a href="/MY CASH/employee/manage_inventory.php" class="button action-link" style="background: linear-gradient(135deg, #10b981, #059669); color: white;">📦 Manage Inventory</a>
  <a href="/MY CASH/employee/report_stock.php" class="button action-link" style="background: #ef4444; color: white;">⚠️ Report Stock Issue</a>
</div>

<div class="stats-grid">
  <div class="stat-card sales">
    <div class="stat-icon">💵</div>
    <div class="stat-label">Today's Total Sales</div>
    <div class="stat-value">RWF <?=number_format($today_sales['total_amount'], 0)?></div>
    <div class="stat-sub">$<?=number_format(rwf_to_usd($today_sales['total_amount']), 2)?> • <?=$today_sales['total_transactions']?> transactions</div>
  </div>

  <div class="stat-card cash">
    <div class="stat-icon">💵</div>
    <div class="stat-label">Cash Payments</div>
    <div class="stat-value">RWF <?=number_format($today_sales['cash'], 0)?></div>
    <div class="stat-sub">$<?=number_format(rwf_to_usd($today_sales['cash']), 2)?> • <?=$today_sales['total_amount'] > 0 ? round(($today_sales['cash'] / $today_sales['total_amount']) * 100) : 0?>%</div>
  </div>

  <div class="stat-card mobile">
    <div class="stat-icon">📱</div>
    <div class="stat-label">Mobile Money</div>
    <div class="stat-value">RWF <?=number_format($today_sales['mobile_money'], 0)?></div>
    <div class="stat-sub">$<?=number_format(rwf_to_usd($today_sales['mobile_money']), 2)?> • <?=$today_sales['total_amount'] > 0 ? round(($today_sales['mobile_money'] / $today_sales['total_amount']) * 100) : 0?>%</div>
  </div>

  <div class="stat-card alerts">
    <div class="stat-icon">⚠️</div>
    <div class="stat-label">Pending Alerts</div>
    <div class="stat-value"><?=count($pending_alerts)?></div>
    <div class="stat-sub">Items need attention</div>
  </div>

  <div class="stat-card week">
    <div class="stat-icon">📅</div>
    <div class="stat-label">Week's Total</div>
    <div class="stat-value">RWF <?=number_format($week_total, 0)?></div>
    <div class="stat-sub">$<?=number_format(rwf_to_usd($week_total), 2)?> • Last 7 days</div>
  </div>
</div>

<h3 style="margin-bottom: 20px; color: var(--card-text); font-size: 1.3rem; font-weight: 700; display: flex; align-items: center; gap: 8px;">
  ⚡ Quick Actions
</h3>

<div class="quick-actions-grid">
  <a href="/MY CASH/employee/my_profile.php" class="quick-action-card">
    <div class="quick-action-icon">👤</div>
    <div class="quick-action-label">My Profile</div>
    <div class="quick-action-desc">View & edit information</div>
  </a>

  <a href="/MY CASH/employee/my_attendance.php" class="quick-action-card">
    <div class="quick-action-icon">🕐</div>
    <div class="quick-action-label">My Attendance</div>
    <div class="quick-action-desc">Check in/out history</div>
  </a>

  <a href="/MY CASH/employee/my_corrections.php" class="quick-action-card">
    <div class="quick-action-icon">🔧</div>
    <div class="quick-action-label">Report Mistake</div>
    <div class="quick-action-desc">Request correction</div>
  </a>

  <a href="/MY CASH/employee/my_payments.php" class="quick-action-card">
    <div class="quick-action-icon">💰</div>
    <div class="quick-action-label">My Payments</div>
    <div class="quick-action-desc">Salary & payment history</div>
  </a>

  <a href="/MY CASH/employee/chat.php" class="quick-action-card">
    <div class="quick-action-icon">💬</div>
    <div class="quick-action-label">Team Chat</div>
    <div class="quick-action-desc">Message your team</div>
  </a>
</div>

<div class="content-grid">
  <div class="card">
    <h2>
      <span>📝</span>
      Today's Transactions
    </h2>

    <?php if (empty($today_transactions)): ?>
      <div class="empty-state">
        <div class="empty-icon">🛒</div>
        <p>No transactions recorded today</p>
        <p style="font-size: 12px; margin-top: 8px;">Click "Record Sale" to add your first transaction</p>
      </div>
    <?php else: ?>
      <div class="transaction-list">
        <?php foreach ($today_transactions as $trans): ?>
          <div class="transaction-item">
            <div class="transaction-header">
              <div>
                <div class="transaction-title"><?=htmlspecialchars($trans['title'])?></div>
                <?php if (!empty($trans['customer_name'])): ?>
                  <div class="transaction-customer">Customer: <?=htmlspecialchars($trans['customer_name'])?></div>
                <?php endif; ?>
              </div>
              <div>
                <div class="transaction-amount">RWF <?=number_format($trans['total_amount'] ?? 0, 0)?></div>
                <div class="transaction-usd">$<?=number_format(rwf_to_usd($trans['total_amount'] ?? 0), 2)?></div>
              </div>
            </div>
            <div class="transaction-meta">
              <span>⏰ <?=date('g:i A', strtotime($trans['task_time']))?></span>
              <span class="badge badge-<?=strtolower($trans['payment_method'] ?? 'cash')?>">
                <?php 
                $icons = ['cash' => '💵', 'mobile_money' => '📱', 'bank_transfer' => '🏦', 'credit' => '💳'];
                echo $icons[$trans['payment_method'] ?? 'cash'] ?? '💵';
                echo ' ' . ucwords(str_replace('_', ' ', $trans['payment_method'] ?? 'Cash')); 
                ?>
              </span>
              <?php if (!empty($trans['items_sold'])): ?>
                <span>📦 <?=htmlspecialchars($trans['items_sold'])?></span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>
      <span>⚠️</span>
      Stock Alerts
    </h2>

    <?php if (empty($pending_alerts)): ?>
      <div class="empty-state" style="padding: 32px 16px;">
        <div class="empty-icon" style="font-size: 3rem;">✅</div>
        <p style="font-size: 14px;">All stock levels are good!</p>
        <p style="font-size: 11px; margin-top: 4px;">No pending alerts</p>
      </div>
    <?php else: ?>
      <div class="alert-list">
        <?php foreach ($pending_alerts as $alert): ?>
          <div class="alert-item <?=$alert['urgency']?>">
            <div class="alert-header">
              <div class="alert-title"><?=htmlspecialchars($alert['item_name'])?></div>
              <span class="alert-urgency urgency-<?=$alert['urgency']?>">
                <?=strtoupper($alert['urgency'])?>
              </span>
            </div>
            <div class="alert-meta">
              <div style="margin-bottom: 4px;">
                <strong><?=ucfirst(str_replace('_', ' ', $alert['alert_type']))?></strong>
                <?php if ($alert['current_quantity'] > 0): ?>
                  • <?=$alert['current_quantity']?> left
                <?php endif; ?>
              </div>
              <?php if (!empty($alert['notes'])): ?>
                <div style="font-size: 11px; color: var(--muted); margin-bottom: 4px;">
                  <?=htmlspecialchars($alert['notes'])?>
                </div>
              <?php endif; ?>
              <div style="font-size: 11px;">
                📅 <?=date('M j, g:i A', strtotime($alert['alert_date'] . ' ' . $alert['alert_time']))?>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

  </main> <!-- .emp-main-content -->
</div> <!-- .emp-dashboard-layout -->

<script>
// ==========================================
// THEME TOGGLE FUNCTIONALITY
// ==========================================
const themeToggle = document.getElementById('theme-toggle');
const html = document.documentElement;

// Apply theme
function applyTheme(theme) {
  if (theme === 'dark') {
    html.setAttribute('data-theme', 'dark');
    themeToggle.textContent = '☀️';
  } else {
    html.removeAttribute('data-theme');
    themeToggle.textContent = '🌙';
  }
}

// Initialize theme from localStorage
const savedTheme = localStorage.getItem('employee_theme') || 'light';
applyTheme(savedTheme);

// Toggle theme on click
themeToggle.addEventListener('click', () => {
  const currentTheme = html.getAttribute('data-theme');
  const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
  applyTheme(newTheme);
  localStorage.setItem('employee_theme', newTheme);
});

// Auto-detect system preference on first visit
if (!localStorage.getItem('employee_theme')) {
  if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    applyTheme('dark');
    localStorage.setItem('employee_theme', 'dark');
  }
}

// ==========================================
// SINGLE PAGE APP NAVIGATION
// ==========================================

// IMPORTANT: Redirect non-dashboard employee pages to dashboard
// This ensures the SPA always loads properly
if (window.location.pathname.includes('/employee/') && 
    !window.location.pathname.includes('/employee/dashboard.php') &&
    !window.location.pathname.includes('/employee/logout.php') &&
    !window.location.pathname.endsWith('/employee/')) {
  
  // Extract page name and redirect to dashboard
  const pathParts = window.location.pathname.split('/');
  const pageFile = pathParts[pathParts.length - 1];
  
  // Redirect to dashboard immediately to prevent loading standalone page
  window.location.href = '/MY CASH/employee/dashboard.php#' + pageFile.replace('.php', '');
  // Stop script execution
  throw new Error('Redirecting to dashboard');
}

const mainContent = document.querySelector('.emp-main-content');
const navLinks = document.querySelectorAll('.sidebar-nav-item:not([href*="logout"]), .quick-action-card, .action-link');
let currentPage = 'dashboard';

// Store original dashboard content
const dashboardContent = mainContent.innerHTML;

// Loading spinner HTML
const loadingHTML = `
  <div style="display: flex; align-items: center; justify-content: center; min-height: 400px; flex-direction: column; gap: 20px;">
    <div style="width: 60px; height: 60px; border: 4px solid var(--border-weak); border-top-color: #10b981; border-radius: 50%; animation: spin 1s linear infinite;"></div>
    <div style="color: var(--muted); font-weight: 600;">Loading...</div>
  </div>
  <style>
    @keyframes spin {
      to { transform: rotate(360deg); }
    }
  </style>
`;

// Load page content via AJAX
async function loadPage(url, pageName) {
  // Don't reload if already on this page
  if (pageName === currentPage) return;
  
  // Show loading state
  mainContent.style.opacity = '0.5';
  mainContent.style.pointerEvents = 'none';
  
  try {
    // Use AJAX content loader
    const response = await fetch(`/MY CASH/employee/ajax_content_loader.php?page=${pageName}`);
    
    if (!response.ok) throw new Error('Failed to load page');
    
    const html = await response.text();
    
    // Fade out
    mainContent.style.transition = 'opacity 0.2s ease';
    mainContent.style.opacity = '0';
    
    setTimeout(() => {
      // Update content
      mainContent.innerHTML = html;
      
      // Update active state in sidebar
      document.querySelectorAll('.sidebar-nav-item').forEach(link => {
        link.classList.remove('active');
      });
      
      const activeLink = document.querySelector(`.sidebar-nav-item[href="${url}"]`);
      if (activeLink) {
        activeLink.classList.add('active');
      }
      
      // Fade in
      mainContent.style.opacity = '1';
      mainContent.style.pointerEvents = 'auto';
      
      // Update current page
      currentPage = pageName;
      
      // Update URL without reload
      history.pushState({ page: pageName, url: url }, '', url);
      
      // Scroll to top
      window.scrollTo({ top: 0, behavior: 'smooth' });
      
      // Re-initialize any scripts in loaded content
      reinitializeScripts();
      
    }, 200);
    
  } catch (error) {
    console.error('Error loading page:', error);
    mainContent.style.opacity = '1';
    mainContent.style.pointerEvents = 'auto';
    
    // Show error message
    mainContent.innerHTML = `
      <div style="text-align: center; padding: 60px 20px;">
        <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.3;">⚠️</div>
        <h2 style="color: var(--card-text); margin-bottom: 10px;">Failed to Load Page</h2>
        <p style="color: var(--muted); margin-bottom: 30px;">There was an error loading the content.</p>
        <button onclick="location.reload()" class="button primary">Refresh Page</button>
      </div>
    `;
  }
}

// Re-initialize scripts in dynamically loaded content
function reinitializeScripts() {
  // Find and execute scripts in the loaded content
  const scripts = mainContent.querySelectorAll('script');
  scripts.forEach(oldScript => {
    const newScript = document.createElement('script');
    if (oldScript.src) {
      newScript.src = oldScript.src;
    } else {
      newScript.textContent = oldScript.textContent;
    }
    oldScript.parentNode.replaceChild(newScript, oldScript);
  });
  
  // Re-attach form submit handlers if needed
  const forms = mainContent.querySelectorAll('form');
  forms.forEach(form => {
    // Prevent default form submission to keep SPA behavior
    form.addEventListener('submit', async (e) => {
      // Allow forms with data-ajax="false" to submit normally
      if (form.dataset.ajax === 'false') return;
      
      e.preventDefault();
      
      const formData = new FormData(form);
      const action = form.action || window.location.href;
      const method = form.method || 'POST';
      
      try {
        const response = await fetch(action, {
          method: method,
          body: formData
        });
        
        if (response.ok) {
          const result = await response.text();
          
          // Check if response is JSON
          try {
            const jsonResult = JSON.parse(result);
            if (jsonResult.success) {
              // Show success message
              showNotification('Success!', 'success');
              // Reload current page to show updated data
              loadPage(window.location.pathname, currentPage);
            } else if (jsonResult.error) {
              showNotification(jsonResult.error, 'error');
            }
          } catch {
            // HTML response - update content
            mainContent.innerHTML = result;
            reinitializeScripts();
          }
        }
      } catch (error) {
        console.error('Form submission error:', error);
        showNotification('An error occurred. Please try again.', 'error');
      }
    });
  });
}

// Show notification toast
function showNotification(message, type = 'info') {
  const toast = document.createElement('div');
  toast.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
    color: white;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
    z-index: 10000;
    font-weight: 600;
    animation: slideIn 0.3s ease;
  `;
  toast.textContent = message;
  
  const style = document.createElement('style');
  style.textContent = `
    @keyframes slideIn {
      from { transform: translateX(100%); opacity: 0; }
      to { transform: translateX(0); opacity: 1; }
    }
  `;
  document.head.appendChild(style);
  
  document.body.appendChild(toast);
  
  setTimeout(() => {
    toast.style.animation = 'slideIn 0.3s ease reverse';
    setTimeout(() => toast.remove(), 300);
  }, 3000);
}

// Handle browser back/forward
window.addEventListener('popstate', (event) => {
  if (event.state && event.state.page) {
    if (event.state.page === 'dashboard') {
      mainContent.innerHTML = dashboardContent;
      currentPage = 'dashboard';
      document.querySelectorAll('.sidebar-nav-item').forEach(link => {
        link.classList.remove('active');
      });
      document.querySelector('.sidebar-nav-item[href*="dashboard"]').classList.add('active');
    } else {
      loadPage(event.state.url, event.state.page);
    }
  }
});

// Set initial state
const currentUrl = window.location.pathname;
const currentHash = window.location.hash.substring(1); // Remove # symbol
const currentUrlParts = currentUrl.split('/');
const currentFileName = currentUrlParts[currentUrlParts.length - 1];

// Check if we have a hash (from redirect) and load that page
if (currentHash) {
  const pageName = currentHash;
  const pageUrl = `/MY CASH/employee/${pageName}.php`;
  
  loadPage(pageUrl, pageName);
  currentPage = pageName;
  
  // Update active navigation state
  document.querySelectorAll('.sidebar-nav-item').forEach(link => {
    link.classList.remove('active');
    const linkHref = link.getAttribute('href');
    if (linkHref && (linkHref.includes(`${pageName}.php`) || linkHref === pageUrl)) {
      link.classList.add('active');
    }
  });
  
  // Clear hash from URL
  history.replaceState({ page: pageName, url: pageUrl }, '', '/MY CASH/employee/dashboard.php');
} else {
  // We're on dashboard, set initial state
  history.replaceState({ page: 'dashboard', url: window.location.href }, '', window.location.href);
}

// Attach click handlers to navigation links
navLinks.forEach(link => {
  link.addEventListener('click', (e) => {
    const href = link.getAttribute('href');
    
    // Skip external links and logout
    if (!href || href.startsWith('http') || href.includes('logout')) {
      return;
    }
    
    e.preventDefault();
    
    // Extract page name from URL
    const pageName = href.split('/').pop().replace('.php', '');
    
    // Load dashboard content if clicking dashboard link
    if (href.includes('dashboard.php')) {
      mainContent.style.transition = 'opacity 0.2s ease';
      mainContent.style.opacity = '0';
      
      setTimeout(() => {
        mainContent.innerHTML = dashboardContent;
        mainContent.style.opacity = '1';
        currentPage = 'dashboard';
        
        // Update active state
        document.querySelectorAll('.sidebar-nav-item').forEach(l => l.classList.remove('active'));
        link.classList.add('active');
        
        // Update URL
        history.pushState({ page: 'dashboard', url: href }, '', href);
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }, 200);
    } else {
      loadPage(href, pageName);
    }
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
