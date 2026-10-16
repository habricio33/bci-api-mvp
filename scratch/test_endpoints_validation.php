<?php
session_start();
$_SESSION['usuario'] = 'test_user';

echo "=== Testing filtros_lotes.php ===\n";
ob_start();
require __DIR__ . '/../api/filtros_lotes.php';
$outFilters = ob_get_clean();
$jsonFilters = json_decode($outFilters, true);
if ($jsonFilters && isset($jsonFilters['geo_lotes_dm'])) {
    echo "filtros_lotes.php OK!\n";
    echo "Stats: " . json_encode($jsonFilters['geo_lotes_dm']['stats']) . "\n";
    echo "Status count items: " . count($jsonFilters['geo_lotes_dm']['status']) . "\n";
    echo "Utilizacao count items: " . count($jsonFilters['geo_lotes_dm']['utilizacao']) . "\n";
} else {
    echo "filtros_lotes.php ERROR:\n" . $outFilters . "\n";
}

echo "\n=== Testing camada.php for geo_lotes_dm with BBOX ===\n";
$_GET['tipo'] = 'geo_lotes_dm';
$_GET['bbox'] = '-40.665,-20.375,-40.655,-20.365';
ob_start();
require __DIR__ . '/../api/camada.php';
$outCamada = ob_get_clean();
$jsonCamada = json_decode($outCamada, true);
if ($jsonCamada && isset($jsonCamada['features'])) {
    echo "camada.php OK! Features returned: " . count($jsonCamada['features']) . "\n";
    if (count($jsonCamada['features']) > 0) {
        echo "Sample feature properties: " . json_encode($jsonCamada['features'][0]['properties'], JSON_UNESCAPED_UNICODE) . "\n";
    }
} else {
    echo "camada.php ERROR:\n" . substr($outCamada, 0, 500) . "\n";
}
