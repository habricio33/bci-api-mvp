<?php
/**
 * Endpoint para busca segmentada de redes (Água ou Esgoto) por período de implantação.
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

$tipo = $_GET['tipo'] ?? '';
$de = $_GET['de'] ?? '';
$ate = $_GET['ate'] ?? '';

// Whitelist de tabelas/views de redes
$allowedTypes = [
    'vw_rede_agua' => 'id_rede_agua',
    'vw_rede_esgoto' => 'id_rede_esgoto'
];

if (!array_key_exists($tipo, $allowedTypes)) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'Tipo de rede inválido ou não autorizado.'
    ]);
    exit;
}

if (empty($de) || empty($ate)) {
    echo json_encode([]);
    exit;
}

$db = getDBConnection();
$idCol = $allowedTypes[$tipo];

try {
    // Consulta redes no período informado
    $stmt = $db->prepare("
        SELECT 
            t.\"$idCol\" AS id,
            COALESCE(t.material, 'Não informado') AS material,
            COALESCE(CAST(t.diametro AS TEXT), 'Não informado') AS diametro,
            TO_CHAR(t.data_implantacao, 'DD/MM/YYYY') AS data_formatada,
            ST_X(ST_Centroid(ST_Transform(ST_Force2D(t.geom), 4326))) AS lng,
            ST_Y(ST_Centroid(ST_Transform(ST_Force2D(t.geom), 4326))) AS lat,
            ST_AsGeoJSON(ST_Transform(ST_Force2D(t.geom), 4326)) AS geojson
        FROM \"$tipo\" t
        WHERE t.data_implantacao >= :de 
          AND t.data_implantacao <= :ate
        ORDER BY t.data_implantacao DESC, id ASC
        LIMIT 60
    ");

    $stmt->execute([
        ':de' => $de,
        ':ate' => $ate
    ]);
    $rows = $stmt->fetchAll();

    $results = [];
    foreach ($rows as $row) {
        $lat = $row['lat'] !== null ? (float)$row['lat'] : 0.0;
        $lng = $row['lng'] !== null ? (float)$row['lng'] : 0.0;
        
        // Tratar valores NaN ou Infinitos retornados por geometrias vazias/inválidas no PostGIS
        if (is_nan($lat) || is_infinite($lat)) {
            $lat = 0.0;
        }
        if (is_nan($lng) || is_infinite($lng)) {
            $lng = 0.0;
        }

        $results[] = [
            'id' => $row['id'],
            'camada' => $tipo,
            'material' => $row['material'],
            'diametro' => $row['diametro'],
            'data_implantacao' => $row['data_formatada'],
            'lat' => $lat,
            'lng' => $lng,
            'geojson' => json_decode($row['geojson'], true)
        ];
    }

    $json = json_encode($results, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        echo json_encode([
            'error' => true,
            'message' => 'Erro de codificação JSON: ' . json_last_error_msg()
        ]);
    } else {
        echo $json;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao buscar redes: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
