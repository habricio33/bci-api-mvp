<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $lat = -20.360871295615826;
    $lng = -40.6604877232192;

    $stmtLote = $db->prepare("
        SELECT id, inscimob, inscimob2, 'geo_lotes_dm' as camada,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
               ST_Distance(ST_Transform(ST_Force2D(geom), 4326)::geography, ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography) as dist
        FROM geo_lotes_dm
        WHERE ST_DWithin(ST_Transform(ST_Force2D(geom), 4326)::geography, ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography, 2)
        ORDER BY dist ASC
        LIMIT 1
    ");
    $stmtLote->execute([':lng' => $lng, ':lat' => $lat]);
    $lote = $stmtLote->fetch();

    $stmtEdif = $db->prepare("
        SELECT id, inscimob, caract, beiral, fator, obs, 'geo_edificacoes_dm' as camada,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
               ST_Distance(ST_Transform(ST_Force2D(geom), 4326)::geography, ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography) as dist
        FROM geo_edificacoes_dm
        WHERE ST_DWithin(ST_Transform(ST_Force2D(geom), 4326)::geography, ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography, 2)
        ORDER BY dist ASC
        LIMIT 1
    ");
    $stmtEdif->execute([':lng' => $lng, ':lat' => $lat]);
    $edif = $stmtEdif->fetch();

    $lotePrefix = null;
    if ($lote && !empty($lote['inscimob'])) {
        $lotePrefix = trim($lote['inscimob']);
    } elseif ($lote && !empty($lote['inscimob2'])) {
        $raw = trim($lote['inscimob2']);
        $lotePrefix = (strlen($raw) >= 11) ? substr($raw, 0, 11) : $raw;
    } elseif ($edif && !empty($edif['inscimob'])) {
        $raw = trim($edif['inscimob']);
        $lotePrefix = (strlen($raw) >= 11) ? substr($raw, 0, 11) : $raw;
    }

    if (!$lote && $lotePrefix) {
        $stmtLoteByPrefix = $db->prepare("
            SELECT id, inscimob, inscimob2, 'geo_lotes_dm' as camada,
                   ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson
            FROM geo_lotes_dm
            WHERE inscimob = :prefix OR inscimob2 LIKE :prefix_like
            LIMIT 1
        ");
        $stmtLoteByPrefix->execute([':prefix' => $lotePrefix, ':prefix_like' => $lotePrefix . '%']);
        $lote = $stmtLoteByPrefix->fetch();
    }

    $loteGeojson = $lote && !empty($lote['geojson']) ? json_decode($lote['geojson'], true) : null;
    $edifGeojson = $edif && !empty($edif['geojson']) ? json_decode($edif['geojson'], true) : null;
    $highlightGeojson = $loteGeojson ?? $edifGeojson;

    echo json_encode([
        'found' => true,
        'lote_prefix' => $lotePrefix,
        'has_lote_geom' => $loteGeojson !== null,
        'has_edif_geom' => $edifGeojson !== null,
        'highlight_type' => $highlightGeojson['type'] ?? null
    ]);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
