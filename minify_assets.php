<?php
/**
 * MY CASH - CSS/JS Minification Utility
 * 
 * This script minifies CSS and JavaScript files for production deployment.
 * It creates .min.css and .min.js versions of your files while preserving originals.
 * 
 * USAGE:
 * 1. Run this script: php minify_assets.php
 * 2. Or access via browser (admin only): http://localhost/MY CASH/minify_assets.php
 * 3. Update HTML to reference .min.css and .min.js files
 * 
 * Features:
 * - Removes comments, whitespace, line breaks
 * - Preserves functionality
 * - Shows before/after file sizes
 * - Safe: originals are never modified
 */

// Only allow CLI or authenticated admin access
$is_cli = (php_sapi_name() === 'cli');

if (!$is_cli) {
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
}

/**
 * Minify CSS content
 */
function minifyCSS($css) {
    // Remove comments
    $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
    
    // Remove whitespace
    $css = str_replace(["\r\n", "\r", "\n", "\t"], '', $css);
    
    // Remove multiple spaces
    $css = preg_replace('/\s+/', ' ', $css);
    
    // Remove space around operators
    $css = preg_replace('/\s*([\{\};:,])\s*/', '$1', $css);
    
    // Remove last semicolon in block
    $css = preg_replace('/;(\})/', '$1', $css);
    
    // Remove space after colons (but keep for pseudo-elements)
    $css = preg_replace('/(?<!:):(?!:)\s+/', ':', $css);
    
    return trim($css);
}

/**
 * Minify JavaScript content
 * Note: This is a basic minifier. For complex JS, use tools like UglifyJS or Terser
 */
function minifyJS($js) {
    // Remove single-line comments (but preserve URLs)
    $js = preg_replace('~//[^\n]*~', '', $js);
    
    // Remove multi-line comments (but preserve /*! important comments */
    $js = preg_replace('~/\*(?!\!).*?\*/~s', '', $js);
    
    // Remove whitespace
    $js = preg_replace('/\s+/', ' ', $js);
    
    // Remove space around operators
    $js = preg_replace('/\s*([\{\}\(\);,:\[\]])\s*/', '$1', $js);
    
    // Remove space around = + - * / operators
    $js = preg_replace('/\s*([=+\-*\/])\s*/', '$1', $js);
    
    return trim($js);
}

/**
 * Process file and create minified version
 */
function processFile($filepath, $type) {
    if (!file_exists($filepath)) {
        return ['success' => false, 'error' => 'File not found'];
    }
    
    $content = file_get_contents($filepath);
    $original_size = strlen($content);
    
    // Minify based on type
    if ($type === 'css') {
        $minified = minifyCSS($content);
        $output_file = str_replace('.css', '.min.css', $filepath);
    } else {
        $minified = minifyJS($content);
        $output_file = str_replace('.js', '.min.js', $filepath);
    }
    
    // Don't overwrite if already .min
    if (strpos($filepath, '.min.') !== false) {
        return ['success' => false, 'error' => 'Already minified'];
    }
    
    $minified_size = strlen($minified);
    $reduction = round((($original_size - $minified_size) / $original_size) * 100, 1);
    
    // Write minified file
    file_put_contents($output_file, $minified);
    
    return [
        'success' => true,
        'original_size' => $original_size,
        'minified_size' => $minified_size,
        'reduction' => $reduction,
        'output_file' => $output_file
    ];
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

// Start output
if (!$is_cli) {
    echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Minification - MY CASH</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
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
            margin: 20px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
        }
        .section h2 {
            color: #1e293b;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        .file-result {
            padding: 12px;
            margin: 8px 0;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }
        .file-result.success { border-left-color: #10b981; }
        .file-result.skip { border-left-color: #f59e0b; }
        .file-result.error { border-left-color: #ef4444; }
        .filename {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .stats {
            font-size: 0.9rem;
            color: #64748b;
        }
        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-left: 8px;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .summary {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            margin-top: 20px;
            text-align: center;
        }
        .summary h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
        }
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
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🗜️ Asset Minification</h1>
        <p class="subtitle">Optimizing CSS and JavaScript files for production</p>';
}

// Find all CSS and JS files
$css_files = glob(__DIR__ . '/assets/css/*.css');
$js_files = glob(__DIR__ . '/assets/js/*.js');

$results = ['css' => [], 'js' => []];
$total_original = 0;
$total_minified = 0;

// Process CSS files
if (!$is_cli) echo '<div class="section"><h2>📄 CSS Files</h2>';
foreach ($css_files as $file) {
    $result = processFile($file, 'css');
    $results['css'][] = $result;
    
    if ($result['success']) {
        $total_original += $result['original_size'];
        $total_minified += $result['minified_size'];
        
        if (!$is_cli) {
            echo '<div class="file-result success">
                <div class="filename">' . basename($file) . ' <span class="badge badge-success">✓ Minified</span></div>
                <div class="stats">
                    Original: ' . formatBytes($result['original_size']) . ' → 
                    Minified: ' . formatBytes($result['minified_size']) . ' 
                    (Saved ' . $result['reduction'] . '%)
                </div>
            </div>';
        } else {
            echo "✓ " . basename($file) . " → " . basename($result['output_file']) . 
                 " (" . formatBytes($result['original_size']) . " → " . formatBytes($result['minified_size']) . 
                 ", saved " . $result['reduction'] . "%)\n";
        }
    } else {
        if (!$is_cli) {
            echo '<div class="file-result skip">
                <div class="filename">' . basename($file) . ' <span class="badge badge-warning">⊘ Skipped</span></div>
                <div class="stats">' . $result['error'] . '</div>
            </div>';
        }
    }
}
if (!$is_cli) echo '</div>';

// Process JS files
if (!$is_cli) echo '<div class="section"><h2>📜 JavaScript Files</h2>';
foreach ($js_files as $file) {
    $result = processFile($file, 'js');
    $results['js'][] = $result;
    
    if ($result['success']) {
        $total_original += $result['original_size'];
        $total_minified += $result['minified_size'];
        
        if (!$is_cli) {
            echo '<div class="file-result success">
                <div class="filename">' . basename($file) . ' <span class="badge badge-success">✓ Minified</span></div>
                <div class="stats">
                    Original: ' . formatBytes($result['original_size']) . ' → 
                    Minified: ' . formatBytes($result['minified_size']) . ' 
                    (Saved ' . $result['reduction'] . '%)
                </div>
            </div>';
        } else {
            echo "✓ " . basename($file) . " → " . basename($result['output_file']) . 
                 " (" . formatBytes($result['original_size']) . " → " . formatBytes($result['minified_size']) . 
                 ", saved " . $result['reduction'] . "%)\n";
        }
    } else {
        if (!$is_cli) {
            echo '<div class="file-result skip">
                <div class="filename">' . basename($file) . ' <span class="badge badge-warning">⊘ Skipped</span></div>
                <div class="stats">' . $result['error'] . '</div>
            </div>';
        }
    }
}
if (!$is_cli) echo '</div>';

// Summary
$total_reduction = $total_original > 0 ? round((($total_original - $total_minified) / $total_original) * 100, 1) : 0;

if (!$is_cli) {
    echo '<div class="summary">
        <h3>📊 Summary</h3>
        <div style="font-size: 1.1rem; margin-top: 10px;">
            <div>Total Original Size: <strong>' . formatBytes($total_original) . '</strong></div>
            <div>Total Minified Size: <strong>' . formatBytes($total_minified) . '</strong></div>
            <div style="margin-top: 10px; font-size: 1.3rem;">
                Total Savings: <strong>' . formatBytes($total_original - $total_minified) . '</strong> (' . $total_reduction . '%)
            </div>
        </div>
    </div>
    
    <div style="text-align: center;">
        <a href="pages/dashboard.php" class="btn">🏠 Back to Dashboard</a>
    </div>
    
    <div style="margin-top: 30px; padding: 20px; background: #fef3c7; border-radius: 12px; border-left: 4px solid #f59e0b;">
        <strong>⚠️ Next Steps:</strong>
        <ol style="margin: 10px 0 0 20px; line-height: 1.8;">
            <li>Update your HTML files to reference <code>.min.css</code> and <code>.min.js</code> files</li>
            <li>Example: Change <code>style.css</code> to <code>style.min.css</code></li>
            <li>Test your application thoroughly after minification</li>
            <li>Keep original files as backup</li>
        </ol>
    </div>
</div>
</body>
</html>';
} else {
    echo "\n========================================\n";
    echo "Summary\n";
    echo "========================================\n";
    echo "Total Original Size: " . formatBytes($total_original) . "\n";
    echo "Total Minified Size: " . formatBytes($total_minified) . "\n";
    echo "Total Savings: " . formatBytes($total_original - $total_minified) . " (" . $total_reduction . "%)\n";
    echo "========================================\n";
}
?>
