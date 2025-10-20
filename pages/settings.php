<?php
// ensure session and auth happen before sending any output
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../includes/db.php';
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) { header('Location: /MY CASH/pages/login.php'); exit; }
// include header after auth check
include __DIR__ . '/../includes/header.php';

// fetch user
$user = null;
try {
  $ust = $conn->prepare('SELECT id, name, email, avatar, password FROM users WHERE id = ?');
  $ust->execute([$user_id]);
  $user = $ust->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) { $user = null; }

$errors = [];
$success = '';

// handle avatar upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'avatar') {
  if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    $errors[] = 'Please select a valid image file.';
  } else {
    $f = $_FILES['avatar'];
    $allowed = ['image/jpeg','image/png','image/webp'];
    if (!in_array($f['type'], $allowed)) { $errors[] = 'Unsupported image type.'; }
    if ($f['size'] > 2 * 1024 * 1024) { $errors[] = 'Image too large (max 2MB).'; }
    if (empty($errors)) {
      $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
      $dir = __DIR__ . '/../assets/uploads/avatars';
      if (!is_dir($dir)) mkdir($dir, 0755, true);
      $name = uniqid('av_') . '.' . $ext;
      $dest = $dir . '/' . $name;
      if (move_uploaded_file($f['tmp_name'], $dest)) {
        // update DB if column exists; try to add column if missing; otherwise fallback to JSON mapping
  $publicPath = '/MY CASH/assets/uploads/avatars/' . $name;
        try {
          $colStmt = $conn->query("SHOW COLUMNS FROM users LIKE 'avatar'");
          $hasAvatarCol = ($colStmt && $colStmt->fetch());
        } catch (Exception $e) { $hasAvatarCol = false; }
        if ($hasAvatarCol) {
          try {
            $u = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
            $u->execute([$publicPath, $user_id]);
            $success = 'Avatar updated.';
          } catch (Exception $e) { $errors[] = 'Failed to update avatar: ' . $e->getMessage(); }
        } else {
          // try adding column (best-effort)
          try {
            $conn->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL");
            // now update
            $u = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
            $u->execute([$publicPath, $user_id]);
            $success = 'Avatar updated (column added).';
          } catch (Exception $e) {
            // fallback to JSON mapping
            try {
              $mapPath = __DIR__ . '/../assets/data/user_avatars.json';
              $map = [];
              if (file_exists($mapPath)) $map = json_decode(file_get_contents($mapPath), true) ?: [];
              $map[strval($user_id)] = $publicPath;
              if (!is_dir(dirname($mapPath))) mkdir(dirname($mapPath), 0755, true);
              file_put_contents($mapPath, json_encode($map, JSON_PRETTY_PRINT));
              $success = 'Avatar uploaded (stored in fallback).';
            } catch (Exception $e2) {
              $errors[] = 'Failed to persist avatar: ' . $e2->getMessage();
            }
          }
        }
      } else { $errors[] = 'Failed to move uploaded file.'; }
    }
  }
}

// handle email change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'email') {
  $new = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
  if (!$new) { $errors[] = 'Enter a valid email.'; }
  else {
    try {
      $up = $conn->prepare('UPDATE users SET email = ? WHERE id = ?');
      $up->execute([$new, $user_id]);
      $success = 'Email updated.';
    } catch (Exception $e) { $errors[] = 'Failed to update email: ' . $e->getMessage(); }
  }
}

// handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'password') {
  $current = $_POST['current_password'] ?? '';
  $new = $_POST['new_password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';
  if (empty($current) || empty($new) || empty($confirm)) { $errors[] = 'All password fields are required.'; }
  elseif ($new !== $confirm) { $errors[] = 'New password and confirm do not match.'; }
  else {
    try {
      // verify current password - users.password assumed to be password_hash
      $stmt = $conn->prepare('SELECT password FROM users WHERE id = ?'); $stmt->execute([$user_id]); $r = $stmt->fetch(PDO::FETCH_ASSOC);
      $hash = $r ? $r['password'] : '';
      if (!password_verify($current, $hash)) { $errors[] = 'Current password is incorrect.'; }
      else {
        $nh = password_hash($new, PASSWORD_DEFAULT);
        $u = $conn->prepare('UPDATE users SET password = ? WHERE id = ?'); $u->execute([$nh, $user_id]);
        $success = 'Password changed.';
      }
    } catch (Exception $e) { $errors[] = 'Failed to change password: ' . $e->getMessage(); }
  }
}

// refetch user for display
try { $ust = $conn->prepare('SELECT id, name, email, avatar FROM users WHERE id = ?'); $ust->execute([$user_id]); $user = $ust->fetch(PDO::FETCH_ASSOC); } catch (Exception $e) {}

?>

<div class="card" style="margin:12px">
  <div class="card-title"><h3>Settings</h3></div>
  <?php if (!empty($success)): ?><div class="alert success"><?=htmlspecialchars($success)?></div><?php endif; ?>
  <?php if (!empty($errors)): foreach($errors as $er){ ?><div class="alert danger"><?=htmlspecialchars($er)?></div><?php } endif; ?>

  <h4>Profile</h4>
  <div style="display:flex;gap:12px;align-items:center">
    <div style="width:80px;height:80px;border-radius:8px;overflow:hidden">
  <?php if (!empty($user['avatar'])): ?><img src="<?=htmlspecialchars($user['avatar'])?>" style="width:100%;height:100%;object-fit:cover"><?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:linear-gradient(90deg,var(--accent-500),var(--accent-400));color:var(--button-text);font-weight:700;font-size:28px"><?=htmlspecialchars(strtoupper(substr($user['name'] ?? 'U',0,1)))?></div><?php endif; ?>
    </div>
    <div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="avatar">
        <label>Upload avatar (jpg/png/webp, max 2MB)</label>
        <input type="file" name="avatar" accept="image/*">
        <div style="margin-top:8px"><button class="button" type="submit">Upload</button></div>
      </form>
    </div>
  </div>

  <hr>
  <h4>Account</h4>
  <form method="POST">
    <input type="hidden" name="action" value="email">
    <label>Email</label>
    <input name="email" type="email" value="<?=htmlspecialchars($user['email'] ?? '')?>">
    <div style="margin-top:8px"><button class="button" type="submit">Change email</button></div>
  </form>

  <hr>
  <h4>Change password</h4>
  <form method="POST">
    <input type="hidden" name="action" value="password">
    <label>Current password</label>
    <input name="current_password" type="password">
    <label>New password</label>
    <input name="new_password" type="password">
    <label>Confirm new password</label>
    <input name="confirm_password" type="password">
    <div style="margin-top:8px"><button class="button" type="submit">Change password</button></div>
  </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
