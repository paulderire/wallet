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
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name = trim($_POST['first_name'] ?? '');
  $last_name = trim($_POST['last_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $phone = trim($_POST['phone'] ?? '');
  $role = trim($_POST['role'] ?? '');
  $department = trim($_POST['department'] ?? '');
  $salary = trim($_POST['salary'] ?? '');
  $hire_date = trim($_POST['hire_date'] ?? '');
  $status = $_POST['status'] ?? 'active';
  $address = trim($_POST['address'] ?? '');
  $date_of_birth = trim($_POST['date_of_birth'] ?? '');
  
  // Validation
  if (empty($first_name) || empty($last_name) || empty($role)) {
    $error = 'First name, last name, and role are required fields';
  } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address';
  } else {
    try {
      // Check if email already exists (if provided)
      if (!empty($email)) {
        $stmt = $conn->prepare("SELECT id FROM employees WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
          $error = 'An employee with this email already exists';
        }
      }
      
      if (empty($error)) {
        // Generate unique employee_id
        $employee_id = 'EMP' . strtoupper(substr(uniqid(), -8));
        
        // Insert new employee
        $stmt = $conn->prepare("INSERT INTO employees (user_id, employee_id, first_name, last_name, email, phone, address, date_of_birth, department, role, hire_date, salary, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([
          $user_id,
          $employee_id,
          $first_name,
          $last_name,
          !empty($email) ? $email : null,
          !empty($phone) ? $phone : null,
          !empty($address) ? $address : null,
          !empty($date_of_birth) ? $date_of_birth : null,
          !empty($department) ? $department : null,
          $role,
          !empty($hire_date) ? $hire_date : date('Y-m-d'),
          $salary ? floatval($salary) : null,
          $status
        ]);
        
        $success = 'Employee added successfully! Employee ID: ' . $employee_id;
        
        // Clear form
        $first_name = $last_name = $email = $phone = $role = $department = $salary = $hire_date = $address = $date_of_birth = '';
      }
    } catch (Exception $e) {
      $error = 'Error adding employee: ' . $e->getMessage();
    }
  }
}

include __DIR__ . '/../includes/header.php';
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
    display: flex;
    justify-content: space-between;
    align-items: center;
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
  
  .back-btn {
    padding: 10px 20px;
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.3s ease;
    border: 2px solid rgba(102, 126, 234, 0.2);
  }
  
  .back-btn:hover {
    background: rgba(102, 126, 234, 0.2);
    transform: translateY(-2px);
  }
  
  .form-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    padding: 32px;
    border-radius: 16px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
    max-width: 800px;
    margin: 0 auto;
  }
  
  .alert {
    padding: 14px 20px;
    border-radius: 10px;
    margin-bottom: 24px;
    font-size: 14px;
    font-weight: 500;
  }
  
  .alert-success {
    background: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
  }
  
  .alert-error {
    background: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
  }
  
  .form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
    margin-bottom: 24px;
  }
  
  .form-group {
    display: flex;
    flex-direction: column;
  }
  
  .form-group.full-width {
    grid-column: 1 / -1;
  }
  
  .form-label {
    font-size: 14px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
  }
  
  .form-label .required {
    color: #e53e3e;
  }
  
  .form-input,
  .form-select,
  textarea.form-input {
    padding: 12px 16px;
    border: 2px solid #e2e8f0;
    border-radius: 10px;
    font-size: 14px;
    font-family: 'Inter', sans-serif;
    background: #f7fafc;
    transition: all 0.3s ease;
    width: 100%;
    box-sizing: border-box;
  }
  
  textarea.form-input {
    resize: vertical;
    min-height: 80px;
  }
  
  .form-input:focus,
  .form-select:focus,
  textarea.form-input:focus {
    outline: none;
    border-color: #667eea;
    background: white;
    box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
  }
  
  .form-buttons {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 32px;
  }
  
  .btn {
    padding: 12px 24px;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    display: inline-block;
    text-align: center;
    border: none;
  }
  
  .btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
  }
  
  .btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
  }
  
  .btn-secondary {
    background: #e2e8f0;
    color: #4a5568;
  }
  
  .btn-secondary:hover {
    background: #cbd5e0;
    transform: translateY(-2px);
  }
  
  .form-help {
    font-size: 12px;
    color: #718096;
    margin-top: 4px;
  }
  
  @media (max-width: 768px) {
    .form-grid {
      grid-template-columns: 1fr;
    }
    
    .form-card {
      padding: 24px;
    }
  }
</style>

<div class="main-content">
  <div class="page-header">
    <h1 class="page-title">➕ Add New Employee</h1>
    <a href="/MY CASH/business/employees.php" class="back-btn">← Back to Employees</a>
  </div>
  
  <div class="form-card">
    <?php if ($error): ?>
      <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
        <br>
        <a href="/MY CASH/business/employees.php" style="color: #155724; font-weight: 600;">View all employees →</a>
      </div>
    <?php endif; ?>
    
    <form method="POST" action="">
      <div class="form-grid">
        <!-- First Name -->
        <div class="form-group">
          <label class="form-label">
            First Name <span class="required">*</span>
          </label>
          <input 
            type="text" 
            name="first_name" 
            class="form-input" 
            value="<?php echo htmlspecialchars($first_name ?? ''); ?>"
            required
            placeholder="John"
          >
        </div>
        
        <!-- Last Name -->
        <div class="form-group">
          <label class="form-label">
            Last Name <span class="required">*</span>
          </label>
          <input 
            type="text" 
            name="last_name" 
            class="form-input" 
            value="<?php echo htmlspecialchars($last_name ?? ''); ?>"
            required
            placeholder="Doe"
          >
        </div>
        
        <!-- Email -->
        <div class="form-group">
          <label class="form-label">
            Email Address
          </label>
          <input 
            type="email" 
            name="email" 
            class="form-input" 
            value="<?php echo htmlspecialchars($email ?? ''); ?>"
            placeholder="john.doe@company.com"
          >
          <span class="form-help">Optional - Used for employee login</span>
        </div>
        
        <!-- Phone -->
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input 
            type="tel" 
            name="phone" 
            class="form-input" 
            value="<?php echo htmlspecialchars($phone ?? ''); ?>"
            placeholder="+250 788 123 456"
          >
        </div>
        
        <!-- Role -->
        <div class="form-group">
          <label class="form-label">
            Role/Position <span class="required">*</span>
          </label>
          <input 
            type="text" 
            name="role" 
            class="form-input" 
            value="<?php echo htmlspecialchars($role ?? ''); ?>"
            required
            placeholder="Sales Manager"
          >
        </div>
        
        <!-- Department -->
        <div class="form-group">
          <label class="form-label">Department</label>
          <select name="department" class="form-select">
            <option value="">Select Department</option>
            <option value="Sales" <?php echo (($department ?? '') === 'Sales') ? 'selected' : ''; ?>>Sales</option>
            <option value="Marketing" <?php echo (($department ?? '') === 'Marketing') ? 'selected' : ''; ?>>Marketing</option>
            <option value="Operations" <?php echo (($department ?? '') === 'Operations') ? 'selected' : ''; ?>>Operations</option>
            <option value="Finance" <?php echo (($department ?? '') === 'Finance') ? 'selected' : ''; ?>>Finance</option>
            <option value="HR" <?php echo (($department ?? '') === 'HR') ? 'selected' : ''; ?>>Human Resources</option>
            <option value="IT" <?php echo (($department ?? '') === 'IT') ? 'selected' : ''; ?>>IT</option>
            <option value="Customer Service" <?php echo (($department ?? '') === 'Customer Service') ? 'selected' : ''; ?>>Customer Service</option>
            <option value="Production" <?php echo (($department ?? '') === 'Production') ? 'selected' : ''; ?>>Production</option>
            <option value="Administration" <?php echo (($department ?? '') === 'Administration') ? 'selected' : ''; ?>>Administration</option>
          </select>
        </div>
        
        <!-- Salary -->
        <div class="form-group">
          <label class="form-label">Salary (RWF)</label>
          <input 
            type="number" 
            name="salary" 
            class="form-input" 
            value="<?php echo htmlspecialchars($salary ?? ''); ?>"
            step="1000"
            min="0"
            placeholder="500000"
          >
          <span class="form-help">Monthly salary in Rwandan Francs</span>
        </div>
        
        <!-- Date of Birth -->
        <div class="form-group">
          <label class="form-label">Date of Birth</label>
          <input 
            type="date" 
            name="date_of_birth" 
            class="form-input" 
            value="<?php echo htmlspecialchars($date_of_birth ?? ''); ?>"
            max="<?php echo date('Y-m-d', strtotime('-18 years')); ?>"
          >
          <span class="form-help">Must be at least 18 years old</span>
        </div>
        
        <!-- Hire Date -->
        <div class="form-group">
          <label class="form-label">Hire Date</label>
          <input 
            type="date" 
            name="hire_date" 
            class="form-input" 
            value="<?php echo htmlspecialchars($hire_date ?? date('Y-m-d')); ?>"
            max="<?php echo date('Y-m-d'); ?>"
          >
          <span class="form-help">Defaults to today if not set</span>
        </div>
        
        <!-- Status -->
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="active" <?php echo (($status ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo (($status ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
            <option value="terminated" <?php echo (($status ?? '') === 'terminated') ? 'selected' : ''; ?>>Terminated</option>
          </select>
          <span class="form-help">Only active employees can login</span>
        </div>
        
        <!-- Address (Full Width) -->
        <div class="form-group full-width">
          <label class="form-label">Address</label>
          <textarea 
            name="address" 
            class="form-input" 
            rows="3"
            placeholder="Employee's residential address"
          ><?php echo htmlspecialchars($address ?? ''); ?></textarea>
        </div>
      </div>
      
      <div class="form-buttons">
        <a href="/MY CASH/business/employees.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">💾 Save Employee</button>
      </div>
    </form>
    
    <div style="margin-top: 32px; padding: 16px; background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%); border-radius: 10px; border: 1px solid rgba(102, 126, 234, 0.2);">
      <div style="display: flex; gap: 12px; align-items: flex-start;">
        <div style="font-size: 20px;">💡</div>
        <div style="font-size: 13px; color: #4a5568; line-height: 1.6;">
          <strong style="color: #2d3748; display: block; margin-bottom: 4px;">Important Note:</strong>
          After adding an employee, you need to set their login password using 
          <strong>Business > Setup Employee Access</strong> before they can login to the system.
        </div>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
