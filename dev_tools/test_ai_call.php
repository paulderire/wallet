<?php
// Moved from assets/tmp/test_ai_call.php -> dev_tools for safe storage
// This file should not be publicly accessible. Keep only for debugging.

// Minimal wrapper to call the original test (if needed)
if (php_sapi_name() !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}

echo "dev_tools/test_ai_call.php placeholder\n";
?>
