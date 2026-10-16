<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$start = microtime(true);

// 1. Estatísticas gerais
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

$elapsed = (microtime(true) - $start) * 1000;
echo "Time: " . round($elapsed, 2) . "ms\n";
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
