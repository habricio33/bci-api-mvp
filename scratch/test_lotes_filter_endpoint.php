<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "=== Grouping Status Cadastral ===\n";
$stmtStatus = $db->query('
    SELECT 
        CASE 
            WHEN g."INSC_MUNIC" IS NOT NULL THEN \'Com Cadastro\'
            ELSE \'Não Cadastrado (Em branco)\'
        END as valor,
        COUNT(l.id) as total,
        CASE 
            WHEN g."INSC_MUNIC" IS NOT NULL THEN 0
            ELSE 1
        END as is_blank
    FROM geo.geo_lotes_dm l
    LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
    GROUP BY 1, 3
    ORDER BY is_blank ASC, total DESC
');
print_r($stmtStatus->fetchAll());

echo "\n=== Grouping Utilização ===\n";
$stmtUtil = $db->query('
    SELECT 
        CASE 
            WHEN g."ULTILIZACAO_DO_IMOVEL9027" IS NOT NULL AND TRIM(g."ULTILIZACAO_DO_IMOVEL9027") <> \'\' 
            THEN TRIM(g."ULTILIZACAO_DO_IMOVEL9027")
            ELSE \'Não Informado\'
        END as valor,
        COUNT(l.id) as total,
        CASE 
            WHEN g."ULTILIZACAO_DO_IMOVEL9027" IS NOT NULL AND TRIM(g."ULTILIZACAO_DO_IMOVEL9027") <> \'\' THEN 0
            ELSE 1
        END as is_blank
    FROM geo.geo_lotes_dm l
    LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
    GROUP BY 1, 3
    ORDER BY is_blank ASC, total DESC
');
print_r($stmtUtil->fetchAll());

echo "\n=== Grouping Ocupação ===\n";
$stmtOcup = $db->query('
    SELECT 
        CASE 
            WHEN g."OCUPACAO9025" IS NOT NULL AND TRIM(g."OCUPACAO9025") <> \'\' 
            THEN TRIM(g."OCUPACAO9025")
            ELSE \'Não Informado\'
        END as valor,
        COUNT(l.id) as total,
        CASE 
            WHEN g."OCUPACAO9025" IS NOT NULL AND TRIM(g."OCUPACAO9025") <> \'\' THEN 0
            ELSE 1
        END as is_blank
    FROM geo.geo_lotes_dm l
    LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
    GROUP BY 1, 3
    ORDER BY is_blank ASC, total DESC
');
print_r($stmtOcup->fetchAll());
