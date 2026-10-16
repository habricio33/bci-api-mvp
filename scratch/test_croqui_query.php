<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $inscMunic = '01010040238001';
    $prefix = substr($inscMunic, 0, 11);

    // 1. Busca Lote Selecionado
    $stmtLote = $db->prepare('
        SELECT id, inscimob, inscimob2,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
               ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lng,
               ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lat
        FROM geo_lotes_dm
        WHERE inscimob = :prefix OR inscimob2 LIKE :prefix_like
        LIMIT 1
    ');
    $stmtLote->execute([':prefix' => $prefix, ':prefix_like' => $prefix . '%']);
    $lote = $stmtLote->fetch();

    echo "Lote Selecionado:\n";
    print_r($lote ? "Found ID {$lote['id']}, lat {$lote['lat']}, lng {$lote['lng']}" : "Not found");
    echo "\n";

    if ($lote) {
        // 2. Busca Lotes Vizinhos para o Croqui de Localização (raio de 120m)
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
        $stmtVizinhos->execute([':prefix' => $prefix, ':lng' => $lote['lng'], ':lat' => $lote['lat']]);
        $vizinhos = $stmtVizinhos->fetchAll();
        echo "Lotes vizinhos encontrados no raio de 120m: " . count($vizinhos) . "\n";

        // 3. Busca Edificações no Lote/Vizinhança
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
        $stmtEdifs->execute([':lng' => $lote['lng'], ':lat' => $lote['lat']]);
        $edifs = $stmtEdifs->fetchAll();
        echo "Edificações vizinhas encontradas: " . count($edifs) . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
