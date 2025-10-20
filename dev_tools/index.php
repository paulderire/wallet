<?php
http_response_code(403);
header('Content-Type: text/plain; charset=utf-8');
echo "403 Forbidden - dev_tools moved for security.\n";
echo "Dev tools are stored outside the web root at: C:\\xampp\\htdocs\\MY CASH-dev_tools\\\n";
exit;
?>