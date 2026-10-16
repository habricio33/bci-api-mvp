<?php
/**
 * Endpoint para retornar estatísticas e dados de agrupamento para filtros de Edificações (geo_edificacoes_dm + GP_IMOVEL).
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
    // 1. Estatísticas gerais de preenchimento cadastral das edificações
    $stmtStats = $db->query('
        SELECT 
            COUNT(e.id) as total,
            COUNT(g."INSC_MUNIC") as com_informacao,
            COUNT(CASE WHEN g."INSC_MUNIC" IS NULL THEN 1 END) as sem_informacao
        FROM geo.geo_edificacoes_dm e
        LEFT JOIN public."GP_IMOVEL" g ON e.inscimob = g."INSC_MUNIC"
    ');
    $stats = $stmtStats->fetch(PDO::FETCH_ASSOC);

    // 2. Agrupamento por Status Cadastral
    $stmtStatus = $db->query('
        SELECT 
            CASE 
                WHEN g."INSC_MUNIC" IS NOT NULL THEN \'Com Cadastro\'
                ELSE \'Não Cadastrado (Em branco)\'
            END as valor,
            COUNT(e.id) as total,
            CASE 
                WHEN g."INSC_MUNIC" IS NOT NULL THEN 0
                ELSE 1
            END as is_blank
        FROM geo.geo_edificacoes_dm e
        LEFT JOIN public."GP_IMOVEL" g ON e.inscimob = g."INSC_MUNIC"
        GROUP BY 1, 3
        ORDER BY is_blank ASC, total DESC
    ');
    $statusList = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

    // Helper para gerar agrupamento de campos de forma consistente
    function fetchGrouping($db, $columnName) {
        $stmt = $db->query("
            SELECT 
                CASE 
                    WHEN g.\"$columnName\" IS NOT NULL AND TRIM(g.\"$columnName\") <> '' 
                    THEN TRIM(g.\"$columnName\")
                    ELSE 'Não Informado'
                END as valor,
                COUNT(e.id) as total,
                CASE 
                    WHEN g.\"$columnName\" IS NOT NULL AND TRIM(g.\"$columnName\") <> '' THEN 0
                    ELSE 1
                END as is_blank
            FROM geo.geo_edificacoes_dm e
            LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
            GROUP BY 1, 3
            ORDER BY is_blank ASC, total DESC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $tipoConstrucaoList = fetchGrouping($db, 'TIPO_DE_CONSTRUCAO10042');
    $estruturaList = fetchGrouping($db, 'ESTRUTURA10043');
    $estadoConservacaoList = fetchGrouping($db, 'ESTADO_DE_CONSERVACAO10044');
    $situacaoImovelList = fetchGrouping($db, 'SITUACAO_DO_IMOVEL10048');
    $esgotoList = fetchGrouping($db, 'ESGOTO12001');
    $aguaList = fetchGrouping($db, 'AGUA12002');
    $lixoList = fetchGrouping($db, 'LIXO12006');

    $total = (int)$stats['total'];
    $comInfo = (int)$stats['com_informacao'];
    $semInfo = (int)$stats['sem_informacao'];

    $response = [
        'geo_edificacoes_dm' => [
            'stats' => [
                'total' => $total,
                'com_informacao' => $comInfo,
                'sem_informacao' => $semInfo,
                'pct_com_informacao' => $total > 0 ? round(($comInfo / $total) * 100, 1) : 0
            ],
            'tipo_construcao' => $tipoConstrucaoList,
            'estrutura' => $estruturaList,
            'estado_conservacao' => $estadoConservacaoList,
            'situacao_imovel' => $situacaoImovelList,
            'esgoto' => $esgotoList,
            'agua' => $aguaList,
            'lixo' => $lixoList,
            'status' => $statusList
        ]
    ];

    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao processar dados de filtros de edificações: ' . $e->getMessage()
    ]);
}
