<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost:8086/login_action.php");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['username' => 'admin', 'password' => 'admin']));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_COOKIEJAR, __DIR__ . '/cookie.txt');
$response = curl_exec($ch);
curl_close($ch);

echo "=== Login response ===\n";
echo substr($response, 0, 300) . "\n";

// Test filtros_lotes.php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost:8086/api/filtros_lotes.php");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
$responseFiltros = curl_exec($ch);
curl_close($ch);

echo "\n=== API Filtros Lotes response ===\n";
echo $responseFiltros . "\n";

// Test camada.php for lotes
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "http://localhost:8086/api/camada.php?tipo=geo_lotes_dm&bbox=-40.665,-20.375,-40.655,-20.365");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_COOKIEFILE, __DIR__ . '/cookie.txt');
$responseCamada = curl_exec($ch);
curl_close($ch);

$jsonCamada = json_decode($responseCamada, true);
echo "\n=== API Camada Lotes response count ===\n";
if ($jsonCamada && isset($jsonCamada['features'])) {
    echo "Features count: " . count($jsonCamada['features']) . "\n";
    if (count($jsonCamada['features']) > 0) {
        echo "Sample properties: " . json_encode($jsonCamada['features'][0]['properties'], JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "Error: " . substr($responseCamada, 0, 500) . "\n";
}
