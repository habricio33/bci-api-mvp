<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $stmt = $db->query('
        SELECT column_name, data_type 
        FROM information_schema.columns 
        WHERE table_name = \'GP_IMOVEL_FOTOS\' OR table_name = \'gp_imovel_fotos\'
    ');
    $cols = $stmt->fetchAll();
    echo "Columns in GP_IMOVEL_FOTOS:\n";
    print_r($cols);

    $stmtSample = $db->query('SELECT * FROM public."GP_IMOVEL_FOTOS" LIMIT 5');
    $sample = $stmtSample->fetchAll();
    echo "\nSample rows:\n";
    print_r($sample);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
