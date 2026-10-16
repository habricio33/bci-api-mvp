<?php
/**
 * Endpoint CRUD para Edição e Cadastro de Atributos Alfanuméricos do Ramal de Água.
 * 
 * Métodos suportados:
 *  - GET: Busca atributos do ramal e edificação pelo id_geo ou pelas coordenadas lat/lng do clique.
 *  - POST: Insere ou atualiza os atributos vinculados à edificação/ramal (Upsert).
 *  - DELETE: Remove o registro alfanumérico da tabela ramal_agua, mantendo a geometria da edificação intacta.
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
            // Busca direta por id_geo da edificação
            $stmt = $db->prepare("
                SELECT 
                    g.id AS id_geo,
                    ROUND(ST_Area(ST_Transform(ST_Force2D(g.geom), 31983))::numeric, 2) AS area_m2,
                    e.id AS edificacao_id,
                    e.lote_id,
                    e.numero_postal,
                    e.tipo::text AS tipo_edificacao,
                    e.observacao AS obs_edificacao,
                    r.id AS ramal_id,
                    r.rede_agua_id,
                    r.unidade_consumidora_id,
                    r.a_direita,
                    r.a_esquerda,
                    r.extensao,
                    r.diametro,
                    r.material,
                    r.profundidade,
                    r.tipo_pavimento,
                    r.hidrometro,
                    r.observacao AS observacao,
                    TO_CHAR(r.data_implantacao, 'YYYY-MM-DD') AS data_implantacao
                FROM edificacao_geo g
                LEFT JOIN edificacao e ON g.id = e.id_geo
                LEFT JOIN ramal_agua r ON e.id_geo = r.id_edificacao
                WHERE g.id = :id_geo
                LIMIT 1
            ");
            $stmt->execute([':id_geo' => $idGeo]);
            $feature = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$feature) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Imóvel / Edificação não encontrado.'
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => $feature,
                'tipo_camada' => 'vw_edificacao_ramal_agua'
            ]);
            exit;
        } elseif ($lat !== null && $lng !== null) {
            // Busca espacial: primeiro verifica se o clique está dentro do polígono da edificação
            // ou pega a edificação mais próxima em até 15 metros
            $stmt = $db->prepare("
                SELECT 
                    g.id AS id_geo,
                    ROUND(ST_Area(ST_Transform(ST_Force2D(g.geom), 31983))::numeric, 2) AS area_m2,
                    ROUND(ST_Distance(
                        ST_Transform(ST_Force2D(g.geom), 4326)::geography, 
                        ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography
                    )::numeric, 1) AS distancia_clique_m,
                    e.id AS edificacao_id,
                    e.lote_id,
                    e.numero_postal,
                    e.tipo::text AS tipo_edificacao,
                    e.observacao AS obs_edificacao,
                    r.id AS ramal_id,
                    r.rede_agua_id,
                    r.unidade_consumidora_id,
                    r.a_direita,
                    r.a_esquerda,
                    r.extensao,
                    r.diametro,
                    r.material,
                    r.profundidade,
                    r.tipo_pavimento,
                    r.hidrometro,
                    r.observacao AS observacao,
                    TO_CHAR(r.data_implantacao, 'YYYY-MM-DD') AS data_implantacao
                FROM edificacao_geo g
                LEFT JOIN edificacao e ON g.id = e.id_geo
                LEFT JOIN ramal_agua r ON e.id_geo = r.id_edificacao
                WHERE ST_DWithin(
                    ST_Transform(ST_Force2D(g.geom), 4326)::geography, 
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
                    'message' => 'Nenhum imóvel / ramal de água encontrado próximo ao clique.'
                ]);
                exit;
            }

            echo json_encode([
                'success' => true,
                'data' => $feature,
                'tipo_camada' => 'vw_edificacao_ramal_agua'
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
    // 2. MÉTODO POST - Salvar / Inserir / Atualizar Atributos do Ramal (Upsert)
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
                'message' => 'O identificador geométrico do imóvel (id_geo) é obrigatório.'
            ]);
            exit;
        }

        // 1. Verifica se a geometria existe em edificacao_geo
        $stmtCheck = $db->prepare("SELECT id FROM edificacao_geo WHERE id = :id_geo");
        $stmtCheck->execute([':id_geo' => $idGeo]);
        if (!$stmtCheck->fetch()) {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'A geometria informada (id_geo: ' . $idGeo . ') não existe no banco.'
            ]);
            exit;
        }

        // 2. Sanitização dos campos de edificação
        $numeroPostal = isset($data['numero_postal']) && trim($data['numero_postal']) !== '' ? trim($data['numero_postal']) : null;
        $tipoEdificacao = isset($data['tipo_edificacao']) && trim($data['tipo_edificacao']) !== '' ? trim($data['tipo_edificacao']) : null;
        
        $validTiposEdif = ['Residencial', 'Comercial', 'Industrial', 'Misto'];
        if ($tipoEdificacao !== null && !in_array($tipoEdificacao, $validTiposEdif)) {
            $tipoEdificacao = null;
        }

        // 3. Upsert na tabela edificacao (para sincronizar número postal e tipo de edificação)
        $stmtUpsertEdif = $db->prepare("
            INSERT INTO edificacao (id_geo, numero_postal, tipo)
            VALUES (:id_geo, :numero_postal, :tipo::tipo_edificacao)
            ON CONFLICT (id_geo) DO UPDATE SET
                numero_postal = COALESCE(EXCLUDED.numero_postal, edificacao.numero_postal),
                tipo = COALESCE(EXCLUDED.tipo, edificacao.tipo)
            RETURNING id;
        ");
        $stmtUpsertEdif->execute([
            ':id_geo' => $idGeo,
            ':numero_postal' => $numeroPostal,
            ':tipo' => $tipoEdificacao
        ]);
        $edifRow = $stmtUpsertEdif->fetch(PDO::FETCH_ASSOC);
        $edificacaoId = $edifRow['id'] ?? null;

        // 4. Sanitização dos campos do ramal de água
        $hidrometro = isset($data['hidrometro']) && trim($data['hidrometro']) !== '' ? trim($data['hidrometro']) : null;
        $material = isset($data['material']) && trim($data['material']) !== '' ? trim($data['material']) : null;
        $diametro = isset($data['diametro']) && is_numeric($data['diametro']) ? (float)$data['diametro'] : null;
        $profundidade = isset($data['profundidade']) && is_numeric($data['profundidade']) ? (float)$data['profundidade'] : null;
        $tipoPavimento = isset($data['tipo_pavimento']) && trim($data['tipo_pavimento']) !== '' ? trim($data['tipo_pavimento']) : null;
        $aDireita = isset($data['a_direita']) && is_numeric($data['a_direita']) ? (float)$data['a_direita'] : null;
        $aEsquerda = isset($data['a_esquerda']) && is_numeric($data['a_esquerda']) ? (float)$data['a_esquerda'] : null;
        $extensao = isset($data['extensao']) && is_numeric($data['extensao']) ? (float)$data['extensao'] : null;
        $dataImplantacao = isset($data['data_implantacao']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data['data_implantacao']) ? $data['data_implantacao'] : null;
        $observacao = isset($data['observacao']) && trim($data['observacao']) !== '' ? trim($data['observacao']) : null;

        // 5. Upsert na tabela ramal_agua com ON CONFLICT (id_edificacao)
        $sqlUpsertRamal = "
            INSERT INTO ramal_agua (
                id_geo,
                id_edificacao,
                a_direita,
                a_esquerda,
                extensao,
                diametro,
                material,
                profundidade,
                tipo_pavimento,
                hidrometro,
                observacao,
                data_implantacao
            ) VALUES (
                :id_geo,
                :id_edificacao,
                :a_direita,
                :a_esquerda,
                :extensao,
                :diametro,
                :material,
                :profundidade,
                :tipo_pavimento,
                :hidrometro,
                :observacao,
                :data_implantacao
            )
            ON CONFLICT (id_edificacao) 
            DO UPDATE SET
                a_direita = EXCLUDED.a_direita,
                a_esquerda = EXCLUDED.a_esquerda,
                extensao = EXCLUDED.extensao,
                diametro = EXCLUDED.diametro,
                material = EXCLUDED.material,
                profundidade = EXCLUDED.profundidade,
                tipo_pavimento = EXCLUDED.tipo_pavimento,
                hidrometro = EXCLUDED.hidrometro,
                observacao = EXCLUDED.observacao,
                data_implantacao = EXCLUDED.data_implantacao
            RETURNING id;
        ";

        $stmtSaveRamal = $db->prepare($sqlUpsertRamal);
        $stmtSaveRamal->execute([
            ':id_geo' => $idGeo,
            ':id_edificacao' => $idGeo,
            ':a_direita' => $aDireita,
            ':a_esquerda' => $aEsquerda,
            ':extensao' => $extensao,
            ':diametro' => $diametro,
            ':material' => $material,
            ':profundidade' => $profundidade,
            ':tipo_pavimento' => $tipoPavimento,
            ':hidrometro' => $hidrometro,
            ':observacao' => $observacao,
            ':data_implantacao' => $dataImplantacao
        ]);

        $savedRamal = $stmtSaveRamal->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'success' => true,
            'message' => 'Informações do ramal de água salvas com sucesso!',
            'ramal_id' => $savedRamal['id'] ?? null,
            'edificacao_id' => $edificacaoId,
            'id_geo' => $idGeo
        ]);
        exit;
    }

    // =========================================================================
    // 3. MÉTODO DELETE - Remover Dados Alfanuméricos do Ramal de Água
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
                'message' => 'ID do imóvel (id_geo) é obrigatório para exclusão.'
            ]);
            exit;
        }

        $stmtDel = $db->prepare("DELETE FROM ramal_agua WHERE id_edificacao = :id_geo OR id_geo = :id_geo");
        $stmtDel->execute([':id_geo' => $idGeo]);

        echo json_encode([
            'success' => true,
            'message' => 'Atributos alfanuméricos do ramal de água removidos com sucesso. A geometria foi preservada.',
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
