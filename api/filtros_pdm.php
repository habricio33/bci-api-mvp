<?php
/**
 * Endpoint para retornar estatísticas e dados de agrupamento para filtros do PDM / Zoneamento (geo_zoneamento_dm + pdm.zoneamento_pdm).
 */

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

require_once __DIR__ . '/../config/db.php';

$db = getDBConnection();

try {
    // Parâmetros oficiais de siglas e nomes das zonas do PDM
    $siglasParam = [
        ['sigla' => 'ZOC', 'nome' => 'Zona de Ocupação Controlada'],
        ['sigla' => 'ZOP', 'nome' => 'Zona de Ocupação Preferencial'],
        ['sigla' => 'ZEC', 'nome' => 'Zona de Expansão Controlada'],
        ['sigla' => 'ZEIS', 'nome' => 'Zona Especial de Interesse Social'],
        ['sigla' => 'ER', 'nome' => 'Especial de Recuperação'],
        ['sigla' => 'ZIT', 'nome' => 'Zona de Interesse Turístico'],
        ['sigla' => 'ED', 'nome' => 'Eixo de Dinamização'],
        ['sigla' => 'EP', 'nome' => 'Eixo Principal'],
        ['sigla' => 'ZE', 'nome' => 'Zona Especial'],
        ['sigla' => 'ZEU', 'nome' => 'Zona de Expansão Urbana'],
        ['sigla' => 'ZRU', 'nome' => 'Zona Rurbana']
    ];

    // Busca o quantitativo agrupado por sigla no banco
    $stmt = $db->query('
        SELECT 
            TRIM(A.SIGLA) as sigla,
            COUNT(D.ID_ZONA) as total
        FROM geo."geo_zoneamento_dm" AS D
        INNER JOIN pdm."zoneamento_pdm" AS A ON A.ID_ZONA = D.ID_ZONA
        GROUP BY TRIM(A.SIGLA)
    ');
    $countsBySigla = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $siglaList = [];
    $totalComDados = 0;

    foreach ($siglasParam as $item) {
        $sigla = $item['sigla'];
        $total = isset($countsBySigla[$sigla]) ? (int)$countsBySigla[$sigla] : 0;
        $totalComDados += $total;
        $siglaList[] = [
            'valor' => $sigla,
            'label' => "{$sigla} - {$item['nome']}",
            'nome' => $item['nome'],
            'total' => $total,
            'is_blank' => 0
        ];
    }

    // Total de feições na camada geográfica
    $totalGeom = (int)$db->query('SELECT count(*) FROM geo."geo_zoneamento_dm"')->fetchColumn();
    $semDados = max(0, $totalGeom - $totalComDados);

    if ($semDados > 0) {
        $siglaList[] = [
            'valor' => 'Não Informado',
            'label' => 'Não Informado / Não Classificado',
            'nome' => 'Não Classificado',
            'total' => $semDados,
            'is_blank' => 1
        ];
    }

    $response = [
        'geo_zoneamento_dm' => [
            'stats' => [
                'total' => $totalGeom,
                'com_informacao' => $totalComDados,
                'sem_informacao' => $semDados,
                'pct_com_informacao' => $totalGeom > 0 ? round(($totalComDados / $totalGeom) * 100, 1) : 0
            ],
            'sigla' => $siglaList
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao processar dados de filtros do PDM: ' . $e->getMessage()
    ]);
}
