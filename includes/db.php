<?php
/**
 * Database connection helper
 * Reads credentials from environment variables when available.
 * For production, set DB_HOST, DB_NAME, DB_USER, DB_PASS in your environment or web server config.
 */

// Local sensible defaults (use only for local development)
$default = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'wallet_db',
    'DB_USER' => 'root',
    'DB_PASS' => 'Uwasedelice1!',
];

// Read from environment, fallback to defaults
$host = getenv('DB_HOST') ?: $default['DB_HOST'];
$dbname = getenv('DB_NAME') ?: $default['DB_NAME'];
$username = getenv('DB_USER') ?: $default['DB_USER'];
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : $default['DB_PASS'];

// Safety check: prevent accidental production use with default root/empty password
if ((php_sapi_name() !== 'cli') && ($username === 'root' && $password === '')) {
    // In a production environment you should NOT use root with empty password.
    // Fail fast with an instructive message (logged) to encourage secure config.
    error_log('CRITICAL: Database credentials appear to be default (root with empty password). Update includes/db.php or set environment variables DB_USER/DB_PASS.');
    // Show a friendly message without revealing credentials
    die('Database not configured. Please update database credentials in includes/db.php or set environment variables.');
}

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $conn = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    // Log full error for administrators, but show a generic message to users
    error_log('Database connection failed: ' . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "Connection failed: " . $e->getMessage() . PHP_EOL;
    } else {
        http_response_code(500);
        die('Unable to connect to the database. Please contact the administrator.');
    }
}

?>
