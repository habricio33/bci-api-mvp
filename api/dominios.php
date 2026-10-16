<?php
/**
 * Endpoint para retornar valores distintos existentes no banco de dados.
 * Usado para popular dinamicamente os dropdowns de categoria, material e diâmetro.
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

$campo = $_GET['campo'] ?? '';
$tipo = $_GET['tipo'] ?? '';

// Whitelist de colunas permitidas
$allowedFields = [
    'categoria' => 'tipo',
    'material' => 'material',
    'diametro' => 'diametro'
];

// Whitelist de tabelas/views permitidas
$allowedTables = [
    'vw_rede_agua',
    'vw_rede_esgoto',
    'vw_edificacao_ramal_agua',
    'vw_edificacao_ramal_esgoto',
    'edificacao',
    'rede_agua',
    'rede_esgoto',
    'ramal_agua',
    'ramal_esgoto'
];

if (!array_key_exists($campo, $allowedFields) || !in_array($tipo, $allowedTables)) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'Parâmetros inválidos ou não autorizados.'
    ]);
    exit;
}

$db = getDBConnection();
$columnName = $allowedFields[$campo];

try {
    // Mapeamento caso venha nome legado de edificacao
    $tableName = $tipo;
    if ($tableName === 'edificacao') {
        $tableName = 'geo_edificacoes_dm';
        if ($columnName === 'tipo') {
            $columnName = 'caract';
        }
    }

    // Verifica se a tabela/view existe no banco antes de consultar
    $checkStmt = $db->prepare("
        SELECT 1 
        FROM information_schema.tables 
        WHERE table_name = :table_name AND table_schema IN ('geo', 'public')
    ");
    $checkStmt->execute([':table_name' => $tableName]);
    if (!$checkStmt->fetch()) {
        echo json_encode([]);
        exit;
    }

    // Consulta dinâmica segura utilizando apenas identificadores validados na whitelist
    $query = "
        SELECT DISTINCT \"$columnName\" AS valor 
        FROM \"$tableName\" 
        WHERE \"$columnName\" IS NOT NULL 
          AND CAST(\"$columnName\" AS TEXT) <> ''
        ORDER BY valor ASC
    ";

    $stmt = $db->query($query);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode($results);
} catch (Exception $e) {
    // Retorna lista vazia para evitar quebrar o carregamento da interface
    echo json_encode([]);
}
