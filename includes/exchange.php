<?php
// Simple exchange rate helper using exchangerate.host free API with basic file caching
function get_rate_to_rwf($base = 'USD'){
    $cacheFile = __DIR__ . '/../assets/data/exchange_cache.json';
    $cacheTtl = 3600; // 1 hour
    $now = time();
    $data = null;
    if (file_exists($cacheFile)){
        $raw = @file_get_contents($cacheFile);
        $j = json_decode($raw, true);
        if ($j && isset($j['ts']) && ($now - $j['ts'] < $cacheTtl) && isset($j['rates'][$base])){
            return $j['rates'][$base];
        }
    }
    $url = "https://api.exchangerate.host/latest?base={$base}&symbols=RWF";
    $ctx = stream_context_create(['http'=>['timeout'=>5]]);
    $res = @file_get_contents($url,false,$ctx);
    if ($res){
        $j = json_decode($res,true);
        if ($j && isset($j['rates']['RWF'])){
            $rate = floatval($j['rates']['RWF']);
            $save = ['ts'=>$now,'rates'=>[$base=>$rate]];
            @file_put_contents($cacheFile,json_encode($save,JSON_PRETTY_PRINT));
            return $rate;
        }
    }
    // fallback to 1:1 if API fails
    return 1.0;
}
?>