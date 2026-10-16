<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$stmt = $db->query('
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN g."INSC_MUNIC" IS NOT NULL THEN 1 END) as com_cadastro,
        COUNT(CASE WHEN g."INSC_MUNIC" IS NULL THEN 1 END) as sem_cadastro
    FROM geo.geo_lotes_dm l
    LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
');
print_r($stmt->fetch());

$sampleComCadastro = $db->query('
    SELECT l.id, l.inscimob, l.inscimob2, g."INSC_MUNIC", g."ULTILIZACAO_DO_IMOVEL9027", g."NOMEDOCONTRIBUINTE"
    FROM geo.geo_lotes_dm l
    INNER JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
    LIMIT 5
')->fetchAll();
print_r($sampleComCadastro);
