<?php
// Start session and require login before any output so redirects work
if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['user_id'])) {
  header('Location: /MY CASH/pages/login.php');
  exit;
}

$uid = $_SESSION['user_id'];

// include DB for upload handling and user reload
include __DIR__ . '/../includes/db.php';

$errors = [];
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!empty($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $f = $_FILES['avatar'];
    $allowed = ['image/jpeg','image/png','image/webp'];
    if (!in_array($f['type'], $allowed)) {
      $errors[] = 'Only PNG/JPEG/WEBP allowed';
    } elseif ($f['size'] > 1024*1024*2) {
      $errors[] = 'Max size 2MB';
    } else {
      $ext = pathinfo($f['name'], PATHINFO_EXTENSION);
      $name = 'avatar_' . $uid . '_' . time() . '.' . $ext;
      $dstDir = __DIR__ . '/../assets/uploads';
      if (!is_dir($dstDir)) mkdir($dstDir, 0755, true);
      $dst = $dstDir . '/' . $name;
      if (move_uploaded_file($f['tmp_name'], $dst)) {
        // save relative path to DB
        try {
          $p = '/MY CASH/assets/uploads/' . $name;
          $stmt = $conn->prepare('UPDATE users SET avatar = ? WHERE id = ?');
          $stmt->execute([$p, $uid]);
          $success = 'Avatar uploaded';
        } catch (Exception $e) {
          $errors[] = 'DB error';
        }
      } else {
        $errors[] = 'Upload failed';
      }
    }
  } else {
    $errors[] = 'No file uploaded';
  }
}

// reload user
$user = ['name'=>'User','avatar'=>''];
try {
  $s = $conn->prepare('SELECT name, avatar, email FROM users WHERE id = ?');
  $s->execute([$uid]);
  $user = $s->fetch(PDO::FETCH_ASSOC) ?: $user;
} catch (Exception $e) {}

include __DIR__ . '/../includes/header.php';
?>

<div class="card card--center" style="max-width:640px;margin:12px auto">
  <div class="card-title"><h3>Profile</h3></div>
  <?php if ($success): ?><div class="notice success"><?=htmlspecialchars($success)?></div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="notice error">
      <ul>
      <?php foreach($errors as $err): ?><li><?=htmlspecialchars($err)?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <div style="display:flex;gap:12px;align-items:center">
    <div class="avatar" style="width:96px;height:96px">
      <?php if (!empty($user['avatar'])): ?>
        <img src="<?=htmlspecialchars($user['avatar'])?>" alt="avatar" style="width:100%;height:100%;object-fit:cover;border-radius:8px">
      <?php else: ?>
  <div style="width:100%;height:100%;background:linear-gradient(90deg,var(--accent-500),var(--accent-400));display:flex;align-items:center;justify-content:center;color:var(--button-text);font-weight:700;font-size:28px;border-radius:8px"><?=htmlspecialchars(substr($user['name'],0,1))?></div>
      <?php endif; ?>
    </div>
    <div style="flex:1">
      <h4><?=htmlspecialchars($user['name'])?></h4>
      <div class="muted"><?=htmlspecialchars($user['email'] ?? '')?></div>

      <form method="POST" enctype="multipart/form-data" style="margin-top:12px">
        <label>Change avatar</label>
        <input type="file" name="avatar" accept="image/*" required>
        <div style="margin-top:8px"><button type="submit">Upload</button></div>
      </form>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php';
