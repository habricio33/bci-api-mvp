<?php
session_start();
$_SESSION['usuario'] = 'admin';
$_GET['action'] = 'listar';
$_GET['dataInicial'] = '2026-10-01';
$_GET['dataFinal'] = '2026-10-13';
$_GET['size'] = 3;

ob_start();
require __DIR__ . '/../api/espelho_tributario.php';
$output = ob_get_clean();

echo "Status Output:\n";
$json = json_decode($output, true);
if ($json && isset($json['success']) && $json['success']) {
    echo "Sucesso! Total retornado: " . count($json['items']) . " de " . $json['totalCount'] . "\n";
    echo "Item 0: " . $json['items'][0]['contribuinte'] . " (" . $json['items'][0]['inscricaoCadastral'] . ")\n";
} else {
    echo "Resposta crua:\n" . $output . "\n";
}
