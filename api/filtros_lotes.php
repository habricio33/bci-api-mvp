<?php
/**
 * Endpoint para retornar estatísticas e dados de agrupamento para filtros dos Lotes (geo_lotes_dm + GP_IMOVEL).
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
    // 1. Estatísticas gerais de preenchimento cadastral dos lotes
    $stmtStats = $db->query('
        SELECT 
            COUNT(l.id) as total,
            COUNT(g."INSC_MUNIC") as com_informacao,
            COUNT(CASE WHEN g."INSC_MUNIC" IS NULL THEN 1 END) as sem_informacao
        FROM geo.geo_lotes_dm l
        LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
    ');
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // 2. Agrupamento por Status Cadastral
    $stmtStatus = $db->query('
        SELECT 
            CASE 
                WHEN g."INSC_MUNIC" IS NOT NULL THEN \'Com Cadastro\'
                ELSE \'Não Cadastrado (Em branco)\'
            END as valor,
            COUNT(l.id) as total,
            CASE 
                WHEN g."INSC_MUNIC" IS NOT NULL THEN 0
                ELSE 1
            END as is_blank
        FROM geo.geo_lotes_dm l
        LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
        GROUP BY 1, 3
        ORDER BY is_blank ASC, total DESC
    ');
    $statusList = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

    // 3. Agrupamento por Utilização do Imóvel (UTILIZACAO_DO_IMOVEL9027)
    $stmtUtil = $db->query('
        SELECT 
            CASE 
                WHEN g."ULTILIZACAO_DO_IMOVEL9027" IS NOT NULL AND TRIM(g."ULTILIZACAO_DO_IMOVEL9027") <> \'\' 
                THEN TRIM(g."ULTILIZACAO_DO_IMOVEL9027")
                ELSE \'Não Informado\'
            END as valor,
            COUNT(l.id) as total,
            CASE 
                WHEN g."ULTILIZACAO_DO_IMOVEL9027" IS NOT NULL AND TRIM(g."ULTILIZACAO_DO_IMOVEL9027") <> \'\' THEN 0
                ELSE 1
            END as is_blank
        FROM geo.geo_lotes_dm l
        LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
        GROUP BY 1, 3
        ORDER BY is_blank ASC, total DESC
    ');
    $utilList = $stmtUtil->fetchAll(PDO::FETCH_ASSOC);

    // 4. Agrupamento por Ocupação do Imóvel (OCUPACAO9025)
    $stmtOcup = $db->query('
        SELECT 
            CASE 
                WHEN g."OCUPACAO9025" IS NOT NULL AND TRIM(g."OCUPACAO9025") <> \'\' 
                THEN TRIM(g."OCUPACAO9025")
                ELSE \'Não Informado\'
            END as valor,
            COUNT(l.id) as total,
            CASE 
                WHEN g."OCUPACAO9025" IS NOT NULL AND TRIM(g."OCUPACAO9025") <> \'\' THEN 0
                ELSE 1
            END as is_blank
        FROM geo.geo_lotes_dm l
        LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
        GROUP BY 1, 3
        ORDER BY is_blank ASC, total DESC
    ');
    $ocupList = $stmtOcup->fetchAll(PDO::FETCH_ASSOC);

    $total = (int)$stats['total'];
    $comInfo = (int)$stats['com_informacao'];
    $semInfo = (int)$stats['sem_informacao'];

    $response = [
        'geo_lotes_dm' => [
            'stats' => [
                'total' => $total,
                'com_informacao' => $comInfo,
                'sem_informacao' => $semInfo,
                'pct_com_informacao' => $total > 0 ? round(($comInfo / $total) * 100, 1) : 0,
                'pct_sem_informacao' => $total > 0 ? round(($semInfo / $total) * 100, 1) : 0,
            ],
            'status' => array_map(function($s) {
                return [
                    'valor' => $s['valor'],
                    'total' => (int)$s['total'],
                    'is_blank' => (bool)$s['is_blank']
                ];
            }, $statusList),
            'utilizacao' => array_map(function($u) {
                return [
                    'valor' => $u['valor'],
                    'total' => (int)$u['total'],
                    'is_blank' => (bool)$u['is_blank']
                ];
            }, $utilList),
            'ocupacao' => array_map(function($o) {
                return [
                    'valor' => $o['valor'],
                    'total' => (int)$o['total'],
                    'is_blank' => (bool)$o['is_blank']
                ];
            }, $ocupList)
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao obter estatísticas dos filtros de lotes: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
