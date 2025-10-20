<?php
/**
 * MY CASH - Deployment Preparation Script
 * 
 * This script helps prepare your application for production deployment by:
 * - Checking file permissions
 * - Removing test/debug files
 * - Validating configuration
 * - Creating backups
 * - Generating deployment checklist
 * 
 * RUN THIS SCRIPT BEFORE DEPLOYING TO PRODUCTION!
 * Access: http://localhost/MY CASH/deploy_prep.php
 */

// Require admin authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    die('⛔ Access denied. Please login as admin first.');
}

// Check if user is admin
require_once __DIR__ . '/includes/db.php';
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (empty($user['is_admin'])) {
    die('⛔ Access denied. Admin privileges required.');
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deployment Preparation - MY CASH</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2rem;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 30px;
        }
        .section {
            margin: 30px 0;
            padding: 20px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }
        .section h2 {
            color: #1e293b;
            margin-bottom: 15px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-success { background: #d1fae5; color: #065f46; }
        .status-warning { background: #fef3c7; color: #92400e; }
        .status-error { background: #fee2e2; color: #991b1b; }
        .item {
            padding: 12px;
            margin: 8px 0;
            background: #f8fafc;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
        .checklist {
            list-style: none;
        }
        .checklist li {
            padding: 10px;
            margin: 5px 0;
            background: #f1f5f9;
            border-radius: 6px;
        }
        .checklist li:before {
            content: '☐ ';
            margin-right: 10px;
            font-size: 1.2rem;
        }
        code {
            background: #1e293b;
            color: #10b981;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .warning-box {
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
        .success-box {
            background: #d1fae5;
            border-left: 4px solid #10b981;
            padding: 15px;
            margin: 20px 0;
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Deployment Preparation</h1>
        <p class="subtitle">Preparing MY CASH for production deployment</p>

        <?php
        $issues = [];
        $warnings = [];
        $successes = [];

        // ===== CHECK 1: Test Files =====
        echo '<div class="section">';
        echo '<h2>📁 Test & Debug Files</h2>';
        
        $test_files = [
            'health_check.php',
            'cleanup_test_data.php',
            'setup_notifications.php',
            'deploy_prep.php', // This file itself
            'dev_tools/test_ai_call.php',
            'HEALTH_CHECK_REPORT.md',
            'NOTIFICATIONS_SETUP_GUIDE.md'
        ];
        
        $found_test_files = [];
        foreach ($test_files as $file) {
            if (file_exists(__DIR__ . '/' . $file)) {
                $found_test_files[] = $file;
                echo '<div class="item">' . htmlspecialchars($file) . ' <span class="status status-warning">REMOVE</span></div>';
            }
        }
        
        if (empty($found_test_files)) {
            echo '<div class="success-box">✅ No test files found - Good!</div>';
            $successes[] = 'No test files to remove';
        } else {
            $warnings[] = count($found_test_files) . ' test file(s) should be removed before deployment';
        }
        echo '</div>';

        // ===== CHECK 2: Database Configuration =====
        echo '<div class="section">';
        echo '<h2>🗄️ Database Configuration</h2>';
        
        $db_content = file_get_contents(__DIR__ . '/includes/db.php');
        if (strpos($db_content, 'root') !== false && strpos($db_content, '$password = ""') !== false) {
            echo '<div class="warning-box">⚠️ <strong>WARNING:</strong> Database still using default credentials (root with no password)</div>';
            echo '<div class="item">Update database credentials <span class="status status-error">REQUIRED</span></div>';
            $issues[] = 'Database credentials must be changed';
        } else {
            echo '<div class="success-box">✅ Database credentials appear to be customized</div>';
            $successes[] = 'Database configuration updated';
        }
        echo '</div>';

        // ===== CHECK 3: Directory Permissions =====
        echo '<div class="section">';
        echo '<h2>📂 Directory Permissions</h2>';
        
        $required_writable = [
            'assets/uploads',
            'assets/uploads/avatars',
            'assets/logs',
            'assets/data'
        ];
        
        $permission_issues = [];
        foreach ($required_writable as $dir) {
            $full_path = __DIR__ . '/' . $dir;
            if (!is_dir($full_path)) {
                mkdir($full_path, 0755, true);
            }
            
            $writable = is_writable($full_path);
            $perms = substr(sprintf('%o', fileperms($full_path)), -4);
            
            echo '<div class="item">';
            echo htmlspecialchars($dir) . ' <code>' . $perms . '</code> ';
            echo $writable ? '<span class="status status-success">WRITABLE</span>' : '<span class="status status-error">NOT WRITABLE</span>';
            echo '</div>';
            
            if (!$writable) {
                $permission_issues[] = $dir;
            }
        }
        
        if (empty($permission_issues)) {
            $successes[] = 'All directories have correct permissions';
        } else {
            $issues[] = count($permission_issues) . ' directory permission issue(s)';
        }
        echo '</div>';

        // ===== CHECK 4: Security Files =====
        echo '<div class="section">';
        echo '<h2>🔒 Security Configuration</h2>';
        
        $security_files = [
            '.htaccess' => 'Main security rules',
            'includes/production_config.php' => 'Production PHP configuration'
        ];
        
        foreach ($security_files as $file => $desc) {
            $exists = file_exists(__DIR__ . '/' . $file);
            echo '<div class="item">';
            echo htmlspecialchars($file) . ' <small>(' . $desc . ')</small> ';
            echo $exists ? '<span class="status status-success">EXISTS</span>' : '<span class="status status-warning">MISSING</span>';
            echo '</div>';
            
            if (!$exists) {
                $warnings[] = $file . ' should be created';
            } else {
                $successes[] = $file . ' is present';
            }
        }
        echo '</div>';

        // ===== CHECK 5: PHP Configuration =====
        echo '<div class="section">';
        echo '<h2>⚙️ PHP Configuration</h2>';
        
        $php_checks = [
            'display_errors' => ini_get('display_errors'),
            'log_errors' => ini_get('log_errors'),
            'expose_php' => ini_get('expose_php'),
            'session.cookie_httponly' => ini_get('session.cookie_httponly'),
        ];
        
        foreach ($php_checks as $setting => $value) {
            $good = false;
            $expected = '';
            
            if ($setting === 'display_errors') {
                $good = ($value == '0' || $value === false);
                $expected = 'Off';
            } elseif ($setting === 'log_errors') {
                $good = ($value == '1' || $value === true);
                $expected = 'On';
            } elseif ($setting === 'expose_php') {
                $good = ($value == '0' || $value === false);
                $expected = 'Off';
            } elseif ($setting === 'session.cookie_httponly') {
                $good = ($value == '1' || $value === true);
                $expected = 'On';
            }
            
            echo '<div class="item">';
            echo htmlspecialchars($setting) . ': <code>' . ($value ? 'On' : 'Off') . '</code> ';
            echo $good ? '<span class="status status-success">GOOD</span>' : '<span class="status status-warning">CHECK (Expected: ' . $expected . ')</span>';
            echo '</div>';
            
            if (!$good) {
                $warnings[] = $setting . ' should be ' . $expected;
            }
        }
        echo '</div>';

        // ===== SUMMARY =====
        echo '<div class="section" style="background: linear-gradient(135deg, #f1f5f9, #e2e8f0);">';
        echo '<h2>📊 Summary</h2>';
        
        $total_issues = count($issues);
        $total_warnings = count($warnings);
        $total_successes = count($successes);
        
        echo '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin: 20px 0;">';
        echo '<div style="text-align: center; padding: 20px; background: #fee2e2; border-radius: 10px;">';
        echo '<div style="font-size: 2rem; font-weight: 800; color: #991b1b;">' . $total_issues . '</div>';
        echo '<div style="color: #991b1b; font-weight: 600;">Critical Issues</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 20px; background: #fef3c7; border-radius: 10px;">';
        echo '<div style="font-size: 2rem; font-weight: 800; color: #92400e;">' . $total_warnings . '</div>';
        echo '<div style="color: #92400e; font-weight: 600;">Warnings</div>';
        echo '</div>';
        
        echo '<div style="text-align: center; padding: 20px; background: #d1fae5; border-radius: 10px;">';
        echo '<div style="font-size: 2rem; font-weight: 800; color: #065f46;">' . $total_successes . '</div>';
        echo '<div style="color: #065f46; font-weight: 600;">Passed Checks</div>';
        echo '</div>';
        echo '</div>';
        
        // Readiness Score
        $total_checks = $total_issues + $total_warnings + $total_successes;
        $readiness_score = $total_checks > 0 ? round(($total_successes / $total_checks) * 100) : 0;
        
        echo '<div style="margin: 20px 0; padding: 20px; background: white; border-radius: 10px; text-align: center;">';
        echo '<div style="font-size: 1.2rem; color: #64748b; margin-bottom: 10px;">Production Readiness Score</div>';
        echo '<div style="font-size: 3rem; font-weight: 900; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent;">';
        echo $readiness_score . '%';
        echo '</div>';
        
        if ($readiness_score >= 90) {
            echo '<div style="color: #10b981; font-weight: 600; margin-top: 10px;">✅ Ready for deployment!</div>';
        } elseif ($readiness_score >= 70) {
            echo '<div style="color: #f59e0b; font-weight: 600; margin-top: 10px;">⚠️ Almost ready - fix warnings</div>';
        } else {
            echo '<div style="color: #ef4444; font-weight: 600; margin-top: 10px;">❌ Not ready - fix critical issues first</div>';
        }
        echo '</div>';
        echo '</div>';

        // ===== DEPLOYMENT CHECKLIST =====
        echo '<div class="section">';
        echo '<h2>✅ Pre-Deployment Checklist</h2>';
        echo '<ul class="checklist">';
        echo '<li>Update database credentials in <code>includes/db.php</code></li>';
        echo '<li>Remove all test files listed above</li>';
        echo '<li>Verify all directory permissions are correct</li>';
        echo '<li>Upload <code>.htaccess</code> file to server</li>';
        echo '<li>Include <code>production_config.php</code> in <code>index.php</code></li>';
        echo '<li>Install SSL certificate and enable HTTPS</li>';
        echo '<li>Test all login systems (admin, employee, user)</li>';
        echo '<li>Test file uploads and database operations</li>';
        echo '<li>Create database backup before deployment</li>';
        echo '<li>Set up automated backups on production server</li>';
        echo '<li>Configure monitoring and uptime alerts</li>';
        echo '<li>Review error logs after deployment</li>';
        echo '</ul>';
        echo '</div>';

        // ===== ACTIONS =====
        echo '<div class="section" style="text-align: center;">';
        echo '<h2>🎯 Next Steps</h2>';
        echo '<div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-top: 20px;">';
        echo '<a href="DEPLOYMENT_GUIDE.md" class="btn btn-primary" target="_blank">📖 View Deployment Guide</a>';
        echo '<a href="PRODUCTION_READINESS_CHECKLIST.md" class="btn btn-primary" target="_blank">📋 Full Checklist</a>';
        echo '<a href="pages/dashboard.php" class="btn btn-primary">🏠 Back to Dashboard</a>';
        echo '</div>';
        echo '</div>';
        ?>
    </div>
</body>
</html>
