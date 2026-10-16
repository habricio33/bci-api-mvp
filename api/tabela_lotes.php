<?php
/**
 * Endpoint para listagem paginada e filtrada de dados cadastrais (Lotes ou Edificações)
 * com suporte a busca textual, ordenação por colunas e filtros temáticos.
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
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/db.php';

$db = getDBConnection();

try {
    $type = trim($_GET['type'] ?? 'lotes');
    if ($type !== 'edificacoes') {
        $type = 'lotes';
    }

    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(5, min(200, (int)($_GET['limit'] ?? 25)));
    $offset = ($page - 1) * $limit;

    $search = trim($_GET['search'] ?? '');
    $criterion = trim($_GET['criterion'] ?? 'all');
    $selectedClass = trim($_GET['class'] ?? '');
    $activeClassesParam = trim($_GET['active_classes'] ?? '');

    $whereConditions = [];
    $params = [];

    if ($type === 'edificacoes') {
        // 1. Filtro por Busca Textual - Edificações
        if ($search !== '') {
            $searchLike = '%' . $search . '%';
            $whereConditions[] = '(
                e.inscimob ILIKE :search
                OR g."INSC_MUNIC" ILIKE :search
                OR g."NOMEDOCONTRIBUINTE" ILIKE :search
                OR g."NOMELOGRADOURO" ILIKE :search
                OR g."NOMEBAIRRO" ILIKE :search
                OR g."TIPO_DE_CONSTRUCAO10042" ILIKE :search
                OR g."ESTRUTURA10043" ILIKE :search
                OR g."ESTADO_DE_CONSERVACAO10044" ILIKE :search
                OR g."SITUACAO_DO_IMOVEL10048" ILIKE :search
                OR g."ESGOTO12001" ILIKE :search
                OR g."AGUA12002" ILIKE :search
                OR g."LIXO12006" ILIKE :search
            )';
            $params[':search'] = $searchLike;
        }

        // 2. Mapeamento de Colunas para Filtros Temáticos - Edificações
        $criterionColumnMap = [
            'status' => 'status',
            'tipo_construcao' => 'g."TIPO_DE_CONSTRUCAO10042"',
            'estrutura' => 'g."ESTRUTURA10043"',
            'estado_conservacao' => 'g."ESTADO_DE_CONSERVACAO10044"',
            'situacao_imovel' => 'g."SITUACAO_DO_IMOVEL10048"',
            'esgoto' => 'g."ESGOTO12001"',
            'agua' => 'g."AGUA12002"',
            'lixo' => 'g."LIXO12006"'
        ];

        // Filtro por Classe Específica selecionada
        if ($selectedClass !== '' && $selectedClass !== 'all') {
            if ($criterion === 'status') {
                if ($selectedClass === 'Com Cadastro') {
                    $whereConditions[] = 'g."INSC_MUNIC" IS NOT NULL';
                } elseif ($selectedClass === 'Não Cadastrado (Em branco)' || $selectedClass === 'Não Cadastrado') {
                    $whereConditions[] = 'g."INSC_MUNIC" IS NULL';
                }
            } elseif (isset($criterionColumnMap[$criterion])) {
                $colExpr = $criterionColumnMap[$criterion];
                if ($selectedClass === 'Não Informado' || strpos($selectedClass, 'branco') !== false || strpos($selectedClass, 'Sem Dados') !== false) {
                    $whereConditions[] = "($colExpr IS NULL OR TRIM(CAST($colExpr AS text)) = '')";
                } else {
                    $whereConditions[] = "TRIM(CAST($colExpr AS text)) = :selected_class";
                    $params[':selected_class'] = $selectedClass;
                }
            }
        }

        // 3. Filtro por Lista de Classes Ativas na Legenda
        if ($activeClassesParam !== '' && $activeClassesParam !== 'all' && ($selectedClass === '' || $selectedClass === 'all')) {
            $classesArray = array_map('trim', explode('|||', $activeClassesParam));
            $classesArray = array_filter($classesArray, function($c) { return $c !== ''; });

            if (!empty($classesArray)) {
                $classOrClauses = [];
                $paramIndex = 0;

                foreach ($classesArray as $cls) {
                    $pName = ':act_cls_' . $paramIndex++;
                    if ($criterion === 'status') {
                        if ($cls === 'Com Cadastro') {
                            $classOrClauses[] = 'g."INSC_MUNIC" IS NOT NULL';
                        } elseif (strpos($cls, 'Não Cadastrado') !== false || strpos($cls, 'branco') !== false) {
                            $classOrClauses[] = 'g."INSC_MUNIC" IS NULL';
                        }
                    } elseif (isset($criterionColumnMap[$criterion])) {
                        $colExpr = $criterionColumnMap[$criterion];
                        if ($cls === 'Não Informado' || strpos($cls, 'branco') !== false) {
                            $classOrClauses[] = "($colExpr IS NULL OR TRIM(CAST($colExpr AS text)) = '')";
                        } else {
                            $classOrClauses[] = "TRIM(CAST($colExpr AS text)) = $pName";
                            $params[$pName] = $cls;
                        }
                    }
                }

                if (!empty($classOrClauses)) {
                    $whereConditions[] = '(' . implode(' OR ', $classOrClauses) . ')';
                }
            }
        }

        $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Contagem Total Filtrada
        $countSql = "
            SELECT COUNT(e.id) as total
            FROM geo.geo_edificacoes_dm e
            LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
            $whereSql
        ";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $limit;
        }

        $sortBy = $_GET['sort_by'] ?? 'id';
        $sortDir = strtoupper($_GET['sort_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $allowedSort = [
            'id' => 'e.id',
            'insc_munic' => 'COALESCE(g."INSC_MUNIC", e.inscimob)',
            'contribuinte' => 'g."NOMEDOCONTRIBUINTE"',
            'logradouro' => 'g."NOMELOGRADOURO"',
            'bairro' => 'g."NOMEBAIRRO"',
            'tipo_construcao' => 'g."TIPO_DE_CONSTRUCAO10042"',
            'estrutura' => 'g."ESTRUTURA10043"',
            'estado_conservacao' => 'g."ESTADO_DE_CONSERVACAO10044"',
            'situacao_imovel' => 'g."SITUACAO_DO_IMOVEL10048"',
            'esgoto' => 'g."ESGOTO12001"',
            'agua' => 'g."AGUA12002"',
            'area_unidade' => 'g."AREA_DA_UNIDADE13066"',
            'area_total_edificacao' => 'g."AREA_TOTAL_DA_EDIFICACAO13067"'
        ];
        $orderColumn = $allowedSort[$sortBy] ?? 'e.id';

        $dataSql = "
            SELECT 
                e.id,
                e.inscimob,
                COALESCE(g.\"INSC_MUNIC\", e.inscimob, '-') as insc_munic,
                (g.\"INSC_MUNIC\" IS NOT NULL) as has_cadastro,
                CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Com Cadastro' ELSE 'Não Cadastrado' END as status_cadastral,
                COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-') as contribuinte,
                COALESCE(g.\"NOMELOGRADOURO\", '-') as logradouro,
                COALESCE(g.\"NOMEBAIRRO\", '-') as bairro,
                COALESCE(g.\"TIPO_DE_CONSTRUCAO10042\", '-') as tipo_construcao,
                COALESCE(g.\"ESTRUTURA10043\", '-') as estrutura,
                COALESCE(g.\"ESTADO_DE_CONSERVACAO10044\", '-') as estado_conservacao,
                COALESCE(g.\"SITUACAO_DO_IMOVEL10048\", '-') as situacao_imovel,
                COALESCE(g.\"ESGOTO12001\", '-') as esgoto,
                COALESCE(g.\"AGUA12002\", '-') as agua,
                COALESCE(g.\"AREA_DA_UNIDADE13066\", '-') as area_unidade,
                COALESCE(g.\"AREA_TOTAL_DA_EDIFICACAO13067\", '-') as area_total_edificacao,
                ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326))) AS numeric), 6) as lng,
                ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326))) AS numeric), 6) as lat
            FROM geo.geo_edificacoes_dm e
            LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
            $whereSql
            ORDER BY $orderColumn $sortDir
            LIMIT :limit OFFSET :offset
        ";

    } else {
        // 1. Filtro por Busca Textual - Lotes
        if ($search !== '') {
            $searchLike = '%' . $search . '%';
            $whereConditions[] = '(
                l.inscimob ILIKE :search
                OR l.inscimob2 ILIKE :search
                OR g."INSC_MUNIC" ILIKE :search
                OR g."NOMEDOCONTRIBUINTE" ILIKE :search
                OR g."NOMELOGRADOURO" ILIKE :search
                OR g."NOMEBAIRRO" ILIKE :search
                OR g."ULTILIZACAO_DO_IMOVEL9027" ILIKE :search
                OR g."OCUPACAO9025" ILIKE :search
            )';
            $params[':search'] = $searchLike;
        }

        // 2. Mapeamento de Colunas para Filtros Temáticos - Lotes
        $criterionColumnMap = [
            'status' => 'status',
            'utilizacao' => 'g."ULTILIZACAO_DO_IMOVEL9027"',
            'ocupacao' => 'g."OCUPACAO9025"'
        ];

        // Filtro por Classe Específica selecionada
        if ($selectedClass !== '' && $selectedClass !== 'all') {
            if ($criterion === 'status') {
                if ($selectedClass === 'Com Cadastro') {
                    $whereConditions[] = 'g."INSC_MUNIC" IS NOT NULL';
                } elseif ($selectedClass === 'Não Cadastrado (Em branco)' || $selectedClass === 'Não Cadastrado') {
                    $whereConditions[] = 'g."INSC_MUNIC" IS NULL';
                }
            } elseif (isset($criterionColumnMap[$criterion])) {
                $colExpr = $criterionColumnMap[$criterion];
                if ($selectedClass === 'Não Informado' || strpos($selectedClass, 'branco') !== false || strpos($selectedClass, 'Sem Dados') !== false) {
                    $whereConditions[] = "($colExpr IS NULL OR TRIM(CAST($colExpr AS text)) = '')";
                } else {
                    $whereConditions[] = "TRIM(CAST($colExpr AS text)) = :selected_class";
                    $params[':selected_class'] = $selectedClass;
                }
            }
        }

        // 3. Filtro por Lista de Classes Ativas na Legenda
        if ($activeClassesParam !== '' && $activeClassesParam !== 'all' && ($selectedClass === '' || $selectedClass === 'all')) {
            $classesArray = array_map('trim', explode('|||', $activeClassesParam));
            $classesArray = array_filter($classesArray, function($c) { return $c !== ''; });

            if (!empty($classesArray)) {
                $classOrClauses = [];
                $paramIndex = 0;

                foreach ($classesArray as $cls) {
                    $pName = ':act_cls_' . $paramIndex++;
                    if ($criterion === 'status') {
                        if ($cls === 'Com Cadastro') {
                            $classOrClauses[] = 'g."INSC_MUNIC" IS NOT NULL';
                        } elseif (strpos($cls, 'Não Cadastrado') !== false || strpos($cls, 'branco') !== false) {
                            $classOrClauses[] = 'g."INSC_MUNIC" IS NULL';
                        }
                    } elseif (isset($criterionColumnMap[$criterion])) {
                        $colExpr = $criterionColumnMap[$criterion];
                        if ($cls === 'Não Informado' || strpos($cls, 'branco') !== false) {
                            $classOrClauses[] = "($colExpr IS NULL OR TRIM(CAST($colExpr AS text)) = '')";
                        } else {
                            $classOrClauses[] = "TRIM(CAST($colExpr AS text)) = $pName";
                            $params[$pName] = $cls;
                        }
                    }
                }

                if (!empty($classOrClauses)) {
                    $whereConditions[] = '(' . implode(' OR ', $classOrClauses) . ')';
                }
            }
        }

        $whereSql = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Contagem Total Filtrada
        $countSql = "
            SELECT COUNT(l.id) as total
            FROM geo.geo_lotes_dm l
            LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\"
            $whereSql
        ";
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $totalPages = max(1, (int)ceil($total / $limit));
        if ($page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $limit;
        }

        $sortBy = $_GET['sort_by'] ?? 'id';
        $sortDir = strtoupper($_GET['sort_dir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        $allowedSort = [
            'id' => 'l.id',
            'inscimob2' => 'l.inscimob2',
            'inscimob' => 'l.inscimob',
            'status_cadastral' => 'g."INSC_MUNIC"',
            'utilizacao' => 'g."ULTILIZACAO_DO_IMOVEL9027"',
            'ocupacao' => 'g."OCUPACAO9025"',
            'proprietario' => 'g."NOMEDOCONTRIBUINTE"',
            'logradouro' => 'g."NOMELOGRADOURO"',
            'bairro' => 'g."NOMEBAIRRO"',
            'area_terreno' => 'g."AREA_DO_TERRENO13065"'
        ];
        $orderColumn = $allowedSort[$sortBy] ?? 'l.id';

        $dataSql = "
            SELECT 
                l.id,
                l.inscimob,
                l.inscimob2,
                l.obs,
                COALESCE(l.inscimob2, g.\"INSC_MUNIC\", l.inscimob, '-') as insc_munic,
                (g.\"INSC_MUNIC\" IS NOT NULL) as has_cadastro,
                CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Com Cadastro' ELSE 'Não Cadastrado' END as status_cadastral,
                COALESCE(g.\"ULTILIZACAO_DO_IMOVEL9027\", '-') as utilizacao,
                COALESCE(g.\"OCUPACAO9025\", '-') as ocupacao,
                COALESCE(g.\"NOMEDOCONTRIBUINTE\", '-') as proprietario,
                COALESCE(g.\"NOMELOGRADOURO\", '-') as logradouro,
                COALESCE(g.\"NOMEBAIRRO\", '-') as bairro,
                COALESCE(g.\"AREA_DO_TERRENO13065\", '-') as area_terreno,
                ROUND(CAST(ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) as lng,
                ROUND(CAST(ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))) AS numeric), 6) as lat
            FROM geo.geo_lotes_dm l
            LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\"
            $whereSql
            ORDER BY $orderColumn $sortDir
            LIMIT :limit OFFSET :offset
        ";
    }

    $dataStmt = $db->prepare($dataSql);
    foreach ($params as $k => $v) {
        $dataStmt->bindValue($k, $v);
    }
    $dataStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'type' => $type,
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'total_pages' => $totalPages,
        'criterion' => $criterion,
        'selected_class' => $selectedClass,
        'data' => $rows
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao carregar tabela de dados: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
