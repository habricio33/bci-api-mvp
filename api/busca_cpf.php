<?php
/**
 * Endpoint para busca segmentada de Imóveis por CPF / CNPJ do Contribuinte.
 * Retorna os dados do cadastro imobiliário (GP_IMOVEL) e suas geometrias associadas.
 */

// Registrar erros em arquivo local no workspace para fins de depuração
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $msg = date('[Y-m-d H:i:s] ') . "PHP Error [$errno]: $errstr in $errfile on line $errline\n";
    file_put_contents(__DIR__ . '/../api_error.log', $msg, FILE_APPEND);
    return false;
});

session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'error' => true,
        'message' => 'Não autorizado. Por favor, faça login.'
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

$rawTerm = trim($_GET['q'] ?? '');

if (empty($rawTerm)) {
    echo json_encode([]);
    exit;
}

$cleanDigits = preg_replace('/[^0-9]/', '', $rawTerm);

$db = getDBConnection();

try {
    $sql = '
        WITH matched_imoveis AS (
            SELECT 
                i."ID" AS id,
                i."INSC_MUNIC" AS insc_munic,
                COALESCE(i."NOMEDOCONTRIBUINTE", \'Não informado\') AS contribuinte,
                COALESCE(i."CPF_CNPJ", \'Não informado\') AS cpf_cnpj,
                TRIM(CONCAT_WS(\' \', i."NOMETIPOLOGRA", i."NOMELOGRADOURO")) AS logradouro,
                COALESCE(i."NUMEROLOGRADOURO", \'\') AS numero,
                COALESCE(i."NOMEBAIRRO", \'\') AS bairro,
                COALESCE(i."NOMEMUNICIPIO", \'Domingos Martins\') AS municipio
            FROM public."GP_IMOVEL" i
            WHERE i."CPF_CNPJ" ILIKE :raw_term 
               OR (:has_digits = 1 AND REGEXP_REPLACE(COALESCE(i."CPF_CNPJ", \'\'), \'[^0-9]\', \'\', \'g\') ILIKE :clean_digits)
               OR i."NOMEDOCONTRIBUINTE" ILIKE :raw_term
            ORDER BY i."NOMEDOCONTRIBUINTE" ASC, i."INSC_MUNIC" ASC
            LIMIT 50
        )
        SELECT 
            m.*,
            COALESCE(
                ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))),
                ST_X(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326)))
            ) AS lng,
            COALESCE(
                ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))),
                ST_Y(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326)))
            ) AS lat,
            COALESCE(
                ST_AsGeoJSON(ST_Transform(ST_Force2D(l.geom), 4326)),
                ST_AsGeoJSON(ST_Transform(ST_Force2D(e.geom), 4326))
            ) AS geojson
        FROM matched_imoveis m
        LEFT JOIN LATERAL (
            SELECT geom FROM geo.geo_lotes_dm 
            WHERE inscimob = SUBSTRING(m.insc_munic, 1, 11) 
               OR inscimob2 = m.insc_munic 
               OR inscimob2 LIKE SUBSTRING(m.insc_munic, 1, 11) || \'%\' 
               OR inscimob = m.insc_munic
            LIMIT 1
        ) l ON true
        LEFT JOIN LATERAL (
            SELECT geom FROM geo.geo_edificacoes_dm 
            WHERE inscimob = m.insc_munic 
               OR inscimob = SUBSTRING(m.insc_munic, 1, 11)
            LIMIT 1
        ) e ON true
    ';

    $stmt = $db->prepare($sql);
    $hasDigits = !empty($cleanDigits) ? 1 : 0;
    $stmt->execute([
        ':raw_term' => '%' . $rawTerm . '%',
        ':has_digits' => $hasDigits,
        ':clean_digits' => '%' . $cleanDigits . '%'
    ]);
    $rows = $stmt->fetchAll();

    $results = [];
    foreach ($rows as $row) {
        $hasGeom = !empty($row['geojson']) && $row['lat'] !== null && $row['lng'] !== null;
        $lat = $hasGeom ? (float)$row['lat'] : null;
        $lng = $hasGeom ? (float)$row['lng'] : null;

        if ($hasGeom && (is_nan($lat) || is_infinite($lat) || is_nan($lng) || is_infinite($lng))) {
            $hasGeom = false;
            $lat = null;
            $lng = null;
        }

        $results[] = [
            'id' => $row['id'],
            'insc_munic' => $row['insc_munic'],
            'contribuinte' => $row['contribuinte'],
            'cpf_cnpj' => $row['cpf_cnpj'],
            'logradouro' => $row['logradouro'],
            'numero' => $row['numero'],
            'bairro' => $row['bairro'],
            'municipio' => $row['municipio'],
            'tem_geom' => $hasGeom,
            'lat' => $lat,
            'lng' => $lng,
            'geojson' => $hasGeom ? json_decode($row['geojson'], true) : null
        ];
    }

    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao buscar imóveis por CPF/CNPJ: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
