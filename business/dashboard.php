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

// Redirect to new financial dashboard with employee balances and alerts
header("Location: /MY CASH/business/financial_dashboard.php");
exit;

// Business statistics
$total_employees = 0; $active_employees = 0; $total_projects = 0; $active_projects = 0;
$total_payroll = 0; $pending_leaves = 0;

try {
  $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) as active FROM employees WHERE user_id=?");
  $stmt->execute([$user_id]);
  $emp_stats = $stmt->fetch(PDO::FETCH_ASSOC);
  $total_employees = $emp_stats['total'] ?? 0;
  $active_employees = $emp_stats['active'] ?? 0;
} catch (Exception $e) {}

try {
  $stmt = $conn->prepare("SELECT COUNT(*) as total, SUM(CASE WHEN status IN ('in progress', 'not started') THEN 1 ELSE 0 END) as active FROM projects WHERE user_id=?");
  $stmt->execute([$user_id]);
  $proj_stats = $stmt->fetch(PDO::FETCH_ASSOC);
  $total_projects = $proj_stats['total'] ?? 0;
  $active_projects = $proj_stats['active'] ?? 0;
} catch (Exception $e) {}

try {
  $stmt = $conn->prepare("SELECT SUM(salary) as total FROM employees WHERE user_id=? AND status='active'");
  $stmt->execute([$user_id]);
  $payroll_stats = $stmt->fetch(PDO::FETCH_ASSOC);
  $total_payroll = $payroll_stats['total'] ?? 0;
} catch (Exception $e) {}

try {
  $stmt = $conn->prepare("SELECT COUNT(*) as pending FROM leave_requests lr JOIN employees e ON lr.employee_id=e.id WHERE e.user_id=? AND lr.status='pending'");
  $stmt->execute([$user_id]);
  $leave_stats = $stmt->fetch(PDO::FETCH_ASSOC);
  $pending_leaves = $leave_stats['pending'] ?? 0;
} catch (Exception $e) {}

// Recent employees
$recent_employees = [];
try {
  $stmt = $conn->prepare("SELECT * FROM employees WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
  $stmt->execute([$user_id]);
  $recent_employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Recent projects
$recent_projects = [];
try {
  $stmt = $conn->prepare("SELECT * FROM projects WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
  $stmt->execute([$user_id]);
  $recent_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>

<style>
.business-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 40px;
  border-radius: 20px;
  margin-bottom: 32px;
  color: white;
  position: relative;
  overflow: hidden;
}
.business-header::before {
  content: '🏢';
  position: absolute;
  right: 40px;
  top: 50%;
  transform: translateY(-50%);
  font-size: 8rem;
  opacity: 0.1;
}
.business-header h1 {
  font-size: 2.5rem;
  font-weight: 800;
  margin: 0 0 12px 0;
  color: white;
}
.business-header p {
  font-size: 1.1rem;
  opacity: 0.9;
  margin: 0;
}
.quick-actions {
  display: flex;
  gap: 12px;
  margin-bottom: 32px;
  flex-wrap: wrap;
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
.stat-card.employees::before { background: linear-gradient(90deg, #2ed573, #11998e); }
.stat-card.projects::before { background: linear-gradient(90deg, #667eea, #764ba2); }
.stat-card.payroll::before { background: linear-gradient(90deg, #ffa500, #ff6348); }
.stat-card.leaves::before { background: linear-gradient(90deg, #3498db, #2980b9); }
.stat-icon {
  font-size: 2.5rem;
  opacity: 0.2;
  position: absolute;
  right: 20px;
  top: 20px;
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
.content-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
  gap: 24px;
}
.section-card {
  background: var(--card-bg);
  border: 1px solid var(--border-weak);
  border-radius: 16px;
  padding: 24px;
}
.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}
.section-title {
  font-size: 1.3rem;
  font-weight: 700;
  color: var(--card-text);
  margin: 0;
}
.item-list {
  list-style: none;
  padding: 0;
  margin: 0;
}
.item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  margin-bottom: 12px;
  background: rgba(var(--card-text-rgb), 0.03);
  border: 1px solid var(--border-weak);
  border-radius: 10px;
  transition: all 0.3s;
}
.item:hover {
  background: rgba(167, 139, 250, 0.08);
  border-color: #a78bfa;
  transform: translateX(4px);
}
.item-avatar {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #667eea, #764ba2);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 1.5rem;
  flex-shrink: 0;
}
.item-info {
  flex: 1;
}
.item-name {
  font-weight: 700;
  color: var(--card-text);
  margin-bottom: 4px;
}
.item-meta {
  font-size: 0.85rem;
  color: var(--muted);
}
.badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-size: 0.75rem;
  font-weight: 600;
  text-transform: uppercase;
}
.badge.active { background: rgba(46, 213, 115, 0.15); color: #2ed573; }
.badge.inactive { background: rgba(149, 165, 166, 0.15); color: #95a5a6; }
.badge.in-progress { background: rgba(52, 152, 219, 0.15); color: #3498db; }
.badge.completed { background: rgba(46, 213, 115, 0.15); color: #2ed573; }
.badge.not-started { background: rgba(255, 165, 0, 0.15); color: #ffa500; }
.empty-state {
  text-align: center;
  padding: 40px 20px;
  color: var(--muted);
}
@media (max-width: 768px) {
  .content-grid {
    grid-template-columns: 1fr;
  }
}
</style>

<div class="business-header">
  <h1>🏢 Business Management</h1>
  <p>Manage employees, projects, payroll, and track business performance</p>
</div>

<div class="quick-actions">
  <a href="/MY CASH/business/employees.php" class="button primary">👥 Manage Employees</a>
  <a href="/MY CASH/business/projects.php" class="button ghost">📁 Projects</a>
  <a href="/MY CASH/business/payroll.php" class="button ghost">💰 Payroll</a>
  <a href="/MY CASH/business/reports.php" class="button ghost">📊 Reports</a>
</div>

<div class="stats-grid">
  <div class="stat-card employees">
    <div class="stat-icon">👥</div>
    <div class="stat-label">Total Employees</div>
    <div class="stat-value"><?=$total_employees?></div>
    <div class="stat-sub"><?=$active_employees?> active</div>
  </div>

  <div class="stat-card projects">
    <div class="stat-icon">📁</div>
    <div class="stat-label">Projects</div>
    <div class="stat-value"><?=$total_projects?></div>
    <div class="stat-sub"><?=$active_projects?> active</div>
  </div>

  <div class="stat-card payroll">
    <div class="stat-icon">💰</div>
    <div class="stat-label">Monthly Payroll</div>
    <div class="stat-value">$<?=number_format($total_payroll, 0)?></div>
    <div class="stat-sub">Active employees</div>
  </div>

  <div class="stat-card leaves">
    <div class="stat-icon">📋</div>
    <div class="stat-label">Pending Leaves</div>
    <div class="stat-value"><?=$pending_leaves?></div>
    <div class="stat-sub">Awaiting approval</div>
  </div>
</div>

<div class="content-grid">
  <div class="section-card">
    <div class="section-header">
      <h2 class="section-title">👥 Recent Employees</h2>
      <a href="/MY CASH/business/employees.php" class="button ghost small">View All</a>
    </div>
    
    <?php if(empty($recent_employees)): ?>
      <div class="empty-state">
        <p>No employees yet. <a href="/MY CASH/business/add_employee.php">Add your first employee</a></p>
      </div>
    <?php else: ?>
      <ul class="item-list">
        <?php foreach($recent_employees as $emp): ?>
          <li class="item">
            <div class="item-avatar">
              <?=strtoupper(substr($emp['first_name'], 0, 1))?>
            </div>
            <div class="item-info">
              <div class="item-name"><?=htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name'])?></div>
              <div class="item-meta"><?=htmlspecialchars($emp['role'] ?? 'Employee')?> • <?=htmlspecialchars($emp['department'] ?? 'General')?></div>
            </div>
            <span class="badge <?=strtolower(str_replace(' ', '-', $emp['status']))?>"><?=ucfirst($emp['status'])?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>

  <div class="section-card">
    <div class="section-header">
      <h2 class="section-title">📁 Recent Projects</h2>
      <a href="/MY CASH/business/projects.php" class="button ghost small">View All</a>
    </div>
    
    <?php if(empty($recent_projects)): ?>
      <div class="empty-state">
        <p>No projects yet. <a href="/MY CASH/business/add_project.php">Create your first project</a></p>
      </div>
    <?php else: ?>
      <ul class="item-list">
        <?php foreach($recent_projects as $proj): ?>
          <li class="item">
            <div class="item-avatar">📁</div>
            <div class="item-info">
              <div class="item-name"><?=htmlspecialchars($proj['project_name'])?></div>
              <div class="item-meta">
                <?=$proj['deadline'] ? 'Due: ' . date('M d, Y', strtotime($proj['deadline'])) : 'No deadline'?>
              </div>
            </div>
            <span class="badge <?=strtolower(str_replace(' ', '-', $proj['status']))?>"><?=ucfirst($proj['status'])?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
