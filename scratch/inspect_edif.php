<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "=== GEO_EDIFICACOES_DM COLUMNS ===\n";
    $colsEdif = $db->query("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'geo_edificacoes_dm'
        ORDER BY ordinal_position
    ")->fetchAll();
    foreach ($colsEdif as $c) {
        echo "{$c['column_name']} ({$c['data_type']})\n";
    }

    echo "\n=== SAMPLE LOTES INSCIMOB ===\n";
    $lotesSample = $db->query("SELECT id, inscimob, inscimob2 FROM geo.geo_lotes_dm WHERE inscimob IS NOT NULL AND inscimob != '' LIMIT 5")->fetchAll();
    print_r($lotesSample);

    echo "\n=== SAMPLE EDIFICACOES ===\n";
    $edifSample = $db->query("SELECT * FROM geo.geo_edificacoes_dm LIMIT 3")->fetchAll();
    print_r($edifSample);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
