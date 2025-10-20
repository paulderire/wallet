<?php
/**
 * MY CASH - Database Backup Script
 * 
 * Features:
 * - Automated MySQL database backup
 * - Compression (GZIP)
 * - Backup rotation (keeps last 7 days by default)
 * - Can be run via browser or cron job
 * - Email notifications (optional)
 * 
 * CRON JOB SETUP (daily at 2 AM):
 * 0 2 * * * /usr/bin/php /path/to/backup_database.php >> /path/to/logs/backup.log 2>&1
 * 
 * Manual Access: http://localhost/MY CASH/backup_database.php
 */

// Configuration
define('BACKUP_DIR', __DIR__ . '/backups');
define('KEEP_BACKUPS', 7); // Number of backups to keep
define('ENABLE_EMAIL', false); // Set to true to enable email notifications
define('BACKUP_EMAIL', 'admin@mycash.local'); // Email for notifications

// Database credentials (read from db.php)
require_once __DIR__ . '/includes/db.php';

// Get database credentials from includes/db.php if available, otherwise fall back to safe defaults
$db_host = isset($host) ? $host : 'localhost';
$db_name = isset($dbname) ? $dbname : 'wallet_db';
$db_user = isset($username) ? $username : 'root';
$db_pass = isset($password) ? $password : '';

// Only allow CLI or authenticated admin access
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        die('⛔ Access denied. Please login as admin first.');
    }
    
    // Check if user is admin
    $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (empty($user['is_admin'])) {
        die('⛔ Access denied. Admin privileges required.');
    }
}

// Create backup directory if it doesn't exist
if (!is_dir(BACKUP_DIR)) {
    mkdir(BACKUP_DIR, 0755, true);
}

// Generate backup filename with timestamp
$timestamp = date('Y-m-d_H-i-s');
$backup_file = BACKUP_DIR . '/mycash_backup_' . $timestamp . '.sql';
$backup_file_gz = $backup_file . '.gz';

// Start output
if (!$is_cli) {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Backup - MY CASH</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            max-width: 700px;
            width: 100%;
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
        .log {
            background: #1e293b;
            color: #10b981;
            padding: 20px;
            border-radius: 12px;
            font-family: "Courier New", monospace;
            font-size: 14px;
            max-height: 400px;
            overflow-y: auto;
            margin: 20px 0;
        }
        .log div {
            margin: 5px 0;
        }
        .success { color: #10b981; }
        .error { color: #ef4444; }
        .warning { color: #f59e0b; }
        .info { color: #60a5fa; }
        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
            margin-top: 20px;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💾 Database Backup</h1>
        <p class="subtitle">Creating backup of MY CASH database</p>
        <div class="log">';
}

/**
 * Log message with timestamp
 */
function logMessage($message, $type = 'info') {
    global $is_cli;
    
    $timestamp = date('H:i:s');
    $colors = [
        'success' => $is_cli ? "\033[32m" : '',
        'error' => $is_cli ? "\033[31m" : '',
        'warning' => $is_cli ? "\033[33m" : '',
        'info' => $is_cli ? "\033[36m" : '',
    ];
    $reset = $is_cli ? "\033[0m" : '';
    
    $formatted_message = "[$timestamp] $message";
    
    if ($is_cli) {
        echo $colors[$type] . $formatted_message . $reset . PHP_EOL;
    } else {
        echo "<div class='$type'>$formatted_message</div>";
        flush();
        ob_flush();
    }
}

try {
    logMessage('Starting database backup...', 'info');
    logMessage('Database: ' . $db_name, 'info');
    
    // Check if mysqldump is available
    $mysqldump_path = null;
    
    // Common mysqldump locations
    $possible_paths = [
        'C:\\xampp\\mysql\\bin\\mysqldump.exe', // XAMPP Windows
        'C:\\wamp64\\bin\\mysql\\mysql8.0.27\\bin\\mysqldump.exe', // WAMP
        '/usr/bin/mysqldump', // Linux
        '/usr/local/bin/mysqldump', // macOS Homebrew
        '/Applications/XAMPP/bin/mysqldump', // XAMPP macOS
    ];
    
    foreach ($possible_paths as $path) {
        if (file_exists($path)) {
            $mysqldump_path = $path;
            break;
        }
    }
    
    // Try to find mysqldump in PATH
    if (!$mysqldump_path) {
        $test = $is_cli ? shell_exec('which mysqldump 2>/dev/null') : null;
        if ($test) {
            $mysqldump_path = trim($test);
        }
    }
    
    if (!$mysqldump_path) {
        // Fallback: try "mysqldump" directly
        $mysqldump_path = 'mysqldump';
        logMessage('Using "mysqldump" from PATH', 'warning');
    } else {
        logMessage('Found mysqldump: ' . $mysqldump_path, 'success');
    }
    
    // Helper to quote arguments correctly on Windows vs Unix-like systems
    $is_windows = stripos(PHP_OS, 'WIN') === 0;
    $quoteArg = function ($val) use ($is_windows) {
        if ($is_windows) {
            // Escape double quotes and wrap in double quotes for Windows
            return '"' . str_replace('"', '\\"', $val) . '"';
        }
        return escapeshellarg($val);
    };

    // Build mysqldump command parts
    $cmdParts = [];
    // Quote the mysqldump path (may contain spaces)
    $cmdParts[] = ($is_windows ? '"' . $mysqldump_path . '"' : escapeshellarg($mysqldump_path));
    $cmdParts[] = '--user=' . ($is_windows ? '"' . $db_user . '"' : escapeshellarg($db_user));

    // Only include password flag (with empty value if empty) to avoid interactive prompt
    $cmdParts[] = '--password=' . ($is_windows ? '"' . $db_pass . '"' : escapeshellarg($db_pass));
    $cmdParts[] = '--host=' . ($is_windows ? '"' . $db_host . '"' : escapeshellarg($db_host));
    $cmdParts[] = $is_windows ? '"' . $db_name . '"' : escapeshellarg($db_name);

    // Destination file (quote properly)
    $dest = $is_windows ? '"' . $backup_file . '"' : escapeshellarg($backup_file);

    // Join and add redirection for stdout/stderr
    $command = implode(' ', $cmdParts) . ' > ' . $dest . ' 2>&1';
    
    logMessage('Executing backup command...', 'info');
    
    // Execute backup
    exec($command, $output, $return_var);

    // Log the raw mysqldump output for debugging if anything goes wrong
    if (!empty($output)) {
        logMessage('mysqldump output:\n' . implode("\n", $output), 'info');
    }

    if ($return_var !== 0) {
        throw new Exception('Backup failed with exit code: ' . $return_var . "\n" . implode("\n", $output));
    }
    
    // Check if backup file was created
    if (!file_exists($backup_file) || filesize($backup_file) === 0) {
        throw new Exception('Backup file was not created or is empty');
    }
    
    $backup_size = filesize($backup_file);
    logMessage('Backup created: ' . round($backup_size / 1024, 2) . ' KB', 'success');
    
    // Compress backup with GZIP
    logMessage('Compressing backup...', 'info');
    
    $gz = gzopen($backup_file_gz, 'w9');
    if (!$gz) {
        throw new Exception('Failed to create compressed backup');
    }
    
    $fp = fopen($backup_file, 'rb');
    while (!feof($fp)) {
        gzwrite($gz, fread($fp, 1024 * 512));
    }
    fclose($fp);
    gzclose($gz);
    
    $compressed_size = filesize($backup_file_gz);
    $compression_ratio = round((1 - ($compressed_size / $backup_size)) * 100, 1);
    
    logMessage('Compressed: ' . round($compressed_size / 1024, 2) . ' KB (' . $compression_ratio . '% reduction)', 'success');
    
    // Delete uncompressed file
    unlink($backup_file);
    logMessage('Removed uncompressed file', 'info');
    
    // Cleanup old backups
    logMessage('Cleaning up old backups...', 'info');
    
    $backups = glob(BACKUP_DIR . '/mycash_backup_*.sql.gz');
    rsort($backups); // Sort newest first
    
    $deleted = 0;
    foreach (array_slice($backups, KEEP_BACKUPS) as $old_backup) {
        if (unlink($old_backup)) {
            $deleted++;
            logMessage('Deleted: ' . basename($old_backup), 'warning');
        }
    }
    
    if ($deleted > 0) {
        logMessage("Deleted $deleted old backup(s)", 'success');
    } else {
        logMessage('No old backups to delete', 'info');
    }
    
    // Summary
    logMessage('', 'info');
    logMessage('=== BACKUP COMPLETE ===', 'success');
    logMessage('File: ' . basename($backup_file_gz), 'success');
    logMessage('Size: ' . round($compressed_size / 1024, 2) . ' KB', 'success');
    logMessage('Location: ' . BACKUP_DIR, 'success');
    logMessage('Backups kept: ' . min(count($backups), KEEP_BACKUPS), 'success');
    
    // Send email notification if enabled
    if (ENABLE_EMAIL && !$is_cli) {
        $subject = 'MY CASH - Database Backup Successful';
        $message = "Database backup completed successfully.\n\n";
        $message .= "File: " . basename($backup_file_gz) . "\n";
        $message .= "Size: " . round($compressed_size / 1024, 2) . " KB\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        
        if (mail(BACKUP_EMAIL, $subject, $message)) {
            logMessage('Email notification sent', 'success');
        }
    }
    
} catch (Exception $e) {
    logMessage('ERROR: ' . $e->getMessage(), 'error');
    
    // Send error email if enabled
    if (ENABLE_EMAIL && !$is_cli) {
        $subject = 'MY CASH - Database Backup FAILED';
        $message = "Database backup failed!\n\n";
        $message .= "Error: " . $e->getMessage() . "\n";
        $message .= "Time: " . date('Y-m-d H:i:s') . "\n";
        
        mail(BACKUP_EMAIL, $subject, $message);
    }
    
    exit(1);
}

if (!$is_cli) {
    echo '</div>
        <a href="pages/dashboard.php" class="btn">🏠 Back to Dashboard</a>
    </div>
</body>
</html>';
}
?>