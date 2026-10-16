<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $lat = -20.360730;
    $lng = -40.660915;

    // Busca Lote mais próximo
    $stmtLote = $db->prepare("
        SELECT id, inscimob, inscimob2, 'geo_lotes_dm' as camada,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
               ST_Distance(ST_Transform(ST_Force2D(geom), 4326)::geography, ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography) as dist
        FROM geo_lotes_dm
        ORDER BY dist ASC
        LIMIT 1
    ");
    $stmtLote->execute([':lng' => $lng, ':lat' => $lat]);
    $lote = $stmtLote->fetch();
    echo "Lote found: ID {$lote['id']}, INSC {$lote['inscimob']}, dist {$lote['dist']}\n";
    echo "GeoJSON length: " . strlen($lote['geojson']) . "\n";
    echo "GeoJSON sample: " . substr($lote['geojson'], 0, 100) . "\n";

    // Busca Edificacao
    $stmtEdif = $db->prepare("
        SELECT id, inscimob, caract, beiral, fator, obs, 'geo_edificacoes_dm' as camada,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
               ST_Distance(ST_Transform(ST_Force2D(geom), 4326)::geography, ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography) as dist
        FROM geo_edificacoes_dm
        ORDER BY dist ASC
        LIMIT 1
    ");
    $stmtEdif->execute([':lng' => $lng, ':lat' => $lat]);
    $edif = $stmtEdif->fetch();
    echo "\nEdif found: ID {$edif['id']}, INSC {$edif['inscimob']}, dist {$edif['dist']}\n";
    echo "GeoJSON length: " . strlen($edif['geojson']) . "\n";

    // Testar busca de lote por inscimob quando tiver edif
    if ($edif && !empty($edif['inscimob'])) {
        $prefix = substr(trim($edif['inscimob']), 0, 11);
        $stmtLoteByInsc = $db->prepare("
            SELECT id, inscimob, ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson
            FROM geo_lotes_dm
            WHERE inscimob = :prefix OR inscimob2 LIKE :prefix_like
            LIMIT 1
        ");
        $stmtLoteByInsc->execute([':prefix' => $prefix, ':prefix_like' => $prefix . '%']);
        $loteByInsc = $stmtLoteByInsc->fetch();
        echo "\nLote by Inscription ({$prefix}): " . ($loteByInsc ? "Found ID {$loteByInsc['id']}" : "Not found") . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
