<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "=== TEST CPF_CNPJ SAMPLES ===\n";
    $stmtCpf = $db->query('
        SELECT "ID", "INSC_MUNIC", "NOMEDOCONTRIBUINTE", "CPF_CNPJ", "NOMELOGRADOURO", "NUMEROLOGRADOURO", "NOMEBAIRRO"
        FROM public."GP_IMOVEL"
        WHERE "CPF_CNPJ" IS NOT NULL AND "CPF_CNPJ" != \'\'
        LIMIT 5
    ');
    print_r($stmtCpf->fetchAll());

    echo "\n=== TOTAL GP_IMOVEL ROWS ===\n";
    $count = $db->query('SELECT COUNT(*) FROM public."GP_IMOVEL"')->fetchColumn();
    echo "Total: $count\n";

    echo "\n=== MATCHING GP_IMOVEL WITH LOTES ===\n";
    $stmtMatch = $db->query('
        SELECT 
            i."ID", i."INSC_MUNIC", i."NOMEDOCONTRIBUINTE", i."CPF_CNPJ",
            i."NOMELOGRADOURO", i."NUMEROLOGRADOURO", i."NOMEBAIRRO",
            l.id as lote_id,
            ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) as lng,
            ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) as lat
        FROM public."GP_IMOVEL" i
        LEFT JOIN geo.geo_lotes_dm l 
            ON (l.inscimob = SUBSTRING(i."INSC_MUNIC", 1, 11) OR l.inscimob2 LIKE SUBSTRING(i."INSC_MUNIC", 1, 11) || \'%\' OR l.inscimob = i."INSC_MUNIC")
        WHERE l.id IS NOT NULL
        LIMIT 5
    ');
    print_r($stmtMatch->fetchAll());

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
