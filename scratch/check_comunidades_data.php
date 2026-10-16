<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $stmtSample = $db->query("SELECT id2, nome, comunidade FROM geo_comunidades_dm LIMIT 10");
    print_r($stmtSample->fetchAll());
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
