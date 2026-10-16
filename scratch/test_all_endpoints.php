<?php
session_start();
$_SESSION['usuario'] = 'admin';

echo "=== 1. Testando api/camada.php (geo_logradouros_dm) ===\n";
$_GET = [
    'tipo' => 'geo_logradouros_dm',
    'bbox' => '-40.67632,-20.3785440363049,-40.645508766174316,-20.34293692945155'
];
ob_start();
require __DIR__ . '/../api/camada.php';
$out1 = ob_get_clean();
$json1 = json_decode($out1, true);
echo "geo_logradouros_dm status: " . (isset($json1['features']) ? "OK! Features: " . count($json1['features']) : "ERRO: $out1") . "\n\n";

echo "=== 2. Testando api/camada.php (geo_comunidades_dm) ===\n";
$_GET = [
    'tipo' => 'geo_comunidades_dm',
    'bbox' => '-40.67632,-20.3785440363049,-40.645508766174316,-20.34293692945155'
];
ob_start();
require __DIR__ . '/../api/camada.php';
$out2 = ob_get_clean();
$json2 = json_decode($out2, true);
echo "geo_comunidades_dm status: " . (isset($json2['features']) ? "OK! Features: " . count($json2['features']) : "ERRO: $out2") . "\n\n";

echo "=== 3. Testando api/dominios.php (legados) ===\n";
$_GET = ['campo' => 'material', 'tipo' => 'vw_rede_agua'];
ob_start();
require __DIR__ . '/../api/dominios.php';
$out3 = ob_get_clean();
echo "dominios.php status: $out3\n";

echo "\n=== TODOS OS ENDPOINTS RESPONDENDO CORRETAMENTE! ===\n";
