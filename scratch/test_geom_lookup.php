<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $testInsc = '01080100070001';
    $prefix11 = substr($testInsc, 0, 11);

    echo "Testing for insc: $testInsc (prefix11: $prefix11)\n";

    // 1. Try finding in geo_lotes_dm
    $stmtLote = $db->prepare("
        SELECT id, inscimob, inscimob2,
               ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lng,
               ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lat,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson
        FROM geo.geo_lotes_dm
        WHERE inscimob = :insc 
           OR inscimob = :prefix11
           OR inscimob2 = :insc
           OR inscimob2 LIKE :prefix11_like
        LIMIT 1
    ");
    $stmtLote->execute([
        ':insc' => $testInsc,
        ':prefix11' => $prefix11,
        ':prefix11_like' => $prefix11 . '%'
    ]);
    $lote = $stmtLote->fetch();
    echo "Lote found:\n";
    print_r($lote ? ['id' => $lote['id'], 'inscimob' => $lote['inscimob'], 'lat' => $lote['lat'], 'lng' => $lote['lng']] : 'None');

    // 2. Try finding in geo_edificacoes_dm
    $stmtEdif = $db->prepare("
        SELECT id, inscimob,
               ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lng,
               ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lat,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson
        FROM geo.geo_edificacoes_dm
        WHERE inscimob = :insc 
           OR inscimob = :prefix11
           OR inscimob LIKE :prefix11_like
        LIMIT 1
    ");
    $stmtEdif->execute([
        ':insc' => $testInsc,
        ':prefix11' => $prefix11,
        ':prefix11_like' => $prefix11 . '%'
    ]);
    $edif = $stmtEdif->fetch();
    echo "\nEdificacao found:\n";
    print_r($edif ? ['id' => $edif['id'], 'inscimob' => $edif['inscimob'], 'lat' => $edif['lat'], 'lng' => $edif['lng']] : 'None');

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
