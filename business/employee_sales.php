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

// Get filter parameters
$date_filter = $_GET['date'] ?? date('Y-m-d');
$employee_filter = $_GET['employee'] ?? 'all';
$payment_filter = $_GET['payment'] ?? 'all';

// Build query with filters
$query = "SELECT 
    et.id,
    et.employee_id,
    et.task_date,
    et.title,
    et.customer_name,
    et.items_sold,
    et.total_amount,
    et.payment_method,
    et.created_at,
    CONCAT(e.first_name, ' ', e.last_name) as employee_name,
    e.role as employee_role
    FROM employee_tasks et
    LEFT JOIN employees e ON et.employee_id = e.id
    WHERE et.user_id = ? AND et.transaction_type = 'sale'";

$params = [$user_id];

if ($date_filter !== 'all') {
    $query .= " AND et.task_date = ?";
    $params[] = $date_filter;
}

if ($employee_filter !== 'all') {
    $query .= " AND et.employee_id = ?";
    $params[] = $employee_filter;
}

if ($payment_filter !== 'all') {
    $query .= " AND et.payment_method = ?";
    $params[] = $payment_filter;
}

$query .= " ORDER BY et.task_date DESC, et.created_at DESC LIMIT 100";

$sales = [];
$total_sales = 0;
$total_cash = 0;
$total_mobile = 0;
$transaction_count = 0;

try {
  $stmt = $conn->prepare($query);
  $stmt->execute($params);
  $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
  
  // Calculate totals
  foreach ($sales as $sale) {
      $total_sales += $sale['total_amount'];
      if ($sale['payment_method'] === 'cash') $total_cash += $sale['total_amount'];
      if ($sale['payment_method'] === 'mobile_money') $total_mobile += $sale['total_amount'];
      $transaction_count++;
  }
} catch (Exception $e) {}

// Get employees for filter dropdown
$employees = [];
try {
  $stmt = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as full_name, role FROM employees WHERE user_id = ? AND status = 'active' ORDER BY first_name, last_name");
  $stmt->execute([$user_id]);
  $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

include __DIR__ . '/../includes/header.php';
?>

<style>
.sales-container {
  max-width: 1600px;
  margin: 0 auto;
  padding: 24px;
}

.sales-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 32px;
  border-radius: 16px;
  margin-bottom: 24px;
  color: white;
  box-shadow: 0 8px 32px rgba(102, 126, 234, 0.3);
}

.sales-header h1 {
  font-size: 32px;
  font-weight: 800;
  margin-bottom: 8px;
}

.filters-card {
  background: white;
  padding: 24px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  margin-bottom: 24px;
}

.filter-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 16px;
}

.filter-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.filter-group label {
  font-size: 12px;
  font-weight: 600;
  color: #718096;
  text-transform: uppercase;
}

.filter-group select,
.filter-group input {
  padding: 10px;
  border: 2px solid #e2e8f0;
  border-radius: 8px;
  font-size: 14px;
  font-family: 'Inter', sans-serif;
}

.filter-group select:focus,
.filter-group input:focus {
  outline: none;
  border-color: #667eea;
}

.stats-summary {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.summary-card {
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  border-left: 4px solid #667eea;
}

.summary-card.success {
  border-left-color: #10b981;
}

.summary-label {
  font-size: 11px;
  color: #718096;
  font-weight: 600;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.summary-value {
  font-size: 24px;
  font-weight: 900;
  color: #1a202c;
}

.sales-table-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  overflow: hidden;
}

.table-header {
  padding: 20px 24px;
  background: #f7fafc;
  border-bottom: 2px solid #e2e8f0;
  font-weight: 700;
  font-size: 16px;
  color: #1a202c;
}

.sales-table {
  width: 100%;
  border-collapse: collapse;
}

.sales-table th {
  background: #f7fafc;
  padding: 12px 16px;
  text-align: left;
  font-size: 11px;
  font-weight: 700;
  color: #718096;
  text-transform: uppercase;
  border-bottom: 2px solid #e2e8f0;
}

.sales-table td {
  padding: 16px;
  border-bottom: 1px solid #f1f5f9;
  font-size: 14px;
}

.sales-table tr:hover {
  background: #f8fafc;
}

.payment-badge {
  display: inline-block;
  padding: 4px 10px;
  border-radius: 6px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
}

.payment-badge.cash {
  background: #d1fae5;
  color: #065f46;
}

.payment-badge.mobile_money {
  background: #dbeafe;
  color: #1e40af;
}

.payment-badge.bank_transfer {
  background: #e0e7ff;
  color: #3730a3;
}

.payment-badge.credit {
  background: #fef3c7;
  color: #92400e;
}

.employee-badge {
  display: inline-block;
  padding: 4px 8px;
  background: #f1f5f9;
  border-radius: 4px;
  font-size: 11px;
  font-weight: 600;
  color: #475569;
}

.amount-cell {
  font-weight: 700;
  color: #10b981;
  font-size: 15px;
}

.empty-state {
  text-align: center;
  padding: 60px 20px;
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
  border: none;
  cursor: pointer;
  font-family: 'Inter', sans-serif;
}

.btn-primary {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
}

.btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-secondary {
  background: #f1f5f9;
  color: #475569;
}

.btn-secondary:hover {
  background: #e2e8f0;
}
</style>

<div class="sales-container">
  <!-- Header -->
  <div class="sales-header">
    <h1>📊 Employee Sales Records</h1>
    <p>Detailed transaction history and sales analytics</p>
  </div>

  <!-- Filters -->
  <div class="filters-card">
    <form method="GET" action="">
      <div class="filter-row">
        <div class="filter-group">
          <label>Date</label>
          <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter); ?>">
        </div>

        <div class="filter-group">
          <label>Employee</label>
          <select name="employee">
            <option value="all" <?php echo $employee_filter === 'all' ? 'selected' : ''; ?>>All Employees</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?php echo $emp['id']; ?>" <?php echo $employee_filter == $emp['id'] ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo htmlspecialchars($emp['role']); ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label>Payment Method</label>
          <select name="payment">
            <option value="all" <?php echo $payment_filter === 'all' ? 'selected' : ''; ?>>All Methods</option>
            <option value="cash" <?php echo $payment_filter === 'cash' ? 'selected' : ''; ?>>Cash</option>
            <option value="mobile_money" <?php echo $payment_filter === 'mobile_money' ? 'selected' : ''; ?>>Mobile Money</option>
            <option value="bank_transfer" <?php echo $payment_filter === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
            <option value="credit" <?php echo $payment_filter === 'credit' ? 'selected' : ''; ?>>Credit</option>
          </select>
        </div>
      </div>

      <div style="display: flex; gap: 12px;">
        <button type="submit" class="btn btn-primary">🔍 Apply Filters</button>
        <a href="employee_sales.php" class="btn btn-secondary">🔄 Reset</a>
        <a href="financial_dashboard.php" class="btn btn-secondary" style="margin-left: auto;">← Back to Dashboard</a>
      </div>
    </form>
  </div>

  <!-- Summary Stats -->
  <div class="stats-summary">
    <div class="summary-card success">
      <div class="summary-label">Total Sales</div>
      <div class="summary-value">RWF <?php echo number_format($total_sales, 0); ?></div>
      <div style="font-size: 14px; color: #059669; margin-top: 4px; font-weight: 600;">
        $<?php echo number_format($total_sales / USD_TO_RWF, 2); ?>
      </div>
    </div>
    <div class="summary-card success">
      <div class="summary-label">Cash Collected</div>
      <div class="summary-value">RWF <?php echo number_format($total_cash, 0); ?></div>
      <div style="font-size: 14px; color: #059669; margin-top: 4px; font-weight: 600;">
        $<?php echo number_format($total_cash / USD_TO_RWF, 2); ?>
      </div>
    </div>
    <div class="summary-card">
      <div class="summary-label">Mobile Money</div>
      <div class="summary-value">RWF <?php echo number_format($total_mobile, 0); ?></div>
      <div style="font-size: 14px; color: #667eea; margin-top: 4px; font-weight: 600;">
        $<?php echo number_format($total_mobile / USD_TO_RWF, 2); ?>
      </div>
    </div>
    <div class="summary-card">
      <div class="summary-label">Transactions</div>
      <div class="summary-value"><?php echo $transaction_count; ?></div>
    </div>
  </div>

  <!-- Sales Table -->
  <div class="sales-table-card">
    <div class="table-header">
      📝 Sales Transactions (Showing <?php echo count($sales); ?> records)
    </div>

    <?php if (empty($sales)): ?>
      <div class="empty-state">
        <div class="empty-icon">📊</div>
        <p>No sales records found for the selected filters</p>
        <p style="font-size: 13px; margin-top: 8px;">Try adjusting your filters or date range</p>
      </div>
    <?php else: ?>
      <table class="sales-table">
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Employee</th>
            <th>Customer</th>
            <th>Items</th>
            <th>Payment</th>
            <th>Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sales as $sale): ?>
            <tr>
              <td>
                <div style="font-weight: 600;"><?php echo date('M j, Y', strtotime($sale['task_date'])); ?></div>
                <div style="font-size: 12px; color: #718096;">
                  <?php echo date('g:i A', strtotime($sale['created_at'])); ?>
                </div>
              </td>
              <td>
                <div style="font-weight: 600;"><?php echo htmlspecialchars($sale['employee_name'] ?? 'N/A'); ?></div>
                <span class="employee-badge"><?php echo htmlspecialchars($sale['employee_role'] ?? 'N/A'); ?></span>
              </td>
              <td style="font-weight: 500;">
                <?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in'); ?>
              </td>
              <td style="max-width: 300px; font-size: 13px; color: #475569;">
                <?php echo htmlspecialchars($sale['items_sold'] ?? $sale['title']); ?>
              </td>
              <td>
                <span class="payment-badge <?php echo $sale['payment_method']; ?>">
                  <?php echo str_replace('_', ' ', $sale['payment_method']); ?>
                </span>
              </td>
              <td class="amount-cell">
                <div>RWF <?php echo number_format($sale['total_amount'], 0); ?></div>
                <div style="font-size: 12px; color: #059669; font-weight: 600; margin-top: 2px;">
                  $<?php echo number_format($sale['total_amount'] / USD_TO_RWF, 2); ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
