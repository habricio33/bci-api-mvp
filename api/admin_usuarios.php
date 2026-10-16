<?php
/**
 * API Administrativa para Gestão de Usuários e Métricas do Sistema
 * Gerencia a tabela public.usuario com autenticação e validações seguras.
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
        ]);
        exit;
    }
}

require_once __DIR__ . '/../config/db.php';

$db = getDBConnection();
$action = isset($_REQUEST['action']) ? trim($_REQUEST['action']) : 'stats';

try {
    // =========================================================================
    // 1. ESTATÍSTICAS E INDICADORES GERAIS (DASHBOARD)
    // =========================================================================
    if ($action === 'stats') {
        header('Content-Type: application/json; charset=utf-8');

        // Total de Usuários
        $stmtTotal = $db->query("SELECT COUNT(*) FROM public.usuario");
        $totalUsuarios = (int)$stmtTotal->fetchColumn();

        // Contagem por Status
        $stmtStatus = $db->query("
            SELECT 
                status::text AS status,
                COUNT(*) AS total
            FROM public.usuario
            GROUP BY status
        ");
        $statusCountsRaw = $stmtStatus->fetchAll(PDO::FETCH_ASSOC);

        $statusCounts = [
            'ativo' => 0,
            'inativo' => 0,
            'bloqueado' => 0
        ];
        foreach ($statusCountsRaw as $row) {
            $key = strtolower($row['status']);
            $statusCounts[$key] = (int)$row['total'];
        }

        // Contagem por Perfil
        $stmtPerfil = $db->query("
            SELECT 
                perfil::text AS perfil,
                COUNT(*) AS total
            FROM public.usuario
            GROUP BY perfil
            ORDER BY total DESC
        ");
        $perfilCountsRaw = $stmtPerfil->fetchAll(PDO::FETCH_ASSOC);

        $perfilCounts = [
            'administrador' => 0,
            'editor' => 0,
            'visualizador' => 0
        ];
        $totalPerfisDistintos = count($perfilCountsRaw);
        foreach ($perfilCountsRaw as $row) {
            $key = strtolower($row['perfil']);
            $perfilCounts[$key] = (int)$row['total'];
        }

        // Usuários Recentes / Atividades Recentes
        $stmtRecentes = $db->query("
            SELECT 
                id, 
                nome, 
                login, 
                perfil::text AS perfil, 
                status::text AS status, 
                ultimo_login, 
                criado_em 
            FROM public.usuario 
            ORDER BY COALESCE(ultimo_login, criado_em) DESC 
            LIMIT 5
        ");
        $recentes = $stmtRecentes->fetchAll(PDO::FETCH_ASSOC);

        // Estatísticas do Banco / Sistema
        $totalLotes = 0;
        $totalImoveis = 0;
        try {
            $stmtLotes = $db->query("SELECT COUNT(*) FROM geo.geo_lotes_dm");
            $totalLotes = (int)$stmtLotes->fetchColumn();
        } catch (Exception $e) {}

        try {
            $stmtImov = $db->query("SELECT COUNT(*) FROM public.\"GP_IMOVEL\"");
            $totalImoveis = (int)$stmtImov->fetchColumn();
        } catch (Exception $e) {}

        $totalSincronizados = 0;
        try {
            $stmtSync = $db->query("SELECT COUNT(*) FROM public.tributario_api_imovel");
            $totalSincronizados = (int)$stmtSync->fetchColumn();
        } catch (Exception $e) {}

        echo json_encode([
            'success' => true,
            'kpis' => [
                'total_usuarios' => $totalUsuarios,
                'usuarios_ativos' => $statusCounts['ativo'],
                'usuarios_inativos' => $statusCounts['inativo'],
                'usuarios_bloqueados' => $statusCounts['bloqueado'],
                'total_perfis' => 3,
                'perfis_breakdown' => $perfilCounts,
                'status_breakdown' => $statusCounts,
                'total_lotes' => $totalLotes,
                'total_imoveis' => $totalImoveis,
                'total_sincronizados' => $totalSincronizados
            ],
            'recentes' => $recentes,
            'server_time' => date('d/m/Y H:i:s')
        ]);
        exit;
    }

    // =========================================================================
    // 2. LISTAGEM PAGINADA E FILTRADA DE USUÁRIOS
    // =========================================================================
    if ($action === 'listar') {
        header('Content-Type: application/json; charset=utf-8');

        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $perfil = isset($_GET['perfil']) ? trim($_GET['perfil']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $limit = isset($_GET['limit']) ? min(100, max(5, (int)$_GET['limit'])) : 15;
        $offset = ($page - 1) * $limit;

        $where = ["1=1"];
        $params = [];

        if ($search !== '') {
            $where[] = "(nome ILIKE :search OR login ILIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($perfil !== '' && $perfil !== 'todos') {
            $where[] = "perfil::text = :perfil";
            $params[':perfil'] = $perfil;
        }

        if ($status !== '' && $status !== 'todos') {
            $where[] = "status::text = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        // Contagem Total Filtrada
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM public.usuario WHERE {$whereSql}");
        $stmtCount->execute($params);
        $totalRecords = (int)$stmtCount->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        // Busca dos registros
        $stmtUsers = $db->prepare("
            SELECT 
                id, 
                nome, 
                login, 
                perfil::text AS perfil, 
                status::text AS status, 
                ultimo_login, 
                criado_em, 
                atualizado_em
            FROM public.usuario
            WHERE {$whereSql}
            ORDER BY id ASC
            LIMIT :limit OFFSET :offset
        ");

        foreach ($params as $k => $v) {
            $stmtUsers->bindValue($k, $v);
        }
        $stmtUsers->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtUsers->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtUsers->execute();
        $usuarios = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'page' => $page,
            'limit' => $limit,
            'total_records' => $totalRecords,
            'total_pages' => max(1, $totalPages),
            'usuarios' => $usuarios
        ]);
        exit;
    }

    // =========================================================================
    // 3. SALVAR / EDITAR USUÁRIO
    // =========================================================================
    if ($action === 'salvar') {
        header('Content-Type: application/json; charset=utf-8');

        $id = isset($_POST['id']) && is_numeric($_POST['id']) ? (int)$_POST['id'] : null;
        $nome = isset($_POST['nome']) ? trim($_POST['nome']) : '';
        $login = isset($_POST['login']) ? trim($_POST['login']) : '';
        $perfil = isset($_POST['perfil']) ? strtolower(trim($_POST['perfil'])) : 'visualizador';
        $status = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : 'ativo';
        $senha = isset($_POST['senha']) ? trim($_POST['senha']) : '';

        if (empty($nome) || empty($login)) {
            echo json_encode(['error' => true, 'message' => 'Nome e login são campos obrigatórios.']);
            exit;
        }

        // Validação de enums
        $validPerfis = ['administrador', 'editor', 'visualizador'];
        $validStatus = ['ativo', 'inativo', 'bloqueado'];

        if (!in_array($perfil, $validPerfis)) {
            $perfil = 'visualizador';
        }
        if (!in_array($status, $validStatus)) {
            $status = 'ativo';
        }

        // Verifica unicidade do login
        if ($id) {
            $stmtCheck = $db->prepare("SELECT id FROM public.usuario WHERE LOWER(login) = LOWER(?) AND id <> ?");
            $stmtCheck->execute([$login, $id]);
        } else {
            $stmtCheck = $db->prepare("SELECT id FROM public.usuario WHERE LOWER(login) = LOWER(?)");
            $stmtCheck->execute([$login]);
        }

        if ($stmtCheck->fetch()) {
            echo json_encode(['error' => true, 'message' => 'Este login já está em uso por outro usuário.']);
            exit;
        }

        if ($id) {
            // Atualizar usuário existente
            if (!empty($senha)) {
                $hashSenha = password_hash($senha, PASSWORD_DEFAULT);
                $stmtUpdate = $db->prepare("
                    UPDATE public.usuario 
                    SET nome = ?, login = ?, senha = ?, perfil = ?::perfil_usuario, status = ?::status_usuario, atualizado_em = NOW() 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$nome, $login, $hashSenha, $perfil, $status, $id]);
            } else {
                $stmtUpdate = $db->prepare("
                    UPDATE public.usuario 
                    SET nome = ?, login = ?, perfil = ?::perfil_usuario, status = ?::status_usuario, atualizado_em = NOW() 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$nome, $login, $perfil, $status, $id]);
            }

            echo json_encode(['success' => true, 'message' => 'Usuário atualizado com sucesso!']);
            exit;
        } else {
            // Criar novo usuário
            if (empty($senha)) {
                echo json_encode(['error' => true, 'message' => 'A senha inicial é obrigatória para novo usuário.']);
                exit;
            }

            $hashSenha = password_hash($senha, PASSWORD_DEFAULT);
            $stmtInsert = $db->prepare("
                INSERT INTO public.usuario (nome, login, senha, perfil, status, criado_em, atualizado_em) 
                VALUES (?, ?, ?, ?::perfil_usuario, ?::status_usuario, NOW(), NOW())
                RETURNING id
            ");
            $stmtInsert->execute([$nome, $login, $hashSenha, $perfil, $status]);
            $novoId = $stmtInsert->fetchColumn();

            echo json_encode(['success' => true, 'message' => 'Usuário cadastrado com sucesso!', 'id' => $novoId]);
            exit;
        }
    }

    // =========================================================================
    // 4. ALTERAR STATUS RÁPIDO (ATIVO / INATIVO / BLOQUEADO)
    // =========================================================================
    if ($action === 'alterar_status') {
        header('Content-Type: application/json; charset=utf-8');

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $novoStatus = isset($_POST['status']) ? strtolower(trim($_POST['status'])) : '';

        $validStatus = ['ativo', 'inativo', 'bloqueado'];
        if (!$id || !in_array($novoStatus, $validStatus)) {
            echo json_encode(['error' => true, 'message' => 'Parâmetros inválidos.']);
            exit;
        }

        // Impede que o usuário logado desative a si próprio
        if (isset($_SESSION['usuario']['id']) && (int)$_SESSION['usuario']['id'] === $id && $novoStatus !== 'ativo') {
            echo json_encode(['error' => true, 'message' => 'Você não pode desativar ou bloquear o seu próprio usuário logado.']);
            exit;
        }

        $stmt = $db->prepare("UPDATE public.usuario SET status = ?::status_usuario, atualizado_em = NOW() WHERE id = ?");
        $stmt->execute([$novoStatus, $id]);

        echo json_encode(['success' => true, 'message' => "Status do usuário alterado para '{$novoStatus}' com sucesso!"]);
        exit;
    }

    // =========================================================================
    // 5. REDEFINIR SENHA
    // =========================================================================
    if ($action === 'redefinir_senha') {
        header('Content-Type: application/json; charset=utf-8');

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $novaSenha = isset($_POST['senha']) ? trim($_POST['senha']) : '';

        if (!$id || empty($novaSenha)) {
            echo json_encode(['error' => true, 'message' => 'ID de usuário e nova senha são obrigatórios.']);
            exit;
        }

        if (strlen($novaSenha) < 4) {
            echo json_encode(['error' => true, 'message' => 'A senha deve conter no mínimo 4 caracteres.']);
            exit;
        }

        $hashSenha = password_hash($novaSenha, PASSWORD_DEFAULT);
        $stmt = $db->prepare("UPDATE public.usuario SET senha = ?, atualizado_em = NOW() WHERE id = ?");
        $stmt->execute([$hashSenha, $id]);

        echo json_encode(['success' => true, 'message' => 'Senha redefinida com sucesso!']);
        exit;
    }

    // =========================================================================
    // 6. EXCLUIR USUÁRIO
    // =========================================================================
    if ($action === 'excluir') {
        header('Content-Type: application/json; charset=utf-8');

        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if (!$id) {
            echo json_encode(['error' => true, 'message' => 'ID do usuário não informado.']);
            exit;
        }

        // Impede auto-exclusão
        if (isset($_SESSION['usuario']['id']) && (int)$_SESSION['usuario']['id'] === $id) {
            echo json_encode(['error' => true, 'message' => 'Você não pode excluir o seu próprio usuário logado.']);
            exit;
        }

        $stmt = $db->prepare("DELETE FROM public.usuario WHERE id = ?");
        $stmt->execute([$id]);

        echo json_encode(['success' => true, 'message' => 'Usuário excluído com sucesso!']);
        exit;
    }

    // =========================================================================
    // 7. EXPORTAÇÃO DE RELATÓRIO EM CSV
    // =========================================================================
    if ($action === 'export_csv') {
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $perfil = isset($_GET['perfil']) ? trim($_GET['perfil']) : '';
        $status = isset($_GET['status']) ? trim($_GET['status']) : '';

        $where = ["1=1"];
        $params = [];

        if ($search !== '') {
            $where[] = "(nome ILIKE :search OR login ILIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if ($perfil !== '' && $perfil !== 'todos') {
            $where[] = "perfil::text = :perfil";
            $params[':perfil'] = $perfil;
        }

        if ($status !== '' && $status !== 'todos') {
            $where[] = "status::text = :status";
            $params[':status'] = $status;
        }

        $whereSql = implode(' AND ', $where);

        $stmt = $db->prepare("
            SELECT 
                id, 
                nome, 
                login, 
                perfil::text AS perfil, 
                status::text AS status, 
                ultimo_login, 
                criado_em, 
                atualizado_em
            FROM public.usuario
            WHERE {$whereSql}
            ORDER BY id ASC
        ");
        $stmt->execute($params);
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $filename = 'relatorio_usuarios_bci_' . date('Ymd_His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // UTF-8 BOM para abrir acentuação corretamente no Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, [
            'ID',
            'Nome Completo',
            'Login / Usuário',
            'Perfil de Acesso',
            'Status',
            'Último Login',
            'Data de Cadastro',
            'Última Atualização'
        ], ';');

        foreach ($usuarios as $u) {
            fputcsv($out, [
                $u['id'],
                $u['nome'],
                $u['login'],
                ucfirst($u['perfil']),
                ucfirst($u['status']),
                $u['ultimo_login'] ? date('d/m/Y H:i:s', strtotime($u['ultimo_login'])) : 'Nunca acessou',
                $u['criado_em'] ? date('d/m/Y H:i:s', strtotime($u['criado_em'])) : '-',
                $u['atualizado_em'] ? date('d/m/Y H:i:s', strtotime($u['atualizado_em'])) : '-'
            ], ';');
        }

        fclose($out);
        exit;
    }

    echo json_encode(['error' => true, 'message' => 'Ação desconhecida.']);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro interno no servidor: ' . $e->getMessage()
    ]);
    exit;
}
