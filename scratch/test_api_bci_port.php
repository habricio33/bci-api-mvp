<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $inscMunic = '01010040238001';
    $lotePrefix = (strlen($inscMunic) >= 11) ? substr($inscMunic, 0, 11) : $inscMunic;

    // 1. Imovel
    $stmt = $db->prepare('SELECT "ID", "INSC_MUNIC", "NOMEDOCONTRIBUINTE" FROM public."GP_IMOVEL" WHERE "INSC_MUNIC" = :insc LIMIT 1');
    $stmt->execute([':insc' => $inscMunic]);
    $imovel = $stmt->fetch();

    // 2. Foto
    $stmtFoto = $db->prepare('SELECT "ID", "INSC_MUNIC", "NOME_FOTO" FROM public."GP_IMOVEL_FOTOS" WHERE "INSC_MUNIC" = :insc LIMIT 1');
    $stmtFoto->execute([':insc' => $inscMunic]);
    $foto = $stmtFoto->fetch();

    // 3. Croqui
    $stmtLote = $db->prepare('
        SELECT id, inscimob,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
               ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lng,
               ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lat
        FROM geo_lotes_dm
        WHERE inscimob = :prefix OR inscimob2 LIKE :prefix_like
        LIMIT 1
    ');
    $stmtLote->execute([':prefix' => $lotePrefix, ':prefix_like' => $lotePrefix . '%']);
    $lote = $stmtLote->fetch();

    echo "=== Full BCI Test Result ===\n";
    echo "Imovel: " . ($imovel ? "Found ({$imovel['NOMEDOCONTRIBUINTE']})" : "Not found") . "\n";
    echo "Foto: " . ($foto ? "Found ({$foto['NOME_FOTO']})" : "Not found") . "\n";
    echo "Croqui Lote: " . ($lote ? "Found (ID {$lote['id']}, lat {$lote['lat']}, lng {$lote['lng']})" : "Not found") . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
