<?php
/**
 * Endpoint de Análise e Consulta de Divergências de Área (> 10%)
 * entre a tabela espacial (geo.geo_lotes_dm) e a tabela tributária (public."GP_IMOVEL").
 * 
 * Parâmetros GET/POST:
 *  - situacao (string): 'DIVERGÊNCIA', 'DENTRO DO LIMITE', 'SEM CADASTRO NO TRIBUTÁRIO', 'SEM ÁREA NO TRIBUTÁRIO', 'ÁREA TRIBUTÁRIA ZERO', 'TODOS' (padrão: 'TODOS')
 *  - busca (string): Termo de busca livre (inscrição, contribuinte, logradouro, bairro)
 *  - page (int): Número da página (padrão: 1)
 *  - limit (int): Limite de registros por página (padrão: 50, máx: 1000)
 *  - export (string): 'csv' para download direto de planilha
 *  - geojson (int): 1 para retornar a camada GeoJSON dos lotes com suas respectivas situações
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

require_once __DIR__ . '/../config/db.php';

$db = getDBConnection();

$situacaoFiltro = isset($_REQUEST['situacao']) ? trim($_REQUEST['situacao']) : 'TODOS';
$busca = isset($_REQUEST['busca']) ? trim($_REQUEST['busca']) : '';
$page = isset($_REQUEST['page']) ? max(1, (int) $_REQUEST['page']) : 1;
$limit = isset($_REQUEST['limit']) ? max(1, min(1000, (int) $_REQUEST['limit'])) : 50;
$offset = ($page - 1) * $limit;
$isExport = isset($_REQUEST['export']) && strtolower($_REQUEST['export']) === 'csv';
$isGeojson = isset($_REQUEST['geojson']) && ($_REQUEST['geojson'] === '1' || strtolower($_REQUEST['geojson']) === 'true');

// Base CTE / Subquery da Análise de Divergência
$sqlBase = "
    WITH base_divergencia AS (
        SELECT
            g.id,
            g.inscimob2,
            g.inscimob,
            COALESCE(g.inscimob2, t.\"INSC_MUNIC\", g.inscimob, '-') AS insc_munic,
            t.\"NOMEDOCONTRIBUINTE\" AS contribuinte,
            t.\"NOMELOGRADOURO\" AS logradouro,
            t.\"NOMEBAIRRO\" AS bairro,
            g.area_m2 AS area_gis,
            NULLIF(REPLACE(REPLACE(t.\"AREA_DO_TERRENO13065\", '.', ''), ',', '.'), '')::numeric AS area_tributario_num,
            t.\"AREA_DO_TERRENO13065\" AS area_tributario_raw,
            ROUND(
                (
                    g.area_m2
                    - NULLIF(REPLACE(REPLACE(t.\"AREA_DO_TERRENO13065\", '.', ''), ',', '.'), '')::numeric
                )::numeric,
                2
            ) AS diferenca_m2,
            ROUND(
                (
                    (
                        g.area_m2
                        - NULLIF(REPLACE(REPLACE(t.\"AREA_DO_TERRENO13065\", '.', ''), ',', '.'), '')::numeric
                    )
                    /
                    NULLIF(REPLACE(REPLACE(t.\"AREA_DO_TERRENO13065\", '.', ''), ',', '.'), '')::numeric
                    * 100
                )::numeric,
                2
            ) AS diferenca_percentual,
            CASE
                WHEN t.\"INSC_MUNIC\" IS NULL
                    THEN 'SEM CADASTRO NO TRIBUTÁRIO'
                WHEN NULLIF(REPLACE(REPLACE(t.\"AREA_DO_TERRENO13065\", '.', ''), ',', '.'), '') IS NULL
                    THEN 'SEM ÁREA NO TRIBUTÁRIO'
                WHEN NULLIF(REPLACE(REPLACE(t.\"AREA_DO_TERRENO13065\", '.', ''), ',', '.'), '')::numeric = 0
                    THEN 'ÁREA TRIBUTÁRIA ZERO'
                WHEN ABS(
                    (
                        g.area_m2
                        - NULLIF(REPLACE(REPLACE(t.\"AREA_DO_TERRENO13065\", '.', ''), ',', '.'), '')::numeric
                    )
                    /
                    NULLIF(REPLACE(REPLACE(t.\"AREA_DO_TERRENO13065\", '.', ''), ',', '.'), '')::numeric
                ) > 0.10
                    THEN 'DIVERGÊNCIA'
                ELSE 'DENTRO DO LIMITE'
            END AS situacao,
            ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(g.geom), 4326))) AS numeric), 6) as lng,
            ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(g.geom), 4326))) AS numeric), 6) as lat
        FROM geo.\"geo_lotes_dm\" g
        LEFT JOIN public.\"GP_IMOVEL\" t
            ON g.inscimob2 = t.\"INSC_MUNIC\"
    )
";

try {
    // 1. Resumo / KPIs de Situação
    $sqlKPIs = $sqlBase . "
        SELECT 
            COUNT(*) AS total_lotes,
            COUNT(*) FILTER (WHERE situacao = 'DIVERGÊNCIA') AS total_divergencia,
            COUNT(*) FILTER (WHERE situacao = 'DENTRO DO LIMITE') AS total_dentro_limite,
            COUNT(*) FILTER (WHERE situacao = 'SEM CADASTRO NO TRIBUTÁRIO') AS total_sem_cadastro,
            COUNT(*) FILTER (WHERE situacao = 'SEM ÁREA NO TRIBUTÁRIO') AS total_sem_area,
            COUNT(*) FILTER (WHERE situacao = 'ÁREA TRIBUTÁRIA ZERO') AS total_area_zero
        FROM base_divergencia;
    ";
    $stmtKPI = $db->query($sqlKPIs);
    $kpiSummary = $stmtKPI->fetch(PDO::FETCH_ASSOC);

    // 2. Cláusula WHERE de Filtros
    $whereClauses = [];
    $params = [];

    if ($situacaoFiltro !== 'TODOS' && $situacaoFiltro !== '') {
        $whereClauses[] = "situacao = :situacao";
        $params[':situacao'] = $situacaoFiltro;
    }

    if ($busca !== '') {
        $whereClauses[] = "(
            insc_munic ILIKE :busca
            OR COALESCE(contribuinte, '') ILIKE :busca
            OR COALESCE(logradouro, '') ILIKE :busca
            OR COALESCE(bairro, '') ILIKE :busca
        )";
        $params[':busca'] = '%' . $busca . '%';
    }

    $whereSQL = !empty($whereClauses) ? " WHERE " . implode(' AND ', $whereClauses) : "";

    // 3. Se for exportação CSV
    if ($isExport) {
        $sqlExport = $sqlBase . "
            SELECT * FROM base_divergencia
            $whereSQL
            ORDER BY 
                CASE WHEN situacao = 'DIVERGÊNCIA' THEN 1 ELSE 2 END,
                ABS(COALESCE(diferenca_percentual, 0)) DESC,
                id ASC;
        ";
        $stmtExp = $db->prepare($sqlExport);
        $stmtExp->execute($params);
        $rows = $stmtExp->fetchAll(PDO::FETCH_ASSOC);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=divergencia_areas_lotes_' . date('Ymd_His') . '.csv');

        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8

        fputcsv($out, [
            'ID',
            'Inscrição Municipal',
            'Contribuinte',
            'Logradouro',
            'Bairro',
            'Área GIS (m²)',
            'Área Tributário (m²)',
            'Diferença (m²)',
            'Diferença (%)',
            'Situação'
        ], ';');

        foreach ($rows as $r) {
            fputcsv($out, [
                $r['id'],
                $r['insc_munic'],
                $r['contribuinte'] ?? '-',
                $r['logradouro'] ?? '-',
                $r['bairro'] ?? '-',
                $r['area_gis'] !== null ? number_format((float) $r['area_gis'], 2, ',', '.') : '-',
                $r['area_tributario_num'] !== null ? number_format((float) $r['area_tributario_num'], 2, ',', '.') : ($r['area_tributario_raw'] ?? '-'),
                $r['diferenca_m2'] !== null ? number_format((float) $r['diferenca_m2'], 2, ',', '.') : '-',
                $r['diferenca_percentual'] !== null ? number_format((float) $r['diferenca_percentual'], 2, ',', '.') . '%' : '-',
                $r['situacao']
            ], ';');
        }

        fclose($out);
        exit;
    }

    // 4. Se for GeoJSON completo (para pintar/destacar camadas no mapa)
    if ($isGeojson) {
        $sqlGeojson = "
            $sqlBase
            SELECT json_build_object(
                'type', 'FeatureCollection',
                'features', COALESCE(json_agg(
                    json_build_object(
                        'type', 'Feature',
                        'geometry', ST_AsGeoJSON(ST_Transform(ST_Force2D(g.geom), 4326), 6)::json,
                        'properties', json_build_object(
                            'id', b.id,
                            'insc_munic', b.insc_munic,
                            'contribuinte', COALESCE(b.contribuinte, '-'),
                            'logradouro', COALESCE(b.logradouro, '-'),
                            'bairro', COALESCE(b.bairro, '-'),
                            'area_gis', ROUND(b.area_gis::numeric, 2),
                            'area_tributario', b.area_tributario_num,
                            'area_tributario_raw', COALESCE(b.area_tributario_raw, '-'),
                            'diferenca_m2', b.diferenca_m2,
                            'diferenca_percentual', b.diferenca_percentual,
                            'situacao', b.situacao,
                            'lat', b.lat,
                            'lng', b.lng
                        )
                    )
                ), '[]'::json)
            ) AS geojson
            FROM base_divergencia b
            JOIN geo.\"geo_lotes_dm\" g ON b.id = g.id
            $whereSQL;
        ";

        $stmtGeo = $db->prepare($sqlGeojson);
        $stmtGeo->execute($params);
        $geoRow = $stmtGeo->fetch(PDO::FETCH_ASSOC);

        header('Content-Type: application/json; charset=utf-8');
        echo $geoRow['geojson'] ?? '{"type":"FeatureCollection","features":[]}';
        exit;
    }

    // 5. Consulta Paginada de Registros
    $sqlCount = $sqlBase . " SELECT COUNT(*) AS total FROM base_divergencia $whereSQL;";
    $stmtCount = $db->prepare($sqlCount);
    $stmtCount->execute($params);
    $totalFiltered = (int) $stmtCount->fetchColumn();

    $sqlData = $sqlBase . "
        SELECT * FROM base_divergencia
        $whereSQL
        ORDER BY 
            CASE WHEN situacao = 'DIVERGÊNCIA' THEN 1 ELSE 2 END,
            ABS(COALESCE(diferenca_percentual, 0)) DESC,
            id ASC
        LIMIT :limit OFFSET :offset;
    ";

    $stmtData = $db->prepare($sqlData);
    foreach ($params as $k => $v) {
        $stmtData->bindValue($k, $v);
    }
    $stmtData->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmtData->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtData->execute();
    $records = $stmtData->fetchAll(PDO::FETCH_ASSOC);

    // Formatação dos registros para saída
    $formattedRecords = array_map(function ($r) {
        $areaGis = $r['area_gis'] !== null ? (float) $r['area_gis'] : null;
        $areaTrib = $r['area_tributario_num'] !== null ? (float) $r['area_tributario_num'] : null;
        $difM2 = $r['diferenca_m2'] !== null ? (float) $r['diferenca_m2'] : null;
        $difPerc = $r['diferenca_percentual'] !== null ? (float) $r['diferenca_percentual'] : null;

        return [
            'id' => (int) $r['id'],
            'insc_munic' => $r['insc_munic'],
            'contribuinte' => $r['contribuinte'] ?? '-',
            'logradouro' => $r['logradouro'] ?? '-',
            'bairro' => $r['bairro'] ?? '-',
            'area_gis' => $areaGis,
            'area_gis_formatada' => $areaGis !== null ? number_format($areaGis, 2, ',', '.') . ' m²' : '-',
            'area_tributario' => $areaTrib,
            'area_tributario_raw' => $r['area_tributario_raw'] ?? '-',
            'area_tributario_formatada' => $areaTrib !== null ? number_format($areaTrib, 2, ',', '.') . ' m²' : ($r['area_tributario_raw'] ? $r['area_tributario_raw'] . ' m²' : '-'),
            'diferenca_m2' => $difM2,
            'diferenca_m2_formatada' => $difM2 !== null ? ($difM2 > 0 ? '+' : '') . number_format($difM2, 2, ',', '.') . ' m²' : '-',
            'diferenca_percentual' => $difPerc,
            'diferenca_percentual_formatada' => $difPerc !== null ? ($difPerc > 0 ? '+' : '') . number_format($difPerc, 2, ',', '.') . '%' : '-',
            'situacao' => $r['situacao'],
            'lat' => (float) $r['lat'],
            'lng' => (float) $r['lng']
        ];
    }, $records);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'kpis' => [
            'total_lotes' => (int) ($kpiSummary['total_lotes'] ?? 0),
            'total_divergencia' => (int) ($kpiSummary['total_divergencia'] ?? 0),
            'total_dentro_limite' => (int) ($kpiSummary['total_dentro_limite'] ?? 0),
            'total_sem_cadastro' => (int) ($kpiSummary['total_sem_cadastro'] ?? 0),
            'total_sem_area' => (int) ($kpiSummary['total_sem_area'] ?? 0),
            'total_area_zero' => (int) ($kpiSummary['total_area_zero'] ?? 0),
        ],
        'filtros' => [
            'situacao' => $situacaoFiltro,
            'busca' => $busca,
            'page' => $page,
            'limit' => $limit,
            'total_filtrados' => $totalFiltered,
            'total_paginas' => ceil($totalFiltered / $limit)
        ],
        'dados' => $formattedRecords
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao processar cálculo de divergências: ' . $e->getMessage()
    ]);
}
