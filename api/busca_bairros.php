<?php
/**
 * Endpoint para busca segmentada de bairros.
 * Retorna os bairros correspondentes e suas geometrias.
 */

// Registrar erros em arquivo local no workspace para fins de depuração antes de qualquer outra ação
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

$term = $_GET['q'] ?? '';

if (empty(trim($term))) {
    echo json_encode([]);
    exit;
}

$db = getDBConnection();

try {
    // Consulta as comunidades/bairros pelo campo comunidade (com fallback em nome) usando ILIKE
    $stmt = $db->prepare("
        SELECT 
            id2 AS id,
            COALESCE(comunidade, nome, 'Sem nome') AS bairro,
            comunidade,
            nome,
            ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) AS lng,
            ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) AS lat,
            ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) AS geojson
        FROM geo.geo_comunidades_dm
        WHERE comunidade ILIKE :term OR nome ILIKE :term
        ORDER BY COALESCE(comunidade, nome) ASC, id2 ASC
        LIMIT 50
    ");

    $stmt->execute([':term' => '%' . $term . '%']);
    $rows = $stmt->fetchAll();

    $results = [];
    foreach ($rows as $row) {
        $results[] = [
            'id' => $row['id'],
            'bairro' => $row['bairro'],
            'comunidade' => $row['comunidade'],
            'lat' => (float)$row['lat'],
            'lng' => (float)$row['lng'],
            'geojson' => json_decode($row['geojson'], true)
        ];
    }

    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao buscar bairros: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
