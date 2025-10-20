<?php
// NOTICE: This is the old admin/user-only login page
// You can now use the unified login at index.php which supports both admin and employee logins

// ensure session available before any output
if (session_status() === PHP_SESSION_NONE) session_start();
// production helpers: CSRF, rate limiting, escape
include __DIR__ . '/../includes/production_config.php';
// legacy DB connection (if used elsewhere in this file)
include __DIR__ . '/../includes/db.php';

// Process login before including header/output so header() redirects work
$login_error = '';
$success_msg = '';

if (isset($_GET['registered']) && $_GET['registered'] == '1') {
    $success_msg = 'Registration successful! Please login to continue.';
}

if(isset($_POST['login'])){
  // Validate CSRF token
  $token = $_POST['csrf_token'] ?? '';
  if (!validateCSRFToken($token)) {
    $login_error = 'Invalid request (CSRF token mismatch).';
  } else {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // rate limit by IP + email combo
    $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . ($email ?: 'guest');
    if (!checkRateLimit($identifier, 5, 900)) {
      $login_error = 'Too many login attempts. Please try again later.';
    } else {
      $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
      $stmt->execute([$email]);
      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if($user && password_verify($password, $user['password'])){
        // ensure session started (redundant but safe) and set user id
        if (session_status() === PHP_SESSION_NONE) session_start();
        $_SESSION['user_id'] = $user['id'];
        // reset rate limit on success
        resetRateLimit($identifier);
        header("Location: /MY CASH/pages/dashboard.php");
        exit;
      } else {
        $login_error = 'Invalid credentials';
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
  <title>Login - MY CASH</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/MY CASH/assets/css/style.min.css">
  <style>
    body {
      margin: 0;
      padding: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
      font-family: 'Inter', sans-serif;
    }
    
    .auth-container {
      width: 100%;
      max-width: 460px;
      padding: 24px;
    }
    
    .auth-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      padding: 48px 40px;
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }
    
    .auth-logo {
      text-align: center;
      margin-bottom: 32px;
    }
    
    .auth-logo-icon {
      width: 64px;
      height: 64px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 16px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 2rem;
      margin-bottom: 16px;
      box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
    }
    
    .auth-title {
      font-size: 1.8rem;
      font-weight: 900;
      color: #1a202c;
      margin: 0 0 8px 0;
      text-align: center;
    }
    
    .auth-subtitle {
      font-size: 0.95rem;
      color: #718096;
      text-align: center;
      margin: 0 0 32px 0;
    }
    
    .alert {
      padding: 14px 16px;
      border-radius: 12px;
      margin-bottom: 24px;
      font-size: 0.9rem;
      font-weight: 500;
    }
    
    .alert.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }
    
    .alert.danger {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .form-group {
      margin-bottom: 24px;
    }
    
    .form-label {
      display: block;
      font-size: 0.9rem;
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 8px;
    }
    
    .form-input {
      width: 100%;
      padding: 14px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      font-family: 'Inter', sans-serif;
      background: #f7fafc;
    }
    
    .form-input:focus {
      outline: none;
      border-color: #667eea;
      background: white;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }
    
    .btn-submit {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
    }
    
    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(102, 126, 234, 0.5);
    }
    
    .btn-submit:active {
      transform: translateY(0);
    }
    
    .auth-footer {
      margin-top: 24px;
      text-align: center;
      font-size: 0.9rem;
      color: #718096;
    }
    
    .auth-footer a {
      color: #667eea;
      text-decoration: none;
      font-weight: 600;
    }
    
    .auth-footer a:hover {
      text-decoration: underline;
    }
    
    .back-home {
      text-align: center;
      margin-top: 20px;
    }
    
    .back-home a {
      color: white;
      text-decoration: none;
      font-size: 0.9rem;
      opacity: 0.9;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }
    
    .back-home a:hover {
      opacity: 1;
      text-decoration: underline;
    }
    
    @media (max-width: 768px) {
      .auth-card {
        padding: 36px 28px;
      }
      
      .auth-title {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-logo">
        <div class="auth-logo-icon">💰</div>
        <h1 class="auth-title">Welcome Back</h1>
        <p class="auth-subtitle">Sign in to access your financial dashboard</p>
      </div>
      
      <?php if (!empty($success_msg)): ?>
        <div class="alert success"><?=htmlspecialchars($success_msg)?></div>
      <?php endif; ?>
      
      <?php if (!empty($login_error)): ?>
        <div class="alert danger"><?=htmlspecialchars($login_error)?></div>
      <?php endif; ?>
      
      <form method="POST">
        <?php echo csrfTokenField(); ?>
        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" required autocomplete="email">
        </div>
        
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required autocomplete="current-password">
        </div>
        
        <button type="submit" name="login" class="btn-submit">Sign In</button>
      </form>
      
      <div class="auth-footer">
        Don't have an account? <a href="/MY CASH/pages/register.php">Create one now</a>
      </div>
    </div>
    
    <div class="back-home">
      <a href="/MY CASH/">
        <span>←</span>
        <span>Back to Home</span>
      </a>
    </div>
  </div>
</body>
</html>
