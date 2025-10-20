<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';

// Auto-create table if doesn't exist
try {
  $conn->exec("
    CREATE TABLE IF NOT EXISTS transaction_corrections (
      id INT AUTO_INCREMENT PRIMARY KEY,
      employee_id INT NOT NULL,
      transaction_id INT DEFAULT NULL,
      correction_type ENUM('amount', 'payment_method', 'customer', 'item', 'delete', 'other') NOT NULL,
      original_value TEXT,
      corrected_value TEXT,
      reason TEXT NOT NULL,
      status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
      reviewed_by INT DEFAULT NULL,
      review_notes TEXT DEFAULT NULL,
      reviewed_at TIMESTAMP NULL,
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
      INDEX idx_employee (employee_id),
      INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
} catch (Exception $e) {}

$success_msg = $error_msg = '';

// Handle correction request submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_correction'])) {
  try {
    $transaction_id = isset($_POST['transaction_id']) && $_POST['transaction_id'] !== '' ? intval($_POST['transaction_id']) : null;
    $correction_type = $_POST['correction_type'];
    $original_value = trim($_POST['original_value']);
    $corrected_value = trim($_POST['corrected_value']);
    $reason = trim($_POST['reason']);
    
    if (empty($reason)) {
      throw new Exception("Please provide a reason for the correction.");
    }
    
    $stmt = $conn->prepare("
      INSERT INTO transaction_corrections 
      (employee_id, transaction_id, correction_type, original_value, corrected_value, reason)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$employee_id, $transaction_id, $correction_type, $original_value, $corrected_value, $reason]);
    
    $success_msg = "Correction request submitted successfully! An administrator will review it shortly.";
  } catch (Exception $e) {
    $error_msg = "Error: " . $e->getMessage();
  }
}

// Fetch correction requests
$corrections = [];
try {
  $stmt = $conn->prepare("
    SELECT * FROM transaction_corrections 
    WHERE employee_id = ?
    ORDER BY created_at DESC
  ");
  $stmt->execute([$employee_id]);
  $corrections = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Calculate statistics
$total_requests = count($corrections);
$pending = count(array_filter($corrections, fn($c) => $c['status'] === 'pending'));
$approved = count(array_filter($corrections, fn($c) => $c['status'] === 'approved'));
$rejected = count(array_filter($corrections, fn($c) => $c['status'] === 'rejected'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Transaction Corrections - Employee Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 1200px; margin: 0 auto; }
    .header { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
    .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
    .header-title { font-size: 1.8rem; font-weight: 800; color: #1a202c; }
    .btn { padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .btn-primary { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,0.3); }
    .btn-secondary { background: rgba(0,0,0,0.05); color: #4a5568; }
    .alert { padding: 16px 20px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
    .alert-success { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
    .alert-error { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 24px; }
    .stat-card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 12px; padding: 20px; text-align: center; box-shadow: 0 4px 16px rgba(0,0,0,0.1); }
    .stat-icon { font-size: 2rem; margin-bottom: 12px; }
    .stat-value { font-size: 2rem; font-weight: 700; color: #1a202c; margin-bottom: 8px; }
    .stat-label { font-size: 0.85rem; color: #718096; text-transform: uppercase; }
    .card { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 16px; padding: 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); margin-bottom: 24px; }
    .card h3 { font-size: 1.3rem; margin-bottom: 20px; color: #1a202c; padding-bottom: 12px; border-bottom: 2px solid #e2e8f0; }
    .form-group { margin-bottom: 20px; }
    .form-group label { display: block; margin-bottom: 8px; font-weight: 600; color: #1a202c; }
    .form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 1rem; }
    .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
    th { padding: 16px; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; }
    td { padding: 16px; border-bottom: 1px solid #e2e8f0; color: #1a202c; }
    tbody tr:hover { background: rgba(239,68,68,0.05); }
    .badge { padding: 6px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; }
    .badge-pending { background: rgba(251,191,36,0.1); color: #f59e0b; border: 1px solid rgba(251,191,36,0.3); }
    .badge-approved { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
    .badge-rejected { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
    @media (max-width: 768px) { .form-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-content">
        <div>
          <h1 class="header-title">🔧 Transaction Corrections</h1>
          <p style="color:#718096;margin-top:4px">Request corrections for transaction mistakes</p>
        </div>
        <a href="/MY CASH/employee/dashboard.php" class="btn btn-secondary">← Back to Dashboard</a>
      </div>
    </div>

    <?php if ($success_msg): ?>
    <div class="alert alert-success">✅ <?= htmlspecialchars($success_msg) ?></div>
    <?php endif; ?>

    <?php if ($error_msg): ?>
    <div class="alert alert-error">❌ <?= htmlspecialchars($error_msg) ?></div>
    <?php endif; ?>

    <!-- Statistics -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon">📝</div>
        <div class="stat-value"><?= $total_requests ?></div>
        <div class="stat-label">Total Requests</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">⏳</div>
        <div class="stat-value"><?= $pending ?></div>
        <div class="stat-label">Pending Review</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= $approved ?></div>
        <div class="stat-label">Approved</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">❌</div>
        <div class="stat-value"><?= $rejected ?></div>
        <div class="stat-label">Rejected</div>
      </div>
    </div>

    <!-- Submit New Correction -->
    <div class="card">
      <h3>➕ Submit New Correction Request</h3>
      <form method="POST">
        <div class="form-grid">
          <div class="form-group">
            <label>Transaction ID (Optional)</label>
            <input type="number" name="transaction_id" placeholder="Leave blank if not specific">
          </div>
          <div class="form-group">
            <label>Correction Type *</label>
            <select name="correction_type" required>
              <option value="">Select type...</option>
              <option value="amount">Incorrect Amount</option>
              <option value="payment_method">Wrong Payment Method</option>
              <option value="customer">Wrong Customer</option>
              <option value="item">Wrong Item/Product</option>
              <option value="delete">Delete Transaction</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>
        
        <div class="form-grid">
          <div class="form-group">
            <label>Original Value *</label>
            <input type="text" name="original_value" placeholder="What is currently recorded" required>
          </div>
          <div class="form-group">
            <label>Corrected Value *</label>
            <input type="text" name="corrected_value" placeholder="What it should be" required>
          </div>
        </div>
        
        <div class="form-group">
          <label>Reason for Correction *</label>
          <textarea name="reason" rows="4" placeholder="Explain in detail why this correction is needed..." required></textarea>
        </div>
        
        <button type="submit" name="submit_correction" class="btn btn-primary">🔧 Submit Correction Request</button>
      </form>
    </div>

    <!-- Correction History -->
    <div class="card">
      <h3>📊 My Correction Requests</h3>
      
      <?php if (empty($corrections)): ?>
      <div style="text-align:center;padding:60px 20px;color:#a0aec0">
        <div style="font-size:3rem">📋</div>
        <h3>No Correction Requests</h3>
        <p>You haven't submitted any correction requests yet</p>
      </div>
      <?php else: ?>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Type</th>
              <th>Original → Corrected</th>
              <th>Status</th>
              <th>Review Notes</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($corrections as $correction): ?>
            <tr>
              <td style="font-weight:600"><?= date('M d, Y g:i A', strtotime($correction['created_at'])) ?></td>
              <td>
                <span style="font-weight:600;color:#ef4444"><?= strtoupper(str_replace('_', ' ', $correction['correction_type'])) ?></span>
                <?php if ($correction['transaction_id']): ?>
                  <div style="font-size:0.85rem;color:#718096">Transaction #<?= $correction['transaction_id'] ?></div>
                <?php endif; ?>
              </td>
              <td>
                <div style="font-size:0.9rem">
                  <div style="color:#718096;text-decoration:line-through"><?= htmlspecialchars($correction['original_value']) ?></div>
                  <div style="color:#10b981;font-weight:600">→ <?= htmlspecialchars($correction['corrected_value']) ?></div>
                </div>
                <div style="font-size:0.85rem;color:#718096;margin-top:4px"><?= htmlspecialchars($correction['reason']) ?></div>
              </td>
              <td>
                <span class="badge badge-<?= $correction['status'] ?>">
                  <?= strtoupper($correction['status']) ?>
                </span>
                <?php if ($correction['reviewed_at']): ?>
                  <div style="font-size:0.75rem;color:#718096;margin-top:4px">
                    <?= date('M d, Y', strtotime($correction['reviewed_at'])) ?>
                  </div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($correction['review_notes']): ?>
                  <div style="font-size:0.9rem;color:#1a202c"><?= htmlspecialchars($correction['review_notes']) ?></div>
                <?php else: ?>
                  <span style="color:#a0aec0">-</span>
                <?php endif; ?>
              </td>
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
