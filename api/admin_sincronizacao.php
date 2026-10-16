<?php
/**
 * API Administrativa para Sincronização com a API Tributária Oficial
 * Gerencia consultas, sincronização em lote e monitoramento da tabela tributario_api_imovel.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['usuario'])) {
        http_response_code(401);
        echo json_encode([
            'error' => true,
            'message' => 'Acesso não autorizado. Por favor, realize o login.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/TributarioApiClient.php';

$db = getDBConnection();
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'stats';

try {
    $client = new TributarioApiClient();

    // =========================================================================
    // 1. ESTATÍSTICAS E STATUS DA BASE SINCRONIZADA
    // =========================================================================
    if ($action === 'stats') {
        // Total na tabela oficial da API
        $stmtSync = $db->query("SELECT COUNT(*) FROM public.tributario_api_imovel");
        $totalSincronizados = (int)$stmtSync->fetchColumn();

        // Total na tabela legada GP_IMOVEL
        $totalLegados = 0;
        try {
            $stmtLegado = $db->query('SELECT COUNT(*) FROM public."GP_IMOVEL"');
            $totalLegados = (int)$stmtLegado->fetchColumn();
        } catch (Exception $e) {}

        // Última atualização
        $stmtLast = $db->query("
            SELECT MAX(atualizado_em) as ultima_atualizacao,
                   MIN(atualizado_em) as primeira_atualizacao
            FROM public.tributario_api_imovel
        ");
        $lastRow = $stmtLast->fetch(PDO::FETCH_ASSOC);
        $ultimaAtualizacao = $lastRow && !empty($lastRow['ultima_atualizacao']) 
            ? date('d/m/Y H:i:s', strtotime($lastRow['ultima_atualizacao'])) 
            : 'Nenhuma sincronização';

        // Breakdown por Situação (Ativo, Baixado, etc.)
        $stmtSit = $db->query("
            SELECT COALESCE(situacao, 'Não Informado') as situacao, COUNT(*) as total
            FROM public.tributario_api_imovel
            GROUP BY situacao
            ORDER BY total DESC
        ");
        $situacoes = $stmtSit->fetchAll(PDO::FETCH_ASSOC);

        // Última alteração na base
        $stmtMaxAlt = $db->query("
            SELECT 
                MAX(data_alteracao) as max_alt, 
                MAX(data_inclusao) as max_inc, 
                MAX(atualizado_em)::date as max_att_date
            FROM public.tributario_api_imovel
        ");
        $maxAltRow = $stmtMaxAlt->fetch(PDO::FETCH_ASSOC);
        
        $dataIncrementalSugestao = !empty($maxAltRow['max_alt']) 
            ? $maxAltRow['max_alt'] 
            : (!empty($maxAltRow['max_att_date']) ? $maxAltRow['max_att_date'] : date('Y-m-d', strtotime('-30 days')));

        // Próximas páginas estimadas para continuar a carga inicial
        $proximaPagina100 = (int)floor($totalSincronizados / 100) + 1;
        $proximaPagina50 = (int)floor($totalSincronizados / 50) + 1;
        $proximaPaginaEstimada = $proximaPagina100;

        // Percentual em relação ao cadastro geral
        $percentual = ($totalLegados > 0) ? round(($totalSincronizados / $totalLegados) * 100, 2) : 0;

        echo json_encode([
            'success' => true,
            'stats' => [
                'total_sincronizados' => $totalSincronizados,
                'total_legados' => $totalLegados,
                'percentual' => $percentual,
                'ultima_atualizacao' => $ultimaAtualizacao,
                'ultima_data_alteracao' => $maxAltRow['max_alt'] ?? null,
                'sugestao_data_incremental' => $dataIncrementalSugestao,
                'proxima_pagina_estimada' => $proximaPaginaEstimada,
                'proxima_pagina_50' => $proximaPagina50,
                'proxima_pagina_100' => $proximaPagina100,
                'situacoes' => $situacoes
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // 2. TESTE DE CONEXÃO COM A API EXTERNA
    // =========================================================================
    if ($action === 'test_conexao') {
        $startTime = microtime(true);
        $token = $client->getToken();
        $elapsed = round((microtime(true) - $startTime) * 1000, 1);

        echo json_encode([
            'success' => true,
            'message' => 'Conexão e autenticação estabelecidas com sucesso!',
            'tempo_ms' => $elapsed,
            'token_preview' => substr($token, 0, 15) . '...' . substr($token, -10),
            'timestamp' => date('d/m/Y H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // 3. EXECUÇÃO DA SINCRONIZAÇÃO EM LOTE
    // =========================================================================
    if ($action === 'sincronizar') {
        $dataInicial = isset($_POST['data_inicial']) ? trim($_POST['data_inicial']) : (isset($_GET['data_inicial']) ? trim($_GET['data_inicial']) : '');
        $dataFinal = isset($_POST['data_final']) ? trim($_POST['data_final']) : (isset($_GET['data_final']) ? trim($_GET['data_final']) : '');
        $startPage = isset($_POST['start_page']) ? max(1, (int)$_POST['start_page']) : (isset($_GET['start_page']) ? max(1, (int)$_GET['start_page']) : 1);
        $pageSize = isset($_POST['page_size']) ? min(100, max(10, (int)$_POST['page_size'])) : (isset($_GET['page_size']) ? min(100, max(10, (int)$_GET['page_size'])) : 50);
        $maxPages = isset($_POST['max_pages']) ? (int)$_POST['max_pages'] : (isset($_GET['max_pages']) ? (int)$_GET['max_pages'] : 10);

        if (empty($dataInicial) || empty($dataFinal)) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'As datas inicial e final são obrigatórias (formato AAAA-MM-DD).'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        if (strtotime($dataInicial) > strtotime($dataFinal)) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'A data inicial não pode ser maior que a data final.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Se max_pages for 0, significa "Todas as páginas até o final"
        // Se for > 0, permite até 500 páginas (suficiente para cobrir toda a base de 14.077 registros)
        if ($maxPages > 0) {
            $maxPages = min(500, $maxPages);
        } else {
            $maxPages = 0; // Todas as páginas disponíveis
        }
        
        $startTime = microtime(true);
        $resultado = $client->sincronizarPeriodo($dataInicial, $dataFinal, $maxPages, $startPage, $pageSize, $db);
        $tempoExecucao = round((microtime(true) - $startTime), 2);

        // Recalcula o total na tabela
        $novoTotal = (int)$db->query("SELECT COUNT(*) FROM public.tributario_api_imovel")->fetchColumn();

        echo json_encode([
            'success' => true,
            'message' => sprintf(
                'Sincronização concluída! %d imóvel(is) processado(s) e atualizados (Páginas %d a %d).',
                $resultado['total_salvos'],
                $resultado['start_page'],
                $resultado['last_page']
            ),
            'detalhes' => [
                'total_salvos' => $resultado['total_salvos'],
                'data_inicial' => $resultado['data_inicial'],
                'data_final' => $resultado['data_final'],
                'start_page' => $resultado['start_page'],
                'last_page' => $resultado['last_page'],
                'next_page' => $resultado['last_page'] + 1,
                'paginas_processadas' => $resultado['paginas_processadas'],
                'total_pages_api' => $resultado['total_pages_api'],
                'total_count_api' => $resultado['total_count_api'],
                'tempo_segundos' => $tempoExecucao,
                'novo_total_banco' => $novoTotal
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // 4. LISTAGEM PAGINADA DOS REGISTROS SINCRONIZADOS
    // =========================================================================
    if ($action === 'listar') {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $situacao = isset($_GET['situacao']) ? trim($_GET['situacao']) : '';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(5, (int)$_GET['limit'])) : 15;
        $offset = ($page - 1) * $limit;

        $where = [];
        $params = [];

        if (!empty($search)) {
            $where[] = '(insc_munic ILIKE :search OR codigo::text ILIKE :search OR dados_bci->>\'NOMEDOCONTRIBUINTE\' ILIKE :search OR dados_bci->>\'NOMELOGRADOURO\' ILIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        if (!empty($situacao) && $situacao !== 'todos') {
            $where[] = 'situacao = :situacao';
            $params[':situacao'] = $situacao;
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Contagem Total
        $countStmt = $db->prepare("SELECT COUNT(*) FROM public.tributario_api_imovel $whereClause");
        $countStmt->execute($params);
        $totalRecords = (int)$countStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit) ?: 1;

        // Registros Paginados
        $query = "
            SELECT 
                insc_munic,
                codigo,
                situacao,
                dados_bci->>'NOMEDOCONTRIBUINTE' as contribuinte,
                dados_bci->>'CPF_CNPJ' as documento,
                dados_bci->>'NOMELOGRADOURO' as logradouro,
                dados_bci->>'NUMEROLOGRADOURO' as numero,
                dados_bci->>'NOMEBAIRRO' as bairro,
                dados_bci->>'AREA_DO_TERRENO13065' as area_terreno,
                dados_bci->>'AREA_TOTAL_DA_EDIFICACAO13067' as area_construida,
                data_inclusao,
                data_alteracao,
                atualizado_em
            FROM public.tributario_api_imovel
            $whereClause
            ORDER BY atualizado_em DESC, insc_munic ASC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Formata datas
        foreach ($records as &$r) {
            if (!empty($r['atualizado_em'])) {
                $r['atualizado_em_formatado'] = date('d/m/Y H:i', strtotime($r['atualizado_em']));
            } else {
                $r['atualizado_em_formatado'] = '-';
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $records,
            'pagination' => [
                'total_records' => $totalRecords,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'limit' => $limit
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // =========================================================================
    // 5. DETALHES COMPLETOS DE UM REGISTRO SINCRONIZADO
    // =========================================================================
    if ($action === 'detalhes') {
        $insc = isset($_GET['insc']) ? trim($_GET['insc']) : '';
        if (empty($insc)) {
            http_response_code(400);
            echo json_encode(['error' => true, 'message' => 'Parâmetro insc obrigatório.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $stmt = $db->prepare("SELECT * FROM public.tributario_api_imovel WHERE insc_munic = :insc LIMIT 1");
        $stmt->execute([':insc' => $insc]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['error' => true, 'message' => 'Imóvel não encontrado na base oficial.'], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $dadosBci = !empty($row['dados_bci']) ? json_decode($row['dados_bci'], true) : [];
        $dadosRaw = !empty($row['dados_api_raw']) ? json_decode($row['dados_api_raw'], true) : [];

        echo json_encode([
            'success' => true,
            'insc_munic' => $row['insc_munic'],
            'codigo' => $row['codigo'],
            'situacao' => $row['situacao'],
            'atualizado_em' => $row['atualizado_em'],
            'dados_bci' => $dadosBci,
            'dados_api_raw' => $dadosRaw
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Ação não reconhecida.'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro interno: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
