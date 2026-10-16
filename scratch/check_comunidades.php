<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $stmt = $db->query("SELECT column_name, data_type FROM information_schema.columns WHERE table_name = 'geo_comunidades_dm'");
    print_r($stmt->fetchAll());

    $stmtSample = $db->query("SELECT id, id2, nome, comunidade, distrito FROM geo_comunidades_dm LIMIT 5");
    print_r($stmtSample->fetchAll());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
