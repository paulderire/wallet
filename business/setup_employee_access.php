<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
  header("Location: /MY CASH/pages/login.php");
  exit;
}

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
$success = '';
$error = '';

// Handle password setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_password'])) {
  $employee_id = intval($_POST['employee_id']);
  $password = $_POST['password'] ?? '';
  $confirm_password = $_POST['confirm_password'] ?? '';
  
  if (empty($password) || empty($confirm_password)) {
    $error = 'Please enter and confirm the password';
  } elseif ($password !== $confirm_password) {
    $error = 'Passwords do not match';
  } elseif (strlen($password) < 6) {
    $error = 'Password must be at least 6 characters';
  } else {
    try {
      $password_hash = password_hash($password, PASSWORD_DEFAULT);
      $stmt = $conn->prepare("UPDATE employees SET password_hash = ?, is_active = 1 WHERE id = ? AND user_id = ?");
      $stmt->execute([$password_hash, $employee_id, $user_id]);
      
      $success = 'Password set successfully! Employee can now login at /MY CASH/employee_login.php';
    } catch (Exception $e) {
      $error = 'Failed to set password';
    }
  }
}

// Fetch all employees
$employees = [];
try {
  $stmt = $conn->prepare("SELECT id, first_name, last_name, CONCAT(first_name, ' ', last_name) as full_name, email, role, department, status, password_hash, is_active FROM employees WHERE user_id = ? ORDER BY first_name, last_name");
  $stmt->execute([$user_id]);
  $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  // Error fetching employees - possibly database issue
  $error = 'Error loading employees: ' . $e->getMessage();
}

include __DIR__ . '/../includes/header.php';
?>
<style>
  body {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: 100vh;
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
    margin-bottom: 8px;
  }
  
  .page-subtitle {
    color: #718096;
    font-size: 14px;
  }
  
  .employees-table-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 24px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    overflow-x: auto;
  }
  
  .employees-table {
    width: 100%;
    border-collapse: collapse;
  }
  
  .employees-table thead {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
  }
  
  .employees-table th {
    padding: 12px 16px;
    text-align: left;
    font-weight: 700;
    color: #2d3748;
    font-size: 14px;
    border-bottom: 2px solid #e2e8f0;
  }
  
  .employees-table td {
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
    color: #4a5568;
    font-size: 14px;
  }
  
  .employees-table tbody tr:hover {
    background: rgba(102, 126, 234, 0.05);
  }
  
  .badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
  }
  
  .badge-success {
    background: #d4f4dd;
    color: #22543d;
  }
  
  .badge-warning {
    background: #fef5e7;
    color: #744210;
  }
  
  .badge-inactive {
    background: #edf2f7;
    color: #4a5568;
  }
  
  .btn {
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    font-size: 13px;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
  }
  
  .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
  }
  
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(102, 126, 234, 0.3);
  }
  
  .modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 10000;
    align-items: center;
    justify-content: center;
    padding: 20px;
  }
  
  .modal.show {
    display: flex;
  }
  
  .modal-content {
    background: white;
    border-radius: 16px;
    padding: 32px;
    max-width: 500px;
    width: 100%;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  }
  
  .modal-header {
    margin-bottom: 24px;
  }
  
  .modal-title {
    font-size: 22px;
    font-weight: 700;
    color: #2d3748;
  }
  
  .form-group {
    margin-bottom: 20px;
  }
  
  .form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #2d3748;
    font-size: 14px;
  }
  
  .form-group input {
    width: 100%;
    padding: 12px 14px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
  }
  
  .form-group input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  
  .modal-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 24px;
  }
  
  .btn-secondary {
    background: #e2e8f0;
    color: #2d3748;
  }
  
  .alert {
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 14px;
  }
  
  .alert-error {
    background: #fed7d7;
    color: #742a2a;
    border: 1px solid #fc8181;
  }
  
  .alert-success {
    background: #d4f4dd;
    color: #22543d;
    border: 1px solid #48bb78;
  }
</style>

<div class="page-header">
  <h1 class="page-title">🔑 Employee Access Management</h1>
  <p class="page-subtitle">Set up login credentials for your employees</p>
</div>

<?php if ($error): ?>
  <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<?php if ($success): ?>
  <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
<?php endif; ?>

<div class="employees-table-card">
  <?php if (empty($employees)): ?>
    <div style="text-align: center; padding: 32px; color: #a0aec0;">
      <div style="font-size: 48px; margin-bottom: 16px;">👥</div>
      <p>No employees found. <a href="/MY CASH/business/employees.php" style="color: #667eea; font-weight: 600;">Add employees first</a></p>
    </div>
  <?php else: ?>
    <table class="employees-table">
      <thead>
        <tr>
          <th>Employee</th>
          <th>Email</th>
          <th>Role</th>
          <th>Department</th>
          <th>Login Status</th>
          <th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($employees as $emp): ?>
          <tr>
            <td style="font-weight: 600;"><?php echo htmlspecialchars($emp['full_name']); ?></td>
            <td><?php echo htmlspecialchars($emp['email'] ?? 'No email'); ?></td>
            <td><?php echo htmlspecialchars($emp['role'] ?? '-'); ?></td>
            <td><?php echo htmlspecialchars($emp['department'] ?? '-'); ?></td>
            <td>
              <?php if (!empty($emp['password_hash'])): ?>
                <span class="badge badge-success">✓ Active</span>
              <?php else: ?>
                <span class="badge badge-warning">⚠ No Password</span>
              <?php endif; ?>
            </td>
            <td>
              <button 
                class="btn btn-primary" 
                onclick="openPasswordModal(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars($emp['full_name'], ENT_QUOTES); ?>')"
              >
                <?php echo !empty($emp['password_hash']) ? 'Reset Password' : 'Set Password'; ?>
              </button>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Password Setup Modal -->
<div id="passwordModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h2 class="modal-title">Set Login Password</h2>
      <p style="color: #718096; font-size: 14px; margin-top: 4px;">Employee: <strong id="modalEmployeeName"></strong></p>
    </div>
    
    <form method="POST" action="">
      <input type="hidden" name="employee_id" id="modalEmployeeId">
      <input type="hidden" name="setup_password" value="1">
      
      <div class="form-group">
        <label for="password">New Password *</label>
        <input type="password" id="password" name="password" placeholder="Enter password (min 6 characters)" required minlength="6">
      </div>
      
      <div class="form-group">
        <label for="confirm_password">Confirm Password *</label>
        <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-enter password" required>
      </div>
      
      <div class="modal-actions">
        <button type="button" class="btn btn-secondary" onclick="closePasswordModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save Password</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openPasswordModal(employeeId, employeeName) {
    document.getElementById('modalEmployeeId').value = employeeId;
    document.getElementById('modalEmployeeName').textContent = employeeName;
    document.getElementById('passwordModal').classList.add('show');
    document.getElementById('password').value = '';
    document.getElementById('confirm_password').value = '';
  }
  
  function closePasswordModal() {
    document.getElementById('passwordModal').classList.remove('show');
  }
  
  // Close modal when clicking outside
  document.getElementById('passwordModal').addEventListener('click', function(e) {
    if (e.target === this) {
      closePasswordModal();
    }
  });
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
