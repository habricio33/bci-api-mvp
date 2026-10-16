<?php
/**
 * Endpoint principal de busca inteligente.
 * Realiza consultas filtradas em redes e ligações/edificações.
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

$rua = $_GET['rua'] ?? '';
$usuario = $_GET['usuario'] ?? '';
$categoria = $_GET['categoria'] ?? '';
$material = $_GET['material'] ?? '';
$diametro = $_GET['diametro'] ?? '';

// Filtros de data
$dataRedeDe = $_GET['data_rede_de'] ?? '';
$dataRedeAte = $_GET['data_rede_ate'] ?? '';
$dataCcDe = $_GET['data_cc_de'] ?? '';
$dataCcAte = $_GET['data_cc_ate'] ?? '';

$db = getDBConnection();
$results = [];

// Lista de camadas de redes e ramais a serem consultadas
$camadas = [
    'vw_rede_agua' => 'Rede de Água',
    'vw_rede_esgoto' => 'Rede de Esgoto',
    'vw_edificacao_ramal_agua' => 'Ramal de Água',
    'vw_edificacao_ramal_esgoto' => 'Ramal de Esgoto'
];

try {
    foreach ($camadas as $tabela => $rotulo) {
        $whereClauses = [];
        $params = [];

        // 1. Filtro por Rua (espacial via logradouro_osm)
        if (!empty($rua)) {
            $whereClauses[] = "
                ST_DWithin(
                    ST_Transform(t.geom, 4326)::geography,
                    (
                        SELECT ST_Union(ST_Transform(l.geom, 4326))::geography 
                        FROM logradouro_osm l 
                        WHERE l.name = :rua
                    ),
                    25
                )
            ";
            $params[':rua'] = $rua;
        }

        // 2. Filtro por Material
        if (!empty($material)) {
            $whereClauses[] = "t.material = :material";
            $params[':material'] = $material;
        }

        // 3. Filtro por Diâmetro
        if (!empty($diametro)) {
            $whereClauses[] = "t.diametro = :diametro";
            $params[':diametro'] = $diametro;
        }

        // Se for camada de rede (vw_rede_agua ou vw_rede_esgoto)
        if ($tabela === 'vw_rede_agua' || $tabela === 'vw_rede_esgoto') {
            // Ignorar filtro de usuário/titular e categoria (não existem em redes)
            if (!empty($usuario) || !empty($categoria)) {
                continue; 
            }

            // Filtro de data de implantação de redes
            if (!empty($dataRedeDe)) {
                $whereClauses[] = "t.data_implantacao >= :data_rede_de";
                $params[':data_rede_de'] = $dataRedeDe;
            }
            if (!empty($dataRedeAte)) {
                $whereClauses[] = "t.data_implantacao <= :data_rede_ate";
                $params[':data_rede_ate'] = $dataRedeAte;
            }
        }

        // Se for camada de ligação/ramal (vw_edificacao_ramal_agua ou vw_edificacao_ramal_esgoto)
        if ($tabela === 'vw_edificacao_ramal_agua' || $tabela === 'vw_edificacao_ramal_esgoto') {
            // Filtro de Categoria (tipo da edificação)
            if (!empty($categoria)) {
                $whereClauses[] = "t.tipo = :categoria";
                $params[':categoria'] = $categoria;
            }

            // Filtro de data de lançamento no CC (data_implantacao dos ramais)
            if (!empty($dataCcDe)) {
                $whereClauses[] = "t.data_implantacao >= :data_cc_de";
                $params[':data_cc_de'] = $dataCcDe;
            }
            if (!empty($dataCcAte)) {
                $whereClauses[] = "t.data_implantacao <= :data_cc_ate";
                $params[':data_cc_ate'] = $dataCcAte;
            }

            // Filtro por Usuário/Titular (tabela unidade_consumidora ou campo observacao/obs_ramal como fallback)
            if (!empty($usuario)) {
                $whereClauses[] = "
                    (
                        EXISTS (
                            SELECT 1 FROM unidade_consumidora uc 
                            WHERE uc.id = t.unidade_consumidora_id 
                              AND (uc.identificacao ILIKE :usuario OR uc.matricula ILIKE :usuario)
                        )
                        OR t.observacao ILIKE :usuario
                        OR t.obs_ramal ILIKE :usuario
                    )
                ";
                $params[':usuario'] = '%' . $usuario . '%';
            }
        }

        // Se houver filtros, executa a busca
        if (!empty($whereClauses)) {
            $whereSql = "WHERE " . implode(" AND ", $whereClauses);

            // Identifica o ID correto
            $idCol = ($tabela === 'vw_rede_agua') ? 'id_rede_agua' : (($tabela === 'vw_rede_esgoto') ? 'id_rede_esgoto' : 'id_geo');

            $query = "
                SELECT 
                    t.\"$idCol\" AS id,
                    '$tabela' AS camada,
                    ST_X(ST_Centroid(ST_Transform(t.geom, 4326))) AS lng,
                    ST_Y(ST_Centroid(ST_Transform(t.geom, 4326))) AS lat,
                    ST_AsGeoJSON(ST_Transform(t.geom, 4326)) AS geojson,
                    t.*
                FROM \"$tabela\" t
                $whereSql
                LIMIT 50
            ";

            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();

            foreach ($rows as $row) {
                // Remove colunas extras e geométricas do array para manter a resposta leve
                $properties = $row;
                unset($properties['geom'], $properties['geojson'], $properties['lat'], $properties['lng'], $properties['id'], $properties['camada']);

                // Gera um título descritivo para o resultado
                $titulo = $rotulo;
                if (isset($row['material']) && !empty($row['material'])) {
                    $titulo .= " (" . $row['material'];
                    if (isset($row['diametro']) && !empty($row['diametro'])) {
                        $titulo .= " " . $row['diametro'] . "mm";
                    }
                    $titulo .= ")";
                }
                if (isset($row['numero_postal']) && !empty($row['numero_postal'])) {
                    $titulo .= " - Nº " . $row['numero_postal'];
                }

                $results[] = [
                    'id' => $row['id'],
                    'camada' => $row['camada'],
                    'titulo' => $titulo,
                    'lat' => (float)$row['lat'],
                    'lng' => (float)$row['lng'],
                    'geojson' => json_decode($row['geojson'], true),
                    'properties' => $properties
                ];
            }
        }
    }

    echo json_encode($results, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $e) {
    $msg = date('[Y-m-d H:i:s] ') . "Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
    file_put_contents(__DIR__ . '/../api_error.log', $msg, FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro na busca: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
