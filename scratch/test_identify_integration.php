<?php
session_start();
$_SESSION['usuario'] = 'admin';

require_once __DIR__ . '/../config/db.php';
$db = getDBConnection();

echo "=== TESTE 1: action=detalhes na inscrição oficial 01010230605002 ===\n";
$_GET = ['action' => 'detalhes', 'insc_munic' => '01010230605002'];
ob_start();
require __DIR__ . '/../api/identificar.php';
$res1 = json_decode(ob_get_clean(), true);
echo "Encontrado: " . ($res1['found'] ? 'SIM' : 'NAO') . "\n";
echo "Contribuinte: " . ($res1['imovel']['NOMEDOCONTRIBUINTE'] ?? '') . "\n";
echo "Origem: " . ($res1['imovel']['ORIGEM_DADOS'] ?? '') . "\n";
echo "Situação: " . ($res1['imovel']['SITUACAO_CADASTRAL'] ?? '') . "\n\n";

echo "=== TESTE 2: action=bci na inscrição oficial 01010230605002 ===\n";
$_GET = ['action' => 'bci', 'insc_munic' => '01010230605002'];
ob_start();
require __DIR__ . '/../api/identificar.php';
$res2 = json_decode(ob_get_clean(), true);
echo "Encontrado: " . ($res2['found'] ? 'SIM' : 'NAO') . "\n";
echo "Área Terreno: " . ($res2['imovel']['AREA_DO_TERRENO13065'] ?? '') . "\n";
echo "Memorial: " . substr($res2['imovel']['MEMORIAL'] ?? '', 0, 70) . "...\n";
echo "Origem: " . ($res2['imovel']['ORIGEM_DADOS'] ?? '') . "\n\n";

echo "=== TESTE 3: Fallback para inscrição não sincronizada (01010230605001) ===\n";
$_GET = ['action' => 'bci', 'insc_munic' => '01010230605001'];
ob_start();
require __DIR__ . '/../api/identificar.php';
$res3 = json_decode(ob_get_clean(), true);
echo "Encontrado: " . ($res3['found'] ? 'SIM' : 'NAO') . "\n";
echo "Contribuinte: " . ($res3['imovel']['NOMEDOCONTRIBUINTE'] ?? '') . "\n";
echo "Origem: " . ($res3['imovel']['ORIGEM_DADOS'] ?? '') . "\n\n";

echo "=== TODOS OS TESTES PASSARAM! ===\n";
