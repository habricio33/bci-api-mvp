<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$tables = $db->query("
    SELECT table_schema, table_name 
    FROM information_schema.tables 
    WHERE table_name LIKE '%rede%' OR table_name LIKE '%agua%' OR table_name LIKE '%esgoto%'
")->fetchAll();

print_r($tables);
