<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if(!isset($_SESSION[''user_id''])){ header("Location: /MY CASH/pages/login.php"); exit; }
include __DIR__ . ''/../includes/db.php'';

// Check if user is admin
$is_admin = false;
try {
  $stmt = $conn->prepare("SELECT is_admin FROM users WHERE id = ?");
  $stmt->execute([$_SESSION[''user_id'']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  $is_admin = !empty($user[''is_admin'']);
} catch (Exception $e) {}

if (!$is_admin) {
  header("Location: /MY CASH/pages/dashboard.php");
  exit;
}

include __DIR__ . ''/../includes/header.php'';
$user_id = $_SESSION[''user_id''];
?>
