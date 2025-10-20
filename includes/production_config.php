<?php
/**
 * MY CASH - Production Configuration
 * 
 * This file contains all production-specific PHP settings.
 * Include this at the top of index.php and other entry points.
 * 
 * IMPORTANT: Update settings below for your production environment!
 */

// =====================================================
// ERROR HANDLING & LOGGING
// =====================================================

// Hide errors from users (CRITICAL for production)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// Log all errors
error_reporting(E_ALL);
ini_set('log_errors', '1');

// Set custom error log location
ini_set('error_log', __DIR__ . '/../assets/logs/php_errors.log');

// Custom error handler for production
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the error
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    
    // Don't show error details to users
    // Instead show a generic message
    if ($errno === E_ERROR || $errno === E_PARSE || $errno === E_CORE_ERROR) {
        http_response_code(500);
        die('An error occurred. Please try again later.');
    }
    
    return false; // Let PHP handle the error normally
});

// =====================================================
// SESSION SECURITY
// =====================================================

// Prevent session hijacking
ini_set('session.cookie_httponly', 1);  // Prevent JavaScript access to session cookie
ini_set('session.use_only_cookies', 1); // Only use cookies, not URL parameters
ini_set('session.cookie_samesite', 'Strict'); // CSRF protection

// IMPORTANT: Enable this ONLY after SSL/HTTPS is configured
// ini_set('session.cookie_secure', 1);  // Only send cookie over HTTPS

// Session security settings
ini_set('session.use_strict_mode', 1);  // Reject uninitialized session IDs
ini_set('session.use_trans_sid', 0);    // Don't pass session ID in URLs
ini_set('session.gc_maxlifetime', 3600); // Session expires after 1 hour
ini_set('session.cookie_lifetime', 0);   // Cookie expires when browser closes

// Regenerate session ID periodically to prevent fixation
if (session_status() === PHP_SESSION_ACTIVE) {
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // Every 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}

// =====================================================
// FILE UPLOAD SECURITY
// =====================================================

ini_set('upload_max_filesize', '5M');   // Max 5MB per file
ini_set('post_max_size', '10M');        // Max 10MB total POST data
ini_set('max_file_uploads', 5);        // Max 5 files at once
ini_set('file_uploads', 1);             // Enable file uploads

// =====================================================
// PHP SECURITY SETTINGS
// =====================================================

// Hide PHP version from headers
ini_set('expose_php', 'Off');

// Disable dangerous functions
if (function_exists('ini_set')) {
    ini_set('disable_functions', 'exec,passthru,shell_exec,system,proc_open,popen,curl_exec,curl_multi_exec,parse_ini_file,show_source');
}

// Prevent file inclusion vulnerabilities
ini_set('allow_url_fopen', '0');       // Disable remote file access
ini_set('allow_url_include', '0');     // Disable remote file includes

// =====================================================
// TIMEZONE SETTINGS
// =====================================================

// Set your timezone (change as needed)
date_default_timezone_set('UTC'); // or 'America/New_York', 'Europe/London', etc.

// =====================================================
// DATABASE SECURITY HELPERS
// =====================================================

/**
 * Secure database connection helper
 * Returns PDO connection with security best practices
 */
function getSecureDBConnection() {
    // Load from environment variables (recommended)
    // Or use secure configuration file
    
    $host = getenv('DB_HOST') ?: 'localhost';
    $dbname = getenv('DB_NAME') ?: 'your_database';
    $username = getenv('DB_USER') ?: 'your_username';
    $password = getenv('DB_PASS') ?: 'your_password';
    
    try {
        $conn = new PDO(
            "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
            $username,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false, // Use real prepared statements
                PDO::ATTR_PERSISTENT => false,       // Don't use persistent connections
            ]
        );
        return $conn;
    } catch(PDOException $e) {
        // Log the error but don't show details to user
        error_log("Database connection failed: " . $e->getMessage());
        http_response_code(503);
        die('Service temporarily unavailable. Please try again later.');
    }
}

// =====================================================
// CSRF PROTECTION HELPERS
// =====================================================

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output CSRF token input field
 */
function csrfTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

// =====================================================
// RATE LIMITING HELPERS
// =====================================================

/**
 * Simple file-based rate limiter
 * Returns true if request is allowed, false if rate limited
 */
function checkRateLimit($identifier, $max_attempts = 5, $time_window = 900) {
    $rate_limit_file = __DIR__ . '/../assets/data/rate_limits.json';
    
    // Ensure file exists
    if (!file_exists($rate_limit_file)) {
        file_put_contents($rate_limit_file, json_encode([]));
    }
    
    // Read current limits
    $limits = json_decode(file_get_contents($rate_limit_file), true) ?: [];
    
    // Clean old entries
    $current_time = time();
    foreach ($limits as $key => $data) {
        if ($current_time - $data['first_attempt'] > $time_window) {
            unset($limits[$key]);
        }
    }
    
    // Check current identifier
    if (!isset($limits[$identifier])) {
        $limits[$identifier] = [
            'attempts' => 1,
            'first_attempt' => $current_time
        ];
        file_put_contents($rate_limit_file, json_encode($limits));
        return true;
    }
    
    // Increment attempts
    $limits[$identifier]['attempts']++;
    
    // Check if exceeded
    if ($limits[$identifier]['attempts'] > $max_attempts) {
        file_put_contents($rate_limit_file, json_encode($limits));
        return false;
    }
    
    file_put_contents($rate_limit_file, json_encode($limits));
    return true;
}

/**
 * Reset rate limit for identifier
 */
function resetRateLimit($identifier) {
    $rate_limit_file = __DIR__ . '/../assets/data/rate_limits.json';
    
    if (file_exists($rate_limit_file)) {
        $limits = json_decode(file_get_contents($rate_limit_file), true) ?: [];
        unset($limits[$identifier]);
        file_put_contents($rate_limit_file, json_encode($limits));
    }
}

// =====================================================
// XSS PROTECTION HELPERS
// =====================================================

/**
 * Escape output to prevent XSS
 */
function escapeHTML($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return trim(strip_tags($input));
}

// =====================================================
// MAINTENANCE MODE
// =====================================================

// Check if maintenance mode is enabled
if (file_exists(__DIR__ . '/../.maintenance')) {
    // Allow admin IPs to access during maintenance
    $allowed_ips = ['127.0.0.1', '::1']; // Add your IP here
    
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_ips)) {
        http_response_code(503);
        die('Site is under maintenance. Please check back soon.');
    }
}

// =====================================================
// PRODUCTION CONSTANTS
// =====================================================

define('APP_ENV', 'production');
define('APP_DEBUG', false);
define('APP_URL', 'https://yourdomain.com'); // Update with your domain

// =====================================================
// INITIALIZATION
// =====================================================

// Ensure required directories exist and are writable
$required_dirs = [
    __DIR__ . '/../assets/logs',
    __DIR__ . '/../assets/data',
    __DIR__ . '/../assets/uploads',
    __DIR__ . '/../assets/uploads/avatars'
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Create empty rate limits file if it doesn't exist
$rate_limit_file = __DIR__ . '/../assets/data/rate_limits.json';
if (!file_exists($rate_limit_file)) {
    file_put_contents($rate_limit_file, json_encode([]));
}

// =====================================================
// END OF CONFIGURATION
// =====================================================
