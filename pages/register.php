<?php
// start session before any output to allow redirects
if (session_status() === PHP_SESSION_NONE) session_start();

// Production security helpers
require_once __DIR__ . '/../includes/production_config.php';

include __DIR__ . '/../includes/db.php';

$errors = [];
$old = ['name' => '', 'email' => ''];

if (isset($_POST['register'])) {
        // Validate CSRF token
        $token = $_POST['csrf_token'] ?? '';
        if (!validateCSRFToken($token)) {
                $errors[] = 'Invalid request (CSRF token mismatch).';
        } else {
        $name = isset($_POST['name']) ? trim($_POST['name']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $passwordRaw = isset($_POST['password']) ? $_POST['password'] : '';

        $old['name'] = $name;
        $old['email'] = $email;

        // basic validation
        if ($name === '') {
                $errors[] = 'Full name is required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid email address is required.';
        }
        if (strlen($passwordRaw) < 6) {
                $errors[] = 'Password must be at least 6 characters.';
        }

        // check duplicate email
        if (empty($errors)) {
                $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                        $errors[] = 'An account with that email already exists.';
                }
        }

        if (empty($errors)) {
                $password = password_hash($passwordRaw, PASSWORD_DEFAULT);
                try {
                        $stmt = $conn->prepare("INSERT INTO users (name,email,password) VALUES (?,?,?)");
                        $stmt->execute([$name, $email, $password]);
                        // registration successful
                        header('Location: /MY CASH/pages/login.php?registered=1');
                        exit;
                } catch (Exception $e) {
                        // don't reveal DB details to user
                        $errors[] = 'Unable to create account right now. Please try again later.';
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
  <title>Register - MY CASH</title>
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
      padding: 40px 0;
    }
    
    .auth-container {
      width: 100%;
      max-width: 520px;
      padding: 24px;
    }
    
    .auth-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      padding: 44px 48px 40px;
      box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
      border: 1px solid rgba(255, 255, 255, 0.5);
    }
    
    .auth-logo {
      text-align: center;
      margin-bottom: 36px;
    }
    
    .auth-logo-icon {
      width: 68px;
      height: 68px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 2.2rem;
      margin-bottom: 18px;
      box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
    }
    
    .auth-title {
      font-size: 2rem;
      font-weight: 900;
      color: #1a202c;
      margin: 0 0 10px 0;
      text-align: center;
    }
    
    .auth-subtitle {
      font-size: 1rem;
      color: #718096;
      text-align: center;
      margin: 0 0 36px 0;
      line-height: 1.5;
    }
    
    .alert {
      padding: 16px 18px;
      border-radius: 12px;
      margin-bottom: 28px;
      font-size: 0.92rem;
      line-height: 1.5;
    }
    
    .alert.danger {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }
    
    .alert ul {
      margin: 0;
      padding-left: 22px;
    }
    
    .alert li {
      margin: 6px 0;
    }
    
    .form-group {
      margin-bottom: 22px;
    }
    
    .form-label {
      display: block;
      font-size: 0.92rem;
      font-weight: 600;
      color: #2d3748;
      margin-bottom: 9px;
    }
    
    .form-input {
      width: 100%;
      padding: 15px 18px;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-size: 0.98rem;
      transition: all 0.3s ease;
      font-family: 'Inter', sans-serif;
      background: #f7fafc;
      box-sizing: border-box;
    }
    
    .form-input:focus {
      outline: none;
      border-color: #667eea;
      background: white;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }
    
    .form-hint {
      font-size: 0.82rem;
      color: #a0aec0;
      margin-top: 7px;
      line-height: 1.4;
    }
    
    .btn-submit {
      width: 100%;
      padding: 17px 20px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 1.02rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
      margin-bottom: 14px;
    }
    
    .btn-submit:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 24px rgba(102, 126, 234, 0.5);
    }
    
    .btn-submit:active {
      transform: translateY(0);
    }
    
    .btn-secondary {
      width: 100%;
      padding: 15px 20px;
      background: white;
      color: #667eea;
      border: 2px solid #667eea;
      border-radius: 12px;
      font-size: 0.98rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
      text-align: center;
      box-sizing: border-box;
    }
    
    .btn-secondary:hover {
      background: #f7fafc;
      transform: translateY(-1px);
    }
    
    .auth-footer {
      margin-top: 26px;
      text-align: center;
      font-size: 0.88rem;
      color: #718096;
      line-height: 1.5;
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
      margin-top: 24px;
    }
    
    .back-home a {
      color: white;
      text-decoration: none;
      font-size: 0.94rem;
      opacity: 0.92;
      display: inline-flex;
      align-items: center;
      gap: 7px;
      transition: all 0.2s ease;
    }
    
    .back-home a:hover {
      opacity: 1;
      text-decoration: underline;
    }
    
    @media (max-width: 768px) {
      body {
        padding: 20px 0;
      }
      
      .auth-container {
        padding: 16px;
      }
      
      .auth-card {
        padding: 36px 32px 32px;
      }
      
      .auth-title {
        font-size: 1.7rem;
      }
      
      .auth-subtitle {
        font-size: 0.94rem;
      }
      
      .auth-logo-icon {
        width: 60px;
        height: 60px;
        font-size: 2rem;
      }
    }
    
    @media (max-width: 480px) {
      .auth-card {
        padding: 32px 24px 28px;
      }
      
      .form-input {
        font-size: 16px; /* Prevents zoom on iOS */
      }
    }
  </style>
</head>
<body>
  <div class="auth-container">
    <div class="auth-card">
      <div class="auth-logo">
        <div class="auth-logo-icon">💰</div>
        <h1 class="auth-title">Create Account</h1>
        <p class="auth-subtitle">Start managing your finances with confidence</p>
      </div>
      
      <?php if (!empty($errors)): ?>
        <div class="alert danger">
          <ul>
            <?php foreach ($errors as $err): ?>
              <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
      
      <form method="POST" novalidate>
        <?php echo csrfTokenField(); ?>
        <div class="form-group">
          <label class="form-label" for="name">Full Name</label>
          <input type="text" id="name" name="name" class="form-input" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($old['name']); ?>" autocomplete="name">
        </div>
        
        <div class="form-group">
          <label class="form-label" for="email">Email Address</label>
          <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" required value="<?php echo htmlspecialchars($old['email']); ?>" autocomplete="email">
        </div>
        
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input type="password" id="password" name="password" class="form-input" placeholder="Create a strong password" required autocomplete="new-password">
          <div class="form-hint">Must be at least 6 characters long</div>
        </div>
        
        <button type="submit" name="register" class="btn-submit">Create Account</button>
        <a href="/MY CASH/pages/login.php" class="btn-secondary">Already have an account? Sign In</a>
      </form>
      
      <div class="auth-footer">
        By creating an account, you agree to our Terms of Service and Privacy Policy
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
