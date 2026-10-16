<?php
require_once __DIR__ . '/../config/db.php';
$db = getDBConnection();
$row = $db->query('SELECT "INSC_MUNIC" FROM public."GP_IMOVEL" WHERE "INSC_MUNIC" NOT IN (SELECT insc_munic FROM public.tributario_api_imovel) LIMIT 1')->fetch();
$insc = $row['INSC_MUNIC'];
echo "Testing legacy insc: $insc\n";

session_start();
$_SESSION['usuario'] = 'admin';
$_GET = ['action' => 'detalhes', 'insc_munic' => $insc];
ob_start();
require __DIR__ . '/../api/identificar.php';
$json = json_decode(ob_get_clean(), true);
echo "Found: " . ($json['found'] ? 'SIM' : 'NAO') . "\n";
echo "Contribuinte: " . ($json['imovel']['NOMEDOCONTRIBUINTE'] ?? '') . "\n";
echo "Origem: " . ($json['imovel']['ORIGEM_DADOS'] ?? '') . "\n";
