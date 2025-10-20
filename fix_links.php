<?php
/**
 * Fix all linking problems in MY CASH application
 * Replaces /MY CASH/ with /MY CASH/ throughout all PHP files
 */

// Configuration
$rootDir = __DIR__;
$searchPattern = '/MY CASH/';
$replaceWith = '/MY CASH/';
$fileExtensions = ['php'];

// Statistics
$stats = [
    'files_scanned' => 0,
    'files_modified' => 0,
    'total_replacements' => 0,
    'errors' => []
];

/**
 * Recursively scan directory for PHP files
 */
function scanDirectory($dir, $extensions) {
    $files = [];
    if (!is_dir($dir)) return $files;
    
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($path)) {
            // Recursively scan subdirectories
            $files = array_merge($files, scanDirectory($path, $extensions));
        } elseif (is_file($path)) {
            $ext = pathinfo($path, PATHINFO_EXTENSION);
            if (in_array(strtolower($ext), $extensions)) {
                $files[] = $path;
            }
        }
    }
    
    return $files;
}

/**
 * Fix links in a file
 */
function fixLinksInFile($filePath, $search, $replace) {
    global $stats;
    
    try {
        $content = file_get_contents($filePath);
        if ($content === false) {
            $stats['errors'][] = "Could not read: $filePath";
            return false;
        }
        
        // Count occurrences
        $count = substr_count($content, $search);
        
        if ($count > 0) {
            // Replace
            $newContent = str_replace($search, $replace, $content);
            
            // Write back
            if (file_put_contents($filePath, $newContent) === false) {
                $stats['errors'][] = "Could not write: $filePath";
                return false;
            }
            
            $stats['files_modified']++;
            $stats['total_replacements'] += $count;
            
            return [
                'file' => str_replace($GLOBALS['rootDir'] . DIRECTORY_SEPARATOR, '', $filePath),
                'replacements' => $count
            ];
        }
        
        return false;
    } catch (Exception $e) {
        $stats['errors'][] = "Error in $filePath: " . $e->getMessage();
        return false;
    }
}

// Start processing
echo "<!DOCTYPE html>\n";
echo "<html lang='en'>\n";
echo "<head>\n";
echo "  <meta charset='UTF-8'>\n";
echo "  <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
echo "  <title>Fix Links - MY CASH</title>\n";
echo "  <style>\n";
echo "    * { margin: 0; padding: 0; box-sizing: border-box; }\n";
echo "    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; padding: 40px 20px; }\n";
echo "    .container { max-width: 1000px; margin: 0 auto; background: white; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); overflow: hidden; }\n";
echo "    .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 40px; }\n";
echo "    .header h1 { font-size: 28px; font-weight: 700; margin-bottom: 8px; }\n";
echo "    .header p { opacity: 0.9; font-size: 15px; }\n";
echo "    .content { padding: 40px; }\n";
echo "    .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }\n";
echo "    .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; }\n";
echo "    .stat-card .number { font-size: 32px; font-weight: 700; margin-bottom: 8px; }\n";
echo "    .stat-card .label { opacity: 0.9; font-size: 14px; }\n";
echo "    .section { margin-bottom: 30px; }\n";
echo "    .section h2 { font-size: 20px; font-weight: 600; color: #333; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 2px solid #667eea; }\n";
echo "    .file-list { background: #f8f9fa; border-radius: 8px; padding: 20px; max-height: 400px; overflow-y: auto; }\n";
echo "    .file-item { padding: 10px; background: white; border-radius: 6px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }\n";
echo "    .file-path { font-family: 'Courier New', monospace; font-size: 13px; color: #333; }\n";
echo "    .badge { background: #667eea; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px; font-weight: 600; }\n";
echo "    .success { background: #28a745; color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }\n";
echo "    .error { background: #dc3545; color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }\n";
echo "    .info { background: #17a2b8; color: white; padding: 15px 20px; border-radius: 8px; margin-bottom: 20px; }\n";
echo "    .btn { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 20px; transition: transform 0.2s; }\n";
echo "    .btn:hover { transform: translateY(-2px); }\n";
echo "    .spinner { display: inline-block; width: 14px; height: 14px; border: 2px solid rgba(255,255,255,0.3); border-top-color: white; border-radius: 50%; animation: spin 0.8s linear infinite; margin-right: 8px; }\n";
echo "    @keyframes spin { to { transform: rotate(360deg); } }\n";
echo "  </style>\n";
echo "</head>\n";
echo "<body>\n";
echo "  <div class='container'>\n";
echo "    <div class='header'>\n";
echo "      <h1>🔗 Link Fixer</h1>\n";
echo "      <p>Fixing all URL-encoded links in MY CASH application</p>\n";
echo "    </div>\n";
echo "    <div class='content'>\n";

// Get all PHP files
echo "      <div class='info'><span class='spinner'></span>Scanning for PHP files...</div>\n";
flush();

$files = scanDirectory($rootDir, $fileExtensions);
$stats['files_scanned'] = count($files);

echo "      <div class='success'>✓ Found {$stats['files_scanned']} PHP files to check</div>\n";
flush();

// Process files
$modifiedFiles = [];

foreach ($files as $file) {
    $result = fixLinksInFile($file, $searchPattern, $replaceWith);
    if ($result) {
        $modifiedFiles[] = $result;
    }
}

// Display statistics
echo "      <div class='stats'>\n";
echo "        <div class='stat-card'>\n";
echo "          <div class='number'>{$stats['files_scanned']}</div>\n";
echo "          <div class='label'>Files Scanned</div>\n";
echo "        </div>\n";
echo "        <div class='stat-card'>\n";
echo "          <div class='number'>{$stats['files_modified']}</div>\n";
echo "          <div class='label'>Files Modified</div>\n";
echo "        </div>\n";
echo "        <div class='stat-card'>\n";
echo "          <div class='number'>{$stats['total_replacements']}</div>\n";
echo "          <div class='label'>Total Replacements</div>\n";
echo "        </div>\n";
echo "      </div>\n";

// Show modified files
if (!empty($modifiedFiles)) {
    echo "      <div class='section'>\n";
    echo "        <h2>✅ Modified Files ({$stats['files_modified']})</h2>\n";
    echo "        <div class='file-list'>\n";
    foreach ($modifiedFiles as $file) {
        echo "          <div class='file-item'>\n";
        echo "            <span class='file-path'>{$file['file']}</span>\n";
        echo "            <span class='badge'>{$file['replacements']} fix" . ($file['replacements'] > 1 ? 'es' : '') . "</span>\n";
        echo "          </div>\n";
    }
    echo "        </div>\n";
    echo "      </div>\n";
}

// Show errors
if (!empty($stats['errors'])) {
    echo "      <div class='section'>\n";
    echo "        <h2>⚠️ Errors</h2>\n";
    foreach ($stats['errors'] as $error) {
        echo "        <div class='error'>$error</div>\n";
    }
    echo "      </div>\n";
}

// Summary
if ($stats['files_modified'] > 0) {
    echo "      <div class='success'>\n";
    echo "        <strong>✓ Success!</strong> Fixed {$stats['total_replacements']} URL-encoded links in {$stats['files_modified']} files.\n";
    echo "        <br><br>\n";
    echo "        <strong>Changes made:</strong>\n";
    echo "        <ul style='margin-top: 10px; margin-left: 20px;'>\n";
    echo "          <li>Replaced <code>/MY CASH/</code> with <code>/MY CASH/</code></li>\n";
    echo "          <li>All links should now work correctly</li>\n";
    echo "          <li>CSS and JavaScript links fixed</li>\n";
    echo "          <li>Navigation links updated</li>\n";
    echo "        </ul>\n";
    echo "      </div>\n";
} else {
    echo "      <div class='info'>ℹ️ No files needed modifications. All links are already correct!</div>\n";
}

echo "      <a href='/MY CASH/pages/dashboard.php' class='btn'>🏠 Go to Dashboard</a>\n";
echo "      <a href='/MY CASH/index.php' class='btn' style='margin-left: 10px;'>🏠 Go to Home</a>\n";

echo "    </div>\n";
echo "  </div>\n";
echo "</body>\n";
echo "</html>\n";
?>
