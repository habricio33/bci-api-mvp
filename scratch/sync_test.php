<?php
require_once __DIR__ . '/../includes/TributarioApiClient.php';

try {
    echo "Sincronizando lote teste 2026-10-01 a 2026-10-13...\n";
    $client = new TributarioApiClient();
    $res = $client->sincronizarPeriodo('2026-10-01', '2026-10-13', 5);
    echo "Resultado da sincronização:\n";
    print_r($res);

    echo "\nBuscando inscrição oficial 01010230605002 no banco:\n";
    $imovel = TributarioApiClient::buscarOficialLocal('01010230605002');
    print_r([
        'INSC_MUNIC' => $imovel['INSC_MUNIC'],
        'CONTRIBUINTE' => $imovel['NOMEDOCONTRIBUINTE'],
        'SITUACAO' => $imovel['SITUACAO_CADASTRAL'],
        'MEMORIAL' => $imovel['MEMORIAL'],
        'ORIGEM' => $imovel['ORIGEM_DADOS']
    ]);
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
