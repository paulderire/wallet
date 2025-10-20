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
$user_id = $_SESSION['user_id'];

// Fetch employees
$employees = [];
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$department_filter = $_GET['department'] ?? '';

try {
  $query = "SELECT * FROM employees WHERE user_id = ?";
  $params = [$user_id];
  
  if ($search) {
    $query .= " AND (name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
  }
  
  if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
  }
  
  if ($department_filter) {
    $query .= " AND department = ?";
    $params[] = $department_filter;
  }
  
  $query .= " ORDER BY created_at DESC";
  
  $stmt = $conn->prepare($query);
  $stmt->execute($params);
  $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $employees = [];
}

// Get unique departments
$departments = [];
try {
  $stmt = $conn->prepare("SELECT DISTINCT department FROM employees WHERE user_id = ? AND department IS NOT NULL ORDER BY department");
  $stmt->execute([$user_id]);
  $departments = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}
?>
<style>
  #app-main {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: calc(100vh - 64px);
    padding: 24px;
    margin: 0;
    width: 100%;
    max-width: none;
    font-family: 'Inter', sans-serif;
  }
  
  .page-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px 32px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    margin-bottom: 32px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
  }
  
  .page-title {
    font-size: 28px;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0;
  }
  
  .filters-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    margin-bottom: 24px;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
  }
  
  .filter-grid {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 16px;
    align-items: end;
  }
  
  .filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #4a5568;
    font-size: 14px;
  }
  
  .filter-group input,
  .filter-group select {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
  }
  
  .filter-group input:focus,
  .filter-group select:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  
  .employees-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
  }
  
  .employee-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    transition: transform 0.2s, box-shadow 0.2s;
  }
  
  .employee-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 48px rgba(0, 0, 0, 0.15);
  }
  
  .employee-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 16px;
  }
  
  .employee-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 24px;
  }
  
  .employee-info h3 {
    margin: 0 0 4px 0;
    font-size: 18px;
    font-weight: 700;
    color: #2d3748;
  }
  
  .employee-role {
    color: #718096;
    font-size: 14px;
  }
  
  .employee-details {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 16px;
  }
  
  .detail-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    color: #4a5568;
  }
  
  .detail-icon {
    color: #667eea;
  }
  
  .employee-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 16px;
    border-top: 1px solid #e2e8f0;
  }
  
  .status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
  }
  
  .status-active {
    background: #d4f4dd;
    color: #22543d;
  }
  
  .status-inactive {
    background: #fed7d7;
    color: #742a2a;
  }
  
  .btn {
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    font-size: 14px;
  }
  
  .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
  }
  
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
  }
  
  .btn-filter {
    padding: 10px 24px;
  }
  
  .empty-state {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 64px 32px;
    border-radius: 16px;
    text-align: center;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    max-width: 1400px;
    margin-left: auto;
    margin-right: auto;
  }
  
  .empty-state-icon {
    font-size: 64px;
    margin-bottom: 16px;
  }
  
  .empty-state h3 {
    margin: 0 0 8px 0;
    font-size: 24px;
    color: #2d3748;
  }
  
  .empty-state p {
    color: #718096;
    margin-bottom: 24px;
  }
  
  @media (max-width: 768px) {
    .filter-grid {
      grid-template-columns: 1fr;
    }
    
    .employees-grid {
      grid-template-columns: 1fr;
    }
  }
</style>

<div class="page-header">
  <h1 class="page-title">👥 Employee Management</h1>
  <a href="/MY CASH/business/add_employee.php" class="btn btn-primary">+ Add Employee</a>
</div>

<div class="filters-card">
  <form method="GET" action="">
    <div class="filter-grid">
      <div class="filter-group">
        <label>Search</label>
        <input type="text" name="search" placeholder="Name, email, or phone..." value="<?php echo htmlspecialchars($search); ?>">
      </div>
      
      <div class="filter-group">
        <label>Status</label>
        <select name="status">
          <option value="">All Status</option>
          <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
          <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
      </div>
      
      <div class="filter-group">
        <label>Department</label>
        <select name="department">
          <option value="">All Departments</option>
          <?php foreach ($departments as $dept): ?>
            <option value="<?php echo htmlspecialchars($dept); ?>" <?php echo $department_filter === $dept ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($dept); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      
      <div class="filter-group">
        <button type="submit" class="btn btn-primary btn-filter">Apply Filters</button>
      </div>
    </div>
  </form>
</div>

<?php if (empty($employees)): ?>
  <div class="empty-state">
    <div class="empty-state-icon">👥</div>
    <h3>No Employees Found</h3>
    <p>Start building your team by adding your first employee</p>
    <a href="/MY CASH/business/add_employee.php" class="btn btn-primary">Add First Employee</a>
  </div>
<?php else: ?>
  <div class="employees-grid">
    <?php foreach ($employees as $emp): ?>
      <div class="employee-card">
        <div class="employee-header">
          <div class="employee-avatar">
            <?php echo strtoupper(substr($emp['name'] ?? 'E', 0, 1)); ?>
          </div>
          <div class="employee-info">
            <h3><?php echo htmlspecialchars($emp['name'] ?? 'N/A'); ?></h3>
            <div class="employee-role"><?php echo htmlspecialchars($emp['role'] ?? 'Employee'); ?></div>
          </div>
        </div>
        
        <div class="employee-details">
          <?php if (!empty($emp['email'])): ?>
            <div class="detail-row">
              <span class="detail-icon">📧</span>
              <span><?php echo htmlspecialchars($emp['email']); ?></span>
            </div>
          <?php endif; ?>
          
          <?php if (!empty($emp['phone'])): ?>
            <div class="detail-row">
              <span class="detail-icon">📱</span>
              <span><?php echo htmlspecialchars($emp['phone']); ?></span>
            </div>
          <?php endif; ?>
          
          <?php if (!empty($emp['department'])): ?>
            <div class="detail-row">
              <span class="detail-icon">🏢</span>
              <span><?php echo htmlspecialchars($emp['department']); ?></span>
            </div>
          <?php endif; ?>
          
          <?php if (!empty($emp['salary'])): ?>
            <div class="detail-row">
              <span class="detail-icon">💰</span>
              <span>RWF <?php echo number_format($emp['salary'], 0); ?></span>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="employee-footer">
          <span class="status-badge status-<?php echo strtolower($emp['status'] ?? 'active'); ?>">
            <?php echo ucfirst($emp['status'] ?? 'active'); ?>
          </span>
          <span style="font-size: 12px; color: #a0aec0;">
            Joined <?php echo date('M Y', strtotime($emp['hire_date'] ?? $emp['created_at'])); ?>
          </span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>
