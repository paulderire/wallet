<?php
/**
 * MY CASH - Complete Application Health Check
 * Scans for errors, missing files, database issues, and more
 */

session_start();
require_once __DIR__ . '/includes/db.php';

$results = [
    'errors' => [],
    'warnings' => [],
    'info' => [],
    'success' => []
];

echo "<!DOCTYPE html>
<html>
<head>
    <title>MY CASH - Health Check</title>
    <style>
        body { font-family: 'Inter', sans-serif; padding: 20px; background: #f9fafb; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #111827; margin-bottom: 30px; }
        h2 { color: #374151; margin-top: 30px; border-bottom: 2px solid #e5e7eb; padding-bottom: 10px; }
        .check-item { padding: 12px; margin: 8px 0; border-radius: 8px; display: flex; align-items: start; gap: 12px; }
        .success { background: #D1FAE5; border-left: 4px solid #10B981; }
        .warning { background: #FEF3C7; border-left: 4px solid #F59E0B; }
        .error { background: #FEE2E2; border-left: 4px solid #EF4444; }
        .info { background: #DBEAFE; border-left: 4px solid #3B82F6; }
        .icon { font-size: 20px; flex-shrink: 0; }
        .message { flex: 1; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0; }
        .stat-card { background: #F3F4F6; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 32px; font-weight: bold; color: #111827; }
        .stat-label { color: #6B7280; margin-top: 8px; }
        pre { background: #F3F4F6; padding: 12px; border-radius: 6px; overflow-x: auto; }
        .file-list { max-height: 300px; overflow-y: auto; background: #F9FAFB; padding: 12px; border-radius: 6px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>
<h1>🏥 MY CASH - Application Health Check</h1>
<p style='color: #6B7280;'>Comprehensive scan of your application for errors, issues, and optimization opportunities</p>
";

// ==================== CHECK 1: PHP VERSION & EXTENSIONS ====================
echo "<h2>1. PHP Environment</h2>";

$phpVersion = phpversion();
if (version_compare($phpVersion, '7.4.0', '>=')) {
    echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'><strong>PHP Version:</strong> $phpVersion (Good)</span></div>";
} else {
    echo "<div class='check-item error'><span class='icon'>❌</span><span class='message'><strong>PHP Version:</strong> $phpVersion (Upgrade to 7.4+ recommended)</span></div>";
}

$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'session'];
foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'>Extension <strong>$ext</strong> loaded</span></div>";
    } else {
        echo "<div class='check-item error'><span class='icon'>❌</span><span class='message'>Extension <strong>$ext</strong> NOT loaded</span></div>";
    }
}

// ==================== CHECK 2: DATABASE CONNECTION ====================
echo "<h2>2. Database Connection</h2>";

try {
    $conn->query("SELECT 1");
    echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'>Database connection successful</span></div>";
    
    // Check database size
    $stmt = $conn->query("SELECT table_schema AS 'Database', 
        ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' 
        FROM information_schema.TABLES 
        WHERE table_schema = DATABASE() 
        GROUP BY table_schema");
    $size = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<div class='check-item info'><span class='icon'>ℹ️</span><span class='message'>Database size: <strong>{$size['Size (MB)']} MB</strong></span></div>";
    
} catch (Exception $e) {
    echo "<div class='check-item error'><span class='icon'>❌</span><span class='message'>Database connection failed: " . htmlspecialchars($e->getMessage()) . "</span></div>";
}

// ==================== CHECK 3: DATABASE TABLES ====================
echo "<h2>3. Database Tables</h2>";

$requiredTables = [
    'users' => 'User accounts',
    'employees' => 'Employee records',
    'accounts' => 'Financial accounts',
    'chat_rooms' => 'Chat system',
    'chat_messages' => 'Chat messages',
    'chat_participants' => 'Chat participants',
    'notifications' => 'Notifications',
    'messages' => 'System messages'
];

try {
    $stmt = $conn->query("SHOW TABLES");
    $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($requiredTables as $table => $description) {
        if (in_array($table, $existingTables)) {
            // Count records
            try {
                $countStmt = $conn->query("SELECT COUNT(*) as count FROM `$table`");
                $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'><strong>$table</strong> exists ($description) - $count records</span></div>";
            } catch (Exception $e) {
                echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'><strong>$table</strong> exists ($description)</span></div>";
            }
        } else {
            echo "<div class='check-item warning'><span class='icon'>⚠️</span><span class='message'><strong>$table</strong> missing ($description) - Run setup script</span></div>";
        }
    }
    
    // Show additional tables
    $additionalTables = array_diff($existingTables, array_keys($requiredTables));
    if (count($additionalTables) > 0) {
        echo "<div class='check-item info'><span class='icon'>ℹ️</span><span class='message'>Additional tables: " . implode(', ', $additionalTables) . "</span></div>";
    }
    
} catch (Exception $e) {
    echo "<div class='check-item error'><span class='icon'>❌</span><span class='message'>Error checking tables: " . htmlspecialchars($e->getMessage()) . "</span></div>";
}

// ==================== CHECK 4: FILE STRUCTURE ====================
echo "<h2>4. File Structure</h2>";

$criticalFiles = [
    'index.php' => 'Main entry point',
    'includes/db.php' => 'Database connection',
    'includes/header.php' => 'Global header',
    'includes/footer.php' => 'Global footer',
    'includes/chat_api.php' => 'Chat API',
    'includes/notifications_api.php' => 'Notifications API',
    'includes/floating_chat.php' => 'Floating chat widget',
    'includes/notification_widget.php' => 'Notification widget',
    'pages/dashboard.php' => 'Admin dashboard',
    'employee/dashboard.php' => 'Employee dashboard',
    'assets/css/style.css' => 'Main styles',
    'assets/css/employee-theme.css' => 'Employee theme'
];

$missingFiles = [];
foreach ($criticalFiles as $file => $desc) {
    if (file_exists(__DIR__ . '/' . $file)) {
        $size = filesize(__DIR__ . '/' . $file);
        $sizeKB = round($size / 1024, 2);
        echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'><strong>$file</strong> exists ($desc) - {$sizeKB}KB</span></div>";
    } else {
        $missingFiles[] = $file;
        echo "<div class='check-item error'><span class='icon'>❌</span><span class='message'><strong>$file</strong> missing ($desc)</span></div>";
    }
}

// ==================== CHECK 5: DIRECTORY PERMISSIONS ====================
echo "<h2>5. Directory Permissions</h2>";

$writableDirs = [
    'assets/uploads',
    'assets/uploads/avatars',
    'assets/logs',
    'assets/data'
];

foreach ($writableDirs as $dir) {
    $path = __DIR__ . '/' . $dir;
    if (is_dir($path)) {
        if (is_writable($path)) {
            echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'><strong>$dir/</strong> is writable</span></div>";
        } else {
            echo "<div class='check-item error'><span class='icon'>❌</span><span class='message'><strong>$dir/</strong> is NOT writable - chmod 755 or 777</span></div>";
        }
    } else {
        echo "<div class='check-item warning'><span class='icon'>⚠️</span><span class='message'><strong>$dir/</strong> does not exist - will be created on demand</span></div>";
    }
}

// ==================== CHECK 6: TEST/DEBUG FILES ====================
echo "<h2>6. Test & Debug Files (Should be removed)</h2>";

$testFiles = [];
$scanDirs = [__DIR__];
foreach ($scanDirs as $scanDir) {
    $files = glob($scanDir . '/*');
    foreach ($files as $file) {
        $basename = basename($file);
        if (preg_match('/(test|debug|tmp|temp|sample|example|backup|old).*\.php$/i', $basename) && is_file($file)) {
            $testFiles[] = str_replace(__DIR__ . '/', '', $file);
        }
    }
}

if (count($testFiles) > 0) {
    echo "<div class='check-item warning'><span class='icon'>⚠️</span><span class='message'><strong>Test/Debug files found:</strong></span></div>";
    echo "<div class='file-list'>";
    foreach ($testFiles as $testFile) {
        echo "- $testFile<br>";
    }
    echo "</div>";
} else {
    echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'>No test/debug files found in root</span></div>";
}

// ==================== CHECK 7: SESSION HANDLING ====================
echo "<h2>7. Session Configuration</h2>";

if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'>Session is active</span></div>";
    
    if (!empty($_SESSION['user_id']) || !empty($_SESSION['employee_id'])) {
        $userType = !empty($_SESSION['employee_id']) ? 'Employee' : 'Admin/User';
        $id = $_SESSION['employee_id'] ?? $_SESSION['user_id'];
        echo "<div class='check-item info'><span class='icon'>ℹ️</span><span class='message'>Logged in as <strong>$userType</strong> (ID: $id)</span></div>";
    } else {
        echo "<div class='check-item warning'><span class='icon'>⚠️</span><span class='message'>No active login session</span></div>";
    }
} else {
    echo "<div class='check-item error'><span class='icon'>❌</span><span class='message'>Session not started</span></div>";
}

// ==================== CHECK 8: ERROR LOGS ====================
echo "<h2>8. Error Logs</h2>";

$logFiles = [
    'assets/logs/ai_debug.log',
    'assets/logs/transfer_errors.log'
];

$totalLogSize = 0;
foreach ($logFiles as $logFile) {
    if (file_exists(__DIR__ . '/' . $logFile)) {
        $size = filesize(__DIR__ . '/' . $logFile);
        $totalLogSize += $size;
        $sizeKB = round($size / 1024, 2);
        
        if ($size > 1048576) { // > 1MB
            echo "<div class='check-item warning'><span class='icon'>⚠️</span><span class='message'><strong>$logFile</strong> is large ({$sizeKB}KB) - consider clearing</span></div>";
        } else {
            echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'><strong>$logFile</strong> exists ({$sizeKB}KB)</span></div>";
        }
        
        // Show last 3 lines if not empty
        if ($size > 0 && $size < 102400) { // Only if < 100KB
            $lines = file(__DIR__ . '/' . $logFile);
            $lastLines = array_slice($lines, -3);
            if (count($lastLines) > 0) {
                echo "<div class='check-item info'><span class='icon'>📄</span><span class='message'>Last entries:</span></div>";
                echo "<pre style='font-size: 11px; margin-left: 40px;'>" . htmlspecialchars(implode('', $lastLines)) . "</pre>";
            }
        }
    }
}

// ==================== CHECK 9: SECURITY CHECKS ====================
echo "<h2>9. Security Checks</h2>";

// Check for exposed config files
$exposedFiles = ['includes/db.php', 'config.php', '.env'];
foreach ($exposedFiles as $file) {
    if (file_exists(__DIR__ . '/' . $file)) {
        echo "<div class='check-item info'><span class='icon'>ℹ️</span><span class='message'><strong>$file</strong> exists - ensure .htaccess blocks direct access</span></div>";
    }
}

// Check for session security
if (ini_get('session.cookie_httponly') == 1) {
    echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'>Session cookies are HTTPOnly</span></div>";
} else {
    echo "<div class='check-item warning'><span class='icon'>⚠️</span><span class='message'>Session cookies NOT HTTPOnly - vulnerable to XSS</span></div>";
}

// ==================== CHECK 10: PERFORMANCE ====================
echo "<h2>10. Performance Metrics</h2>";

$memoryUsage = memory_get_usage(true);
$memoryMB = round($memoryUsage / 1024 / 1024, 2);
echo "<div class='check-item info'><span class='icon'>📊</span><span class='message'>Memory usage: <strong>{$memoryMB} MB</strong></span></div>";

$memoryLimit = ini_get('memory_limit');
echo "<div class='check-item info'><span class='icon'>📊</span><span class='message'>Memory limit: <strong>$memoryLimit</strong></span></div>";

// ==================== SUMMARY ====================
echo "<h2>Summary</h2>";

$stats = [
    'Total Tables' => count($existingTables ?? []),
    'Missing Files' => count($missingFiles),
    'Test Files' => count($testFiles),
    'Log Size' => round($totalLogSize / 1024, 2) . ' KB'
];

echo "<div class='stats'>";
foreach ($stats as $label => $value) {
    echo "<div class='stat-card'>";
    echo "<div class='stat-value'>$value</div>";
    echo "<div class='stat-label'>$label</div>";
    echo "</div>";
}
echo "</div>";

// Recommendations
echo "<h2>Recommendations</h2>";

if (count($testFiles) > 0) {
    echo "<div class='check-item warning'><span class='icon'>⚠️</span><span class='message'>Run <strong>cleanup_test_data.php</strong> to remove test files</span></div>";
}

if (!in_array('notifications', $existingTables ?? [])) {
    echo "<div class='check-item warning'><span class='icon'>⚠️</span><span class='message'>Run <strong>setup_notifications.php</strong> to create notification tables</span></div>";
}

if ($totalLogSize > 1048576) {
    echo "<div class='check-item warning'><span class='icon'>⚠️</span><span class='message'>Clear log files to free up space</span></div>";
}

echo "<div class='check-item success'><span class='icon'>✅</span><span class='message'>Overall: Application is functional</span></div>";

echo "
</div>
</body>
</html>
";
?>
