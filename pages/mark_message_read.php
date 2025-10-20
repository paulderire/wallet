<?php
// Simple endpoint to mark a message as read by id
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok'=>false,'error'=>'Method not allowed']);
  exit;
}
$id = $_POST['id'] ?? null;
if (!$id) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'Missing id']);
  exit;
}
$path = __DIR__ . '/../assets/data/messages.json';
if (!file_exists($path)) {
  echo json_encode(['ok'=>false,'error'=>'No messages file']);
  exit;
}
$msgs = json_decode(file_get_contents($path), true) ?: [];
$found = false;
for ($i=0;$i<count($msgs);$i++) {
  if (isset($msgs[$i]['id']) && $msgs[$i]['id'] === $id) {
    $msgs[$i]['is_read'] = true;
    $found = true; break;
  }
}
if ($found) {
  file_put_contents($path, json_encode($msgs, JSON_PRETTY_PRINT));
  echo json_encode(['ok'=>true]);
} else {
  echo json_encode(['ok'=>false,'error'=>'Not found']);
}
