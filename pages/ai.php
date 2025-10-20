<?php
// AI assistant page (supports full page and compact widget modes)
// Single coherent implementation to avoid previous duplicate blocks that caused parse errors.
include __DIR__ . '/../includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isWidget = isset($_GET['widget']) && $_GET['widget'] == '1';
// require auth early when not widget (so redirect can happen before output)
if(!isset($_SESSION['user_id'])){
    if ($isWidget) { echo '<div class="muted">Please log in to use the AI assistant.</div>'; exit; }
    header("Location: /MY CASH/pages/login.php");
    exit;
}

if (!$isWidget) {
  include __DIR__ . '/../includes/header.php';
}

$prompt = '';
// Support JSON POST (widget) as well as form POST
$rawInput = file_get_contents('php://input');
$contentType = $_SERVER['CONTENT_TYPE'] ?? ($_SERVER['HTTP_CONTENT_TYPE'] ?? '');

// If this looks like a JSON/widget call, enable buffering and prepare for a JSON response early
$isJsonBody = $rawInput && stripos($contentType, 'application/json') !== false;
if ($isJsonBody || ($isWidget && $_SERVER['REQUEST_METHOD'] === 'POST')) {
  if (!ob_get_level()) ob_start();
  // ensure response will be JSON
  header('Content-Type: application/json');
}

if ($rawInput && stripos($contentType, 'application/json') !== false) {
  $decoded = json_decode($rawInput, true);
  if (is_array($decoded)) {
    $prompt = trim($decoded['message'] ?? $decoded['prompt'] ?? '');
  }
} else {
  $prompt = trim($_POST['prompt'] ?? '');
}
$response = '';

// AI enabled config
$configPath = __DIR__ . '/../assets/config/ai_enabled.json';
$aiEnabled = true;
if (file_exists($configPath)) {
  $cfg = json_decode(file_get_contents($configPath), true);
  if (is_array($cfg) && array_key_exists('enabled', $cfg)) $aiEnabled = (bool)$cfg['enabled'];
}

// load API key from local config if present
$keyPath = __DIR__ . '/../assets/config/ai_key.json';
$localKey = '';
if (file_exists($keyPath)) {
  $k = json_decode(file_get_contents($keyPath), true);
  if (is_array($k) && !empty($k['key'])) $localKey = trim($k['key']);
}

// simple per-session call counter
if (!isset($_SESSION['ai_calls'])) $_SESSION['ai_calls'] = 0;
if ($_SESSION['ai_calls'] > 1000) $_SESSION['ai_calls'] = 0;

if ($prompt !== '') {
  // debug log incoming widget JSON requests (helps if the widget seems unresponsive)
  try {
    $logDir = __DIR__ . '/../assets/logs';
    if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
    if ($isJsonBody || $isWidget) {
      $dbg = '['.date('c').'] AI request by user='.($_SESSION['user_id']??'anon')." prompt=".substr($prompt,0,200)."\n";
      @file_put_contents($logDir.'/ai_debug.log', $dbg, FILE_APPEND);
    }
  } catch (Exception $e) { /* ignore logging errors */ }
  $openai_key = $localKey ?: (getenv('OPENAI_API_KEY') ?: ($_SERVER['OPENAI_API_KEY'] ?? ''));
  if ($openai_key && $aiEnabled) {
    if ($_SESSION['ai_calls'] >= 6) {
      $response = "(AI) Rate limit reached for this session. Try again later.";
    } else {
      $payload = [
        'model' => 'gpt-3.5-turbo',
        'messages' => [
          ['role' => 'system', 'content' => "You are a helpful assistant that summarizes personal finance info succinctly. If asked for calculations, respond with numbers only where appropriate. Be concise."],
          ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.2,
        'max_tokens' => 400
      ];

      $ch = curl_init('https://api.openai.com/v1/chat/completions');
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $openai_key
      ]);
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
      curl_setopt($ch, CURLOPT_TIMEOUT, 10);

      $res = curl_exec($ch);
      $err = curl_error($ch);
      $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      if ($res && $code >= 200 && $code < 300) {
        $j = json_decode($res, true);
        if (isset($j['choices'][0]['message']['content'])) {
          $response = trim($j['choices'][0]['message']['content']);
        } else {
          $response = "(AI) Unexpected response format from API.";
        }
      } else {
        // detailed debug logging for failures
        try {
          $logDir = __DIR__ . '/../assets/logs';
          if (!is_dir($logDir)) @mkdir($logDir, 0755, true);
          $dbg = '['.date('c').'] AI request failed user='.(($_SESSION['user_id']??'anon'))." code={$code} err=".($err?:'')." snippet=".substr($res?:'',0,800)."\n";
          @file_put_contents($logDir.'/ai_debug.log', $dbg, FILE_APPEND);
        } catch (Exception $e) { /* ignore logging errors */ }
        $response = "(AI fallback) Could not reach AI service" . ($err ? (': '.$err) : '') . ".";
      }

      // log
      try {
        $logPath = __DIR__ . '/../assets/data/ai_logs.json';
        $logs = [];
        if (file_exists($logPath)) $logs = json_decode(file_get_contents($logPath), true) ?: [];
        $logs[] = ['time'=>date('c'),'user_id'=>$_SESSION['user_id'],'prompt'=>$prompt,'response'=>substr($response,0,200)];
        file_put_contents($logPath,json_encode($logs,JSON_PRETTY_PRINT));
      } catch (Exception $e) { /* ignore logging errors */ }

      $_SESSION['ai_calls'] = ($_SESSION['ai_calls'] ?? 0) + 1;
    }
  } else {
    if (!$aiEnabled) {
      $response = "(AI) External AI is disabled by admin. You can still use local mock responses.";
    } else {
      $response = "(AI) This is a placeholder response to: " . htmlspecialchars($prompt);
    }
  }

  // If XHR or JSON POST (widget), return JSON with 'reply'
  $isXhr = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
  $isJsonBody = $rawInput && stripos($contentType, 'application/json') !== false;
  if ($isXhr || $isJsonBody || ($isWidget && $_SERVER['REQUEST_METHOD'] === 'POST')) {
    // clear any accidental output
    if (ob_get_level()) { ob_clean(); }
    echo json_encode(['ok' => true, 'reply' => $response]);
    if (ob_get_level()) ob_end_flush();
    exit;
  }
}

// Render UI
if ($isWidget) {
  // compact chat UI for iframe widget
  ?>
  <!doctype html>
  <html>
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="/MY CASH/assets/css/style.min.css">
  </head>
  <body style="margin:0;padding:12px;background:transparent;color:var(--text)">
    <div style="background:var(--card-bg-solid);padding:12px;border-radius:8px;max-width:820px;box-sizing:border-box">
      <h3 style="margin:0 0 8px 0">AI Assistant</h3>
      <div id="ai-response" style="min-height:120px;padding:10px;border-radius:8px;background:var(--input-bg);color:var(--card-text);margin-bottom:8px;white-space:pre-wrap"><?=htmlspecialchars($response)?></div>
      <form id="ai-form">
        <textarea name="prompt" id="ai-prompt" rows="3" style="width:100%;padding:8px;border-radius:8px;border:1px solid var(--border-weak);background:var(--input-bg);color:var(--card-text)"></textarea>
        <div style="display:flex;gap:8px;margin-top:8px">
          <button type="submit" class="button">Send</button>
          <button type="button" id="ai-clear" class="button secondary">Clear</button>
        </div>
      </form>
    </div>
    <script>
      (function(){
        var form = document.getElementById('ai-form');
        var resp = document.getElementById('ai-response');
        var prompt = document.getElementById('ai-prompt');
        var clear = document.getElementById('ai-clear');
        function setLoading(on){ if (on) resp.textContent = 'Thinking...'; }
        form.addEventListener('submit', function(e){
          e.preventDefault();
          var fd = new FormData(); fd.append('prompt', prompt.value||'');
          setLoading(true);
          fetch('/MY CASH/pages/ai.php?widget=1', {method:'POST', body: fd, headers: {'X-Requested-With':'XMLHttpRequest'}}).then(function(r){ return r.json(); }).then(function(j){ if (j && j.response) resp.textContent = j.response; else resp.textContent = '(no response)'; }).catch(function(){ resp.textContent = 'Failed to reach AI service.'; });
        });
        clear.addEventListener('click', function(){ prompt.value=''; resp.textContent=''; });
      })();
    </script>
  </body>
  </html>
  <?php
  exit;
}

// Full page rendering
?>
<div class="card">
  <div class="card-title"><h2>AI Assistant</h2></div>
  <form method="POST">
    <label for="prompt">Ask something about your finances (e.g., "How much did I spend on groceries last month?")</label>
  <textarea name="prompt" id="prompt" rows="4" style="width:100%;padding:10px;border-radius:8px;background:transparent;border:1px solid var(--border-weak);color:var(--card-text);margin-top:8px"><?=htmlspecialchars($prompt)?></textarea>
    <div style="margin-top:8px">
      <button type="submit" class="button">Ask AI</button>
    </div>
  </form>

  <?php if ($response): ?>
    <div class="card" style="margin-top:12px">
      <h3>Response</h3>
  <pre style="white-space:pre-wrap;color:var(--card-text);background:var(--card-bg);padding:12px;border-radius:8px;border:1px solid var(--border-weak)"><?= $response ?></pre>
    </div>
  <?php endif; ?>
</div>

<?php if (!$isWidget) include __DIR__ . '/../includes/footer.php';