<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}

include __DIR__ . '/../includes/db.php';

$employee_id = $_SESSION['employee_id'];
$employee_name = $_SESSION['employee_name'] ?? 'Employee';

$success_msg = $error_msg = '';
$today = date('Y-m-d');

// Handle check-in
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_in'])) {
  try {
    // Check if already checked in today
    $stmt = $conn->prepare("SELECT * FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
    $stmt->execute([$employee_id, $today]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing && $existing['check_in_time']) {
      $error_msg = "You already checked in today at " . date('g:i A', strtotime($existing['check_in_time']));
    } else {
      // Get work settings
      $settings_stmt = $conn->query("SELECT * FROM attendance_settings LIMIT 1");
      $settings = $settings_stmt->fetch(PDO::FETCH_ASSOC);
      
      $check_in_time = date('H:i:s');
      $work_start = $settings['work_start_time'] ?? '08:00:00';
      $late_threshold = $settings['late_threshold_minutes'] ?? 15;
      
      // Calculate if late
      $start_time = strtotime($work_start);
      $actual_time = strtotime($check_in_time);
      $diff_minutes = ($actual_time - $start_time) / 60;
      $status = $diff_minutes > $late_threshold ? 'late' : 'present';
      
      // Insert or update
      if ($existing) {
        $stmt = $conn->prepare("UPDATE employee_attendance SET check_in_time = ?, status = ?, checked_in_by = ? WHERE id = ?");
        $stmt->execute([$check_in_time, $status, $employee_id, $existing['id']]);
      } else {
        $stmt = $conn->prepare("
          INSERT INTO employee_attendance (employee_id, attendance_date, check_in_time, status, checked_in_by)
          VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$employee_id, $today, $check_in_time, $status, $employee_id]);
      }
      
      $success_msg = "Checked in successfully at " . date('g:i A', strtotime($check_in_time)) . ($status === 'late' ? ' (Late)' : '');
    }
  } catch (Exception $e) {
    $error_msg = "Error: " . $e->getMessage();
  }
}

// Handle check-out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_out'])) {
  try {
    $stmt = $conn->prepare("SELECT * FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
    $stmt->execute([$employee_id, $today]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$attendance || !$attendance['check_in_time']) {
      $error_msg = "You need to check in first!";
    } elseif ($attendance['check_out_time']) {
      $error_msg = "You already checked out today at " . date('g:i A', strtotime($attendance['check_out_time']));
    } else {
      $check_out_time = date('H:i:s');
      
      // Calculate work hours
      $check_in = strtotime($attendance['check_in_time']);
      $check_out = strtotime($check_out_time);
      $work_hours = ($check_out - $check_in) / 3600;
      
      $stmt = $conn->prepare("
        UPDATE employee_attendance 
        SET check_out_time = ?, work_hours = ?, checked_out_by = ?
        WHERE id = ?
      ");
      $stmt->execute([$check_out_time, $work_hours, $employee_id, $attendance['id']]);
      
      $success_msg = "Checked out successfully at " . date('g:i A', strtotime($check_out_time)) . " • Work hours: " . number_format($work_hours, 2);
    }
  } catch (Exception $e) {
    $error_msg = "Error: " . $e->getMessage();
  }
}

// Get today's attendance
$today_attendance = null;
try {
  $stmt = $conn->prepare("SELECT * FROM employee_attendance WHERE employee_id = ? AND attendance_date = ?");
  $stmt->execute([$employee_id, $today]);
  $today_attendance = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Get attendance history (last 30 days)
$attendance_history = [];
try {
  $stmt = $conn->prepare("
    SELECT * FROM employee_attendance 
    WHERE employee_id = ? 
    AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ORDER BY attendance_date DESC
  ");
  $stmt->execute([$employee_id]);
  $attendance_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Calculate statistics
$total_present = 0;
$total_late = 0;
$total_absent = 0;
$total_hours = 0;

foreach ($attendance_history as $record) {
  if ($record['status'] === 'present') $total_present++;
  if ($record['status'] === 'late') $total_late++;
  if ($record['status'] === 'absent') $total_absent++;
  if ($record['work_hours']) $total_hours += $record['work_hours'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Attendance - Employee Portal</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #10b981 0%, #059669 100%); min-height: 100vh; padding: 20px; }
    .container { max-width: 1200px; margin: 0 auto; }
    .header { background: rgba(255,255,255,0.95); backdrop-filter: blur(10px); border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: 0 8px 32px rgba(0,0,0,0.1); }
    .header-content { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
    .header-title { font-size: 1.8rem; font-weight: 800; color: #1a202c; }
    .btn { padding: 12px 24px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: all 0.2s; }
    .btn-primary { background: linear-gradient(135deg, #10b981, #059669); color: white; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,0.3); }
    .btn-secondary { background: rgba(0,0,0,0.05); color: #4a5568; }
    .btn-danger { background: linear-gradient(135deg, #ef4444, #dc2626); color: white; }
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
    .today-status { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px; }
    .status-box { padding: 20px; border-radius: 12px; text-align: center; }
    .status-box h4 { margin-bottom: 8px; font-size: 0.9rem; color: #718096; text-transform: uppercase; }
    .status-box p { font-size: 1.5rem; font-weight: 700; }
    .status-present { background: rgba(16,185,129,0.1); border: 2px solid rgba(16,185,129,0.3); color: #10b981; }
    .status-late { background: rgba(251,191,36,0.1); border: 2px solid rgba(251,191,36,0.3); color: #f59e0b; }
    .status-pending { background: rgba(107,114,128,0.1); border: 2px solid rgba(107,114,128,0.3); color: #6b7280; }
    .table-container { overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    thead { background: linear-gradient(135deg, #10b981, #059669); color: white; }
    th { padding: 16px; text-align: left; font-weight: 600; font-size: 0.9rem; text-transform: uppercase; }
    td { padding: 16px; border-bottom: 1px solid #e2e8f0; color: #1a202c; }
    tbody tr:hover { background: rgba(16,185,129,0.05); }
    .badge { padding: 6px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; }
    .badge-present { background: rgba(16,185,129,0.1); color: #10b981; border: 1px solid rgba(16,185,129,0.3); }
    .badge-late { background: rgba(251,191,36,0.1); color: #f59e0b; border: 1px solid rgba(251,191,36,0.3); }
    .badge-absent { background: rgba(239,68,68,0.1); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); }
    @media (max-width: 768px) { .header-content { flex-direction: column; } .stats-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="header-content">
        <div>
          <h1 class="header-title">🕐 My Attendance</h1>
          <p style="color:#718096;margin-top:4px">Track your work hours and attendance history</p>
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
        <div class="stat-icon">✅</div>
        <div class="stat-value"><?= $total_present ?></div>
        <div class="stat-label">On Time (30 days)</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">⏰</div>
        <div class="stat-value"><?= $total_late ?></div>
        <div class="stat-label">Late Arrivals</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">❌</div>
        <div class="stat-value"><?= $total_absent ?></div>
        <div class="stat-label">Absences</div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">⏱️</div>
        <div class="stat-value"><?= number_format($total_hours, 1) ?></div>
        <div class="stat-label">Total Hours</div>
      </div>
    </div>

    <!-- Today's Attendance -->
    <div class="card">
      <h3>📅 Today's Attendance - <?= date('l, F j, Y') ?></h3>
      
      <div class="today-status">
        <div class="status-box <?= $today_attendance && $today_attendance['check_in_time'] ? ($today_attendance['status'] === 'late' ? 'status-late' : 'status-present') : 'status-pending' ?>">
          <h4>Check-In Time</h4>
          <p><?= $today_attendance && $today_attendance['check_in_time'] ? date('g:i A', strtotime($today_attendance['check_in_time'])) : 'Not checked in' ?></p>
        </div>
        
        <div class="status-box <?= $today_attendance && $today_attendance['check_out_time'] ? 'status-present' : 'status-pending' ?>">
          <h4>Check-Out Time</h4>
          <p><?= $today_attendance && $today_attendance['check_out_time'] ? date('g:i A', strtotime($today_attendance['check_out_time'])) : 'Not checked out' ?></p>
        </div>
        
        <div class="status-box status-present">
          <h4>Work Hours</h4>
          <p><?= $today_attendance && $today_attendance['work_hours'] ? number_format($today_attendance['work_hours'], 2) . ' hrs' : '-' ?></p>
        </div>
        
        <div class="status-box <?= $today_attendance && $today_attendance['status'] ? ($today_attendance['status'] === 'present' ? 'status-present' : ($today_attendance['status'] === 'late' ? 'status-late' : 'status-pending')) : 'status-pending' ?>">
          <h4>Status</h4>
          <p><?= $today_attendance && $today_attendance['status'] ? strtoupper($today_attendance['status']) : 'PENDING' ?></p>
        </div>
      </div>

      <div style="display:flex;gap:12px;justify-content:center">
        <form method="POST" style="display:inline">
          <button type="submit" name="check_in" class="btn btn-primary" 
                  <?= $today_attendance && $today_attendance['check_in_time'] ? 'disabled' : '' ?>>
            ✅ Check In
          </button>
        </form>
        <form method="POST" style="display:inline">
          <button type="submit" name="check_out" class="btn btn-danger"
                  <?= !$today_attendance || !$today_attendance['check_in_time'] || $today_attendance['check_out_time'] ? 'disabled' : '' ?>>
            🚪 Check Out
          </button>
        </form>
      </div>
    </div>

    <!-- Attendance History -->
    <div class="card">
      <h3>📊 Attendance History (Last 30 Days)</h3>
      
      <?php if (empty($attendance_history)): ?>
      <div style="text-align:center;padding:60px 20px;color:#a0aec0">
        <div style="font-size:3rem">📋</div>
        <h3>No Attendance Records</h3>
        <p>Your attendance history will appear here</p>
      </div>
      <?php else: ?>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Check-In</th>
              <th>Check-Out</th>
              <th>Work Hours</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($attendance_history as $record): ?>
            <tr>
              <td style="font-weight:600"><?= date('M d, Y (D)', strtotime($record['attendance_date'])) ?></td>
              <td><?= $record['check_in_time'] ? date('g:i A', strtotime($record['check_in_time'])) : '-' ?></td>
              <td><?= $record['check_out_time'] ? date('g:i A', strtotime($record['check_out_time'])) : '-' ?></td>
              <td style="font-weight:700"><?= $record['work_hours'] ? number_format($record['work_hours'], 2) . ' hrs' : '-' ?></td>
              <td>
                <span class="badge badge-<?= $record['status'] ?>">
                  <?= strtoupper($record['status']) ?>
                </span>
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
