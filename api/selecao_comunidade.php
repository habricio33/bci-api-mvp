<?php
/**
 * Endpoint da Ferramenta de Seleção Espacial por Comunidade (geo.geo_comunidades_dm).
 * Permite selecionar uma comunidade (por ID ou clique no mapa) e analisar / quantificar
 * todos os lotes (geo.geo_lotes_dm) e edificações (geo.geo_edificacoes_dm) contidos nela.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (php_sapi_name() !== 'cli') {
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
}

require_once __DIR__ . '/../config/db.php';

$db = getDBConnection();

$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'analisar';

try {
    // =========================================================================
    // 1. LISTAR TODAS AS COMUNIDADES (Para dropdown/autocomplete)
    // =========================================================================
    if ($action === 'listar') {
        header('Content-Type: application/json; charset=utf-8');
        $stmt = $db->query("
            SELECT 
                id2 AS id,
                COALESCE(comunidade, nome, 'Sem nome') AS comunidade,
                nome AS municipio,
                altitude_m,
                ROUND((ST_Area(geom) / 10000)::numeric, 2) AS area_ha,
                ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) AS numeric), 6) AS lng,
                ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) AS numeric), 6) AS lat
            FROM geo.geo_comunidades_dm
            ORDER BY COALESCE(comunidade, nome) ASC;
        ");
        $comunidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total' => count($comunidades),
            'data' => $comunidades
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // =========================================================================
    // 2. IDENTIFICAR COMUNIDADE (Por ID ou Coordenadas lat/lng)
    // =========================================================================
    $comunidadeId = isset($_REQUEST['id']) ? (int) $_REQUEST['id'] : null;
    $lat = isset($_REQUEST['lat']) ? (float) $_REQUEST['lat'] : null;
    $lng = isset($_REQUEST['lng']) ? (float) $_REQUEST['lng'] : null;

    if (!$comunidadeId && ($lat !== null && $lng !== null)) {
        // 1. Busca a comunidade que contém ou intercepta o ponto clicado
        $stmtFind = $db->prepare("
            SELECT id2 
            FROM geo.geo_comunidades_dm
            WHERE ST_Intersects(geom, ST_Transform(ST_SetSRID(ST_Point(:lng, :lat), 4326), 31984))
            LIMIT 1;
        ");
        $stmtFind->execute([':lng' => $lng, ':lat' => $lat]);
        $comunidadeId = (int) $stmtFind->fetchColumn();

        // 2. Se o clique caiu ligeiramente fora do polígono exato, busca a comunidade mais próxima
        if (!$comunidadeId) {
            $stmtNear = $db->prepare("
                SELECT id2 
                FROM geo.geo_comunidades_dm
                ORDER BY ST_Distance(geom, ST_Transform(ST_SetSRID(ST_Point(:lng, :lat), 4326), 31984)) ASC
                LIMIT 1;
            ");
            $stmtNear->execute([':lng' => $lng, ':lat' => $lat]);
            $comunidadeId = (int) $stmtNear->fetchColumn();
        }
    }

    if (!$comunidadeId) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Nenhuma comunidade encontrada para a coordenada ou ID informado.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Informações da Comunidade
    $stmtCom = $db->prepare("
        SELECT 
            id2 AS id,
            COALESCE(comunidade, nome, 'Sem nome') AS comunidade,
            nome AS municipio,
            altitude_m,
            ibge,
            ROUND(ST_Area(geom)::numeric, 2) AS area_m2,
            ROUND((ST_Area(geom) / 10000)::numeric, 2) AS area_ha,
            ROUND((ST_Area(geom) / 1000000)::numeric, 2) AS area_km2,
            ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) AS numeric), 6) AS lng,
            ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) AS numeric), 6) AS lat,
            ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326), 6) AS geojson
        FROM geo.geo_comunidades_dm
        WHERE id2 = :id;
    ");
    $stmtCom->execute([':id' => $comunidadeId]);
    $comunidadeInfo = $stmtCom->fetch(PDO::FETCH_ASSOC);

    if (!$comunidadeInfo) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Comunidade não encontrada.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // 3. EXPORTAÇÃO CSV (Lotes ou Edificações da Comunidade)
    // =========================================================================
    if ($action === 'export_csv') {
        $tipoExport = isset($_REQUEST['tipo']) ? strtolower(trim($_REQUEST['tipo'])) : 'lotes';
        $filename = 'comunidade_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $comunidadeInfo['comunidade']) . '_' . $tipoExport . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 para Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        if ($tipoExport === 'edificacoes') {
            fputcsv($out, [
                'ID Edificação',
                'Inscrição Imobiliária / Municipal',
                'Status Cadastral',
                'Nome do Contribuinte',
                'Logradouro',
                'Bairro / Comunidade',
                'Tipo de Construção',
                'Estrutura',
                'Estado de Conservação',
                'Situação do Imóvel',
                'Rede de Esgoto',
                'Rede de Água',
                'Área da Unidade (m²)',
                'Área Total Edificação (m²)',
                'Latitude',
                'Longitude'
            ], ';');

            $stmtExp = $db->prepare("
                WITH com AS (
                    SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
                )
                SELECT 
                    e.id,
                    COALESCE(g.\"INSC_MUNIC\", e.inscimob, '-') AS insc_munic,
                    CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Cadastrado' ELSE 'Não Cadastrado' END AS status_cadastral,
                    COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-') AS contribuinte,
                    COALESCE(g.\"NOMELOGRADOURO\", '-') AS logradouro,
                    COALESCE(g.\"NOMEBAIRRO\", '-') AS bairro,
                    COALESCE(g.\"TIPO_DE_CONSTRUCAO10042\", '-') AS tipo_construcao,
                    COALESCE(g.\"ESTRUTURA10043\", '-') AS estrutura,
                    COALESCE(g.\"ESTADO_DE_CONSERVACAO10044\", '-') AS estado_conservacao,
                    COALESCE(g.\"SITUACAO_DO_IMOVEL10048\", '-') AS situacao_imovel,
                    COALESCE(g.\"ESGOTO12001\", '-') AS esgoto,
                    COALESCE(g.\"AGUA12002\", '-') AS agua,
                    COALESCE(g.\"AREA_DA_UNIDADE13066\", '-') AS area_unidade,
                    COALESCE(g.\"AREA_TOTAL_DA_EDIFICACAO13067\", '-') AS area_total_edificacao,
                    ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326))) AS numeric), 6) AS lat,
                    ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326))) AS numeric), 6) AS lng
                FROM geo.geo_edificacoes_dm e
                JOIN com ON ST_Intersects(e.geom, com.geom)
                LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
                ORDER BY e.id ASC;
            ");
            $stmtExp->execute([':id' => $comunidadeId]);
            while ($row = $stmtExp->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($out, [
                    $row['id'],
                    $row['insc_munic'],
                    $row['status_cadastral'],
                    $row['contribuinte'],
                    $row['logradouro'],
                    $row['bairro'],
                    $row['tipo_construcao'],
                    $row['estrutura'],
                    $row['estado_conservacao'],
                    $row['situacao_imovel'],
                    $row['esgoto'],
                    $row['agua'],
                    $row['area_unidade'],
                    $row['area_total_edificacao'],
                    $row['lat'],
                    $row['lng']
                ], ';');
            }
        } else {
            // Exportar Lotes
            fputcsv($out, [
                'ID Lote',
                'Inscrição Municipal',
                'Inscrição Imob Shape',
                'Status Cadastral',
                'Contribuinte',
                'Logradouro',
                'Bairro',
                'Área GIS (m²)',
                'Área Tributário (m²)',
                'Utilização',
                'Ocupação',
                'Latitude',
                'Longitude'
            ], ';');

            $stmtExp = $db->prepare("
                WITH com AS (
                    SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
                )
                SELECT 
                    l.id,
                    COALESCE(g.\"INSC_MUNIC\", l.inscimob2, l.inscimob, '-') AS insc_munic,
                    COALESCE(l.inscimob2, l.inscimob, '-') AS insc_shape,
                    CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Cadastrado' ELSE 'Não Cadastrado' END AS status_cadastral,
                    COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-') AS contribuinte,
                    COALESCE(g.\"NOMELOGRADOURO\", '-') AS logradouro,
                    COALESCE(g.\"NOMEBAIRRO\", '-') AS bairro,
                    ROUND(COALESCE(l.area_m2, ST_Area(l.geom))::numeric, 2) AS area_gis,
                    COALESCE(g.\"AREA_DO_TERRENO13065\", '-') AS area_tributario,
                    COALESCE(g.\"ULTILIZACAO_DO_IMOVEL9027\", '-') AS utilizacao,
                    COALESCE(g.\"OCUPACAO9025\", '-') AS ocupacao,
                    ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) AS lat,
                    ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) AS lng
                FROM geo.geo_lotes_dm l
                JOIN com ON ST_Intersects(l.geom, com.geom)
                LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\"
                ORDER BY l.id ASC;
            ");
            $stmtExp->execute([':id' => $comunidadeId]);
            while ($row = $stmtExp->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($out, [
                    $row['id'],
                    $row['insc_munic'],
                    $row['insc_shape'],
                    $row['status_cadastral'],
                    $row['contribuinte'],
                    $row['logradouro'],
                    $row['bairro'],
                    $row['area_gis'],
                    $row['area_tributario'],
                    $row['utilizacao'],
                    $row['ocupacao'],
                    $row['lat'],
                    $row['lng']
                ], ';');
            }
        }

        fclose($out);
        exit;
    }

    // =========================================================================
    // 4. TABELA PAGINADA DE LOTES DA COMUNIDADE
    // =========================================================================
    if ($action === 'tabela_lotes') {
        header('Content-Type: application/json; charset=utf-8');
        $busca = isset($_REQUEST['busca']) ? trim($_REQUEST['busca']) : '';
        $page = isset($_REQUEST['page']) ? max(1, (int) $_REQUEST['page']) : 1;
        $limit = isset($_REQUEST['limit']) ? max(1, min(100, (int) $_REQUEST['limit'])) : 20;
        $offset = ($page - 1) * $limit;

        $whereClause = "";
        $params = [':id' => $comunidadeId];

        if ($busca !== '') {
            $whereClause = " AND (
                l.inscimob2 ILIKE :busca 
                OR l.inscimob ILIKE :busca 
                OR g.\"INSC_MUNIC\" ILIKE :busca 
                OR g.\"NOMEDOCONTRIBUINTE\" ILIKE :busca 
                OR g.\"NOMELOGRADOURO\" ILIKE :busca
            )";
            $params[':busca'] = '%' . $busca . '%';
        }

        // Count
        $stmtCount = $db->prepare("
            WITH com AS (
                SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
            )
            SELECT COUNT(*)
            FROM geo.geo_lotes_dm l
            JOIN com ON ST_Intersects(l.geom, com.geom)
            LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\"
            WHERE 1=1 $whereClause;
        ");
        $stmtCount->execute($params);
        $totalRecords = (int) $stmtCount->fetchColumn();

        // Data
        $stmtData = $db->prepare("
            WITH com AS (
                SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
            )
            SELECT 
                l.id,
                COALESCE(g.\"INSC_MUNIC\", l.inscimob2, l.inscimob, '-') AS insc_munic,
                l.inscimob2,
                (g.\"INSC_MUNIC\" IS NOT NULL) AS has_cadastro,
                COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-') AS contribuinte,
                COALESCE(g.\"NOMELOGRADOURO\", '-') AS logradouro,
                COALESCE(g.\"NOMEBAIRRO\", '-') AS bairro,
                ROUND(COALESCE(l.area_m2, ST_Area(l.geom))::numeric, 2) AS area_gis,
                COALESCE(g.\"AREA_DO_TERRENO13065\", '-') AS area_tributario,
                COALESCE(g.\"ULTILIZACAO_DO_IMOVEL9027\", '-') AS utilizacao,
                ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) AS lng,
                ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) AS lat
            FROM geo.geo_lotes_dm l
            JOIN com ON ST_Intersects(l.geom, com.geom)
            LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\"
            WHERE 1=1 $whereClause
            ORDER BY l.id ASC
            LIMIT :limit OFFSET :offset;
        ");
        foreach ($params as $k => $v) {
            $stmtData->bindValue($k, $v);
        }
        $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtData->execute();
        $records = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total' => $totalRecords,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalRecords / $limit),
            'data' => $records
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // =========================================================================
    // 5. TABELA PAGINADA DE EDIFICAÇÕES DA COMUNIDADE
    // =========================================================================
    if ($action === 'tabela_edificacoes') {
        header('Content-Type: application/json; charset=utf-8');
        $busca = isset($_REQUEST['busca']) ? trim($_REQUEST['busca']) : '';
        $page = isset($_REQUEST['page']) ? max(1, (int) $_REQUEST['page']) : 1;
        $limit = isset($_REQUEST['limit']) ? max(1, min(100, (int) $_REQUEST['limit'])) : 20;
        $offset = ($page - 1) * $limit;

        $whereClause = "";
        $params = [':id' => $comunidadeId];

        if ($busca !== '') {
            $whereClause = " AND (
                e.inscimob ILIKE :busca 
                OR g.\"INSC_MUNIC\" ILIKE :busca 
                OR g.\"NOMEDOCONTRIBUINTE\" ILIKE :busca 
                OR g.\"NOMELOGRADOURO\" ILIKE :busca
                OR g.\"TIPO_DE_CONSTRUCAO10042\" ILIKE :busca
            )";
            $params[':busca'] = '%' . $busca . '%';
        }

        // Count
        $stmtCount = $db->prepare("
            WITH com AS (
                SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
            )
            SELECT COUNT(*)
            FROM geo.geo_edificacoes_dm e
            JOIN com ON ST_Intersects(e.geom, com.geom)
            LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
            WHERE 1=1 $whereClause;
        ");
        $stmtCount->execute($params);
        $totalRecords = (int) $stmtCount->fetchColumn();

        // Data
        $stmtData = $db->prepare("
            WITH com AS (
                SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
            )
            SELECT 
                e.id,
                COALESCE(g.\"INSC_MUNIC\", e.inscimob, '-') AS insc_munic,
                e.inscimob,
                (g.\"INSC_MUNIC\" IS NOT NULL) AS has_cadastro,
                COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-') AS contribuinte,
                COALESCE(g.\"NOMELOGRADOURO\", '-') AS logradouro,
                COALESCE(g.\"NOMEBAIRRO\", '-') AS bairro,
                COALESCE(g.\"TIPO_DE_CONSTRUCAO10042\", '-') AS tipo_construcao,
                COALESCE(g.\"ESTRUTURA10043\", '-') AS estrutura,
                COALESCE(g.\"ESTADO_DE_CONSERVACAO10044\", '-') AS estado_conservacao,
                COALESCE(g.\"SITUACAO_DO_IMOVEL10048\", '-') AS situacao_imovel,
                COALESCE(g.\"ESGOTO12001\", '-') AS esgoto,
                COALESCE(g.\"AGUA12002\", '-') AS agua,
                COALESCE(g.\"AREA_DA_UNIDADE13066\", '-') AS area_unidade,
                COALESCE(g.\"AREA_TOTAL_DA_EDIFICACAO13067\", '-') AS area_total_edificacao,
                ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326))) AS numeric), 6) AS lng,
                ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326))) AS numeric), 6) AS lat
            FROM geo.geo_edificacoes_dm e
            JOIN com ON ST_Intersects(e.geom, com.geom)
            LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
            WHERE 1=1 $whereClause
            ORDER BY e.id ASC
            LIMIT :limit OFFSET :offset;
        ");
        foreach ($params as $k => $v) {
            $stmtData->bindValue($k, $v);
        }
        $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtData->execute();
        $records = $stmtData->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'total' => $totalRecords,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($totalRecords / $limit),
            'data' => $records
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // =========================================================================
    // 6. GEOJSON DESTAQUES (Lotes e Edificações para colorir no mapa)
    // =========================================================================
    if ($action === 'geojson_destaques') {
        header('Content-Type: application/json; charset=utf-8');

        $tipo = isset($_REQUEST['tipo']) ? strtolower(trim($_REQUEST['tipo'])) : 'ambos';

        $queries = [];

        if ($tipo === 'lotes' || $tipo === 'ambos') {
            $queries[] = "
                SELECT json_build_object(
                    'type', 'Feature',
                    'geometry', ST_AsGeoJSON(ST_Transform(ST_Force2D(l.geom), 4326), 6)::json,
                    'properties', json_build_object(
                        'camada', 'lotes',
                        'id', l.id,
                        'insc_munic', COALESCE(g.\"INSC_MUNIC\", l.inscimob2, l.inscimob, '-'),
                        'contribuinte', COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-'),
                        'logradouro', COALESCE(g.\"NOMELOGRADOURO\", '-'),
                        'area_gis', ROUND(COALESCE(l.area_m2, ST_Area(l.geom))::numeric, 2),
                        'area_tributario', COALESCE(g.\"AREA_DO_TERRENO13065\", '-'),
                        'has_cadastro', (g.\"INSC_MUNIC\" IS NOT NULL),
                        'status', CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Cadastrado' ELSE 'Sem Cadastro' END
                    )
                ) AS feature
                FROM geo.geo_lotes_dm l
                JOIN com ON ST_Intersects(l.geom, com.geom)
                LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\"
            ";
        }

        if ($tipo === 'edificacoes' || $tipo === 'ambos') {
            $queries[] = "
                SELECT json_build_object(
                    'type', 'Feature',
                    'geometry', ST_AsGeoJSON(ST_Transform(ST_Force2D(e.geom), 4326), 6)::json,
                    'properties', json_build_object(
                        'camada', 'edificacoes',
                        'id', e.id,
                        'insc_munic', COALESCE(g.\"INSC_MUNIC\", e.inscimob, '-'),
                        'contribuinte', COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-'),
                        'logradouro', COALESCE(g.\"NOMELOGRADOURO\", '-'),
                        'tipo_construcao', COALESCE(g.\"TIPO_DE_CONSTRUCAO10042\", '-'),
                        'esgoto', COALESCE(g.\"ESGOTO12001\", '-'),
                        'agua', COALESCE(g.\"AGUA12002\", '-'),
                        'area_total_edificacao', COALESCE(g.\"AREA_TOTAL_DA_EDIFICACAO13067\", '-'),
                        'has_cadastro', (g.\"INSC_MUNIC\" IS NOT NULL),
                        'status', CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Cadastrado' ELSE 'Sem Cadastro' END
                    )
                ) AS feature
                FROM geo.geo_edificacoes_dm e
                JOIN com ON ST_Intersects(e.geom, com.geom)
                LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
            ";
        }

        $unionSQL = implode(" UNION ALL ", $queries);

        $sqlGeo = "
            WITH com AS (
                SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
            ),
            features_raw AS (
                $unionSQL
            )
            SELECT json_build_object(
                'type', 'FeatureCollection',
                'features', COALESCE(json_agg(feature), '[]'::json)
            ) AS geojson
            FROM features_raw;
        ";

        $stmtGeo = $db->prepare($sqlGeo);
        $stmtGeo->execute([':id' => $comunidadeId]);
        $geoRow = $stmtGeo->fetch(PDO::FETCH_ASSOC);

        echo $geoRow['geojson'] ?? '{"type":"FeatureCollection","features":[]}';
        exit;
    }

    // =========================================================================
    // 7. AÇÃO PADRÃO: ANALISAR E RETORNAR SUMÁRIO COMPLETO
    // =========================================================================
    header('Content-Type: application/json; charset=utf-8');

    // Contagem e métricas de Lotes
    $stmtLotesSum = $db->prepare("
        WITH com AS (
            SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
        ),
        lotes_com AS (
            SELECT 
                l.id,
                l.area_m2,
                (g.\"INSC_MUNIC\" IS NOT NULL) AS has_cad
            FROM geo.geo_lotes_dm l
            JOIN com ON ST_Intersects(l.geom, com.geom)
            LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\"
        )
        SELECT 
            COUNT(*) AS total_lotes,
            COUNT(*) FILTER (WHERE has_cad = true) AS lotes_com_cadastro,
            COUNT(*) FILTER (WHERE has_cad = false) AS lotes_sem_cadastro,
            COALESCE(ROUND(SUM(area_m2)::numeric, 2), 0) AS area_total_lotes_m2
        FROM lotes_com;
    ");
    $stmtLotesSum->execute([':id' => $comunidadeId]);
    $lotesSummary = $stmtLotesSum->fetch(PDO::FETCH_ASSOC);

    // Contagem e métricas de Edificações
    $stmtEdifSum = $db->prepare("
        WITH com AS (
            SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
        ),
        edif_com AS (
            SELECT 
                e.id,
                (g.\"INSC_MUNIC\" IS NOT NULL) AS has_cad,
                g.\"TIPO_DE_CONSTRUCAO10042\" AS tipo_construcao,
                g.\"ESTADO_DE_CONSERVACAO10044\" AS estado_conservacao,
                g.\"AGUA12002\" AS agua,
                g.\"ESGOTO12001\" AS esgoto
            FROM geo.geo_edificacoes_dm e
            JOIN com ON ST_Intersects(e.geom, com.geom)
            LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
        )
        SELECT 
            COUNT(*) AS total_edificacoes,
            COUNT(*) FILTER (WHERE has_cad = true) AS edificacoes_com_cadastro,
            COUNT(*) FILTER (WHERE has_cad = false) AS edificacoes_sem_cadastro,
            COUNT(*) FILTER (WHERE agua ILIKE '%SIM%' OR agua ILIKE '%REDE%') AS edificacoes_com_agua,
            COUNT(*) FILTER (WHERE esgoto ILIKE '%SIM%' OR esgoto ILIKE '%REDE%' OR esgoto ILIKE '%FOSSA%') AS edificacoes_com_esgoto
        FROM edif_com;
    ");
    $stmtEdifSum->execute([':id' => $comunidadeId]);
    $edifSummary = $stmtEdifSum->fetch(PDO::FETCH_ASSOC);

    // Distribuição de Tipos de Construção
    $stmtTipos = $db->prepare("
        WITH com AS (
            SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
        )
        SELECT 
            COALESCE(NULLIF(TRIM(g.\"TIPO_DE_CONSTRUCAO10042\"), ''), 'Não Informado / Sem Cadastro') AS tipo,
            COUNT(*) AS total
        FROM geo.geo_edificacoes_dm e
        JOIN com ON ST_Intersects(e.geom, com.geom)
        LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
        GROUP BY tipo
        ORDER BY total DESC
        LIMIT 6;
    ");
    $stmtTipos->execute([':id' => $comunidadeId]);
    $distribuicaoTipos = $stmtTipos->fetchAll(PDO::FETCH_ASSOC);

    // Distribuição de Estado de Conservação
    $stmtCons = $db->prepare("
        WITH com AS (
            SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id
        )
        SELECT 
            COALESCE(NULLIF(TRIM(g.\"ESTADO_DE_CONSERVACAO10044\"), ''), 'Não Informado / Sem Cadastro') AS estado,
            COUNT(*) AS total
        FROM geo.geo_edificacoes_dm e
        JOIN com ON ST_Intersects(e.geom, com.geom)
        LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
        GROUP BY estado
        ORDER BY total DESC
        LIMIT 6;
    ");
    $stmtCons->execute([':id' => $comunidadeId]);
    $distribuicaoConservacao = $stmtCons->fetchAll(PDO::FETCH_ASSOC);

    $areaHa = (float) ($comunidadeInfo['area_ha'] ?? 0);
    $totalLotes = (int) ($lotesSummary['total_lotes'] ?? 0);
    $totalEdif = (int) ($edifSummary['total_edificacoes'] ?? 0);

    $densidadeEdifHa = $areaHa > 0 ? round($totalEdif / $areaHa, 2) : 0;
    $densidadeLotesHa = $areaHa > 0 ? round($totalLotes / $areaHa, 2) : 0;
    $mediaEdifLote = $totalLotes > 0 ? round($totalEdif / $totalLotes, 2) : 0;

    echo json_encode([
        'success' => true,
        'comunidade' => [
            'id' => (int) $comunidadeInfo['id'],
            'nome' => $comunidadeInfo['comunidade'],
            'municipio' => $comunidadeInfo['municipio'],
            'altitude_m' => $comunidadeInfo['altitude_m'],
            'ibge' => $comunidadeInfo['ibge'],
            'area_m2' => (float) $comunidadeInfo['area_m2'],
            'area_ha' => (float) $comunidadeInfo['area_ha'],
            'area_km2' => (float) $comunidadeInfo['area_km2'],
            'lat' => (float) $comunidadeInfo['lat'],
            'lng' => (float) $comunidadeInfo['lng'],
            'geojson' => json_decode($comunidadeInfo['geojson'], true)
        ],
        'metricas' => [
            'total_lotes' => $totalLotes,
            'lotes_com_cadastro' => (int) ($lotesSummary['lotes_com_cadastro'] ?? 0),
            'lotes_sem_cadastro' => (int) ($lotesSummary['lotes_sem_cadastro'] ?? 0),
            'area_total_lotes_m2' => (float) ($lotesSummary['area_total_lotes_m2'] ?? 0),
            'total_edificacoes' => $totalEdif,
            'edificacoes_com_cadastro' => (int) ($edifSummary['edificacoes_com_cadastro'] ?? 0),
            'edificacoes_sem_cadastro' => (int) ($edifSummary['edificacoes_sem_cadastro'] ?? 0),
            'edificacoes_com_agua' => (int) ($edifSummary['edificacoes_com_agua'] ?? 0),
            'edificacoes_com_esgoto' => (int) ($edifSummary['edificacoes_com_esgoto'] ?? 0),
            'densidade_edif_ha' => $densidadeEdifHa,
            'densidade_lotes_ha' => $densidadeLotesHa,
            'media_edif_por_lote' => $mediaEdifLote
        ],
        'distribuicoes' => [
            'tipos_construcao' => $distribuicaoTipos,
            'estado_conservacao' => $distribuicaoConservacao
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Erro interno na seleção espacial: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
