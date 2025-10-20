<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Production security helpers
require_once __DIR__ . '/includes/production_config.php';

// NOTICE: You can also login from the main page at index.php
// The unified login system will automatically detect if you're an employee or admin

// If already logged in as employee, redirect to dashboard
if (!empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee/dashboard.php");
  exit;
}

include __DIR__ . '/includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Validate CSRF token
  $token = $_POST['csrf_token'] ?? '';
  if (!validateCSRFToken($token)) {
    $error = 'Invalid request (CSRF token mismatch).';
  } else {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
      $error = 'Please enter both email and password';
    } else {
      // Rate limit by IP + email
      $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|employee|' . $email;
      if (!checkRateLimit($identifier, 5, 900)) {
        $error = 'Too many login attempts. Please try again in 15 minutes.';
      } else {
    try {
      // Find employee by email
      $stmt = $conn->prepare("SELECT e.*, u.name as company_name 
        FROM employees e 
        LEFT JOIN users u ON e.user_id = u.id 
        WHERE e.email = ? AND e.status = 'active' AND e.is_active = 1");
      $stmt->execute([$email]);
      $employee = $stmt->fetch(PDO::FETCH_ASSOC);
      
      if ($employee && !empty($employee['password_hash'])) {
        // Verify password
        if (password_verify($password, $employee['password_hash'])) {
          // Set employee session
          $_SESSION['employee_id'] = $employee['id'];
          $_SESSION['employee_name'] = $employee['name'];
          $_SESSION['employee_email'] = $employee['email'];
          $_SESSION['employee_role'] = $employee['role'];
          $_SESSION['employee_user_id'] = $employee['user_id'];
          $_SESSION['employee_company'] = $employee['company_name'];
          
          // Update last login
          $updateStmt = $conn->prepare("UPDATE employees SET last_login = NOW() WHERE id = ?");
          $updateStmt->execute([$employee['id']]);
          
          // Reset rate limit on successful login
          resetRateLimit($identifier);
          
          header("Location: /MY CASH/employee/dashboard.php");
          exit;
        } else {
          $error = 'Invalid email or password';
        }
      } else {
        $error = 'Account not found or inactive. Please contact your administrator.';
      }
    } catch (Exception $e) {
      $error = 'Login failed. Please try again.';
    }
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Employee Login - MY CASH</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }
    
    body {
      font-family: 'Inter', sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    
    .login-container {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      width: 100%;
      max-width: 440px;
      padding: 48px 40px;
    }
    
    .logo-section {
      text-align: center;
      margin-bottom: 32px;
    }
    
    .logo-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 40px;
      color: white;
      margin-bottom: 16px;
      box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
    }
    
    .logo-title {
      font-size: 28px;
      font-weight: 800;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 4px;
    }
    
    .logo-subtitle {
      color: #718096;
      font-size: 14px;
    }
    
    .form-group {
      margin-bottom: 24px;
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
      padding: 14px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-size: 15px;
      font-family: 'Inter', sans-serif;
      transition: all 0.2s;
    }
    
    .form-group input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .btn-login {
      width: 100%;
      padding: 14px 24px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      font-family: 'Inter', sans-serif;
      margin-top: 8px;
    }
    
    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 32px rgba(102, 126, 234, 0.4);
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
    
    .divider {
      text-align: center;
      margin: 24px 0;
      color: #a0aec0;
      font-size: 14px;
      position: relative;
    }
    
    .divider::before,
    .divider::after {
      content: '';
      position: absolute;
      top: 50%;
      width: 40%;
      height: 1px;
      background: #e2e8f0;
    }
    
    .divider::before {
      left: 0;
    }
    
    .divider::after {
      right: 0;
    }
    
    .bottom-links {
      text-align: center;
      margin-top: 24px;
    }
    
    .bottom-links a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
      font-size: 14px;
      transition: color 0.2s;
    }
    
    .bottom-links a:hover {
      color: #764ba2;
      text-decoration: underline;
    }
    
    .info-box {
      background: rgba(102, 126, 234, 0.1);
      border: 1px solid rgba(102, 126, 234, 0.2);
      border-radius: 12px;
      padding: 16px;
      margin-bottom: 24px;
      font-size: 13px;
      color: #2d3748;
      line-height: 1.6;
    }
    
    .info-box strong {
      color: #667eea;
    }
  </style>
</head>
<body>
  <div class="login-container">
    <div class="logo-section">
      <div class="logo-icon">👤</div>
      <h1 class="logo-title">Employee Portal</h1>
      <p class="logo-subtitle">Track your daily tasks and activities</p>
    </div>
    
    <?php if ($error): ?>
      <div class="alert alert-error">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
      <div class="alert alert-success">
        <?php echo htmlspecialchars($success); ?>
      </div>
    <?php endif; ?>
    
    <div class="info-box">
      <strong>🔐 Secure Employee Access</strong><br>
      Use your employee email and password provided by your administrator to access your work dashboard.
    </div>
    
    <form method="POST" action="">
      <?php echo csrfTokenField(); ?>
      <div class="form-group">
        <label for="email">Email Address</label>
        <input 
          type="email" 
          id="email" 
          name="email" 
          placeholder="your.email@company.com"
          required 
          value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
        >
      </div>
      
      <div class="form-group">
        <label for="password">Password</label>
        <input 
          type="password" 
          id="password" 
          name="password" 
          placeholder="Enter your password"
          required
        >
      </div>
      
      <button type="submit" class="btn-login">
        Login to Dashboard
      </button>
    </form>
    
    <div class="divider">OR</div>
    
    <div class="bottom-links">
      <a href="/MY CASH/pages/login.php">← Back to Main Login</a>
    </div>
  </div>
</body>
</html>
