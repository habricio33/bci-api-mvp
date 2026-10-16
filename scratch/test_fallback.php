<?php
session_start();
$_SESSION['usuario'] = 'admin';
$_GET = ['action' => 'detalhes', 'insc_munic' => '01010230605001'];
ob_start();
require __DIR__ . '/../api/identificar.php';
$json = json_decode(ob_get_clean(), true);
echo "Fallback GP_IMOVEL:\n";
echo "Found: " . ($json['found'] ? 'SIM' : 'NAO') . "\n";
echo "Contribuinte: " . ($json['imovel']['NOMEDOCONTRIBUINTE'] ?? '') . "\n";
echo "Origem: " . ($json['imovel']['ORIGEM_DADOS'] ?? '') . "\n";
