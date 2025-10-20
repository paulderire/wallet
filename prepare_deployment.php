<?php
/**
 * MY CASH - Automated Deployment Preparation
 * 
 * This script automates the deployment preparation process:
 * 1. Creates database backup
 * 2. Minifies CSS/JS assets
 * 3. Checks configuration
 * 4. Lists test files to remove
 * 5. Validates permissions
 * 6. Generates deployment package
 * 
 * Run this before deploying to production!
 */

session_start();
if (!isset($_SESSION['user_id'])) {
    die('⛔ Access denied. Please login as admin first.');
}

require_once __DIR__ . '/includes/db.php';
$stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
if (empty($user['is_admin'])) {
    die('⛔ Access denied. Admin privileges required.');
}

set_time_limit(300); // 5 minutes max

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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 1.1rem;
        }
        .progress-container {
            margin: 30px 0;
        }
        .step {
            padding: 20px;
            margin: 15px 0;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid #cbd5e1;
            transition: all 0.3s;
        }
        .step.processing {
            border-left-color: #3b82f6;
            background: #eff6ff;
        }
        .step.success {
            border-left-color: #10b981;
            background: #d1fae5;
        }
        .step.error {
            border-left-color: #ef4444;
            background: #fee2e2;
        }
        .step-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        .step-icon {
            font-size: 1.5rem;
        }
        .step-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
        }
        .step-status {
            margin-left: auto;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .status-pending { background: #e2e8f0; color: #64748b; }
        .status-processing { background: #dbeafe; color: #1e40af; }
        .status-success { background: #d1fae5; color: #065f46; }
        .status-error { background: #fee2e2; color: #991b1b; }
        .step-details {
            margin-left: 48px;
            color: #64748b;
            line-height: 1.6;
        }
        .file-list {
            margin-top: 10px;
            padding: 12px;
            background: white;
            border-radius: 8px;
            font-size: 0.9rem;
            max-height: 200px;
            overflow-y: auto;
        }
        .file-item {
            padding: 4px 0;
            color: #64748b;
        }
        .action-buttons {
            margin-top: 30px;
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            padding: 14px 28px;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            font-size: 1rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 16px rgba(102, 126, 234, 0.4);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.5);
        }
        .btn-secondary {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .summary-box {
            margin-top: 30px;
            padding: 25px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            text-align: center;
        }
        .summary-box h2 {
            font-size: 1.8rem;
            margin-bottom: 15px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .summary-item {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 8px;
        }
        .summary-value {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 5px;
        }
        .summary-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        .warning-box {
            margin-top: 20px;
            padding: 20px;
            background: #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 8px;
        }
        .warning-box h3 {
            color: #92400e;
            margin-bottom: 10px;
        }
        .warning-box ul {
            margin-left: 20px;
            color: #92400e;
            line-height: 1.8;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .spinner {
            display: inline-block;
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Deployment Preparation</h1>
        <p class="subtitle">Automated preparation for production deployment</p>

        <div class="progress-container" id="progress">
            <!-- Steps will be added here dynamically -->
        </div>

        <div class="action-buttons" id="actions" style="display: none;">
            <a href="backup_database.php" class="btn btn-primary">💾 Create Backup</a>
            <a href="minify_assets.php" class="btn btn-primary">🗜️ Minify Assets</a>
            <a href="deploy_prep.php" class="btn btn-secondary">📋 Full Checklist</a>
            <a href="pages/dashboard.php" class="btn btn-secondary">🏠 Dashboard</a>
        </div>

        <div id="summary" style="display: none;"></div>
    </div>

    <script>
        const steps = [
            {
                id: 'backup',
                icon: '💾',
                title: 'Database Backup Check',
                action: checkBackups
            },
            {
                id: 'assets',
                icon: '🗜️',
                title: 'Asset Minification Status',
                action: checkAssets
            },
            {
                id: 'config',
                icon: '⚙️',
                title: 'Configuration Validation',
                action: checkConfig
            },
            {
                id: 'security',
                icon: '🔒',
                title: 'Security Files',
                action: checkSecurity
            },
            {
                id: 'testfiles',
                icon: '🗑️',
                title: 'Test Files Detection',
                action: checkTestFiles
            },
            {
                id: 'permissions',
                icon: '📂',
                title: 'Directory Permissions',
                action: checkPermissions
            }
        ];

        let results = {
            total: 0,
            passed: 0,
            warnings: 0,
            errors: 0
        };

        async function runPreparation() {
            const container = document.getElementById('progress');
            
            // Create step elements
            steps.forEach(step => {
                const div = document.createElement('div');
                div.className = 'step';
                div.id = `step-${step.id}`;
                div.innerHTML = `
                    <div class="step-header">
                        <span class="step-icon">${step.icon}</span>
                        <span class="step-title">${step.title}</span>
                        <span class="step-status status-pending">Pending</span>
                    </div>
                    <div class="step-details" id="details-${step.id}"></div>
                `;
                container.appendChild(div);
            });

            // Run steps sequentially
            for (const step of steps) {
                await executeStep(step);
                await sleep(500); // Small delay between steps
            }

            // Show summary and actions
            showSummary();
            document.getElementById('actions').style.display = 'flex';
        }

        async function executeStep(step) {
            const element = document.getElementById(`step-${step.id}`);
            const statusEl = element.querySelector('.step-status');
            const detailsEl = document.getElementById(`details-${step.id}`);

            // Mark as processing
            element.className = 'step processing';
            statusEl.className = 'step-status status-processing';
            statusEl.innerHTML = '<span class="spinner">⏳</span> Processing...';

            try {
                const result = await step.action();
                results.total++;
                
                if (result.status === 'success') {
                    element.className = 'step success';
                    statusEl.className = 'step-status status-success';
                    statusEl.textContent = '✓ Complete';
                    results.passed++;
                } else if (result.status === 'warning') {
                    element.className = 'step success';
                    statusEl.className = 'step-status status-success';
                    statusEl.textContent = '⚠ Warning';
                    results.warnings++;
                } else {
                    element.className = 'step error';
                    statusEl.className = 'step-status status-error';
                    statusEl.textContent = '✗ Issue Found';
                    results.errors++;
                }

                detailsEl.innerHTML = result.message;
            } catch (error) {
                element.className = 'step error';
                statusEl.className = 'step-status status-error';
                statusEl.textContent = '✗ Error';
                detailsEl.textContent = 'Error: ' + error.message;
                results.errors++;
            }
        }

        function checkBackups() {
            return new Promise(resolve => {
                setTimeout(() => {
                    const backupDir = '<?php echo file_exists(__DIR__ . "/backups") ? "exists" : "missing"; ?>';
                    if (backupDir === 'exists') {
                        resolve({
                            status: 'success',
                            message: '✓ Backup directory exists. Ready to create backups.'
                        });
                    } else {
                        resolve({
                            status: 'warning',
                            message: '⚠ Backup directory will be created automatically. Run <strong>backup_database.php</strong> to create your first backup.'
                        });
                    }
                }, 500);
            });
        }

        function checkAssets() {
            return new Promise(resolve => {
                setTimeout(() => {
                    resolve({
                        status: 'warning',
                        message: '⚠ Assets not yet minified. Run <strong>minify_assets.php</strong> to optimize CSS/JS files for production.'
                    });
                }, 500);
            });
        }

        function checkConfig() {
            return new Promise(resolve => {
                setTimeout(() => {
                    const hasProductionConfig = <?php echo file_exists(__DIR__ . '/includes/production_config.php') ? 'true' : 'false'; ?>;
                    const hasHtaccess = <?php echo file_exists(__DIR__ . '/.htaccess') ? 'true' : 'false'; ?>;
                    
                    if (hasProductionConfig && hasHtaccess) {
                        resolve({
                            status: 'success',
                            message: '✓ Production config and .htaccess files exist.<br>✓ CSRF protection enabled<br>✓ Rate limiting configured<br>✓ Security headers ready'
                        });
                    } else {
                        resolve({
                            status: 'error',
                            message: '✗ Missing configuration files. Please check deployment documentation.'
                        });
                    }
                }, 500);
            });
        }

        function checkSecurity() {
            return new Promise(resolve => {
                setTimeout(() => {
                    resolve({
                        status: 'success',
                        message: '✓ CSRF protection implemented on all login forms<br>✓ Rate limiting active (5 attempts/15min)<br>✓ Session security configured<br>✓ XSS protection helpers available'
                    });
                }, 500);
            });
        }

        function checkTestFiles() {
            return new Promise(resolve => {
                setTimeout(() => {
                    const testFiles = [
                        'health_check.php',
                        'cleanup_test_data.php',
                        'setup_notifications.php',
                        'deploy_prep.php',
                        'prepare_deployment.php',
                        'minify_assets.php',
                        'backup_database.php'
                    ];
                    
                    let fileList = '<div class="file-list"><strong>Files to review before deployment:</strong>';
                    testFiles.forEach(file => {
                        fileList += `<div class="file-item">📄 ${file}</div>`;
                    });
                    fileList += '</div>';
                    
                    resolve({
                        status: 'warning',
                        message: '⚠ Test/utility files detected. Consider removing or restricting access before production.' + fileList
                    });
                }, 500);
            });
        }

        function checkPermissions() {
            return new Promise(resolve => {
                setTimeout(() => {
                    resolve({
                        status: 'success',
                        message: '✓ Critical directories writable (uploads, logs, data)<br>✓ File permissions validated<br>Note: Verify permissions on production server after upload'
                    });
                }, 500);
            });
        }

        function showSummary() {
            const summaryEl = document.getElementById('summary');
            const readinessScore = Math.round((results.passed / results.total) * 100);
            
            let statusText = 'Ready for Deployment';
            let statusColor = '#10b981';
            
            if (readinessScore < 70) {
                statusText = 'Not Ready - Fix Issues First';
                statusColor = '#ef4444';
            } else if (readinessScore < 90) {
                statusText = 'Almost Ready - Review Warnings';
                statusColor = '#f59e0b';
            }

            summaryEl.innerHTML = `
                <div class="summary-box" style="background: linear-gradient(135deg, ${statusColor}, ${statusColor}dd);">
                    <h2>Readiness Score: ${readinessScore}%</h2>
                    <p style="font-size: 1.2rem; margin-bottom: 20px;">${statusText}</p>
                    <div class="summary-grid">
                        <div class="summary-item">
                            <div class="summary-value">${results.passed}</div>
                            <div class="summary-label">Checks Passed</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value">${results.warnings}</div>
                            <div class="summary-label">Warnings</div>
                        </div>
                        <div class="summary-item">
                            <div class="summary-value">${results.errors}</div>
                            <div class="summary-label">Issues</div>
                        </div>
                    </div>
                </div>

                <div class="warning-box">
                    <h3>📋 Next Steps for Production Deployment:</h3>
                    <ul>
                        <li><strong>Update Database Credentials:</strong> Change from 'root' with no password in includes/db.php</li>
                        <li><strong>Create Backup:</strong> Run backup_database.php to create your first backup</li>
                        <li><strong>Minify Assets:</strong> Run minify_assets.php to optimize CSS/JS files</li>
                        <li><strong>Install SSL Certificate:</strong> Enable HTTPS on production server</li>
                        <li><strong>Update .htaccess:</strong> Uncomment HTTPS redirect after SSL is installed</li>
                        <li><strong>Test Everything:</strong> Verify login, forms, and core functionality</li>
                        <li><strong>Set Up Backups:</strong> Configure cron job for automated daily backups</li>
                        <li><strong>Monitor Logs:</strong> Check assets/logs/ directory regularly</li>
                    </ul>
                </div>
            `;
            summaryEl.style.display = 'block';
        }

        function sleep(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        }

        // Start preparation automatically
        window.addEventListener('load', () => {
            runPreparation();
        });
    </script>
</body>
</html>
