<?php
// Simple AJAX endpoint for currency conversion using includes/exchange.php
header('Content-Type: application/json');
require __DIR__ . '/exchange.php';
$base = isset($_GET['base']) ? strtoupper(substr($_GET['base'],0,3)) : 'USD';
$target = isset($_GET['target']) ? strtoupper(substr($_GET['target'],0,3)) : 'RWF';
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : null;

// Only support USD <-> RWF for now
if (($base==='USD' && $target==='RWF') || ($base==='RWF' && $target==='USD')){
    if ($base === 'USD' && $target === 'RWF'){
        $rate = get_rate_to_rwf('USD');
        $converted = is_null($amount) ? null : $amount * $rate;
        echo json_encode(['ok'=>true,'rate'=>$rate,'converted'=>$converted,'base'=>'USD','target'=>'RWF']);
        exit;
    }
    if ($base === 'RWF' && $target === 'USD'){
        $rate = get_rate_to_rwf('USD');
        // rate = RWF per 1 USD -> USD = RWF / rate
        $converted = is_null($amount) ? null : ($amount / ($rate?:1));
        echo json_encode(['ok'=>true,'rate'=>1/($rate?:1),'converted'=>$converted,'base'=>'RWF','target'=>'USD']);
        exit;
    }
}

// fallback
http_response_code(400);
echo json_encode(['ok'=>false,'error'=>'unsupported conversion']);
