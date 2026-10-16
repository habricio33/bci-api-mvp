<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    $term = 'Pedra';
    $stmt = $db->prepare("
        SELECT 
            id2 AS id,
            COALESCE(comunidade, nome, 'Sem nome') AS bairro,
            comunidade,
            nome,
            ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) AS lng,
            ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) AS lat
        FROM geo.geo_comunidades_dm
        WHERE comunidade ILIKE :term OR nome ILIKE :term
        ORDER BY COALESCE(comunidade, nome) ASC, id2 ASC
        LIMIT 50
    ");

    $stmt->execute([':term' => '%' . $term . '%']);
    $rows = $stmt->fetchAll();

    print_r($rows);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
