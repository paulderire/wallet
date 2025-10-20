<?php
/**
 * Cleanup Unnecessary Files - MY CASH Application
 * Removes backup files, duplicate pages, and unused utilities
 */

// Statistics
$stats = [
    'backups_removed' => 0,
    'setup_scripts_removed' => 0,
    'debug_scripts_removed' => 0,
    'docs_removed' => 0,
    'total_removed' => 0,
    'files' => []
];

// Files to remove
$filesToRemove = [
    // Backup files in pages/
    'pages/accounts.php.backup',
    'pages/budgets.php.backup',
    'pages/dashboard.php.backup',
    'pages/goals.php.backup',
    'pages/reports.php.backup',
    
    // Debug/diagnostic scripts (keep if needed, comment out if you want to keep them)
    'debug_business_account.php',
    'debug_forex_account.php',
    'check_accounts_table.php',
    'check_data.php',
    'check_forex_table.php',
    'check_tables.php',
    
    // Setup scripts (only needed once)
    'setup_stationery_db.php',
    'setup_forex_system.php',
    'setup_employee_payments.php',
    
    // Database fix scripts (completed tasks)
    'fix_transactions_table.php',
    'backfill_business_account.php',
    'fix_database.php',
    
    // Upgrade scripts (completed)
    'upgrade_forex_trades.php',
    'upgrade_forex_complete.php',
    
    // Test data generator (only for development)
    'generate_test_data.php',
    
    // Old employee backup
    'employee/dashboard_old_backup.php',
    
    // Excessive documentation (keep main ones)
    'ADD_EMPLOYEE_DATABASE_FIX.md',
    'ADD_EMPLOYEE_FIX.md',
    'BUSINESS_ACCOUNT_AUTO_ALLOCATION.md',
    'CHECKUP_SUMMARY.md',
    'EMPLOYEE_DARK_MODE.md',
    'EMPLOYEE_INVENTORY_GUIDE.md',
    'EMPLOYEE_PASSWORD_FIX.md',
    'FINANCIAL_DASHBOARD_FIXES.md',
    'FIXES_APPLIED.md',
    'FOREX_SQL_LIBRARY.md',
    'INVENTORY_AUTO_DEDUCTION_COMPLETE.md',
    'LOGIN_FLOW_DIAGRAM.txt',
    'PRODUCT_MANAGEMENT_COMPLETE.md',
    'PROJECT_COMPLETION.md',
    'QUICK_FIX_GUIDE.md',
    'README_IMPLEMENTATION_COMPLETE.md',
    'STATIONERY_SYSTEM_SETUP.md',
    'SYSTEM_HEALTH_REPORT.md',
    'UNIFIED_LOGIN_GUIDE.md',
    'USD_IMPLEMENTATION_SUMMARY.md',
];

// Start HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cleanup Unnecessary Files - MY CASH</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            min-height: 100vh; 
            padding: 40px 20px; 
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white; 
            border-radius: 16px; 
            box-shadow: 0 20px 60px rgba(0,0,0,0.3); 
            overflow: hidden; 
        }
        .header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 30px 40px; 
        }
        .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }
        .header p { opacity: 0.9; font-size: 15px; }
        .content { padding: 40px; }
        .warning { 
            background: #fff3cd; 
            border-left: 4px solid #ffc107; 
            color: #856404; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 30px; 
        }
        .warning strong { display: block; margin-bottom: 10px; font-size: 16px; }
        .category { margin-bottom: 30px; }
        .category h2 { 
            font-size: 18px; 
            font-weight: 600; 
            color: #333; 
            margin-bottom: 15px; 
            padding-bottom: 10px; 
            border-bottom: 2px solid #667eea; 
        }
        .file-list { background: #f8f9fa; border-radius: 8px; padding: 15px; }
        .file-item { 
            padding: 12px; 
            background: white; 
            border-radius: 6px; 
            margin-bottom: 8px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .file-path { font-family: 'Courier New', monospace; font-size: 13px; color: #333; }
        .badge { 
            padding: 4px 12px; 
            border-radius: 12px; 
            font-size: 12px; 
            font-weight: 600; 
        }
        .badge.removed { background: #28a745; color: white; }
        .badge.kept { background: #6c757d; color: white; }
        .badge.error { background: #dc3545; color: white; }
        .stats { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); 
            gap: 20px; 
            margin-bottom: 30px; 
        }
        .stat-card { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            padding: 20px; 
            border-radius: 12px; 
            text-align: center; 
        }
        .stat-card .number { font-size: 32px; font-weight: 700; margin-bottom: 8px; }
        .stat-card .label { opacity: 0.9; font-size: 14px; }
        .success { 
            background: #d4edda; 
            border-left: 4px solid #28a745; 
            color: #155724; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 20px; 
        }
        .btn { 
            display: inline-block; 
            padding: 12px 24px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
            text-decoration: none; 
            border-radius: 8px; 
            font-weight: 600; 
            margin-top: 20px; 
            transition: transform 0.2s; 
        }
        .btn:hover { transform: translateY(-2px); }
        .form-section { 
            background: #f8f9fa; 
            padding: 20px; 
            border-radius: 8px; 
            margin-bottom: 30px; 
        }
        .checkbox-group { margin: 10px 0; }
        .checkbox-group label { 
            display: flex; 
            align-items: center; 
            padding: 8px; 
            cursor: pointer; 
        }
        .checkbox-group input[type="checkbox"] { 
            margin-right: 10px; 
            width: 18px; 
            height: 18px; 
        }
        .action-buttons { margin-top: 20px; }
        button { 
            padding: 12px 24px; 
            background: #dc3545; 
            color: white; 
            border: none; 
            border-radius: 8px; 
            font-weight: 600; 
            cursor: pointer; 
            margin-right: 10px; 
        }
        button:hover { background: #c82333; }
        button.secondary { background: #6c757d; }
        button.secondary:hover { background: #5a6268; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🧹 Cleanup Unnecessary Files</h1>
            <p>Remove backup files, debug scripts, and duplicate documentation</p>
        </div>
        <div class="content">
            
            <div class="warning">
                <strong>⚠️ Warning</strong>
                This script will permanently delete files from your system. 
                Review the list carefully before proceeding. 
                Some files like debug scripts might be useful for troubleshooting.
            </div>

            <form method="post" action="">
                <div class="form-section">
                    <h3 style="margin-bottom: 15px;">Select Categories to Clean</h3>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="clean_backups" value="1" checked>
                            <strong>Backup Files</strong> (5 files) - Old .backup files in pages/
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="clean_debug" value="1">
                            <strong>Debug Scripts</strong> (6 files) - Diagnostic tools (keep if troubleshooting)
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="clean_setup" value="1" checked>
                            <strong>Setup Scripts</strong> (3 files) - One-time setup files
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="clean_fixes" value="1" checked>
                            <strong>Fix Scripts</strong> (4 files) - Completed database fixes
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="clean_upgrades" value="1" checked>
                            <strong>Upgrade Scripts</strong> (2 files) - Completed migrations
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="clean_docs" value="1">
                            <strong>Old Documentation</strong> (18 files) - Keep main docs only
                        </label>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" name="clean_test" value="1">
                            <strong>Test Data Generator</strong> (1 file) - Development only
                        </label>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" name="action" value="preview">Preview Files</button>
                        <button type="submit" name="action" value="delete" style="background: #dc3545;">Delete Selected</button>
                        <button type="button" class="secondary" onclick="window.location.href='/MY CASH/pages/dashboard.php'">Cancel</button>
                    </div>
                </div>
            </form>

            <?php
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $action = $_POST['action'] ?? 'preview';
                
                // Categorize files
                $categories = [
                    'backups' => [
                        'pages/accounts.php.backup',
                        'pages/budgets.php.backup',
                        'pages/dashboard.php.backup',
                        'pages/goals.php.backup',
                        'pages/reports.php.backup',
                    ],
                    'debug' => [
                        'debug_business_account.php',
                        'debug_forex_account.php',
                        'check_accounts_table.php',
                        'check_data.php',
                        'check_forex_table.php',
                        'check_tables.php',
                    ],
                    'setup' => [
                        'setup_stationery_db.php',
                        'setup_forex_system.php',
                        'setup_employee_payments.php',
                    ],
                    'fixes' => [
                        'fix_transactions_table.php',
                        'backfill_business_account.php',
                        'fix_database.php',
                        'employee/dashboard_old_backup.php',
                    ],
                    'upgrades' => [
                        'upgrade_forex_trades.php',
                        'upgrade_forex_complete.php',
                    ],
                    'docs' => [
                        'ADD_EMPLOYEE_DATABASE_FIX.md',
                        'ADD_EMPLOYEE_FIX.md',
                        'BUSINESS_ACCOUNT_AUTO_ALLOCATION.md',
                        'CHECKUP_SUMMARY.md',
                        'EMPLOYEE_DARK_MODE.md',
                        'EMPLOYEE_INVENTORY_GUIDE.md',
                        'EMPLOYEE_PASSWORD_FIX.md',
                        'FINANCIAL_DASHBOARD_FIXES.md',
                        'FIXES_APPLIED.md',
                        'FOREX_SQL_LIBRARY.md',
                        'INVENTORY_AUTO_DEDUCTION_COMPLETE.md',
                        'LOGIN_FLOW_DIAGRAM.txt',
                        'PRODUCT_MANAGEMENT_COMPLETE.md',
                        'PROJECT_COMPLETION.md',
                        'QUICK_FIX_GUIDE.md',
                        'README_IMPLEMENTATION_COMPLETE.md',
                        'STATIONERY_SYSTEM_SETUP.md',
                        'SYSTEM_HEALTH_REPORT.md',
                        'UNIFIED_LOGIN_GUIDE.md',
                        'USD_IMPLEMENTATION_SUMMARY.md',
                    ],
                    'test' => [
                        'generate_test_data.php',
                    ],
                ];
                
                // Build list of files to process
                $selectedFiles = [];
                foreach ($categories as $cat => $files) {
                    if (isset($_POST["clean_$cat"])) {
                        $selectedFiles = array_merge($selectedFiles, $files);
                    }
                }
                
                if (empty($selectedFiles)) {
                    echo '<div class="warning"><strong>No categories selected!</strong> Please select at least one category to clean.</div>';
                } else {
                    if ($action === 'delete') {
                        echo '<div class="category"><h2>🗑️ Deletion Results</h2><div class="file-list">';
                        
                        foreach ($selectedFiles as $file) {
                            $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $file;
                            $status = 'kept';
                            $message = 'Not found';
                            
                            if (file_exists($fullPath)) {
                                if (unlink($fullPath)) {
                                    $status = 'removed';
                                    $message = 'Deleted';
                                    $stats['total_removed']++;
                                } else {
                                    $status = 'error';
                                    $message = 'Error deleting';
                                }
                            }
                            
                            echo "<div class='file-item'>";
                            echo "<span class='file-path'>$file</span>";
                            echo "<span class='badge $status'>$message</span>";
                            echo "</div>";
                        }
                        
                        echo '</div></div>';
                        
                        echo '<div class="success"><strong>✓ Cleanup Complete!</strong><br>';
                        echo "Removed {$stats['total_removed']} files from your system.</div>";
                        
                    } else {
                        // Preview mode
                        echo '<div class="category"><h2>👁️ Preview: Files to be Deleted (' . count($selectedFiles) . ')</h2>';
                        echo '<div class="file-list">';
                        
                        foreach ($selectedFiles as $file) {
                            $fullPath = __DIR__ . DIRECTORY_SEPARATOR . $file;
                            $exists = file_exists($fullPath);
                            $size = $exists ? filesize($fullPath) : 0;
                            $sizeText = $exists ? number_format($size / 1024, 2) . ' KB' : 'Not found';
                            
                            echo "<div class='file-item'>";
                            echo "<span class='file-path'>$file</span>";
                            echo "<span class='badge " . ($exists ? 'removed' : 'kept') . "'>$sizeText</span>";
                            echo "</div>";
                        }
                        
                        echo '</div></div>';
                        
                        echo '<div class="warning"><strong>Preview Mode</strong><br>';
                        echo 'Click "Delete Selected" to permanently remove these files.</div>';
                    }
                }
            }
            ?>

            <div class="category">
                <h2>📋 Recommended Files to Keep</h2>
                <ul style="margin-left: 20px; line-height: 1.8;">
                    <li><strong>fix_links.php</strong> - Useful for future link issues</li>
                    <li><strong>LINKS_FIXED.md</strong> - Recent fix documentation</li>
                    <li><strong>FIX_SUMMARY.md</strong> - Quick reference guide</li>
                    <li><strong>All main documentation</strong> - Core system guides</li>
                    <li><strong>Debug scripts</strong> - Keep if actively troubleshooting</li>
                </ul>
            </div>

            <a href="/MY CASH/pages/dashboard.php" class="btn">🏠 Back to Dashboard</a>
        </div>
    </div>
</body>
</html>
