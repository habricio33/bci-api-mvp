<?php
/**
 * Endpoint de Análise Espacial por Área de Influência (Buffer) para Lotes e Edificações.
 * 
 * Parâmetros GET/POST:
 *  - lat (float): Latitude do centro da intervenção (WGS84)
 *  - lng (float): Longitude do centro da intervenção (WGS84)
 *  - raio (float): Raio de influência em metros (padrão: 100, min: 5, max: 1000)
 *  - tipo (string): 'lotes', 'edificacoes' ou 'ambos' (padrão: 'ambos')
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'error' => true,
        'message' => 'Não autorizado. Por favor, faça login.'
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/db.php';

$lat = isset($_REQUEST['lat']) ? (float) $_REQUEST['lat'] : null;
$lng = isset($_REQUEST['lng']) ? (float) $_REQUEST['lng'] : null;
$raio = isset($_REQUEST['raio']) ? max(5, min(1000, (float) $_REQUEST['raio'])) : 100;
$tipo = isset($_REQUEST['tipo']) ? trim(strtolower($_REQUEST['tipo'])) : 'ambos';

if (!in_array($tipo, ['lotes', 'edificacoes', 'ambos'])) {
    $tipo = 'ambos';
}

if ($lat === null || $lng === null) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'Coordenadas (lat, lng) são obrigatórias.'
    ]);
    exit;
}

$db = getDBConnection();

$response = [
    'success' => true,
    'params' => [
        'lat' => $lat,
        'lng' => $lng,
        'raio_m' => $raio,
        'tipo' => $tipo
    ],
    'sumario' => [
        'total_lotes' => 0,
        'lotes_com_cadastro' => 0,
        'lotes_sem_cadastro' => 0,
        'area_total_lotes_m2' => 0,
        'total_edificacoes' => 0,
        'edificacoes_com_cadastro' => 0,
        'edificacoes_sem_cadastro' => 0,
        'edificacoes_com_agua' => 0,
        'edificacoes_com_esgoto' => 0
    ],
    'lotes' => [],
    'edificacoes' => [],
    'geojson_destaques' => [
        'type' => 'FeatureCollection',
        'features' => []
    ]
];

try {
    $params = [
        ':lat' => $lat,
        ':lng' => $lng,
        ':raio' => $raio
    ];

    // =========================================================================
    // 1. CONSULTA DE LOTES NO BUFFER
    // =========================================================================
    if ($tipo === 'lotes' || $tipo === 'ambos') {
        try {
            $stmtLotes = $db->prepare("
                WITH buf AS (
                    SELECT ST_Buffer(ST_Transform(ST_SetSRID(ST_Point(:lng, :lat), 4326), 31984), :raio) AS geom_utm
                )
                SELECT 
                    l.id,
                    l.inscimob,
                    l.inscimob2,
                    COALESCE(l.inscimob2, g.\"INSC_MUNIC\", l.inscimob, '-') as insc_munic,
                    (g.\"INSC_MUNIC\" IS NOT NULL) as has_cadastro,
                    CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Com Cadastro' ELSE 'Não Cadastrado' END as status_cadastral,
                    COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-') as contribuinte,
                    COALESCE(g.\"NOMELOGRADOURO\", '-') as logradouro,
                    COALESCE(g.\"NOMEBAIRRO\", '-') as bairro,
                    COALESCE(g.\"ULTILIZACAO_DO_IMOVEL9027\", '-') as utilizacao,
                    COALESCE(g.\"OCUPACAO9025\", '-') as ocupacao,
                    COALESCE(g.\"AREA_DO_TERRENO13065\", '-') as area_terreno,
                    ROUND(ST_Distance(ST_Centroid(ST_Force2D(l.geom)), ST_Centroid(buf.geom_utm))::numeric, 1) as distancia_centro_m,
                    ROUND(ST_Area(ST_Intersection(ST_Force2D(l.geom), buf.geom_utm))::numeric, 2) as area_interceptada_m2,
                    ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) as lng,
                    ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) as lat
                FROM geo.geo_lotes_dm l
                JOIN buf ON ST_Intersects(l.geom, buf.geom_utm)
                LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\"
                ORDER BY distancia_centro_m ASC, l.id ASC;
            ");
            $stmtLotes->execute($params);
            $lotes = $stmtLotes->fetchAll(PDO::FETCH_ASSOC);

            $response['lotes'] = $lotes;
            $response['sumario']['total_lotes'] = count($lotes);

            $comCad = 0;
            $semCad = 0;
            $areaTotal = 0;

            foreach ($lotes as $lote) {
                if ($lote['has_cadastro']) {
                    $comCad++;
                } else {
                    $semCad++;
                }
                $areaTotal += (float) ($lote['area_interceptada_m2'] ?? 0);
            }

            $response['sumario']['lotes_com_cadastro'] = $comCad;
            $response['sumario']['lotes_sem_cadastro'] = $semCad;
            $response['sumario']['area_total_lotes_m2'] = round($areaTotal, 2);

        } catch (Exception $e) {
            error_log('Erro ao calcular lotes no buffer: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 2. CONSULTA DE EDIFICAÇÕES NO BUFFER
    // =========================================================================
    if ($tipo === 'edificacoes' || $tipo === 'ambos') {
        try {
            $stmtEdif = $db->prepare("
                WITH buf AS (
                    SELECT ST_Buffer(ST_Transform(ST_SetSRID(ST_Point(:lng, :lat), 4326), 31984), :raio) AS geom_utm
                )
                SELECT 
                    e.id,
                    e.inscimob,
                    COALESCE(g.\"INSC_MUNIC\", e.inscimob, '-') as insc_munic,
                    (g.\"INSC_MUNIC\" IS NOT NULL) as has_cadastro,
                    CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Com Cadastro' ELSE 'Não Cadastrado' END as status_cadastral,
                    COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-') as contribuinte,
                    COALESCE(g.\"NOMELOGRADOURO\", '-') as logradouro,
                    COALESCE(g.\"NOMEBAIRRO\", '-') as bairro,
                    COALESCE(g.\"TIPO_DE_CONSTRUCAO10042\", '-') as tipo_construcao,
                    COALESCE(g.\"ESTRUTURA10043\", '-') as estrutura,
                    COALESCE(g.\"ESTADO_DE_CONSERVACAO10044\", '-') as estado_conservacao,
                    COALESCE(g.\"SITUACAO_DO_IMOVEL10048\", '-') as situacao_imovel,
                    COALESCE(g.\"ESGOTO12001\", '-') as esgoto,
                    COALESCE(g.\"AGUA12002\", '-') as agua,
                    COALESCE(g.\"AREA_DA_UNIDADE13066\", '-') as area_unidade,
                    COALESCE(g.\"AREA_TOTAL_DA_EDIFICACAO13067\", '-') as area_total_edificacao,
                    ROUND(ST_Distance(ST_Centroid(ST_Force2D(e.geom)), ST_Centroid(buf.geom_utm))::numeric, 1) as distancia_centro_m,
                    ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326))) AS numeric), 6) as lng,
                    ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326))) AS numeric), 6) as lat
                FROM geo.geo_edificacoes_dm e
                JOIN buf ON ST_Intersects(e.geom, buf.geom_utm)
                LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
                ORDER BY distancia_centro_m ASC, e.id ASC;
            ");
            $stmtEdif->execute($params);
            $edificacoes = $stmtEdif->fetchAll(PDO::FETCH_ASSOC);

            $response['edificacoes'] = $edificacoes;
            $response['sumario']['total_edificacoes'] = count($edificacoes);

            $comCadEdif = 0;
            $semCadEdif = 0;
            $comAgua = 0;
            $comEsgoto = 0;

            foreach ($edificacoes as $ed) {
                if ($ed['has_cadastro']) {
                    $comCadEdif++;
                } else {
                    $semCadEdif++;
                }
                if (stripos($ed['agua'], 'sim') !== false) {
                    $comAgua++;
                }
                if (stripos($ed['esgoto'], 'sim') !== false) {
                    $comEsgoto++;
                }
            }

            $response['sumario']['edificacoes_com_cadastro'] = $comCadEdif;
            $response['sumario']['edificacoes_sem_cadastro'] = $semCadEdif;
            $response['sumario']['edificacoes_com_agua'] = $comAgua;
            $response['sumario']['edificacoes_com_esgoto'] = $comEsgoto;

        } catch (Exception $e) {
            error_log('Erro ao calcular edificações no buffer: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // 3. GEOJSON DE DESTAQUES (Polígonos das feições interceptadas)
    // =========================================================================
    try {
        $geojsonQueries = [];

        if ($tipo === 'lotes' || $tipo === 'ambos') {
            $geojsonQueries[] = "
                SELECT json_build_object(
                    'type', 'Feature',
                    'geometry', ST_AsGeoJSON(ST_Transform(ST_Force2D(l.geom), 4326))::json,
                    'properties', json_build_object(
                        'tipo_camada', 'geo_lotes_dm',
                        'id', l.id,
                        'insc_munic', COALESCE(l.inscimob2, g.\"INSC_MUNIC\", l.inscimob, '-'),
                        'contribuinte', COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-'),
                        'logradouro', COALESCE(g.\"NOMELOGRADOURO\", '-'),
                        'bairro', COALESCE(g.\"NOMEBAIRRO\", '-'),
                        'has_cadastro', (g.\"INSC_MUNIC\" IS NOT NULL),
                        'status_cadastral', CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Com Cadastro' ELSE 'Não Cadastrado' END,
                        'area_terreno', COALESCE(g.\"AREA_DO_TERRENO13065\", '-')
                    )
                ) AS features
                FROM geo.geo_lotes_dm l
                JOIN buf ON ST_Intersects(l.geom, buf.geom_utm)
                LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\"
            ";
        }

        if ($tipo === 'edificacoes' || $tipo === 'ambos') {
            $geojsonQueries[] = "
                SELECT json_build_object(
                    'type', 'Feature',
                    'geometry', ST_AsGeoJSON(ST_Transform(ST_Force2D(e.geom), 4326))::json,
                    'properties', json_build_object(
                        'tipo_camada', 'geo_edificacoes_dm',
                        'id', e.id,
                        'insc_munic', COALESCE(g.\"INSC_MUNIC\", e.inscimob, '-'),
                        'contribuinte', COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-'),
                        'logradouro', COALESCE(g.\"NOMELOGRADOURO\", '-'),
                        'bairro', COALESCE(g.\"NOMEBAIRRO\", '-'),
                        'tipo_construcao', COALESCE(g.\"TIPO_DE_CONSTRUCAO10042\", '-'),
                        'conservacao', COALESCE(g.\"ESTADO_DE_CONSERVACAO10044\", '-'),
                        'has_cadastro', (g.\"INSC_MUNIC\" IS NOT NULL),
                        'status_cadastral', CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Com Cadastro' ELSE 'Não Cadastrado' END,
                        'esgoto', COALESCE(g.\"ESGOTO12001\", '-'),
                        'agua', COALESCE(g.\"AGUA12002\", '-')
                    )
                ) AS features
                FROM geo.geo_edificacoes_dm e
                JOIN buf ON ST_Intersects(e.geom, buf.geom_utm)
                LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
            ";
        }

        if (!empty($geojsonQueries)) {
            $unionSql = implode(' UNION ALL ', $geojsonQueries);
            $stmtGeo = $db->prepare("
                WITH buf AS (
                    SELECT ST_Buffer(ST_Transform(ST_SetSRID(ST_Point(:lng, :lat), 4326), 31984), :raio) AS geom_utm
                )
                SELECT json_build_object(
                    'type', 'FeatureCollection',
                    'features', COALESCE(json_agg(features), '[]'::json)
                ) AS geojson
                FROM ($unionSql) q;
            ");
            $stmtGeo->execute($params);
            $geoRow = $stmtGeo->fetch(PDO::FETCH_ASSOC);
            if ($geoRow && !empty($geoRow['geojson'])) {
                $response['geojson_destaques'] = json_decode($geoRow['geojson'], true);
            }
        }
    } catch (Exception $e) {
        error_log('Erro ao gerar GeoJSON de destaque do buffer: ' . $e->getMessage());
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro interno ao processar buffer espacial: ' . $e->getMessage()
    ]);
}
