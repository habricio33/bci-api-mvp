<?php
/**
 * Endpoint para a Ferramenta de Filtro Avançado e Seleção Espacial de Terrenos / Lotes.
 * Filtra lotes (geo.geo_lotes_dm) e dados tributários (public."GP_IMOVEL")
 * por Área do Terreno (AREA_DO_TERRENO13065 com operadores >, <, >=, <=, =, BETWEEN)
 * e Ocupação (OCUPACAO9025).
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
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'filtrar';

try {
    // =========================================================================
    // 1. OBTER OPÇÕES DISPONÍVEIS (Ocupações, Bairros e Estatísticas Básicas)
    // =========================================================================
    if ($action === 'opcoes') {
        header('Content-Type: application/json; charset=utf-8');

        // Lista de Ocupações com contagem de lotes mapeados
        $stmtOcup = $db->query("
            SELECT 
                COALESCE(NULLIF(TRIM(g.\"OCUPACAO9025\"), ''), 'Não Informado') AS ocupacao,
                COUNT(DISTINCT l.id) AS total
            FROM geo.geo_lotes_dm l
            LEFT JOIN public.\"GP_IMOVEL\" g ON (l.inscimob2 = g.\"INSC_MUNIC\" OR l.inscimob = g.\"INSC_MUNIC\")
            GROUP BY ocupacao
            ORDER BY total DESC;
        ");
        $ocupacoes = $stmtOcup->fetchAll(PDO::FETCH_ASSOC);

        // Lista de Bairros com contagem
        $stmtBairros = $db->query("
            SELECT 
                COALESCE(NULLIF(TRIM(g.\"NOMEBAIRRO\"), ''), 'Não Informado') AS bairro,
                COUNT(DISTINCT l.id) AS total
            FROM geo.geo_lotes_dm l
            JOIN public.\"GP_IMOVEL\" g ON (l.inscimob2 = g.\"INSC_MUNIC\" OR l.inscimob = g.\"INSC_MUNIC\")
            WHERE g.\"NOMEBAIRRO\" IS NOT NULL AND TRIM(g.\"NOMEBAIRRO\") <> ''
            GROUP BY bairro
            ORDER BY bairro ASC;
        ");
        $bairros = $stmtBairros->fetchAll(PDO::FETCH_ASSOC);

        // Estatísticas gerais
        $stmtStats = $db->query("
            SELECT 
                COUNT(DISTINCT l.id) AS total_lotes_geo,
                COUNT(DISTINCT g.\"INSC_MUNIC\") AS total_lotes_cadastrados,
                COALESCE(ROUND(AVG(NULLIF(REGEXP_REPLACE(REPLACE(TRIM(g.\"AREA_DO_TERRENO13065\"), ',', '.'), '[^0-9.]', '', 'g'), '')::numeric), 2), 0) AS media_area_terreno,
                COALESCE(ROUND(MAX(NULLIF(REGEXP_REPLACE(REPLACE(TRIM(g.\"AREA_DO_TERRENO13065\"), ',', '.'), '[^0-9.]', '', 'g'), '')::numeric), 2), 0) AS max_area_terreno
            FROM geo.geo_lotes_dm l
            LEFT JOIN public.\"GP_IMOVEL\" g ON (l.inscimob2 = g.\"INSC_MUNIC\" OR l.inscimob = g.\"INSC_MUNIC\");
        ");
        $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'ocupacoes' => $ocupacoes,
            'bairros' => $bairros,
            'estatisticas' => $stats
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // =========================================================================
    // 2. CONSTRUÇÃO DINÂMICA DE FILTROS (Área do Terreno + Ocupação + Bairro + Busca)
    // =========================================================================
    $operadorArea = isset($_REQUEST['operador_area']) ? trim($_REQUEST['operador_area']) : '>';
    $valorArea = (isset($_REQUEST['valor_area']) && $_REQUEST['valor_area'] !== '') ? (float) $_REQUEST['valor_area'] : null;
    $valorAreaMax = (isset($_REQUEST['valor_area_max']) && $_REQUEST['valor_area_max'] !== '') ? (float) $_REQUEST['valor_area_max'] : null;
    $ocupacao = isset($_REQUEST['ocupacao']) ? trim($_REQUEST['ocupacao']) : '';
    $bairro = isset($_REQUEST['bairro']) ? trim($_REQUEST['bairro']) : '';
    $search = isset($_REQUEST['search']) ? trim($_REQUEST['search']) : '';
    $page = isset($_REQUEST['page']) ? max(1, (int) $_REQUEST['page']) : 1;
    $limit = isset($_REQUEST['limit']) ? max(5, min(100, (int) $_REQUEST['limit'])) : 15;
    $offset = ($page - 1) * $limit;

    $whereClauses = ["l.geom IS NOT NULL"];
    $params = [];

    $areaCastExpr = "NULLIF(REGEXP_REPLACE(REPLACE(TRIM(g.\"AREA_DO_TERRENO13065\"), ',', '.'), '[^0-9.]', '', 'g'), '')::numeric";

    // Filtro por Área do Terreno
    if ($valorArea !== null) {
        switch ($operadorArea) {
            case '>':
                $whereClauses[] = "$areaCastExpr > :val_area";
                $params[':val_area'] = $valorArea;
                break;
            case '>=':
                $whereClauses[] = "$areaCastExpr >= :val_area";
                $params[':val_area'] = $valorArea;
                break;
            case '<':
                $whereClauses[] = "$areaCastExpr < :val_area";
                $params[':val_area'] = $valorArea;
                break;
            case '<=':
                $whereClauses[] = "$areaCastExpr <= :val_area";
                $params[':val_area'] = $valorArea;
                break;
            case '=':
                $whereClauses[] = "$areaCastExpr = :val_area";
                $params[':val_area'] = $valorArea;
                break;
            case 'BETWEEN':
                if ($valorAreaMax !== null) {
                    $whereClauses[] = "$areaCastExpr BETWEEN :val_area AND :val_area_max";
                    $params[':val_area'] = min($valorArea, $valorAreaMax);
                    $params[':val_area_max'] = max($valorArea, $valorAreaMax);
                } else {
                    $whereClauses[] = "$areaCastExpr >= :val_area";
                    $params[':val_area'] = $valorArea;
                }
                break;
        }
    }

    // Filtro por Ocupação
    if ($ocupacao !== '' && $ocupacao !== 'ALL' && $ocupacao !== 'TODOS') {
        if ($ocupacao === 'SEM_CADASTRO') {
            $whereClauses[] = "g.\"INSC_MUNIC\" IS NULL";
        } elseif ($ocupacao === 'NAO_INFORMADO') {
            $whereClauses[] = "(g.\"OCUPACAO9025\" IS NULL OR TRIM(g.\"OCUPACAO9025\") = '')";
        } else {
            $whereClauses[] = "TRIM(g.\"OCUPACAO9025\") = :ocupacao";
            $params[':ocupacao'] = $ocupacao;
        }
    }

    // Filtro por Bairro
    if ($bairro !== '' && $bairro !== 'ALL' && $bairro !== 'TODOS') {
        $whereClauses[] = "TRIM(g.\"NOMEBAIRRO\") = :bairro";
        $params[':bairro'] = $bairro;
    }

    // Busca textual rápida
    if ($search !== '') {
        $whereClauses[] = "(
            COALESCE(g.\"INSC_MUNIC\", l.inscimob2, l.inscimob) ILIKE :search
            OR g.\"NOMEDOCONTRIBUINTE\" ILIKE :search
            OR g.\"NOMELOGRADOURO\" ILIKE :search
            OR g.\"NOMEBAIRRO\" ILIKE :search
        )";
        $params[':search'] = '%' . $search . '%';
    }

    $whereSql = implode(' AND ', $whereClauses);

    // =========================================================================
    // 3. RETORNAR GEOJSON COMPLETO (Para renderizar/destacar no mapa)
    // =========================================================================
    if ($action === 'geojson') {
        header('Content-Type: application/json; charset=utf-8');

        $stmtGeo = $db->prepare("
            SELECT json_build_object(
                'type', 'FeatureCollection',
                'features', COALESCE(json_agg(features), '[]'::json)
            ) as geojson
            FROM (
                SELECT json_build_object(
                    'type', 'Feature',
                    'geometry', ST_AsGeoJSON(ST_Transform(ST_Force2D(l.geom), 4326))::json,
                    'properties', json_build_object(
                        'id', l.id,
                        'insc_munic', COALESCE(g.\"INSC_MUNIC\", l.inscimob2, l.inscimob, '-'),
                        'contribuinte', COALESCE(g.\"NOMEDOCONTRIBUINTE\", 'Não Informado'),
                        'logradouro', COALESCE(g.\"NOMELOGRADOURO\", '-'),
                        'bairro', COALESCE(g.\"NOMEBAIRRO\", '-'),
                        'ocupacao', COALESCE(NULLIF(TRIM(g.\"OCUPACAO9025\"), ''), 'Não Informado'),
                        'area_terreno', COALESCE($areaCastExpr, 0),
                        'area_gis', ROUND(ST_Area(l.geom)::numeric, 2)
                    )
                ) as features
                FROM geo.geo_lotes_dm l
                LEFT JOIN public.\"GP_IMOVEL\" g ON (l.inscimob2 = g.\"INSC_MUNIC\" OR l.inscimob = g.\"INSC_MUNIC\")
                WHERE $whereSql
            ) row;
        ");
        $stmtGeo->execute($params);
        $geoRes = $stmtGeo->fetchColumn();

        echo $geoRes ? $geoRes : json_encode(['type' => 'FeatureCollection', 'features' => []]);
        exit;
    }

    // =========================================================================
    // 4. EXPORTAÇÃO CSV
    // =========================================================================
    if ($action === 'export_csv') {
        $filename = 'filtro_terrenos_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 para Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, [
            'ID Lote (GIS)',
            'Inscrição Imobiliária / Municipal',
            'Status Cadastral',
            'Nome do Contribuinte / Proprietário',
            'Logradouro',
            'Bairro / Comunidade',
            'Ocupação do Terreno (OCUPACAO9025)',
            'Área do Terreno Tributário (m²)',
            'Área GIS (m²)',
            'Latitude',
            'Longitude'
        ], ';');

        $stmtExp = $db->prepare("
            SELECT 
                l.id,
                COALESCE(g.\"INSC_MUNIC\", l.inscimob2, l.inscimob, '-') AS insc_munic,
                CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Cadastrado' ELSE 'Não Cadastrado' END AS status_cadastral,
                COALESCE(g.\"NOMEDOCONTRIBUINTE\", 'Não Informado') AS contribuinte,
                COALESCE(g.\"NOMELOGRADOURO\", '-') AS logradouro,
                COALESCE(g.\"NOMEBAIRRO\", '-') AS bairro,
                COALESCE(NULLIF(TRIM(g.\"OCUPACAO9025\"), ''), 'Não Informado') AS ocupacao,
                COALESCE($areaCastExpr, 0) AS area_terreno,
                ROUND(ST_Area(l.geom)::numeric, 2) AS area_gis,
                ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) AS lat,
                ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) AS lng
            FROM geo.geo_lotes_dm l
            LEFT JOIN public.\"GP_IMOVEL\" g ON (l.inscimob2 = g.\"INSC_MUNIC\" OR l.inscimob = g.\"INSC_MUNIC\")
            WHERE $whereSql
            ORDER BY area_terreno DESC, l.id ASC;
        ");
        $stmtExp->execute($params);

        while ($row = $stmtExp->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, [
                $row['id'],
                $row['insc_munic'],
                $row['status_cadastral'],
                $row['contribuinte'],
                $row['logradouro'],
                $row['bairro'],
                $row['ocupacao'],
                number_format((float)$row['area_terreno'], 2, ',', '.'),
                number_format((float)$row['area_gis'], 2, ',', '.'),
                $row['lat'],
                $row['lng']
            ], ';');
        }

        fclose($out);
        exit;
    }

    // =========================================================================
    // 5. FILTRAR E RETORNAR METRICAS + TABELA PAGINADA
    // =========================================================================
    header('Content-Type: application/json; charset=utf-8');

    // Métricas Agregadas
    $stmtMetrics = $db->prepare("
        WITH base_filtrada AS (
            SELECT 
                l.id,
                l.inscimob,
                l.inscimob2,
                ST_Area(l.geom) AS area_gis,
                g.\"INSC_MUNIC\" AS insc_munic,
                g.\"OCUPACAO9025\" AS ocupacao_raw,
                $areaCastExpr AS area_terreno_num
            FROM geo.geo_lotes_dm l
            LEFT JOIN public.\"GP_IMOVEL\" g ON (l.inscimob2 = g.\"INSC_MUNIC\" OR l.inscimob = g.\"INSC_MUNIC\")
            WHERE $whereSql
        )
        SELECT 
            COUNT(DISTINCT id) AS total_lotes,
            COUNT(DISTINCT insc_munic) AS total_cadastrados,
            COALESCE(ROUND(AVG(area_terreno_num)::numeric, 2), 0) AS area_media_terreno,
            COALESCE(ROUND(SUM(area_terreno_num)::numeric, 2), 0) AS area_total_terreno,
            COALESCE(ROUND(SUM(area_gis)::numeric, 2), 0) AS area_total_gis
        FROM base_filtrada;
    ");
    $stmtMetrics->execute($params);
    $metrics = $stmtMetrics->fetch(PDO::FETCH_ASSOC);

    $totalLotes = (int) ($metrics['total_lotes'] ?? 0);
    $totalPaginas = $totalLotes > 0 ? (int) ceil($totalLotes / $limit) : 1;

    // Distribuição por Ocupação no conjunto filtrado
    $stmtDist = $db->prepare("
        SELECT 
            COALESCE(NULLIF(TRIM(g.\"OCUPACAO9025\"), ''), 'Não Informado') AS ocupacao,
            COUNT(DISTINCT l.id) AS total
        FROM geo.geo_lotes_dm l
        LEFT JOIN public.\"GP_IMOVEL\" g ON (l.inscimob2 = g.\"INSC_MUNIC\" OR l.inscimob = g.\"INSC_MUNIC\")
        WHERE $whereSql
        GROUP BY ocupacao
        ORDER BY total DESC;
    ");
    $stmtDist->execute($params);
    $distOcupacao = $stmtDist->fetchAll(PDO::FETCH_ASSOC);

    // Lista paginada de Lotes
    $stmtList = $db->prepare("
        SELECT 
            l.id,
            COALESCE(g.\"INSC_MUNIC\", l.inscimob2, l.inscimob, '-') AS insc_munic,
            (g.\"INSC_MUNIC\" IS NOT NULL) AS has_cad,
            COALESCE(g.\"NOMEDOCONTRIBUINTE\", 'Não Informado') AS contribuinte,
            COALESCE(g.\"NOMELOGRADOURO\", '-') AS logradouro,
            COALESCE(g.\"NOMEBAIRRO\", '-') AS bairro,
            COALESCE(NULLIF(TRIM(g.\"OCUPACAO9025\"), ''), 'Não Informado') AS ocupacao,
            COALESCE($areaCastExpr, 0) AS area_terreno,
            ROUND(ST_Area(l.geom)::numeric, 2) AS area_gis,
            ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) AS lng,
            ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) AS lat
        FROM geo.geo_lotes_dm l
        LEFT JOIN public.\"GP_IMOVEL\" g ON (l.inscimob2 = g.\"INSC_MUNIC\" OR l.inscimob = g.\"INSC_MUNIC\")
        WHERE $whereSql
        ORDER BY area_terreno DESC, l.id ASC
        LIMIT :limit OFFSET :offset;
    ");

    foreach ($params as $k => $v) {
        $stmtList->bindValue($k, $v);
    }
    $stmtList->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtList->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtList->execute();
    $itens = $stmtList->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'metricas' => [
            'total_lotes' => $totalLotes,
            'total_cadastrados' => (int) ($metrics['total_cadastrados'] ?? 0),
            'area_media_terreno' => (float) ($metrics['area_media_terreno'] ?? 0),
            'area_total_terreno' => (float) ($metrics['area_total_terreno'] ?? 0),
            'area_total_gis' => (float) ($metrics['area_total_gis'] ?? 0)
        ],
        'distribuicao_ocupacao' => $distOcupacao,
        'paginacao' => [
            'pagina_atual' => $page,
            'total_paginas' => $totalPaginas,
            'limite' => $limit,
            'total_registros' => $totalLotes
        ],
        'itens' => $itens
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Erro ao processar filtro de terrenos: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
