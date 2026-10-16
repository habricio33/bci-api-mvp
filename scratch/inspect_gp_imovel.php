<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "=== GP_IMOVEL COLUMNS ===\n";
    $cols = $db->query("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'GP_IMOVEL'
        ORDER BY ordinal_position
    ")->fetchAll();
    foreach ($cols as $c) {
        echo "{$c['column_name']} ({$c['data_type']})\n";
    }

    echo "\n=== GP_IMOVEL SAMPLE ===\n";
    $sample = $db->query('SELECT * FROM "GP_IMOVEL" LIMIT 2')->fetchAll();
    print_r($sample);

    echo "\n=== GEO_LOTES_DM COLUMNS ===\n";
    $colsLotes = $db->query("
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = 'geo_lotes_dm'
        ORDER BY ordinal_position
    ")->fetchAll();
    foreach ($colsLotes as $c) {
        echo "{$c['column_name']} ({$c['data_type']})\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
