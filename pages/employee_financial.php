<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: /MY CASH/pages/login.php'); exit; }
include __DIR__ . '/../includes/db.php';

$user_id = $_SESSION['user_id'];

// Check if user is admin
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$is_admin = !empty($user['is_admin']);

if (!$is_admin) {
    header('Location: /MY CASH/pages/dashboard.php');
    exit;
}

$success_msg = $error_msg = '';

// Handle salary update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_salary'])) {
    try {
        $employee_id = intval($_POST['employee_id']);
        $salary = floatval($_POST['salary']);
        $notes = trim($_POST['notes'] ?? '');
        
        // Update employee salary
        $stmt = $conn->prepare("UPDATE employees SET salary = ? WHERE id = ?");
        $stmt->execute([$salary, $employee_id]);
        
        // Log the change
        $logStmt = $conn->prepare("
            INSERT INTO employee_payments (employee_id, user_id, payment_type, amount, status, notes, created_by)
            VALUES (?, ?, 'salary', ?, 'paid', ?, ?)
        ");
        $logStmt->execute([$employee_id, $user_id, $salary, "Salary updated: $notes", $user_id]);
        
        $success_msg = "Salary updated successfully!";
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Handle bonus/deduction
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_adjustment'])) {
    try {
        $employee_id = intval($_POST['employee_id']);
        $adjustment_type = $_POST['adjustment_type'];
        $amount = floatval($_POST['amount']);
        $description = trim($_POST['description']);
        $payment_date = $_POST['payment_date'];
        
        // Insert adjustment
        $stmt = $conn->prepare("
            INSERT INTO employee_payments (employee_id, user_id, payment_type, amount, payment_date, status, notes, created_by)
            VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)
        ");
        $stmt->execute([$employee_id, $user_id, $adjustment_type, $amount, $payment_date, $description, $user_id]);
        
        // Create notification
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (user_id, title, message, type, related_url)
            VALUES (?, ?, ?, 'payment', '/MY CASH/pages/employee_payments.php')
        ");
        $empStmt = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM employees WHERE id = ?");
        $empStmt->execute([$employee_id]);
        $emp = $empStmt->fetch(PDO::FETCH_ASSOC);
        
        $title = ucfirst($adjustment_type) . " Added";
        $message = ucfirst($adjustment_type) . " of " . number_format($amount, 0) . " RWF added for " . $emp['name'];
        $notifStmt->execute([$user_id, $title, $message]);
        
        $success_msg = ucfirst($adjustment_type) . " added successfully!";
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Fetch employee ID from query parameter
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Fetch employee details
$employee = null;
if ($employee_id) {
    $stmt = $conn->prepare("
        SELECT e.*, CONCAT(e.first_name, ' ', e.last_name) as full_name
        FROM employees e
        WHERE e.id = ?
    ");
    $stmt->execute([$employee_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Fetch all employees for dropdown
$employees = [];
try {
    $stmt = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as name, salary FROM employees ORDER BY first_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch financial history
$financial_history = [];
if ($employee_id) {
    try {
        $stmt = $conn->prepare("
            SELECT * FROM employee_payments
            WHERE employee_id = ?
            ORDER BY payment_date DESC, created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$employee_id]);
        $financial_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Calculate totals
$total_bonuses = 0;
$total_deductions = 0;
$pending_payments = 0;

foreach ($financial_history as $record) {
    if ($record['payment_type'] === 'bonus' && $record['status'] === 'paid') {
        $total_bonuses += $record['amount'];
    }
    if ($record['payment_type'] === 'deduction' && $record['status'] === 'paid') {
        $total_deductions += $record['amount'];
    }
    if ($record['status'] === 'pending') {
        $pending_payments += $record['amount'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:16px}
.page-title{font-size:2rem;font-weight:800;background:linear-gradient(135deg,#10b981,#059669);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;margin:0}
.employee-selector{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:24px;margin-bottom:24px}
.employee-selector select{width:100%;padding:12px;border:1px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text);font-size:1rem}
.financial-grid{display:grid;grid-template-columns:1fr 2fr;gap:24px;margin-bottom:24px}
.info-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:24px;box-shadow:var(--overlay-shadow)}
.info-card h3{margin:0 0 20px 0;font-size:1.3rem;color:var(--card-text);display:flex;align-items:center;gap:8px;padding-bottom:12px;border-bottom:2px solid var(--border-weak)}
.info-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border-weak)}
.info-row:last-child{border-bottom:none}
.info-label{font-weight:600;color:var(--muted);font-size:0.9rem}
.info-value{font-weight:600;color:var(--card-text);font-size:1.1rem}
.info-value.salary{color:#10b981;font-size:1.5rem}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:24px}
.stat-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:16px}
.stat-icon{font-size:1.8rem;margin-bottom:8px}
.stat-value{font-size:1.8rem;font-weight:700;color:var(--card-text)}
.stat-label{font-size:0.85rem;color:var(--muted);text-transform:uppercase}
.action-buttons{display:flex;gap:12px;margin-top:20px}
.history-table{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;overflow:hidden}
.history-table table{width:100%;border-collapse:collapse}
.history-table th{background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:16px;text-align:left;font-weight:600;font-size:0.9rem;text-transform:uppercase}
.history-table td{padding:16px;border-bottom:1px solid var(--border-weak);color:var(--card-text)}
.history-table tr:hover{background:rgba(16,185,129,0.05)}
.type-badge{padding:6px 12px;border-radius:12px;font-size:0.85rem;font-weight:600;text-transform:uppercase}
.type-badge.salary{background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.3)}
.type-badge.bonus{background:rgba(59,130,246,0.1);color:#3b82f6;border:1px solid rgba(59,130,246,0.3)}
.type-badge.deduction{background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.3)}
.type-badge.advance{background:rgba(251,191,36,0.1);color:#f59e0b;border:1px solid rgba(251,191,36,0.3)}
.status-badge{padding:6px 12px;border-radius:12px;font-size:0.85rem;font-weight:600;text-transform:uppercase}
.status-badge.paid{background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.3)}
.status-badge.pending{background:rgba(251,191,36,0.1);color:#f59e0b;border:1px solid rgba(251,191,36,0.3)}
.status-badge.cancelled{background:rgba(107,114,128,0.1);color:#6b7280;border:1px solid rgba(107,114,128,0.3)}
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:var(--card-bg);border-radius:16px;padding:32px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
.modal-header{font-size:1.5rem;font-weight:700;margin-bottom:24px;color:var(--card-text)}
.form-group{margin-bottom:20px}
.form-group label{display:block;margin-bottom:8px;font-weight:600;color:var(--card-text)}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px;border:1px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text)}
.empty-state{text-align:center;padding:60px 20px;color:var(--muted)}
.empty-state h3{margin:20px 0 10px 0}
@media (max-width:768px){.financial-grid{grid-template-columns:1fr}}
</style>

<div class="page-header">
    <h1 class="page-title">💰 Employee Financial Management</h1>
    <button class="button primary" onclick="location.reload()">🔄 Refresh</button>
</div>

<?php if ($success_msg): ?>
<div class="alert success"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<div class="employee-selector">
    <label style="display:block;margin-bottom:12px;font-weight:600;color:var(--card-text)">Select Employee</label>
    <select onchange="window.location.href='/MY CASH/pages/employee_financial.php?id=' + this.value">
        <option value="">Choose an employee...</option>
        <?php foreach ($employees as $emp): ?>
            <option value="<?= $emp['id'] ?>" <?= $employee_id == $emp['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($emp['name']) ?> - <?= number_format($emp['salary'] ?? 0, 0) ?> RWF
            </option>
        <?php endforeach; ?>
    </select>
</div>

<?php if ($employee): ?>

<div class="financial-grid">
    <div class="info-card">
        <h3>👤 Employee Details</h3>
        <div class="info-row">
            <span class="info-label">Name</span>
            <span class="info-value"><?= htmlspecialchars($employee['full_name']) ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Position</span>
            <span class="info-value"><?= htmlspecialchars($employee['position'] ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Current Salary</span>
            <span class="info-value salary"><?= number_format($employee['salary'] ?? 0, 0) ?> RWF</span>
        </div>
        <div class="action-buttons">
            <button class="button primary" onclick="openModal('salaryModal')">✏️ Update Salary</button>
            <button class="button secondary" onclick="openModal('adjustmentModal')">➕ Add Adjustment</button>
        </div>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">💵</div>
            <div class="stat-value"><?= number_format($employee['salary'] ?? 0, 0) ?></div>
            <div class="stat-label">Monthly Salary</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🎁</div>
            <div class="stat-value"><?= number_format($total_bonuses, 0) ?></div>
            <div class="stat-label">Total Bonuses</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">➖</div>
            <div class="stat-value"><?= number_format($total_deductions, 0) ?></div>
            <div class="stat-label">Total Deductions</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">⏳</div>
            <div class="stat-value"><?= number_format($pending_payments, 0) ?></div>
            <div class="stat-label">Pending Payments</div>
        </div>
    </div>
</div>

<div class="info-card" style="margin-bottom:24px">
    <h3>📊 Financial History</h3>
    <?php if (empty($financial_history)): ?>
    <div class="empty-state">
        <div style="font-size:3rem">📭</div>
        <h3>No Financial Records</h3>
        <p>No payment history found for this employee</p>
    </div>
    <?php else: ?>
    <div class="history-table">
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
                <?php foreach ($financial_history as $record): ?>
                <tr>
                    <td><?= date('M d, Y', strtotime($record['payment_date'])) ?></td>
                    <td>
                        <span class="type-badge <?= $record['payment_type'] ?>">
                            <?= strtoupper($record['payment_type']) ?>
                        </span>
                    </td>
                    <td style="font-weight:700;<?= $record['payment_type'] === 'deduction' ? 'color:#ef4444' : 'color:#10b981' ?>">
                        <?= $record['payment_type'] === 'deduction' ? '-' : '+' ?><?= number_format($record['amount'], 0) ?> RWF
                    </td>
                    <td>
                        <span class="status-badge <?= $record['status'] ?>">
                            <?= strtoupper($record['status']) ?>
                        </span>
                    </td>
                    <td><?= htmlspecialchars($record['notes'] ?? '') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Update Salary Modal -->
<div id="salaryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">✏️ Update Salary</div>
        <form method="POST">
            <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
            <div class="form-group">
                <label>Employee</label>
                <input type="text" value="<?= htmlspecialchars($employee['full_name']) ?>" readonly>
            </div>
            <div class="form-group">
                <label>Current Salary</label>
                <input type="text" value="<?= number_format($employee['salary'] ?? 0, 0) ?> RWF" readonly>
            </div>
            <div class="form-group">
                <label>New Salary (RWF) *</label>
                <input type="number" name="salary" value="<?= $employee['salary'] ?? 0 ?>" step="1000" required>
            </div>
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" rows="3" placeholder="Reason for salary change..."></textarea>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" name="update_salary" class="button primary">💾 Update Salary</button>
                <button type="button" class="button secondary" onclick="closeModal('salaryModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Adjustment Modal -->
<div id="adjustmentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">➕ Add Financial Adjustment</div>
        <form method="POST">
            <input type="hidden" name="employee_id" value="<?= $employee['id'] ?>">
            <div class="form-group">
                <label>Employee</label>
                <input type="text" value="<?= htmlspecialchars($employee['full_name']) ?>" readonly>
            </div>
            <div class="form-group">
                <label>Adjustment Type *</label>
                <select name="adjustment_type" required>
                    <option value="bonus">Bonus (Addition)</option>
                    <option value="deduction">Deduction (Subtraction)</option>
                    <option value="advance">Advance Payment</option>
                </select>
            </div>
            <div class="form-group">
                <label>Amount (RWF) *</label>
                <input type="number" name="amount" step="1000" required>
            </div>
            <div class="form-group">
                <label>Payment Date *</label>
                <input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Description *</label>
                <textarea name="description" rows="3" placeholder="Reason for this adjustment..." required></textarea>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" name="add_adjustment" class="button primary">➕ Add Adjustment</button>
                <button type="button" class="button secondary" onclick="closeModal('adjustmentModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<?php else: ?>
<div class="empty-state" style="background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:60px 20px">
    <div style="font-size:4rem">💰</div>
    <h2>Select an Employee</h2>
    <p>Choose an employee from the dropdown above to manage their financial information</p>
</div>
<?php endif; ?>

<script>
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
