<?php
require_once __DIR__ . '/../includes/TributarioApiClient.php';

try {
    echo "=== 1. Testando TributarioApiClient ===\n";
    $client = new TributarioApiClient();
    
    echo "Obtendo token...\n";
    $token = $client->getToken();
    echo "Token obtido com sucesso! Tamanho: " . strlen($token) . "\n\n";

    echo "Consultando período 2026-10-01 a 2026-10-13...\n";
    $result = $client->consultarPeriodo('2026-10-01', '2026-10-13', 1, 2);
    echo "Total registros encontrados: " . ($result['totalCount'] ?? 0) . "\n";
    echo "Total páginas: " . ($result['totalPages'] ?? 0) . "\n\n";

    if (!empty($result['items'])) {
        $first = $result['items'][0];
        echo "=== 2. Primeiro Item Cru da API ===\n";
        echo "Código: " . ($first['codigo'] ?? '') . "\n";
        echo "Contribuinte: " . ($first['pessoa']['nome'] ?? '') . "\n";
        echo "CPF: " . ($first['pessoa']['documento'] ?? '') . "\n";
        echo "Inscrição Cadastral: " . ($first['imobiliario']['inscricaoCadastral'] ?? '') . "\n";
        echo "Logradouro: " . ($first['logradouro']['nome'] ?? '') . ", Nº " . ($first['numero'] ?? '') . "\n\n";

        echo "=== 3. Convertido para o formato BCI (compatível com GeoPortal) ===\n";
        $bci = $client->converterItemParaBci($first);
        print_r([
            'INSC_MUNIC' => $bci['INSC_MUNIC'],
            'NOMEDOCONTRIBUINTE' => $bci['NOMEDOCONTRIBUINTE'],
            'CPF_CNPJ' => $bci['CPF_CNPJ'],
            'ENDERECO' => "{$bci['NOMETIPOLOGRA']} {$bci['NOMELOGRADOURO']}, {$bci['NUMEROLOGRADOURO']} - {$bci['NOMEBAIRRO']}",
            'OCUPACAO9025' => $bci['OCUPACAO9025'],
            'ULTILIZACAO_DO_IMOVEL9027' => $bci['ULTILIZACAO_DO_IMOVEL9027'],
            'AREA_DO_TERRENO13065' => $bci['AREA_DO_TERRENO13065'],
            'MEMORIAL' => $bci['MEMORIAL'],
            'ORIGEM_DADOS' => $bci['ORIGEM_DADOS']
        ]);
    }
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
