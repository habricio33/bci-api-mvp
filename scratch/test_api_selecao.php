<?php
$_SESSION['usuario'] = 'admin';

// Test 1: Listar
$_REQUEST['action'] = 'listar';
ob_start();
require __DIR__ . '/../api/selecao_comunidade.php';
$res1 = ob_get_clean();
$json1 = json_decode($res1, true);
echo "Test 1 (Listar): Total " . ($json1['total'] ?? 'ERR') . " comunidades.\n";

// Test 2: Analisar Campinho (id2 = 1)
$_REQUEST['action'] = 'analisar';
$_REQUEST['id'] = 1;
ob_start();
require __DIR__ . '/../api/selecao_comunidade.php';
$res2 = ob_get_clean();
$json2 = json_decode($res2, true);
echo "Test 2 (Analisar 1): " . ($json2['comunidade']['nome'] ?? 'ERR') . 
     " - Lotes: " . ($json2['metricas']['total_lotes'] ?? 'ERR') . 
     " - Edificacoes: " . ($json2['metricas']['total_edificacoes'] ?? 'ERR') . "\n";

// Test 3: Tabela Lotes
$_REQUEST['action'] = 'tabela_lotes';
$_REQUEST['id'] = 1;
$_REQUEST['page'] = 1;
$_REQUEST['limit'] = 5;
ob_start();
require __DIR__ . '/../api/selecao_comunidade.php';
$res3 = ob_get_clean();
$json3 = json_decode($res3, true);
echo "Test 3 (Tabela Lotes): Total " . ($json3['total'] ?? 'ERR') . " - Rows: " . count($json3['data'] ?? []) . "\n";

// Test 4: Tabela Edificacoes
$_REQUEST['action'] = 'tabela_edificacoes';
$_REQUEST['id'] = 1;
$_REQUEST['page'] = 1;
$_REQUEST['limit'] = 5;
ob_start();
require __DIR__ . '/../api/selecao_comunidade.php';
$res4 = ob_get_clean();
$json4 = json_decode($res4, true);
echo "Test 4 (Tabela Edificacoes): Total " . ($json4['total'] ?? 'ERR') . " - Rows: " . count($json4['data'] ?? []) . "\n";
