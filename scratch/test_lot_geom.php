<?php
require_once __DIR__ . '/../config/db.php';
$db = getDBConnection();
$stmt = $db->query('
    SELECT id, inscimob, ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson 
    FROM geo_lotes_dm 
    WHERE inscimob IS NOT NULL 
    LIMIT 2
');
$rows = $stmt->fetchAll();
foreach ($rows as $r) {
    echo "ID: " . $r['id'] . " | INSC: " . $r['inscimob'] . "\n";
    echo "GeoJSON: " . substr($r['geojson'], 0, 100) . "...\n\n";
}
