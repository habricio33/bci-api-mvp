<?php
/**
 * Endpoint para autocomplete de ruas.
 * Retorna logradouros únicos que correspondem ao termo digitado.
 */

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

if (strlen(trim($term)) < 2) {
    echo json_encode([]);
    exit;
}

$db = getDBConnection();

try {
    // Busca os nomes distintos dos logradouros
    $stmt = $db->prepare("
        SELECT DISTINCT nome 
        FROM geo.geo_logradouros_dm 
        WHERE nome ILIKE :term 
        ORDER BY nome ASC 
        LIMIT 15
    ");
    
    $stmt->execute([':term' => '%' . $term . '%']);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($results);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro no autocomplete: ' . $e->getMessage()
    ]);
}
