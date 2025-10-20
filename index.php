<?php
// Start session before any output so header() redirects work
if (session_status() === PHP_SESSION_NONE) session_start();

// Production security helpers
require_once __DIR__ . '/includes/production_config.php';

include __DIR__ . '/includes/db.php';

// Redirect logged-in users before sending page content
if (!empty($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit;
}
if (!empty($_SESSION['employee_id'])) {
    header('Location: employee/dashboard.php');
    exit;
}

// Process unified login
$login_error = '';
if(isset($_POST['login'])){
    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        $login_error = 'Invalid request (CSRF token mismatch).';
    } else {
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        
        // Rate limit by IP + email
        $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|unified|' . $email;
        if (!checkRateLimit($identifier, 5, 900)) {
            $login_error = 'Too many login attempts. Please try again in 15 minutes.';
        } else {
    
            // First, try to login as admin/user
            $stmt = $conn->prepare("SELECT * FROM users WHERE email=?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($user && password_verify($password, $user['password'])){
                // User/Admin login successful
                $_SESSION['user_id'] = $user['id'];
                // Reset rate limit on success
                resetRateLimit($identifier);
                header("Location: pages/dashboard.php");
                exit;
            }
            
            // If not a user, try employee login
            $stmt = $conn->prepare("SELECT e.*, u.name as company_name FROM employees e LEFT JOIN users u ON e.user_id = u.id WHERE e.email = ? AND e.status = 'active' AND e.is_active = 1");
            $stmt->execute([$email]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if($employee && password_verify($password, $employee['password_hash'])){
                // Employee login successful
                $_SESSION['employee_id'] = $employee['id'];
                $_SESSION['employee_name'] = $employee['name'];
                $_SESSION['employee_email'] = $employee['email'];
                $_SESSION['employee_role'] = $employee['role'];
                $_SESSION['employee_user_id'] = $employee['user_id'];
                $_SESSION['employee_company'] = $employee['company_name'];
                
                // Update last login
                $updateStmt = $conn->prepare("UPDATE employees SET last_login = NOW() WHERE id = ?");
                $updateStmt->execute([$employee['id']]);
                
                // Reset rate limit on success
                resetRateLimit($identifier);
                
                header("Location: employee/dashboard.php");
                exit;
            }
            
            // Neither user nor employee found
            $login_error = 'Invalid credentials or account not active';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>MY CASH - Smart Financial Management</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800;900&display=swap" rel="stylesheet">
  <style>
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }
    
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
    }
    
  /* Modern Landing Page Styles */
  .landing-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 50%, #f093fb 100%);
    padding: 0;
    margin: 0;
  }
  
  /* Hero Section - Row 1 */
  .hero-section {
    min-height: 70vh;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 60px;
    align-items: center;
    padding: 100px 60px 60px;
    max-width: 1400px;
    margin: 0 auto;
  }
  
  .hero-content {
    color: white;
  }
  
  .hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    padding: 10px 20px;
    border-radius: 30px;
    font-size: 0.85rem;
    font-weight: 600;
    margin-bottom: 28px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
  }
  
  .hero-title {
    font-size: 3.2rem;
    font-weight: 900;
    line-height: 1.15;
    margin: 0 0 24px 0;
    color: white;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
  }
  
  .hero-subtitle {
    font-size: 1.15rem;
    line-height: 1.7;
    margin-bottom: 40px;
    opacity: 0.95;
    color: rgba(255, 255, 255, 0.95);
  }
  
  .hero-buttons {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
  }
  
  .btn-hero {
    padding: 16px 36px;
    border-radius: 14px;
    font-weight: 700;
    font-size: 1rem;
    text-decoration: none;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    border: none;
    cursor: pointer;
  }
  
  .btn-hero.primary {
    background: white;
    color: #667eea;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  }
  
  .btn-hero.primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 12px 32px rgba(0, 0, 0, 0.3);
  }
  
  .btn-hero.secondary {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.4);
  }
  
  .btn-hero.secondary:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-3px);
    border-color: rgba(255, 255, 255, 0.6);
  }
  
  .hero-visual {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .hero-card-demo {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 42px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.6);
    max-width: 480px;
    width: 100%;
  }
  
  .demo-balance {
    text-align: center;
    margin-bottom: 36px;
    padding-bottom: 24px;
    border-bottom: 2px solid rgba(102, 126, 234, 0.1);
  }
  
  .demo-label {
    font-size: 0.8rem;
    color: #718096;
    margin-bottom: 12px;
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 1.2px;
  }
  
  .demo-amount {
    font-size: 2.8rem;
    font-weight: 900;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin-bottom: 6px;
  }
  
  .demo-currency {
    font-size: 0.85rem;
    color: #a0aec0;
    font-weight: 500;
  }
  
  .demo-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px;
  }
  
  .demo-stat {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
    padding: 18px 14px;
    border-radius: 14px;
    text-align: center;
    border: 1px solid rgba(102, 126, 234, 0.15);
    transition: all 0.3s ease;
  }
  
  .demo-stat:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
  }
  
  .demo-stat-value {
    font-size: 1.8rem;
    font-weight: 800;
    color: #667eea;
    margin-bottom: 4px;
  }
  
  .demo-stat-label {
    font-size: 0.8rem;
    color: #718096;
    font-weight: 600;
  }
  
  /* Features Section - Row 2 */
  .features-section {
    background: white;
    padding: 80px 60px 100px;
  }
  
  .features-container {
    max-width: 1400px;
    margin: 0 auto;
  }
  
  .features-header {
    text-align: center;
    margin-bottom: 60px;
  }
  
  .features-header h2 {
    font-size: 2.5rem;
    font-weight: 900;
    color: #1a202c;
    margin-bottom: 16px;
  }
  
  .features-header p {
    font-size: 1.1rem;
    color: #718096;
    max-width: 600px;
    margin: 0 auto;
  }
  
  .features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 28px;
  }
  
  .feature-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 20px;
    padding: 36px 28px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }
  
  .feature-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
  }
  
  .feature-card:hover::before {
    transform: scaleX(1);
  }
  
  .feature-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 40px rgba(102, 126, 234, 0.25);
    border-color: rgba(102, 126, 234, 0.3);
  }
  
  .feature-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    margin-bottom: 24px;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.35);
  }
  
  .feature-card h3 {
    font-size: 1.3rem;
    font-weight: 800;
    color: #1a202c;
    margin: 0 0 12px 0;
  }
  
  .feature-card p {
    color: #718096;
    line-height: 1.7;
    font-size: 0.95rem;
    margin: 0;
  }
  
  @media (max-width: 1024px) {
    .hero-section {
      grid-template-columns: 1fr;
      gap: 50px;
      padding: 80px 40px 60px;
      min-height: auto;
    }
    
    .hero-title {
      font-size: 2.4rem;
    }
    
    .hero-visual {
      order: -1;
    }
    
    .features-section {
      padding: 60px 40px 80px;
    }
    
    .features-grid {
      grid-template-columns: 1fr;
    }
  }
  
  @media (max-width: 768px) {
    .hero-section {
      padding: 60px 24px 40px;
    }
    
    .hero-title {
      font-size: 1.9rem;
    }
    
    .hero-subtitle {
      font-size: 1rem;
    }
    
    .hero-buttons {
      flex-direction: column;
    }
    
    .btn-hero {
      width: 100%;
      justify-content: center;
    }
    
    .features-section {
      padding: 50px 24px 60px;
    }
    
    .features-header h2 {
      font-size: 1.9rem;
    }
    
    .demo-amount {
      font-size: 2.2rem;
    }
    
    .hero-card-demo {
      padding: 32px 24px;
    }
  }
</style>
<style>
  .demo-currency {
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(10px);
    color: white;
    border: 2px solid rgba(255, 255, 255, 0.3);
}
  
  .btn-hero.secondary:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-3px);
  }
  
  .hero-visual {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  
  .hero-card-demo {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 40px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    border: 1px solid rgba(255, 255, 255, 0.5);
    max-width: 450px;
  }
  
  .demo-balance {
    text-align: center;
    margin-bottom: 32px;
  }
  
  .demo-label {
    font-size: 0.9rem;
    color: #666;
    margin-bottom: 8px;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 1px;
  }
  
  .demo-amount {
    font-size: 3rem;
    font-weight: 900;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
  }
  
  .demo-stats {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 24px;
  }
  
  .demo-stat {
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
    padding: 16px;
    border-radius: 12px;
    text-align: center;
  }
  
  .demo-stat-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #667eea;
    margin-bottom: 4px;
  }
  
  .demo-stat-label {
    font-size: 0.85rem;
    color: #666;
  }
  
  /* Features Section - Row 2 */
  .features-section {
    background: white;
    padding: 80px 60px 100px;
  }
  
  .features-container {
    max-width: 1400px;
    margin: 0 auto;
  }
  
  .features-header {
    text-align: center;
    margin-bottom: 60px;
  }
  
  .features-header h2 {
    font-size: 2.5rem;
    font-weight: 900;
    color: #1a202c;
    margin-bottom: 16px;
  }
  
  .features-header p {
    font-size: 1.1rem;
    color: #718096;
    max-width: 600px;
    margin: 0 auto;
  }
  
  .features-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
    gap: 28px;
  }
  
  .feature-card {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 20px;
    padding: 36px 28px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
  }
  
  .feature-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.3s ease;
  }
  
  .feature-card:hover::before {
    transform: scaleX(1);
  }
  
  .feature-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 16px 40px rgba(102, 126, 234, 0.25);
    border-color: rgba(102, 126, 234, 0.3);
  }
  
  .feature-icon {
    width: 70px;
    height: 70px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2.2rem;
    margin-bottom: 24px;
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.35);
  }
  
  .feature-card h3 {
    font-size: 1.3rem;
    font-weight: 800;
    color: #1a202c;
    margin: 0 0 12px 0;
  }
  
  .feature-card p {
    color: #718096;
    line-height: 1.7;
    font-size: 0.95rem;
    margin: 0;
  }
  
  @media (max-width: 1024px) {
    .hero-section {
      grid-template-columns: 1fr;
      gap: 50px;
      padding: 80px 40px 60px;
      min-height: auto;
    }
    
    .hero-title {
      font-size: 2.4rem;
    }
    
    .hero-visual {
      order: -1;
    }
    
    .features-section {
      padding: 60px 40px 80px;
    }
    
    .features-grid {
      grid-template-columns: 1fr;
    }
  }
  
  @media (max-width: 768px) {
    .hero-section {
      padding: 60px 24px 40px;
    }
    
    .hero-title {
      font-size: 1.9rem;
    }
    
    .hero-subtitle {
      font-size: 1rem;
    }
    
    .hero-buttons {
      flex-direction: column;
    }
    
    .btn-hero {
      width: 100%;
      justify-content: center;
    }
    
    .features-section {
      padding: 50px 24px 60px;
    }
    
    .features-header h2 {
      font-size: 1.9rem;
    }
    
    .demo-amount {
      font-size: 2.2rem;
    }
    
    .hero-card-demo {
      padding: 32px 24px;
    }
  }
  
  /* Login Modal Styles */
  .login-modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.75);
    backdrop-filter: blur(8px);
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.3s ease;
  }
  
  @keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
  }
  
  .login-modal-content {
    background: rgba(255, 255, 255, 0.98);
    backdrop-filter: blur(20px);
    border-radius: 24px;
    padding: 48px 40px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.4);
    border: 1px solid rgba(255, 255, 255, 0.5);
    max-width: 460px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    position: relative;
    animation: slideUp 0.3s ease;
  }
  
  @keyframes slideUp {
    from { 
      opacity: 0;
      transform: translateY(40px);
    }
    to { 
      opacity: 1;
      transform: translateY(0);
    }
  }
  
  .close-modal {
    position: absolute;
    top: 20px;
    right: 20px;
    background: transparent;
    border: none;
    font-size: 2rem;
    color: #718096;
    cursor: pointer;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    transition: all 0.2s ease;
  }
  
  .close-modal:hover {
    background: rgba(102, 126, 234, 0.1);
    color: #667eea;
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
    box-sizing: border-box;
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
  
  .login-info {
    margin-top: 24px;
    padding: 16px;
    background: linear-gradient(135deg, rgba(102, 126, 234, 0.08) 0%, rgba(118, 75, 162, 0.08) 100%);
    border: 1px solid rgba(102, 126, 234, 0.2);
    border-radius: 12px;
    display: flex;
    gap: 12px;
    align-items: flex-start;
  }
  
  .info-icon {
    font-size: 1.3rem;
    flex-shrink: 0;
  }
  
  .info-text {
    font-size: 0.85rem;
    color: #4a5568;
    line-height: 1.6;
  }
  
  .info-text strong {
    color: #2d3748;
    display: block;
    margin-bottom: 4px;
  }
  
  @media (max-width: 768px) {
    .login-modal-content {
      padding: 36px 24px;
      width: 95%;
    }
    
    .auth-title {
      font-size: 1.5rem;
    }
  }
</style>
</head>
<body>

<div class="landing-page">
  <!-- Row 1: Hero Section -->
  <section class="hero-section">
    <div class="hero-content">
      <div class="hero-badge">
        <span>✨</span>
        <span>Smart Financial Management</span>
      </div>
      <h1 class="hero-title">Manage Your Money with Confidence</h1>
      <p class="hero-subtitle">
        Track accounts, monitor transactions, set goals, and achieve financial freedom. 
        All your financial data managed securely on your local machine.
      </p>
      <div class="hero-buttons">
        <a href="/MY CASH/pages/register.php" class="btn-hero primary">
          Get Started Free
          <span>→</span>
        </a>
        <button onclick="openLoginModal()" class="btn-hero secondary">
          Sign In
          <span>→</span>
        </button>
      </div>
    </div>
    
    <div class="hero-visual">
      <div class="hero-card-demo">
        <div class="demo-balance">
          <div class="demo-label">Total Net Worth</div>
          <div class="demo-amount">RWF 2,450,000</div>
          <div class="demo-currency">~$1,884.62</div>
        </div>
        <div class="demo-stats">
          <div class="demo-stat">
            <div class="demo-stat-value">5</div>
            <div class="demo-stat-label">Accounts</div>
          </div>
          <div class="demo-stat">
            <div class="demo-stat-value">12</div>
            <div class="demo-stat-label">Goals</div>
          </div>
          <div class="demo-stat">
            <div class="demo-stat-value">3</div>
            <div class="demo-stat-label">Loans</div>
          </div>
          <div class="demo-stat">
            <div class="demo-stat-value">124</div>
            <div class="demo-stat-label">Transactions</div>
          </div>
        </div>
      </div>
    </div>
  </section>
  
  <!-- Row 2: Features Section -->
  <section class="features-section">
    <div class="features-container">
      <div class="features-header">
        <h2>Everything You Need to Manage Your Finances</h2>
        <p>Powerful features to help you take control of your money</p>
      </div>
      
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">💰</div>
          <h3>Multi-Account Management</h3>
          <p>Track multiple bank accounts, savings, and cash in one place. View balances and transactions across all your accounts effortlessly.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">📊</div>
          <h3>Financial Reports</h3>
          <p>Get detailed insights into your spending patterns, income trends, and net worth with beautiful charts and comprehensive reports.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">🎯</div>
          <h3>Goal Tracking</h3>
          <p>Set financial goals and track your progress. Whether it's saving for a vacation or a new car, stay motivated with visual progress indicators.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">💳</div>
          <h3>Loan Management</h3>
          <p>Keep track of all your loans and debts. Monitor payment schedules, remaining balances, and stay on top of your financial obligations.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">💵</div>
          <h3>Budget Planning</h3>
          <p>Create and manage budgets to control your spending. Set limits for different categories and get notified when you're approaching them.</p>
        </div>
        
        <div class="feature-card">
          <div class="feature-icon">🔒</div>
          <h3>Secure & Private</h3>
          <p>Your data stays on your local machine. No cloud storage, no third-party access. Complete privacy and security for your financial information.</p>
        </div>
      </div>
    </div>
  </section>
</div>

<!-- Login Modal -->
<div id="loginModal" class="login-modal" style="display: <?php echo !empty($login_error) ? 'flex' : 'none'; ?>">
  <div class="login-modal-content">
    <button class="close-modal" onclick="closeLoginModal()">&times;</button>
    
    <div class="auth-logo">
      <div class="auth-logo-icon">💰</div>
      <h2 class="auth-title">Welcome Back</h2>
      <p class="auth-subtitle">Sign in to your account</p>
    </div>
    
    <?php if(!empty($login_error)): ?>
      <div class="alert danger"><?php echo htmlspecialchars($login_error); ?></div>
    <?php endif; ?>
    
    <form method="POST" action="">
      <?php echo csrfTokenField(); ?>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-input" required autocomplete="email" placeholder="your@email.com">
      </div>
      
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-input" required autocomplete="current-password" placeholder="Enter your password">
      </div>
      
      <button type="submit" name="login" class="btn-submit">Sign In</button>
    </form>
    
    <div class="auth-footer">
      Don't have an account? <a href="/MY CASH/pages/register.php">Register here</a>
    </div>
    
    <div class="login-info">
      <div class="info-icon">ℹ️</div>
      <div class="info-text">
        <strong>Login as Admin/User or Employee</strong><br>
        Use your registered email and password. The system will automatically detect your account type and redirect you to the appropriate dashboard.
      </div>
    </div>
  </div>
</div>

<script>
  function openLoginModal() {
    document.getElementById('loginModal').style.display = 'flex';
    // Focus on email input
    setTimeout(() => {
      document.querySelector('#loginModal input[name="email"]').focus();
    }, 100);
  }
  
  function closeLoginModal() {
    document.getElementById('loginModal').style.display = 'none';
  }
  
  // Close modal when clicking outside
  document.getElementById('loginModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
      closeLoginModal();
    }
  });
  
  // Close modal with ESC key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      closeLoginModal();
    }
  });
</script>

</body>
</html>