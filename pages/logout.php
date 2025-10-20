<?php
// Simple logout handler
if (session_status() === PHP_SESSION_NONE) session_start();

// clear session data
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'], $params['secure'], $params['httponly']
    );
}
session_destroy();

// redirect to landing page
header('Location: /MY CASH/index.php');
exit;
