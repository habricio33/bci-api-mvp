<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $stmt = $db->query('SELECT * FROM public."GP_IMOVEL_FOTOS" ORDER BY "ID" ASC');
    $rows = $stmt->fetchAll();
    echo "Total rows in GP_IMOVEL_FOTOS: " . count($rows) . "\n";
    foreach ($rows as $r) {
        $file = basename(str_replace('\\', '/', $r['NOME_FOTO']));
        $exists = file_exists(__DIR__ . '/../img/fotos_fachada/' . $file);
        echo "ID: {$r['ID']} | INSC: {$r['INSC_MUNIC']} | File: {$file} | Exists on disk: " . ($exists ? 'YES' : 'NO') . " | STATUS: {$r['STATUS']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
