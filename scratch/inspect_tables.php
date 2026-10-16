<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // List all tables
    $tables = $db->query("
        SELECT table_schema, table_name 
        FROM information_schema.tables 
        WHERE table_schema NOT IN ('pg_catalog', 'information_schema')
        ORDER BY table_schema, table_name
    ")->fetchAll();

    echo "=== TABLES ===\n";
    foreach ($tables as $t) {
        echo "{$t['table_schema']}.{$t['table_name']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
