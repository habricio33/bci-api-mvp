<?php
session_start();
$_SESSION['usuario'] = 'admin';
$_GET = [
    'tipo' => 'geo_comunidades_dm',
    'bbox' => '-40.67632,-20.3785440363049,-40.645508766174316,-20.34293692945155'
];

try {
    ob_start();
    require __DIR__ . '/../api/camada.php';
    $out = ob_get_clean();
    echo "Comunidades output length: " . strlen($out) . "\n";
    $json = json_decode($out, true);
    if ($json && isset($json['type'])) {
        echo "Comunidades GeoJSON OK! Features: " . count($json['features']) . "\n";
    } else {
        echo "Erro ou retorno:\n" . substr($out, 0, 300) . "\n";
    }
} catch (Throwable $e) {
    echo "EXCECAO: " . $e->getMessage() . "\n";
}
