<?php
/**
 * Endpoint para busca segmentada de logradouros.
 * Retorna os trechos de ruas contendo atributos e cálculo de extensão.
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
    // Consulta os trechos de geo_logradouros_dm correspondentes, com cálculo de extensão em metros
    $stmt = $db->prepare("
        SELECT 
            id,
            COALESCE(nome, 'Sem nome') AS name,
            COALESCE(tipo, 'Não informado') AS surface,
            COALESCE(nome_popul, '') AS highway,
            ROUND(ST_Length(ST_Transform(ST_Force2D(geom), 4326)::geography)) AS extensao,
            ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) AS lng,
            ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) AS lat,
            ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) AS geojson
        FROM geo.geo_logradouros_dm
        WHERE nome ILIKE :term OR nome_popul ILIKE :term
        ORDER BY nome ASC, id ASC
        LIMIT 60
    ");

    $stmt->execute([':term' => '%' . $term . '%']);
    $rows = $stmt->fetchAll();

    $results = [];
    foreach ($rows as $row) {
        $results[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'surface' => $row['surface'],
            'highway' => $row['highway'],
            'extensao' => (int)$row['extensao'],
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
        'message' => 'Erro ao buscar ruas: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
