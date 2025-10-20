<?php
// Base path contains a space; encode properly
$base = 'http://localhost/' . rawurlencode('MY CASH');
$urls = [
    $base . '/',
    $base . '/index.php',
    $base . '/pages/login.php',
    $base . '/pages/dashboard.php',
    $base . '/backup_database.php',
    $base . '/assets/tmp/test_ai_call.php',
    $base . '/dev_tools/test_ai_call.php'
];

foreach ($urls as $url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $body = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "URL: $url\n";
    echo "Status: $code\n";
    if ($err) echo "Error: $err\n";
    $snippet = strip_tags(substr($body, 0, 400));
    echo "Snippet: " . ($snippet ? $snippet : '[no body]') . "\n";
    echo str_repeat('-',40) . "\n";
}
