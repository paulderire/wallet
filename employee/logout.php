<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['employee_id'])) {
  header("Location: /MY CASH/employee_login.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Employee Logout</title>
</head>
<body>
<?php
// Clear employee session
unset($_SESSION['employee_id']);
unset($_SESSION['employee_name']);
unset($_SESSION['employee_email']);
unset($_SESSION['employee_role']);
unset($_SESSION['employee_user_id']);
unset($_SESSION['employee_company']);

session_destroy();

header("Location: /MY CASH/employee_login.php");
exit;
?>
</body>
</html>
