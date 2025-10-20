<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';

// Fetch employee salary
$employee = null;
try {
  $stmt = $conn->prepare("SELECT salary FROM employees WHERE id = ?");
  $stmt->execute([$employee_id]);
  $employee = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch payment history
$payments = [];
try {
  $stmt = $conn->prepare("
    SELECT * FROM employee_payments
    WHERE employee_id = ?
    ORDER BY payment_date DESC, created_at DESC
    LIMIT 50
  ");
  $stmt->execute([$employee_id]);
  $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Calculate totals
$total_received = 0;
$total_bonuses = 0;
$total_deductions = 0;
$pending_amount = 0;

foreach ($payments as $payment) {
  if ($payment['status'] === 'paid') {
    $total_received += $payment['amount'];
    if ($payment['payment_type'] === 'bonus') $total_bonuses += $payment['amount'];
    if ($payment['payment_type'] === 'deduction') $total_deductions += $payment['amount'];
  }
  if ($payment['status'] === 'pending') {
    $pending_amount += $payment['amount'];
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Payments - Employee Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 1200px; margin: 0 auto; }
    .header { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
    .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
    .header-title { font-size: 1.8rem; font-weight: 800; color: #1a202c; }
    .btn { padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .btn-primary { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
    .btn-secondary { background: rgba(0,0,0,0.05); color: #4a5568; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
    .stat-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
    .stat-icon { font-size: 2rem; margin-bottom: 12px; }
    .stat-value { font-size: 2rem; font-weight: 700; color: #1a202c; margin-bottom: 8px; }
    .stat-label { font-size: 0.85rem; color: #718096; text-transform: uppercase; }
    .card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 16px; padding: 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
    .card h3 { font-size: 1.3rem; margin-bottom: 20px; color: #1a202c; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; }
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }
    th { padding: 16px; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; }
    td { padding: 16px; border-bottom: 1px solid #e2e8f0; color: #1a202c; }
    tbody tr:hover { background: rgba(59,130,246,0.05); }
    .badge { padding: 6px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; }
    .badge-salary { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
    .badge-bonus { background: rgba(59,130,246,0.1); color: #3b82f6; border: 1px solid rgba(59,130,246,0.3); }
    .badge-deduction { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
    .badge-advance { background: rgba(251,191,36,0.1); color: #f59e0b; border: 1px solid rgba(251,191,36,0.3); }
    .status-paid { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
    .status-pending { background: rgba(251,191,36,0.1); color: #f59e0b; border: 1px solid rgba(251,191,36,0.3); }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-content">
        <div>
          <h1 class="header-title">💰 My Payments</h1>
          <p style="color:#718096;margin-top:4px">View your salary and payment history</p>
        </div>
        <a href="/MY CASH/employee/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
      </div>
    </div>

    <!-- Statistics -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">💵</div>
        <div class="stat-value"><?= number_format($employee['salary'] ?? 0, 0) ?></div>
        <div class="stat-label">Monthly Salary (RWF)</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">💰</div>
        <div class="stat-value"><?= number_format($total_received, 0) ?></div>
        <div class="stat-label">Total Received (RWF)</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">🎁</div>
        <div class="stat-value"><?= number_format($total_bonuses, 0) ?></div>
        <div class="stat-label">Total Bonuses (RWF)</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-value"><?= number_format($pending_amount, 0) ?></div>
        <div class="stat-label">Pending (RWF)</div>
      </div>
    </div>

    <!-- Payment History -->
    <div class="card">
      <h3>📊 Payment History</h3>
      
      <?php if (empty($payments)): ?>
      <div style="text-align:center;padding:60px 20px;color:#a0aec0">
        <div style="font-size:3rem">💳</div>
        <h3>No Payment Records</h3>
        <p>Your payment history will appear here</p>
      </div>
      <?php else: ?>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Amount</th>
              <th>Status</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($payments as $payment): ?>
            <tr>
              <td style="font-weight:600"><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
              <td>
                <span class="badge badge-<?= $payment['payment_type'] ?>">
                  <?= strtoupper($payment['payment_type']) ?>
                </span>
              </td>
              <td style="font-weight:700;<?= $payment['payment_type'] === 'deduction' ? 'color:#ef4444' : 'color:#10b981' ?>">
                <?= $payment['payment_type'] === 'deduction' ? '-' : '+' ?><?= number_format($payment['amount'], 0) ?> RWF
              </td>
              <td>
                <span class="badge status-<?= $payment['status'] ?>">
                  <?= strtoupper($payment['status']) ?>
                </span>
              </td>
              <td style="font-size:0.9rem;color:#718096"><?= htmlspecialchars($payment['notes'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
