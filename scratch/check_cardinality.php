<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "=== Check 1-to-many in GP_IMOVEL for same INSC_MUNIC ===\n";
$stmt = $db->query('
    SELECT "INSC_MUNIC", COUNT(*) as cnt 
    FROM "GP_IMOVEL" 
    GROUP BY "INSC_MUNIC" 
    HAVING COUNT(*) > 1 
    ORDER BY cnt DESC 
    LIMIT 10
');
$dups = $stmt->fetchAll();
echo "Duplicate INSC_MUNIC in GP_IMOVEL count: " . count($dups) . "\n";
print_r($dups);

echo "\n=== Check join cardinality between geo_lotes_dm and GP_IMOVEL ===\n";
$stmt2 = $db->query('
    SELECT l.id, l.inscimob2, COUNT(g."ID") as gp_count
    FROM geo.geo_lotes_dm l
    LEFT JOIN "GP_IMOVEL" g ON TRIM(l.inscimob2) = TRIM(g."INSC_MUNIC")
    GROUP BY l.id, l.inscimob2
    HAVING COUNT(g."ID") > 1
    LIMIT 10
');
$lotDups = $stmt2->fetchAll();
echo "Lotes matching multiple GP_IMOVEL rows: " . count($lotDups) . "\n";
print_r($lotDups);

echo "\n=== Check lotes with non-empty inscimob2 vs inscimob ===\n";
$stmt3 = $db->query('
    SELECT 
        COUNT(*) as total,
        COUNT(CASE WHEN inscimob2 IS NOT NULL AND TRIM(inscimob2) <> \'\' THEN 1 END) as com_inscimob2,
        COUNT(CASE WHEN inscimob IS NOT NULL AND TRIM(inscimob) <> \'\' THEN 1 END) as com_inscimob
    FROM geo.geo_lotes_dm
');
print_r($stmt3->fetch());
