<?php
/**
 * Endpoint CRUD para Edição e Cadastro de Atributos Alfanuméricos da Rede de Esgoto.
 * 
 * Métodos suportados:
 *  - GET: Busca atributos pelo id_geo ou pelas coordenadas lat/lng do clique.
 *  - POST: Insere ou atualiza os atributos da rede vinculada ao id_geo (Upsert).
 *  - DELETE: Remove o registro alfanumérico da tabela rede_esgoto, mantendo a geometria.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);

session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Não autorizado. Por favor, faça login.'
    ]);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$db = getDBConnection();

try {
    // =========================================================================
    // 1. MÉTODO GET - Consulta por ID_GEO ou por Coordenadas (LAT, LNG)
    // =========================================================================
    if ($method === 'GET') {
        $idGeo = isset($_GET['id_geo']) ? (int)$_GET['id_geo'] : null;
        $lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
        $lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

        if ($idGeo !== null) {
            // Busca direta por id_geo
            $stmt = $db->prepare("
                SELECT 
                    a.id AS id_geo,
                    ROUND(ST_Length(ST_Transform(ST_Force2D(a.geom), 31983))::numeric, 2) AS extensao_m,
                    b.id AS id_alfanumerico,
                    b.rede,
                    b.tipo::text AS tipo,
                    b.diametro,
                    b.material,
                    b.profundidade,
                    b.observacao,
                    TO_CHAR(b.data_implantacao, 'YYYY-MM-DD') AS data_implantacao,
                    b.responsavel_implantacao
                FROM rede_esgoto_geo a
                LEFT JOIN rede_esgoto b ON a.id = b.id_geo
                WHERE a.id = :id_geo
                LIMIT 1
            ");
            $stmt->execute([':id_geo' => $idGeo]);
            $feature = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feature) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Trecho de rede de esgoto não encontrado.'
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => $feature,
                'tipo_camada' => 'vw_rede_esgoto'
            ]);
            exit;
        } elseif ($lat !== null && $lng !== null) {
            // Busca espacial pelo ponto mais próximo (tolerância 15 metros)
            $stmt = $db->prepare("
                SELECT 
                    a.id AS id_geo,
                    ROUND(ST_Length(ST_Transform(ST_Force2D(a.geom), 31983))::numeric, 2) AS extensao_m,
                    ROUND(ST_Distance(
                        ST_Transform(ST_Force2D(a.geom), 4326)::geography, 
                        ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography
                    )::numeric, 1) AS distancia_clique_m,
                    b.id AS id_alfanumerico,
                    b.rede,
                    b.tipo::text AS tipo,
                    b.diametro,
                    b.material,
                    b.profundidade,
                    b.observacao,
                    TO_CHAR(b.data_implantacao, 'YYYY-MM-DD') AS data_implantacao,
                    b.responsavel_implantacao
                FROM rede_esgoto_geo a
                LEFT JOIN rede_esgoto b ON a.id = b.id_geo
                WHERE ST_DWithin(
                    ST_Transform(ST_Force2D(a.geom), 4326)::geography, 
                    ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography, 
                    15
                )
                ORDER BY distancia_clique_m ASC
                LIMIT 1
            ");
            $stmt->execute([
                ':lat' => $lat,
                ':lng' => $lng
            ]);
            $feature = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feature) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Nenhum trecho de rede de esgoto encontrado próximo ao clique.'
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => $feature,
                'tipo_camada' => 'vw_rede_esgoto'
            ]);
            exit;
        } else {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Informe o id_geo ou as coordenadas (lat, lng).'
            ]);
            exit;
        }
    }

    // =========================================================================
    // 2. MÉTODO POST - Salvar / Inserir / Atualizar Atributos (Upsert)
    // =========================================================================
    if ($method === 'POST') {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        if (!$data && !empty($_POST)) {
            $data = $_POST;
        }

        $idGeo = isset($data['id_geo']) ? (int)$data['id_geo'] : null;

        if (!$idGeo || $idGeo <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'O identificador geométrico do trecho (id_geo) é obrigatório.'
            ]);
            exit;
        }

        // Verifica se a geometria existe em rede_esgoto_geo
        $stmtCheck = $db->prepare("SELECT id FROM rede_esgoto_geo WHERE id = :id_geo");
        $stmtCheck->execute([':id_geo' => $idGeo]);
        if (!$stmtCheck->fetch()) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'A geometria informada (id_geo: ' . $idGeo . ') não existe no banco.'
            ]);
            exit;
        }

        // Tratamento e sanitização dos campos alfanuméricos
        $rede = isset($data['rede']) && trim($data['rede']) !== '' ? trim($data['rede']) : null;
        $tipo = isset($data['tipo']) && trim($data['tipo']) !== '' ? trim($data['tipo']) : null;
        $material = isset($data['material']) && trim($data['material']) !== '' ? trim($data['material']) : null;
        $diametro = isset($data['diametro']) && is_numeric($data['diametro']) ? (float)$data['diametro'] : null;
        $profundidade = isset($data['profundidade']) && is_numeric($data['profundidade']) ? (float)$data['profundidade'] : null;
        $dataImplantacao = isset($data['data_implantacao']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['data_implantacao']) ? $data['data_implantacao'] : null;
        $responsavel = isset($data['responsavel_implantacao']) && trim($data['responsavel_implantacao']) !== '' ? trim($data['responsavel_implantacao']) : null;
        $observacao = isset($data['observacao']) && trim($data['observacao']) !== '' ? trim($data['observacao']) : null;

        // Valida se o tipo pertence aos valores válidos de tipo_rede_esgoto
        $validTipos = ['Coletor', 'Coletor principal', 'Interceptor', 'Emissário'];
        if ($tipo !== null && !in_array($tipo, $validTipos)) {
            $tipo = null;
        }

        // Executa o UPSERT com ON CONFLICT (id_geo)
        $sqlUpsert = "
            INSERT INTO rede_esgoto (
                id_geo,
                rede,
                tipo,
                diametro,
                material,
                profundidade,
                data_implantacao,
                responsavel_implantacao,
                observacao
            ) VALUES (
                :id_geo,
                :rede,
                :tipo::tipo_rede_esgoto,
                :diametro,
                :material,
                :profundidade,
                :data_implantacao,
                :responsavel,
                :observacao
            )
            ON CONFLICT (id_geo) 
            DO UPDATE SET
                rede = EXCLUDED.rede,
                tipo = EXCLUDED.tipo,
                diametro = EXCLUDED.diametro,
                material = EXCLUDED.material,
                profundidade = EXCLUDED.profundidade,
                data_implantacao = EXCLUDED.data_implantacao,
                responsavel_implantacao = EXCLUDED.responsavel_implantacao,
                observacao = EXCLUDED.observacao
            RETURNING id;
        ";

        $stmtSave = $db->prepare($sqlUpsert);
        $stmtSave->execute([
            ':id_geo' => $idGeo,
            ':rede' => $rede,
            ':tipo' => $tipo,
            ':diametro' => $diametro,
            ':material' => $material,
            ':profundidade' => $profundidade,
            ':data_implantacao' => $dataImplantacao,
            ':responsavel' => $responsavel,
            ':observacao' => $observacao
        ]);

        $savedRow = $stmtSave->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Informações da rede de esgoto salvas com sucesso!',
            'id_alfanumerico' => $savedRow['id'] ?? null,
            'id_geo' => $idGeo
        ]);
        exit;
    }

    // =========================================================================
    // 3. MÉTODO DELETE - Remover Dados Alfanuméricos do Trecho
    // =========================================================================
    if ($method === 'DELETE' || ($method === 'POST' && isset($_GET['action']) && $_GET['action'] === 'delete')) {
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        if (!$data) {
            $data = $_REQUEST;
        }

        $idGeo = isset($data['id_geo']) ? (int)$data['id_geo'] : (isset($_GET['id_geo']) ? (int)$_GET['id_geo'] : null);

        if (!$idGeo) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'ID do trecho (id_geo) é obrigatório para exclusão.'
            ]);
            exit;
        }

        $stmtDel = $db->prepare("DELETE FROM rede_esgoto WHERE id_geo = :id_geo");
        $stmtDel->execute([':id_geo' => $idGeo]);

        echo json_encode([
            'success' => true,
            'message' => 'Atributos alfanuméricos de esgoto removidos com sucesso. A geometria foi preservada.',
            'id_geo' => $idGeo
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método HTTP não permitido.'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Erro interno no servidor: ' . $e->getMessage()
    ]);
}
