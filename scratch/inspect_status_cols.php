<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$cols = $db->query("
    SELECT column_name, data_type 
    FROM information_schema.columns 
    WHERE table_name = 'GP_IMOVEL'
    ORDER BY ordinal_position
")->fetchAll();

foreach ($cols as $c) {
    if (preg_match('/status|situacao|cadast|baix|ativo|uso|ocup/i', $c['column_name'])) {
        echo "Match: {$c['column_name']} ({$c['data_type']})\n";
        $vals = $db->query("SELECT \"{$c['column_name']}\", COUNT(*) as cnt FROM \"GP_IMOVEL\" GROUP BY 1 ORDER BY 2 DESC LIMIT 10")->fetchAll();
        print_r($vals);
    }
}
