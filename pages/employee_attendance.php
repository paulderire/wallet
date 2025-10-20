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

// Auto-create tables if they don't exist
try {
    $schema = file_get_contents(__DIR__ . '/../db/attendance_schema.sql');
    // Remove comments
    $lines = explode("\n", $schema);
    $cleaned_lines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (!empty($trimmed) && !preg_match('/^--/', $trimmed)) {
            $cleaned_lines[] = $line;
        }
    }
    $schema = implode("\n", $cleaned_lines);
    
    $statements = explode(';', $schema);
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $conn->exec($statement);
        }
    }
} catch (Exception $e) {}

$success_msg = $error_msg = '';

// Handle check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in'])) {
    try {
        $employee_id = intval($_POST['employee_id']);
        $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
        $check_in_time = $_POST['check_in_time'] ?? date('H:i:s');
        
        // Get work start time setting
        $settingsStmt = $conn->query("SELECT * FROM attendance_settings LIMIT 1");
        $settings = $settingsStmt->fetch(PDO::FETCH_ASSOC);
        $work_start = strtotime($settings['work_start_time']);
        $check_in = strtotime($check_in_time);
        $late_threshold = $settings['late_threshold_minutes'] * 60;
        
        // Determine status
        $status = 'present';
        if ($check_in > ($work_start + $late_threshold)) {
            $status = 'late';
        }
        
        // Insert or update attendance
        $stmt = $conn->prepare("
            INSERT INTO employee_attendance (employee_id, attendance_date, check_in_time, status, checked_in_by)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE check_in_time = ?, status = ?, checked_in_by = ?
        ");
        $stmt->execute([$employee_id, $attendance_date, $check_in_time, $status, $user_id, $check_in_time, $status, $user_id]);
        
        $success_msg = "Check-in recorded successfully!";
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Handle check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_out'])) {
    try {
        $employee_id = intval($_POST['employee_id']);
        $attendance_date = $_POST['attendance_date'] ?? date('Y-m-d');
        $check_out_time = $_POST['check_out_time'] ?? date('H:i:s');
        
        // Get existing check-in
        $checkStmt = $conn->prepare("SELECT check_in_time FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
        $checkStmt->execute([$employee_id, $attendance_date]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing) {
            $error_msg = "No check-in record found for this employee today!";
        } else {
            // Calculate work hours
            $check_in = strtotime($existing['check_in_time']);
            $check_out = strtotime($check_out_time);
            $work_hours = ($check_out - $check_in) / 3600;
            
            // Update attendance
            $stmt = $conn->prepare("
                UPDATE employee_attendance 
                SET check_out_time = ?, work_hours = ?, checked_out_by = ?
                WHERE employee_id = ? AND attendance_date = ?
            ");
            $stmt->execute([$check_out_time, $work_hours, $user_id, $employee_id, $attendance_date]);
            
            $success_msg = "Check-out recorded successfully! Work hours: " . number_format($work_hours, 2) . " hours";
        }
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Handle mark absent
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_absent'])) {
    try {
        $employee_id = intval($_POST['employee_id']);
        $attendance_date = $_POST['attendance_date'];
        $notes = trim($_POST['notes'] ?? '');
        
        $stmt = $conn->prepare("
            INSERT INTO employee_attendance (employee_id, attendance_date, status, notes, checked_in_by)
            VALUES (?, ?, 'absent', ?, ?)
            ON DUPLICATE KEY UPDATE status = 'absent', notes = ?, checked_in_by = ?
        ");
        $stmt->execute([$employee_id, $attendance_date, $notes, $user_id, $notes, $user_id]);
        
        $success_msg = "Marked as absent successfully!";
    } catch (Exception $e) {
        $error_msg = "Error: " . $e->getMessage();
    }
}

// Filters
$filter_date = $_GET['date'] ?? date('Y-m-d');
$filter_employee = $_GET['employee'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_month = $_GET['month'] ?? date('Y-m');

// Fetch employees
$employees = [];
try {
    $stmt = $conn->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM employees ORDER BY first_name");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Fetch attendance records
$where = ["1=1"];
$params = [];

if ($filter_date) {
    $where[] = "attendance_date = ?";
    $params[] = $filter_date;
} elseif ($filter_month) {
    $where[] = "DATE_FORMAT(attendance_date, '%Y-%m') = ?";
    $params[] = $filter_month;
}

if ($filter_employee) {
    $where[] = "employee_id = ?";
    $params[] = $filter_employee;
}

if ($filter_status) {
    $where[] = "status = ?";
    $params[] = $filter_status;
}

$where_clause = implode(' AND ', $where);

$attendance_records = [];
try {
    $stmt = $conn->prepare("
        SELECT a.*, CONCAT(e.first_name, ' ', e.last_name) as employee_name
        FROM employee_attendance a
        JOIN employees e ON a.employee_id = e.id
        WHERE $where_clause
        ORDER BY a.attendance_date DESC, a.check_in_time ASC
    ");
    $stmt->execute($params);
    $attendance_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $attendance_records = [];
}

// Calculate statistics
$total_present = 0;
$total_absent = 0;
$total_late = 0;
$total_hours = 0;

foreach ($attendance_records as $record) {
    if ($record['status'] === 'present' || $record['status'] === 'late') {
        $total_present++;
    }
    if ($record['status'] === 'absent') {
        $total_absent++;
    }
    if ($record['status'] === 'late') {
        $total_late++;
    }
    if ($record['work_hours']) {
        $total_hours += $record['work_hours'];
    }
}

include __DIR__ . '/../includes/header.php';
?>

<style>
.page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:32px;flex-wrap:wrap;gap:16px}
.page-title{font-size:2rem;font-weight:800;background:linear-gradient(135deg,#764ba2,#667eea);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent;margin:0}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:20px;margin-bottom:32px}
.stat-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:20px;box-shadow:var(--overlay-shadow)}
.stat-icon{font-size:2rem;margin-bottom:8px}
.stat-value{font-size:2rem;font-weight:700;color:var(--card-text);margin:8px 0}
.stat-label{font-size:0.9rem;color:var(--muted);text-transform:uppercase;letter-spacing:0.5px}
.filters-card{background:var(--card-bg);border:1px solid var(--border-weak);border-radius:16px;padding:24px;margin-bottom:24px}
.filters-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:16px}
.filter-group label{display:block;margin-bottom:6px;font-weight:600;color:var(--card-text);font-size:0.9rem}
.filter-group input,.filter-group select{width:100%;padding:10px;border:1px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text)}
.filter-actions{display:flex;gap:12px;justify-content:flex-end}
.attendance-table{width:100%;background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;overflow:hidden;box-shadow:var(--overlay-shadow)}
.attendance-table table{width:100%;border-collapse:collapse}
.attendance-table th{background:linear-gradient(135deg,#764ba2,#667eea);color:#fff;padding:16px;text-align:left;font-weight:600;font-size:0.9rem;text-transform:uppercase;letter-spacing:0.5px}
.attendance-table td{padding:16px;border-bottom:1px solid var(--border-weak);color:var(--card-text)}
.attendance-table tr:last-child td{border-bottom:none}
.attendance-table tr:hover{background:rgba(118,75,162,0.05)}
.status-badge{padding:6px 12px;border-radius:12px;font-size:0.85rem;font-weight:600;text-transform:uppercase;display:inline-block}
.status-badge.present{background:rgba(16,185,129,0.1);color:#10b981;border:1px solid rgba(16,185,129,0.3)}
.status-badge.absent{background:rgba(239,68,68,0.1);color:#ef4444;border:1px solid rgba(239,68,68,0.3)}
.status-badge.late{background:rgba(251,191,36,0.1);color:#f59e0b;border:1px solid rgba(251,191,36,0.3)}
.status-badge.half_day{background:rgba(59,130,246,0.1);color:#3b82f6;border:1px solid rgba(59,130,246,0.3)}
.quick-actions{display:flex;gap:12px;margin-bottom:24px}
.quick-action-card{flex:1;background:var(--card-bg);border:1px solid var(--border-weak);border-radius:12px;padding:20px;cursor:pointer;transition:all 0.3s}
.quick-action-card:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(118,75,162,0.2)}
.quick-action-card h3{margin:0 0 8px 0;font-size:1.1rem;color:var(--card-text)}
.quick-action-card p{margin:0;font-size:0.9rem;color:var(--muted)}
.modal{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:9999;align-items:center;justify-content:center}
.modal.active{display:flex}
.modal-content{background:var(--card-bg);border-radius:16px;padding:32px;max-width:500px;width:90%;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
.modal-header{font-size:1.5rem;font-weight:700;margin-bottom:24px;color:var(--card-text)}
.form-group{margin-bottom:20px}
.form-group label{display:block;margin-bottom:8px;font-weight:600;color:var(--card-text)}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:12px;border:1px solid var(--border-weak);border-radius:8px;background:var(--card-bg);color:var(--card-text)}
</style>

<div class="page-header">
    <h1 class="page-title">🕐 Attendance Tracking</h1>
    <button class="button primary" onclick="location.reload()">🔄 Refresh</button>
</div>

<?php if ($success_msg): ?>
<div class="alert success"><?= htmlspecialchars($success_msg) ?></div>
<?php endif; ?>

<?php if ($error_msg): ?>
<div class="alert error"><?= htmlspecialchars($error_msg) ?></div>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= $total_present ?></div>
        <div class="stat-label">Present</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">❌</div>
        <div class="stat-value"><?= $total_absent ?></div>
        <div class="stat-label">Absent</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">⏰</div>
        <div class="stat-value"><?= $total_late ?></div>
        <div class="stat-label">Late</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon">🕒</div>
        <div class="stat-value"><?= number_format($total_hours, 1) ?></div>
        <div class="stat-label">Total Hours</div>
    </div>
</div>

<div class="quick-actions">
    <div class="quick-action-card" onclick="openModal('checkInModal')">
        <h3>📥 Check In</h3>
        <p>Record employee check-in time</p>
    </div>
    <div class="quick-action-card" onclick="openModal('checkOutModal')">
        <h3>📤 Check Out</h3>
        <p>Record employee check-out time</p>
    </div>
    <div class="quick-action-card" onclick="openModal('absentModal')">
        <h3>🚫 Mark Absent</h3>
        <p>Mark employee as absent</p>
    </div>
</div>

<div class="filters-card">
    <form method="GET">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Date</label>
                <input type="date" name="date" value="<?= htmlspecialchars($filter_date) ?>">
            </div>
            <div class="filter-group">
                <label>Month</label>
                <input type="month" name="month" value="<?= htmlspecialchars($filter_month) ?>">
            </div>
            <div class="filter-group">
                <label>Employee</label>
                <select name="employee">
                    <option value="">All Employees</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>" <?= $filter_employee == $emp['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($emp['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Status</label>
                <select name="status">
                    <option value="">All Status</option>
                    <option value="present" <?= $filter_status === 'present' ? 'selected' : '' ?>>Present</option>
                    <option value="absent" <?= $filter_status === 'absent' ? 'selected' : '' ?>>Absent</option>
                    <option value="late" <?= $filter_status === 'late' ? 'selected' : '' ?>>Late</option>
                    <option value="half_day" <?= $filter_status === 'half_day' ? 'selected' : '' ?>>Half Day</option>
                </select>
            </div>
        </div>
        <div class="filter-actions">
            <a href="/MY CASH/pages/employee_attendance.php" class="button ghost">Clear</a>
            <button type="submit" class="button primary">Apply Filters</button>
        </div>
    </form>
</div>

<div class="attendance-table">
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Employee</th>
                <th>Check In</th>
                <th>Check Out</th>
                <th>Hours</th>
                <th>Status</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($attendance_records)): ?>
            <tr>
                <td colspan="7" style="text-align:center;padding:40px;color:var(--muted)">
                    No attendance records found
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($attendance_records as $record): ?>
            <tr>
                <td><?= date('M d, Y', strtotime($record['attendance_date'])) ?></td>
                <td><strong><?= htmlspecialchars($record['employee_name']) ?></strong></td>
                <td><?= $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '—' ?></td>
                <td><?= $record['check_out_time'] ? date('h:i A', strtotime($record['check_out_time'])) : '—' ?></td>
                <td><?= $record['work_hours'] ? number_format($record['work_hours'], 2) . 'h' : '—' ?></td>
                <td>
                    <span class="status-badge <?= $record['status'] ?>">
                        <?= strtoupper(str_replace('_', ' ', $record['status'])) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($record['notes'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Check In Modal -->
<div id="checkInModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">📥 Check In Employee</div>
        <form method="POST">
            <div class="form-group">
                <label>Employee *</label>
                <select name="employee_id" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Check In Time *</label>
                <input type="time" name="check_in_time" value="<?= date('H:i') ?>" required>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" name="check_in" class="button primary">Check In</button>
                <button type="button" class="button secondary" onclick="closeModal('checkInModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Check Out Modal -->
<div id="checkOutModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">📤 Check Out Employee</div>
        <form method="POST">
            <div class="form-group">
                <label>Employee *</label>
                <select name="employee_id" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Check Out Time *</label>
                <input type="time" name="check_out_time" value="<?= date('H:i') ?>" required>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" name="check_out" class="button primary">Check Out</button>
                <button type="button" class="button secondary" onclick="closeModal('checkOutModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Mark Absent Modal -->
<div id="absentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">🚫 Mark Absent</div>
        <form method="POST">
            <div class="form-group">
                <label>Employee *</label>
                <select name="employee_id" required>
                    <option value="">Select Employee</option>
                    <?php foreach ($employees as $emp): ?>
                        <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Date *</label>
                <input type="date" name="attendance_date" value="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="form-group">
                <label>Reason/Notes</label>
                <textarea name="notes" rows="3" placeholder="Optional reason for absence"></textarea>
            </div>
            <div style="display:flex;gap:12px">
                <button type="submit" name="mark_absent" class="button danger">Mark Absent</button>
                <button type="button" class="button secondary" onclick="closeModal('absentModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

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
