<?php
// This test file has been archived and moved to ../dev_tools/test_ai_call.php
// It is intentionally non-functional to avoid exposing API keys in production.
http_response_code(410);
header('Content-Type: application/json');
echo json_encode(['ok' => false, 'error' => 'archived', 'message' => 'This test file has been archived. Use dev_tools/test_ai_call.php via CLI.']);
exit;
?>
