<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $inscMunic = '01010040238001';
    $lotePrefix = (strlen($inscMunic) >= 11) ? substr($inscMunic, 0, 11) : $inscMunic;

    $stmtLote = $db->prepare('
        SELECT id, inscimob, inscimob2,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
               ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lng,
               ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lat
        FROM geo_lotes_dm
        WHERE inscimob = :prefix OR inscimob2 LIKE :prefix_like
        LIMIT 1
    ');
    $stmtLote->execute([':prefix' => $lotePrefix, ':prefix_like' => $lotePrefix . '%']);
    $loteData = $stmtLote->fetch();

    $croquiData = [
        'has_geom' => false,
        'lat' => null,
        'lng' => null,
        'lote_selecionado' => null,
        'vizinhos_lotes' => [],
        'edificacoes' => []
    ];

    if ($loteData && !empty($loteData['geojson'])) {
        $croquiData['has_geom'] = true;
        $croquiData['lat'] = (float)$loteData['lat'];
        $croquiData['lng'] = (float)$loteData['lng'];
        $croquiData['lote_selecionado'] = json_decode($loteData['geojson'], true);

        $stmtVizinhos = $db->prepare('
            SELECT id, inscimob,
                   ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
                   (CASE WHEN inscimob = :prefix THEN true ELSE false END) as is_selected
            FROM geo_lotes_dm
            WHERE ST_DWithin(
                ST_Transform(ST_Force2D(geom), 4326)::geography,
                ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography,
                120
            )
            LIMIT 60
        ');
        $stmtVizinhos->execute([':prefix' => $lotePrefix, ':lng' => $loteData['lng'], ':lat' => $loteData['lat']]);
        $vizinhosRaw = $stmtVizinhos->fetchAll();
        foreach ($vizinhosRaw as $v) {
            $croquiData['vizinhos_lotes'][] = [
                'id' => $v['id'],
                'inscimob' => $v['inscimob'],
                'is_selected' => (bool)$v['is_selected'],
                'geojson' => json_decode($v['geojson'], true)
            ];
        }

        $stmtEdifs = $db->prepare('
            SELECT id, inscimob,
                   ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson
            FROM geo_edificacoes_dm
            WHERE ST_DWithin(
                ST_Transform(ST_Force2D(geom), 4326)::geography,
                ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography,
                120
            )
            LIMIT 60
        ');
        $stmtEdifs->execute([':lng' => $loteData['lng'], ':lat' => $loteData['lat']]);
        $edifsRaw = $stmtEdifs->fetchAll();
        foreach ($edifsRaw as $e) {
            $croquiData['edificacoes'][] = [
                'id' => $e['id'],
                'inscimob' => $e['inscimob'],
                'geojson' => json_decode($e['geojson'], true)
            ];
        }
    }

    echo "Croqui Data Prepared:\n";
    echo "has_geom: " . ($croquiData['has_geom'] ? 'true' : 'false') . "\n";
    echo "lat: {$croquiData['lat']}, lng: {$croquiData['lng']}\n";
    echo "vizinhos_lotes count: " . count($croquiData['vizinhos_lotes']) . "\n";
    echo "edificacoes count: " . count($croquiData['edificacoes']) . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
