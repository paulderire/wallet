<?php
if (session_status() === PHP_SESSION_NONE) session_start();
include __DIR__ . '/../includes/db.php';
if(!isset($_SESSION['user_id'])){ header('Location: /MY CASH/pages/login.php'); exit; }

$keyPath = __DIR__ . '/../assets/config/ai_key.json';
$enabledPath = __DIR__ . '/../assets/config/ai_enabled.json';

// handle form
if ($_SERVER['REQUEST_METHOD'] === 'POST'){
  if (isset($_POST['save_key'])){
    $k = trim($_POST['api_key'] ?? '');
    if ($k !== '') file_put_contents($keyPath, json_encode(['key'=>$k],JSON_PRETTY_PRINT));
    else if (file_exists($keyPath)) unlink($keyPath);
  }
  if (isset($_POST['toggle_enabled'])){
    $v = isset($_POST['enabled']) && $_POST['enabled'] === '1';
    file_put_contents($enabledPath, json_encode(['enabled'=>$v],JSON_PRETTY_PRINT));
  }
  header('Location: /MY CASH/pages/ai_settings.php'); exit;
}

// read current
$currentKey = '';
if (file_exists($keyPath)){
  $j = json_decode(file_get_contents($keyPath), true);
  if (is_array($j)) $currentKey = $j['key'] ?? '';
}
$enabled = true;
if (file_exists($enabledPath)){
  $j = json_decode(file_get_contents($enabledPath), true);
  if (is_array($j) && array_key_exists('enabled', $j)) $enabled = (bool)$j['enabled'];
}

include __DIR__ . '/../includes/header.php';
?>
<div class="card">
  <h2>AI Settings</h2>
  <form method="POST">
    <label>OpenAI API Key</label>
    <input name="api_key" value="<?=htmlspecialchars($currentKey)?>" placeholder="sk-...">
    <div style="margin-top:8px"><button class="button" name="save_key">Save Key</button></div>
  </form>

  <form method="POST" style="margin-top:12px">
    <label><input type="checkbox" name="enabled" value="1" <?= $enabled ? 'checked' : '' ?>> Enable AI</label>
    <div style="margin-top:8px"><button class="button" name="toggle_enabled">Save</button></div>
  </form>

  <p class="muted" style="margin-top:12px">Keys are stored locally in <code>assets/config/ai_key.json</code>. Keep your key secret.</p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<script>
  (function(){
    var btn = document.createElement('button');
    btn.textContent = 'Test AI connection';
    btn.className = 'button';
    btn.style.marginTop = '12px';
    var container = document.querySelector('.card');
    if (container) {
      container.appendChild(btn);
      var res = document.createElement('div'); res.style.marginTop='8px'; container.appendChild(res);
      btn.addEventListener('click', function(){
        res.textContent = 'Testing...';
  fetch('/MY CASH/pages/ai.php?widget=1', {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify({message:'Say hi'})}).then(function(r){ return r.json(); }).then(function(j){ if (j && j.reply) res.textContent = 'Reply: ' + j.reply; else res.textContent = 'No reply or error: ' + JSON.stringify(j); }).catch(function(e){ res.textContent = 'Error: ' + (e && e.message ? e.message : e); });
      });
    }
  })();
</script>