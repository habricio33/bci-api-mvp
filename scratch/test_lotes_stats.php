<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "=== Total in geo_lotes_dm ===\n";
$totalLotes = $db->query("SELECT COUNT(*) FROM geo.geo_lotes_dm")->fetchColumn();
echo "Total lotes: $totalLotes\n";

echo "\n=== Total in GP_IMOVEL ===\n";
$totalGp = $db->query('SELECT COUNT(*) FROM "GP_IMOVEL"')->fetchColumn();
echo "Total GP_IMOVEL: $totalGp\n";

echo "\n=== Join test (geo.geo_lotes_dm l LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\") ===\n";
$stmtJoin = $db->query('
    SELECT 
        COUNT(l.id) as total_lotes,
        COUNT(g."INSC_MUNIC") as lotes_com_gp,
        COUNT(DISTINCT l.id) as lotes_distintos_com_gp
    FROM geo.geo_lotes_dm l
    LEFT JOIN "GP_IMOVEL" g ON TRIM(l.inscimob2) = TRIM(g."INSC_MUNIC")
');
print_r($stmtJoin->fetch(PDO::FETCH_ASSOC));

echo "\n=== Distinct STATUS in GP_IMOVEL ===\n";
$stmtStatus = $db->query('
    SELECT "STATUS", COUNT(*) as count 
    FROM "GP_IMOVEL" 
    GROUP BY "STATUS" 
    ORDER BY count DESC
');
print_r($stmtStatus->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Distinct STATUS when joined with geo_lotes_dm ===\n";
$stmtStatusJoin = $db->query('
    SELECT 
        CASE 
            WHEN g."STATUS" IS NULL OR TRIM(g."STATUS") = \'\' THEN \'Não Informado / Sem Cadastro\'
            ELSE TRIM(g."STATUS")
        END as status, 
        COUNT(l.id) as count 
    FROM geo.geo_lotes_dm l
    LEFT JOIN "GP_IMOVEL" g ON TRIM(l.inscimob2) = TRIM(g."INSC_MUNIC")
    GROUP BY 1
    ORDER BY count DESC
');
print_r($stmtStatusJoin->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Distinct UTILIZACAO_DO_IMOVEL9027 in GP_IMOVEL ===\n";
$stmtUtil = $db->query('
    SELECT "ULTILIZACAO_DO_IMOVEL9027", COUNT(*) as count 
    FROM "GP_IMOVEL" 
    GROUP BY "ULTILIZACAO_DO_IMOVEL9027" 
    ORDER BY count DESC
');
print_r($stmtUtil->fetchAll(PDO::FETCH_ASSOC));

echo "\n=== Distinct UTILIZACAO_DO_IMOVEL9027 when joined with geo_lotes_dm ===\n";
$stmtUtilJoin = $db->query('
    SELECT 
        CASE 
            WHEN g."ULTILIZACAO_DO_IMOVEL9027" IS NULL OR TRIM(g."ULTILIZACAO_DO_IMOVEL9027") = \'\' THEN \'Não Informado\'
            ELSE TRIM(g."ULTILIZACAO_DO_IMOVEL9027")
        END as utilizacao,
        COUNT(l.id) as count 
    FROM geo.geo_lotes_dm l
    LEFT JOIN "GP_IMOVEL" g ON TRIM(l.inscimob2) = TRIM(g."INSC_MUNIC")
    GROUP BY 1
    ORDER BY count DESC
');
print_r($stmtUtilJoin->fetchAll(PDO::FETCH_ASSOC));
