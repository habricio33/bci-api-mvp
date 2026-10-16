<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$start = microtime(true);

$query = '
    SELECT json_build_object(
        \'type\', \'FeatureCollection\',
        \'features\', COALESCE(json_agg(features), \'[]\'::json)
    ) as geojson
    FROM (
        SELECT json_build_object(
            \'type\', \'Feature\',
            \'geometry\', ST_AsGeoJSON(ST_Transform(ST_Force2D(l.geom), 4326))::json,
            \'properties\', json_build_object(
                \'id\', l.id,
                \'inscimob\', l.inscimob,
                \'inscimob2\', l.inscimob2,
                \'obs\', l.obs,
                \'has_cadastro\', (g."INSC_MUNIC" IS NOT NULL),
                \'status_cadastral\', CASE WHEN g."INSC_MUNIC" IS NOT NULL THEN \'Com Cadastro\' ELSE \'Sem Cadastro\' END,
                \'status\', COALESCE(g."STATUS", \'Não Informado\'),
                \'utilizacao\', CASE 
                    WHEN g."ULTILIZACAO_DO_IMOVEL9027" IS NOT NULL AND TRIM(g."ULTILIZACAO_DO_IMOVEL9027") <> \'\' 
                    THEN TRIM(g."ULTILIZACAO_DO_IMOVEL9027") 
                    ELSE \'Não Informado\' 
                END,
                \'ocupacao\', g."OCUPACAO9025",
                \'proprietario\', g."NOMEDOCONTRIBUINTE",
                \'area_terreno\', g."AREA_DO_TERRENO13065"
            )
        ) as features
        FROM geo.geo_lotes_dm l
        LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
        WHERE l.geom && ST_Transform(ST_MakeEnvelope(-40.66, -20.38, -40.64, -20.36, 4326), 31984)
    ) row
';

$stmt = $db->query($query);
$res = $stmt->fetch();
$elapsed = (microtime(true) - $start) * 1000;

echo "Elapsed with BBOX: " . round($elapsed, 2) . "ms\n";
