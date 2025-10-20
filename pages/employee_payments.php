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

// Create tables if they don't exist
try {
    $schema = file_get_contents(__DIR__ . '/../db/employee_payments_schema.sql');
    $statements = array_filter(array_map('trim', explode(';', $schema)));
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $conn->exec($statement);
        }
    }
} catch (Exception $e) {
    // Tables might already exist
}

$success_msg = $error_msg = '';

// Handle payment actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();
        
        if (isset($_POST['add_payment'])) {
            $employee_id = intval($_POST['employee_id']);
            $payment_type = $_POST['payment_type'];
            $amount = floatval($_POST['amount']);
            $payment_date = $_POST['payment_date'];
            $payment_month = date('Y-m', strtotime($payment_date));
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $reference_number = trim($_POST['reference_number'] ?? '');
            $notes = trim($_POST['notes'] ?? '');
            $status = $_POST['status'] ?? 'pending';
            
            // Insert payment record
            $stmt = $conn->prepare("
                INSERT INTO employee_payments (
                    employee_id, user_id, payment_type, amount, payment_date, 
                    payment_month, status, payment_method, reference_number, notes, 
                    created_by, paid_at
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $paid_at = ($status === 'paid') ? date('Y-m-d H:i:s') : null;
            
            $stmt->execute([
                $employee_id, $user_id, $payment_type, $amount, $payment_date,
                $payment_month, $status, $payment_method, $reference_number, $notes,
                $user_id, $paid_at
            ]);
            
            // Create notification for employee
            $payment_id = $conn->lastInsertId();
            $employee = $conn->query("SELECT CONCAT(first_name, ' ', last_name) as name FROM employees WHERE id = $employee_id")->fetch();
            $employee_name = $employee ? $employee['name'] : 'Employee';
            $notif_msg = "Payment of " . number_format($amount, 0) . " RWF ($payment_type) has been " . 
                         ($status === 'paid' ? 'processed' : 'scheduled') . " for $payment_date to $employee_name";
            
            $conn->exec("INSERT INTO notifications (user_id, type, message) VALUES ($user_id, 'payment', '$notif_msg')");
            
            $conn->commit();
            $success_msg = "Payment recorded successfully!";
            
        } elseif (isset($_POST['mark_paid'])) {
            $payment_id = intval($_POST['payment_id']);
            
            $stmt = $conn->prepare("
                UPDATE employee_payments 
                SET status = 'paid', paid_at = NOW() 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$payment_id, $user_id]);
            
            $conn->commit();
            $success_msg = "Payment marked as paid!";
            
        } elseif (isset($_POST['cancel_payment'])) {
            $payment_id = intval($_POST['payment_id']);
            
            $stmt = $conn->prepare("
                UPDATE employee_payments 
                SET status = 'cancelled' 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$payment_id, $user_id]);
            
            $conn->commit();
            $success_msg = "Payment cancelled!";
        }
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$employee_filter = $_GET['employee'] ?? '';
$status_filter = $_GET['status'] ?? '';
$month_filter = $_GET['month'] ?? date('Y-m');
$type_filter = $_GET['type'] ?? '';

// Fetch employees for dropdown
$employees_list = $conn->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name, salary FROM employees WHERE user_id = ? AND status = 'active' ORDER BY first_name, last_name");
$employees_list->execute([$user_id]);
$employees = $employees_list->fetchAll(PDO::FETCH_ASSOC);

// Build query for payments
$query = "
    SELECT ep.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name, e.salary as base_salary
    FROM employee_payments ep
    JOIN employees e ON ep.employee_id = e.id
    WHERE ep.user_id = ?
";
$params = [$user_id];

if ($employee_filter) {
    $query .= " AND ep.employee_id = ?";
    $params[] = $employee_filter;
}

if ($status_filter) {
    $query .= " AND ep.status = ?";
    $params[] = $status_filter;
}

if ($month_filter) {
    $query .= " AND ep.payment_month = ?";
    $params[] = $month_filter;
}

if ($type_filter) {
    $query .= " AND ep.payment_type = ?";
    $params[] = $type_filter;
}

$query .= " ORDER BY ep.payment_date DESC, ep.created_at DESC";

$stmt = $conn->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$stats_query = "
    SELECT 
        COUNT(*) as total_payments,
        SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END) as total_paid,
        SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END) as total_pending,
        SUM(CASE WHEN payment_type = 'salary' THEN amount ELSE 0 END) as total_salary,
        SUM(CASE WHEN payment_type = 'bonus' THEN amount ELSE 0 END) as total_bonus
    FROM employee_payments
    WHERE user_id = ? AND payment_month = ?
";
$stats_stmt = $conn->prepare($stats_query);
$stats_stmt->execute([$user_id, $month_filter]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

include __DIR__ . '/../includes/header.php';
?>

<style>
.payment-stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px;margin-bottom:24px}
.stat-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:20px;box-shadow:var(--overlay-shadow)}
.stat-card.paid{border-top:4px solid #10b981}
.stat-card.pending{border-top:4px solid #f59e0b}
.stat-card.salary{border-top:4px solid #8b5cf6}
.stat-card.bonus{border-top:4px solid #3b82f6}
.stat-card.total{border-top:4px solid #06b6d4}
.stat-label{font-size:.85rem;color:var(--muted);margin-bottom:8px}
.stat-value{font-size:1.8rem;font-weight:700;color:var(--card-text)}
.payments-table{width:100%;background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;overflow:hidden}
.payments-table th{background:rgba(139,92,246,.1);padding:16px;text-align:left;font-weight:600;color:var(--card-text);border-bottom:1px solid var(--border-weak)}
.payments-table td{padding:16px;border-bottom:1px solid var(--border-weak)}
.payments-table tr:last-child td{border-bottom:none}
.payments-table tr:hover{background:rgba(139,92,246,.05)}
.status-badge{padding:6px 14px;border-radius:20px;font-size:.75rem;font-weight:700;text-transform:uppercase;letter-spacing:.5px}
.status-badge.paid{background:rgba(16,185,129,.15);color:#10b981;border:1px solid rgba(16,185,129,.3)}
.status-badge.pending{background:rgba(251,191,36,.15);color:#f59e0b;border:1px solid rgba(251,191,36,.3)}
.status-badge.cancelled{background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3)}
.type-badge{padding:4px 12px;border-radius:16px;font-size:.75rem;font-weight:600}
.type-badge.salary{background:rgba(139,92,246,.15);color:#8b5cf6;border:1px solid rgba(139,92,246,.3)}
.type-badge.bonus{background:rgba(59,130,246,.15);color:#3b82f6;border:1px solid rgba(59,130,246,.3)}
.type-badge.advance{background:rgba(6,182,212,.15);color:#06b6d4;border:1px solid rgba(6,182,212,.3)}
.type-badge.deduction{background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3)}
.filters{display:flex;gap:12px;flex-wrap:wrap;margin-bottom:24px;align-items:flex-end}
.filter-group{flex:1;min-width:180px}
.filter-group label{display:block;margin-bottom:6px;font-size:.9rem;font-weight:500;color:var(--card-text)}
.action-buttons{display:flex;gap:8px}
.button.small{padding:6px 12px;font-size:.85rem}
</style>

<div class="page-header">
    <h1>💰 Employee Payments</h1>
    <button class="button primary" id="show-add-payment">+ Add Payment</button>
</div>

<?php if ($success_msg): ?>
<div class="alert success"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<!-- Statistics -->
<div class="payment-stats">
    <div class="stat-card paid">
        <div class="stat-label">💳 Total Paid</div>
        <div class="stat-value"><?= number_format($stats['total_paid'], 0) ?> FRW</div>
        <div class="stat-label">This month</div>
    </div>
    <div class="stat-card pending">
        <div class="stat-label">⏳ Pending</div>
        <div class="stat-value"><?= number_format($stats['total_pending'], 0) ?> FRW</div>
        <div class="stat-label">Awaiting payment</div>
    </div>
    <div class="stat-card salary">
        <div class="stat-label">💼 Salaries</div>
        <div class="stat-value"><?= number_format($stats['total_salary'], 0) ?> FRW</div>
        <div class="stat-label">This month</div>
    </div>
    <div class="stat-card bonus">
        <div class="stat-label">🎁 Bonuses</div>
        <div class="stat-value"><?= number_format($stats['total_bonus'], 0) ?> FRW</div>
        <div class="stat-label">This month</div>
    </div>
    <div class="stat-card total">
        <div class="stat-label">📊 Total Payments</div>
        <div class="stat-value"><?= $stats['total_payments'] ?></div>
        <div class="stat-label">This month</div>
    </div>
</div>

<!-- Filters -->
<div class="card">
    <form method="GET" class="filters">
        <div class="filter-group">
            <label>Month</label>
            <input type="month" name="month" value="<?= htmlspecialchars($month_filter) ?>" style="width:100%">
        </div>
        <div class="filter-group">
            <label>Employee</label>
            <select name="employee" style="width:100%">
                <option value="">All Employees</option>
                <?php foreach ($employees as $emp): ?>
                    <option value="<?= $emp['id'] ?>" <?= $employee_filter == $emp['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($emp['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select name="status" style="width:100%">
                <option value="">All Status</option>
                <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="paid" <?= $status_filter === 'paid' ? 'selected' : '' ?>>Paid</option>
                <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>
        <div class="filter-group">
            <label>Type</label>
            <select name="type" style="width:100%">
                <option value="">All Types</option>
                <option value="salary" <?= $type_filter === 'salary' ? 'selected' : '' ?>>Salary</option>
                <option value="bonus" <?= $type_filter === 'bonus' ? 'selected' : '' ?>>Bonus</option>
                <option value="advance" <?= $type_filter === 'advance' ? 'selected' : '' ?>>Advance</option>
                <option value="deduction" <?= $type_filter === 'deduction' ? 'selected' : '' ?>>Deduction</option>
            </select>
        </div>
        <button type="submit" class="button secondary">Filter</button>
        <a href="?" class="button">Clear</a>
    </form>
</div>

<!-- Payments Table -->
<?php if (empty($payments)): ?>
<div class="card" style="text-align:center;padding:48px 24px">
    <div style="font-size:3rem;margin-bottom:16px;opacity:.3">💸</div>
    <h3>No payments found</h3>
    <p class="muted">Add a payment to get started</p>
</div>
<?php else: ?>
<table class="payments-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Employee</th>
            <th>Type</th>
            <th>Amount</th>
            <th>Method</th>
            <th>Status</th>
            <th>Ref #</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($payments as $payment): ?>
        <tr>
            <td><?= date('M d, Y', strtotime($payment['payment_date'])) ?></td>
            <td>
                <strong><?= htmlspecialchars($payment['employee_name']) ?></strong>
                <?php if ($payment['base_salary']): ?>
                    <br><small style="color:var(--muted)">Base: <?= number_format($payment['base_salary'], 0) ?> FRW</small>
                <?php endif; ?>
            </td>
            <td>
                <span class="type-badge <?= $payment['payment_type'] ?>">
                    <?= strtoupper($payment['payment_type']) ?>
                </span>
            </td>
            <td style="font-weight:700;font-size:1.1rem">
                <?= number_format($payment['amount'], 0) ?> FRW
            </td>
            <td><?= ucwords(str_replace('_', ' ', $payment['payment_method'])) ?></td>
            <td>
                <span class="status-badge <?= $payment['status'] ?>">
                    <?= strtoupper($payment['status']) ?>
                </span>
                <?php if ($payment['paid_at']): ?>
                    <br><small style="color:var(--muted)"><?= date('M d, h:i A', strtotime($payment['paid_at'])) ?></small>
                <?php endif; ?>
            </td>
            <td>
                <?php if ($payment['reference_number']): ?>
                    <code style="font-size:.85rem"><?= htmlspecialchars($payment['reference_number']) ?></code>
                <?php else: ?>
                    <span style="color:var(--muted);font-style:italic">—</span>
                <?php endif; ?>
            </td>
            <td>
                <div class="action-buttons">
                    <?php if ($payment['status'] === 'pending'): ?>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                            <button type="submit" name="mark_paid" class="button success small">Mark Paid</button>
                        </form>
                        <form method="POST" style="display:inline">
                            <input type="hidden" name="payment_id" value="<?= $payment['id'] ?>">
                            <button type="submit" name="cancel_payment" class="button danger small" 
                                    onclick="return confirm('Cancel this payment?')">Cancel</button>
                        </form>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <?php if (!empty($payment['notes'])): ?>
        <tr>
            <td colspan="8" style="background:rgba(139,92,246,.05);font-size:.85rem;color:var(--muted);font-style:italic">
                📝 <?= htmlspecialchars($payment['notes']) ?>
            </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<script>
document.getElementById('show-add-payment').addEventListener('click', function() {
    const today = new Date().toISOString().split('T')[0];
    const thisMonth = today.substring(0, 7);
    
    var html = '<div style="padding:24px"><h3 style="margin-bottom:24px">💰 Add Payment</h3>' +
        '<form method="POST" style="display:grid;grid-template-columns:1fr 1fr;gap:16px">' +
        '<div><label>Employee *</label><select name="employee_id" required style="width:100%">' +
        '<option value="">Select Employee</option>';
    
    <?php foreach ($employees as $emp): ?>
        html += '<option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?> <?= $emp['salary'] ? '(' . number_format($emp['salary'], 0) . ' FRW)' : '' ?></option>';
    <?php endforeach; ?>
    
    html += '</select></div>' +
        '<div><label>Payment Type *</label><select name="payment_type" required style="width:100%">' +
        '<option value="salary" selected>Salary</option>' +
        '<option value="bonus">Bonus</option>' +
        '<option value="advance">Advance</option>' +
        '<option value="deduction">Deduction</option>' +
        '</select></div>' +
        '<div><label>Amount (RWF) *</label><input type="number" step="0.01" name="amount" placeholder="50000" required style="width:100%"></div>' +
        '<div><label>Payment Date *</label><input type="date" name="payment_date" value="' + today + '" required style="width:100%"></div>' +
        '<div><label>Payment Method</label><select name="payment_method" style="width:100%">' +
        '<option value="cash" selected>Cash</option>' +
        '<option value="bank_transfer">Bank Transfer</option>' +
        '<option value="mobile_money">Mobile Money</option>' +
        '<option value="check">Check</option>' +
        '</select></div>' +
        '<div><label>Status</label><select name="status" style="width:100%">' +
        '<option value="pending" selected>Pending</option>' +
        '<option value="paid">Paid</option>' +
        '</select></div>' +
        '<div><label>Reference Number</label><input type="text" name="reference_number" placeholder="REF-001" style="width:100%"></div>' +
        '<div></div>' +
        '<div style="grid-column:1/-1"><label>Notes (Optional)</label><textarea name="notes" rows="3" placeholder="Additional payment details..." style="width:100%"></textarea></div>' +
        '<div style="grid-column:1/-1;display:flex;gap:8px;justify-content:flex-end">' +
        '<button type="button" onclick="window.WCModal.close()" class="button secondary">Cancel</button>' +
        '<button type="submit" name="add_payment" class="button primary">💾 Add Payment</button>' +
        '</div>' +
        '</form></div>';
    
    window.WCModal.open(html);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
