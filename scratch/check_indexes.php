<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "=== Existing Indexes on geo_lotes_dm ===\n";
$idx1 = $db->query("SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'geo_lotes_dm'")->fetchAll();
print_r($idx1);

echo "=== Existing Indexes on GP_IMOVEL ===\n";
$idx2 = $db->query("SELECT indexname, indexdef FROM pg_indexes WHERE tablename = 'GP_IMOVEL'")->fetchAll();
print_r($idx2);
