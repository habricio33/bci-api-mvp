<?php
session_start();
$_SESSION['usuario'] = 'admin';
$_GET = [
    'tipo' => 'geo_logradouros_dm',
    'bbox' => '-40.67632,-20.3785440363049,-40.645508766174316,-20.34293692945155'
];

try {
    ob_start();
    require __DIR__ . '/../api/camada.php';
    $out = ob_get_clean();
    echo "Camada output length: " . strlen($out) . "\n";
    echo $out . "\n";
} catch (Throwable $e) {
    echo "EXCECAO: " . $e->getMessage() . "\n";
}
