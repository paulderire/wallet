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

// Fetch payroll records
$payroll_records = [];
$month_filter = $_GET['month'] ?? date('Y-m');

try {
  $stmt = $conn->prepare("SELECT p.*, e.name as employee_name, e.role 
    FROM payroll p 
    LEFT JOIN employees e ON p.employee_id = e.id 
    WHERE p.user_id = ? AND DATE_FORMAT(p.pay_date, '%Y-%m') = ?
    ORDER BY p.pay_date DESC");
  $stmt->execute([$user_id, $month_filter]);
  $payroll_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  $payroll_records = [];
}

// Calculate totals
$total_payroll = 0;
$total_bonuses = 0;
$total_deductions = 0;
foreach ($payroll_records as $record) {
  $total_payroll += $record['salary'] ?? 0;
  $total_bonuses += $record['bonus'] ?? 0;
  $total_deductions += $record['deductions'] ?? 0;
}
$net_total = $total_payroll + $total_bonuses - $total_deductions;
?>
<style>
  body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
    font-family: 'Inter', sans-serif;
  }
  
  .page-header {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px 32px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    margin-bottom: 32px;
  }
  
  .page-title {
    font-size: 28px;
    font-weight: 800;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin: 0 0 8px 0;
  }
  
  .page-subtitle {
    color: #718096;
    font-size: 14px;
  }
  
  .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
  }
  
  .stat-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    border-left: 4px solid;
  }
  
  .stat-card.total {
    border-left-color: #667eea;
  }
  
  .stat-card.bonuses {
    border-left-color: #48bb78;
  }
  
  .stat-card.deductions {
    border-left-color: #f56565;
  }
  
  .stat-card.net {
    border-left-color: #764ba2;
  }
  
  .stat-label {
    color: #718096;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 8px;
  }
  
  .stat-value {
    font-size: 32px;
    font-weight: 800;
    color: #2d3748;
  }
  
  .filter-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    margin-bottom: 24px;
  }
  
  .filter-form {
    display: flex;
    gap: 16px;
    align-items: end;
  }
  
  .filter-group {
    flex: 1;
  }
  
  .filter-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #4a5568;
    font-size: 14px;
  }
  
  .filter-group input {
    width: 100%;
    padding: 10px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
  }
  
  .payroll-table-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
  }
  
  .payroll-table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .payroll-table thead {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  }
  
  .payroll-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 700;
    color: #2d3748;
    font-size: 14px;
    border-bottom: 2px solid #e2e8f0;
  }
  
  .payroll-table td {
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
    color: #4a5568;
    font-size: 14px;
  }
  
  .payroll-table tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
  }
  
  .employee-info {
    display: flex;
    flex-direction: column;
  }
  
  .employee-name {
    font-weight: 600;
    color: #2d3748;
  }
  
  .employee-role {
    font-size: 12px;
    color: #718096;
  }
  
  .amount {
    font-weight: 600;
  }
  
  .amount.positive {
    color: #48bb78;
  }
  
  .amount.negative {
    color: #f56565;
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
  
  .empty-state {
    text-align: center;
    padding: 64px 32px;
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
  }
  
  @media (max-width: 768px) {
    .stats-grid {
      grid-template-columns: 1fr;
    }
    
    .filter-form {
      flex-direction: column;
    }
    
    .payroll-table-card {
      overflow-x: scroll;
    }
  }
</style>

<div class="page-header">
  <h1 class="page-title">💰 Payroll Management</h1>
  <p class="page-subtitle">Track and manage employee compensation</p>
</div>

<div class="stats-grid">
  <div class="stat-card total">
    <div class="stat-label">Total Salary</div>
    <div class="stat-value">RWF <?php echo number_format($total_payroll, 0); ?></div>
  </div>
  
  <div class="stat-card bonuses">
    <div class="stat-label">Total Bonuses</div>
    <div class="stat-value">RWF <?php echo number_format($total_bonuses, 0); ?></div>
  </div>
  
  <div class="stat-card deductions">
    <div class="stat-label">Total Deductions</div>
    <div class="stat-value">RWF <?php echo number_format($total_deductions, 0); ?></div>
  </div>
  
  <div class="stat-card net">
    <div class="stat-label">Net Payroll</div>
    <div class="stat-value">RWF <?php echo number_format($net_total, 0); ?></div>
  </div>
</div>

<div class="filter-card">
  <form method="GET" action="" class="filter-form">
    <div class="filter-group">
      <label>Select Month</label>
      <input type="month" name="month" value="<?php echo htmlspecialchars($month_filter); ?>">
    </div>
    <button type="submit" class="btn btn-primary">View Month</button>
  </form>
</div>

<div class="payroll-table-card">
  <?php if (empty($payroll_records)): ?>
    <div class="empty-state">
      <div class="empty-state-icon">💰</div>
      <h3>No Payroll Records</h3>
      <p>No payroll data found for <?php echo date('F Y', strtotime($month_filter . '-01')); ?></p>
    </div>
  <?php else: ?>
    <table class="payroll-table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Pay Date</th>
          <th>Base Salary</th>
          <th>Bonuses</th>
          <th>Deductions</th>
          <th>Net Pay</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($payroll_records as $record): 
          $net_pay = ($record['salary'] ?? 0) + ($record['bonus'] ?? 0) - ($record['deductions'] ?? 0);
        ?>
          <tr>
            <td>
              <div class="employee-info">
                <span class="employee-name"><?php echo htmlspecialchars($record['employee_name'] ?? 'Unknown'); ?></span>
                <span class="employee-role"><?php echo htmlspecialchars($record['role'] ?? ''); ?></span>
              </div>
            </td>
            <td><?php echo date('M d, Y', strtotime($record['pay_date'])); ?></td>
            <td class="amount">RWF <?php echo number_format($record['salary'] ?? 0, 0); ?></td>
            <td class="amount positive">+RWF <?php echo number_format($record['bonus'] ?? 0, 0); ?></td>
            <td class="amount negative">-RWF <?php echo number_format($record['deductions'] ?? 0, 0); ?></td>
            <td class="amount" style="font-weight: 700; font-size: 16px;">
              RWF <?php echo number_format($net_pay, 0); ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
