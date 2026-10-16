<?php
/**
 * Endpoint para retornar feições geométricas em formato GeoJSON.
 * Suporta filtragem espacial via BBOX (Bounding Box) do Leaflet.
 */

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
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

require_once __DIR__ . '/../config/db.php';

// Whitelist de tabelas e views permitidas no sistema
$whitelist = [
    'geo_edificacoes_dm',
    'geo_lotes_dm',
    'geo_comunidades_dm',
    'geo_quadras_dm',
    'geo_logradouros_dm',
    'geo_zoneamento_dm'
];

$table = $_GET['tipo'] ?? '';

if (!in_array($table, $whitelist)) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'Camada inválida ou não autorizada.'
    ]);
    exit;
}

$db = getDBConnection();

try {
    // Busca as colunas disponíveis para a view/tabela de forma segura
    $colStmt = $db->prepare("
        SELECT column_name 
        FROM information_schema.columns 
        WHERE table_name = :table_name
    ");
    $colStmt->execute([':table_name' => $table]);
    $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($columns)) {
        http_response_code(404);
        echo json_encode([
            'error' => true,
            'message' => 'Camada vazia ou não encontrada no banco.'
        ]);
        exit;
    }

    // Identifica a coluna geométrica e separa as propriedades
    $geomColumn = null;
    $propertyColumns = [];

    foreach ($columns as $column) {
        if (in_array(strtolower($column), ['geom', 'geometry', 'geometria'])) {
            $geomColumn = $column;
        } else {
            $propertyColumns[] = $column;
        }
    }

    if (!$geomColumn) {
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'message' => 'Coluna geométrica não encontrada para esta camada.'
        ]);
        exit;
    }

    // Monta a seleção de propriedades no formato JSON
    $propPairs = [];
    foreach ($propertyColumns as $col) {
        $propPairs[] = sprintf("'%s', \"%s\"", $col, $col);
    }
    $propertiesSql = !empty($propPairs) ? "json_build_object(" . implode(', ', $propPairs) . ")" : "'{}'::json";

    // Trata o parâmetro BBOX
    $bbox = $_GET['bbox'] ?? '';
    $whereClause = '';
    $params = [];

    if (!empty($bbox)) {
        $coords = explode(',', $bbox);
        if (count($coords) === 4) {
            $minLng = (float) $coords[0];
            $minLat = (float) $coords[1];
            $maxLng = (float) $coords[2];
            $maxLat = (float) $coords[3];

            // Obtém o SRID da tabela para transformar o BBOX de forma indexável
            $sridStmt = $db->prepare("SELECT Find_SRID('geo', :table_name, :geom_col)");
            $sridStmt->execute([':table_name' => $table, ':geom_col' => $geomColumn]);
            $srid = (int)$sridStmt->fetchColumn();
            if (!$srid || $srid <= 0) {
                $srid = 31984; // Padrão SIRGAS 2000 UTM 24S
            }

            // Filtro espacial utilizando ST_MakeEnvelope e o índice espacial (&&)
            $whereClause = "WHERE \"$geomColumn\" && ST_Transform(ST_MakeEnvelope(:min_lng, :min_lat, :max_lng, :max_lat, 4326), $srid)";

            $params = [
                ':min_lng' => $minLng,
                ':min_lat' => $minLat,
                ':max_lng' => $maxLng,
                ':max_lat' => $maxLat
            ];
        }
    }

    // Tratamento especial para a camada geo_lotes_dm (Join com GP_IMOVEL para filtros cadastrais)
    if ($table === 'geo_lotes_dm') {
        $sridStmt = $db->prepare("SELECT Find_SRID('geo', :table_name, :geom_col)");
        $sridStmt->execute([':table_name' => $table, ':geom_col' => 'geom']);
        $srid = (int)$sridStmt->fetchColumn();
        if (!$srid || $srid <= 0) {
            $srid = 31984; // Padrão SIRGAS 2000 UTM 24S
        }

        $whereClause = '';
        $params = [];
        $bbox = $_GET['bbox'] ?? '';

        if (!empty($bbox)) {
            $coords = explode(',', $bbox);
            if (count($coords) === 4) {
                $minLng = (float) $coords[0];
                $minLat = (float) $coords[1];
                $maxLng = (float) $coords[2];
                $maxLat = (float) $coords[3];

                $whereClause = "WHERE l.geom && ST_Transform(ST_MakeEnvelope(:min_lng, :min_lat, :max_lng, :max_lat, 4326), $srid)";
                $params = [
                    ':min_lng' => $minLng,
                    ':min_lat' => $minLat,
                    ':max_lng' => $maxLng,
                    ':max_lat' => $maxLat
                ];
            }
        }

        $query = "
            SELECT json_build_object(
                'type', 'FeatureCollection',
                'features', COALESCE(json_agg(features), '[]'::json)
            ) as geojson
            FROM (
                SELECT json_build_object(
                    'type', 'Feature',
                    'geometry', ST_AsGeoJSON(ST_Transform(ST_Force2D(l.geom), 4326))::json,
                    'properties', json_build_object(
                        'id', l.id,
                        'inscimob', l.inscimob,
                        'inscimob2', l.inscimob2,
                        'obs', l.obs,
                        'has_cadastro', (g.\"INSC_MUNIC\" IS NOT NULL),
                        'status_cadastral', CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Com Cadastro' ELSE 'Não Cadastrado (Em branco)' END,
                        'status', COALESCE(g.\"STATUS\", 'Não Informado'),
                        'utilizacao', CASE 
                            WHEN g.\"ULTILIZACAO_DO_IMOVEL9027\" IS NOT NULL AND TRIM(g.\"ULTILIZACAO_DO_IMOVEL9027\") <> '' 
                            THEN TRIM(g.\"ULTILIZACAO_DO_IMOVEL9027\") 
                            ELSE 'Não Informado' 
                        END,
                        'ocupacao', CASE 
                            WHEN g.\"OCUPACAO9025\" IS NOT NULL AND TRIM(g.\"OCUPACAO9025\") <> '' 
                            THEN TRIM(g.\"OCUPACAO9025\") 
                            ELSE 'Não Informado' 
                        END,
                        'proprietario', g.\"NOMEDOCONTRIBUINTE\",
                        'bairro', g.\"NOMEBAIRRO\",
                        'logradouro', g.\"NOMELOGRADOURO\",
                        'area_terreno', g.\"AREA_DO_TERRENO13065\"
                    )
                ) as features
                FROM geo.geo_lotes_dm l
                LEFT JOIN public.\"GP_IMOVEL\" g ON l.inscimob2 = g.\"INSC_MUNIC\"
                $whereClause
            ) row
        ";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        echo $result['geojson'];
        exit;
    }

    // Tratamento especial para a camada geo_edificacoes_dm (Join com GP_IMOVEL para filtros temáticos e cadastrais)
    if ($table === 'geo_edificacoes_dm') {
        $sridStmt = $db->prepare("SELECT Find_SRID('geo', :table_name, :geom_col)");
        $sridStmt->execute([':table_name' => $table, ':geom_col' => 'geom']);
        $srid = (int)$sridStmt->fetchColumn();
        if (!$srid || $srid <= 0) {
            $srid = 31984; // Padrão SIRGAS 2000 UTM 24S
        }

        $whereClause = '';
        $params = [];
        $bbox = $_GET['bbox'] ?? '';

        if (!empty($bbox)) {
            $coords = explode(',', $bbox);
            if (count($coords) === 4) {
                $minLng = (float) $coords[0];
                $minLat = (float) $coords[1];
                $maxLng = (float) $coords[2];
                $maxLat = (float) $coords[3];

                $whereClause = "WHERE e.geom && ST_Transform(ST_MakeEnvelope(:min_lng, :min_lat, :max_lng, :max_lat, 4326), $srid)";
                $params = [
                    ':min_lng' => $minLng,
                    ':min_lat' => $minLat,
                    ':max_lng' => $maxLng,
                    ':max_lat' => $maxLat
                ];
            }
        }

        $query = "
            SELECT json_build_object(
                'type', 'FeatureCollection',
                'features', COALESCE(json_agg(features), '[]'::json)
            ) as geojson
            FROM (
                SELECT json_build_object(
                    'type', 'Feature',
                    'geometry', ST_AsGeoJSON(ST_Transform(ST_Force2D(e.geom), 4326))::json,
                    'properties', json_build_object(
                        'id', e.id,
                        'inscimob', e.inscimob,
                        'obs', e.obs,
                        'beiral', e.beiral,
                        'fator', e.fator,
                        'caract', e.caract,
                        'has_cadastro', (g.\"INSC_MUNIC\" IS NOT NULL),
                        'status_cadastral', CASE WHEN g.\"INSC_MUNIC\" IS NOT NULL THEN 'Com Cadastro' ELSE 'Não Cadastrado (Em branco)' END,
                        'tipo_construcao', CASE 
                            WHEN g.\"TIPO_DE_CONSTRUCAO10042\" IS NOT NULL AND TRIM(g.\"TIPO_DE_CONSTRUCAO10042\") <> '' 
                            THEN TRIM(g.\"TIPO_DE_CONSTRUCAO10042\") 
                            ELSE 'Não Informado' 
                        END,
                        'TIPO_DE_CONSTRUCAO10042', g.\"TIPO_DE_CONSTRUCAO10042\",
                        'estrutura', CASE 
                            WHEN g.\"ESTRUTURA10043\" IS NOT NULL AND TRIM(g.\"ESTRUTURA10043\") <> '' 
                            THEN TRIM(g.\"ESTRUTURA10043\") 
                            ELSE 'Não Informado' 
                        END,
                        'ESTRUTURA10043', g.\"ESTRUTURA10043\",
                        'estado_conservacao', CASE 
                            WHEN g.\"ESTADO_DE_CONSERVACAO10044\" IS NOT NULL AND TRIM(g.\"ESTADO_DE_CONSERVACAO10044\") <> '' 
                            THEN TRIM(g.\"ESTADO_DE_CONSERVACAO10044\") 
                            ELSE 'Não Informado' 
                        END,
                        'ESTADO_DE_CONSERVACAO10044', g.\"ESTADO_DE_CONSERVACAO10044\",
                        'situacao_imovel', CASE 
                            WHEN g.\"SITUACAO_DO_IMOVEL10048\" IS NOT NULL AND TRIM(g.\"SITUACAO_DO_IMOVEL10048\") <> '' 
                            THEN TRIM(g.\"SITUACAO_DO_IMOVEL10048\") 
                            ELSE 'Não Informado' 
                        END,
                        'SITUACAO_DO_IMOVEL10048', g.\"SITUACAO_DO_IMOVEL10048\",
                        'esgoto', CASE 
                            WHEN g.\"ESGOTO12001\" IS NOT NULL AND TRIM(g.\"ESGOTO12001\") <> '' 
                            THEN TRIM(g.\"ESGOTO12001\") 
                            ELSE 'Não Informado' 
                        END,
                        'ESGOTO12001', g.\"ESGOTO12001\",
                        'agua', CASE 
                            WHEN g.\"AGUA12002\" IS NOT NULL AND TRIM(g.\"AGUA12002\") <> '' 
                            THEN TRIM(g.\"AGUA12002\") 
                            ELSE 'Não Informado' 
                        END,
                        'AGUA12002', g.\"AGUA12002\",
                        'lixo', CASE 
                            WHEN g.\"LIXO12006\" IS NOT NULL AND TRIM(g.\"LIXO12006\") <> '' 
                            THEN TRIM(g.\"LIXO12006\") 
                            ELSE 'Não Informado' 
                        END,
                        'LIXO12006', g.\"LIXO12006\",
                        'proprietario', g.\"NOMEDOCONTRIBUINTE\",
                        'bairro', g.\"NOMEBAIRRO\",
                        'logradouro', g.\"NOMELOGRADOURO\"
                    )
                ) as features
                FROM geo.geo_edificacoes_dm e
                LEFT JOIN public.\"GP_IMOVEL\" g ON e.inscimob = g.\"INSC_MUNIC\"
                $whereClause
            ) row
        ";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        echo $result['geojson'];
        exit;
    }

    // Tratamento especial para a camada geo_zoneamento_dm (PDM: Join com pdm.zoneamento_pdm e agregação de usos_e_indices)
    if ($table === 'geo_zoneamento_dm') {
        $sridStmt = $db->prepare("SELECT Find_SRID('geo', :table_name, :geom_col)");
        $sridStmt->execute([':table_name' => $table, ':geom_col' => 'geom']);
        $srid = (int)$sridStmt->fetchColumn();
        if (!$srid || $srid <= 0) {
            $srid = 31984; // Padrão SIRGAS 2000 UTM 24S
        }

        $whereClause = '';
        $params = [];
        $bbox = $_GET['bbox'] ?? '';

        if (!empty($bbox)) {
            $coords = explode(',', $bbox);
            if (count($coords) === 4) {
                $minLng = (float) $coords[0];
                $minLat = (float) $coords[1];
                $maxLng = (float) $coords[2];
                $maxLat = (float) $coords[3];

                $whereClause = "WHERE D.geom && ST_Transform(ST_MakeEnvelope(:min_lng, :min_lat, :max_lng, :max_lat, 4326), $srid)";
                $params = [
                    ':min_lng' => $minLng,
                    ':min_lat' => $minLat,
                    ':max_lng' => $maxLng,
                    ':max_lat' => $maxLat
                ];
            }
        }

        $query = "
            SELECT json_build_object(
                'type', 'FeatureCollection',
                'features', COALESCE(json_agg(features), '[]'::json)
            ) as geojson
            FROM (
                SELECT json_build_object(
                    'type', 'Feature',
                    'geometry', ST_AsGeoJSON(ST_Transform(ST_Force2D(D.geom), 4326))::json,
                    'properties', json_build_object(
                        'id_zona', D.id_zona,
                        'nome_zona', COALESCE(A.nome_zona, 'Não Classificado'),
                        'sigla', COALESCE(TRIM(A.sigla), 'Não Informado'),
                        'sigla_detalhada', COALESCE(A.sigla_detalhada, 'Não Informado'),
                        'has_cadastro', (A.id_zona IS NOT NULL),
                        'usos_e_indices', (
                            SELECT json_agg(item)
                            FROM (
                                SELECT 
                                    B.nome_uso,
                                    ZU.permissao,
                                    R.id_regra,
                                    R.descricao_cenario,
                                    R.ca_max,
                                    R.to_max,
                                    R.tp_min,
                                    R.gabarito_max as gabarito,
                                    R.altura_max as altura_edificacao,
                                    R.parc_testada_min,
                                    R.parc_area_min,
                                    ZU.observacoes as observacoes_uso,
                                    (
                                        SELECT json_agg(ad)
                                        FROM (
                                            SELECT posicao, valor_minimo, min_pavimentos, max_pavimentos, pelo_menos_um_lado
                                            FROM pdm.afastamentos_detalhe
                                            WHERE id_regra = R.id_regra
                                            ORDER BY posicao, min_pavimentos
                                        ) ad
                                    ) as afastamentos_detalhe
                                FROM pdm.\"zoneamento_usos\" ZU
                                INNER JOIN pdm.\"usos\" B ON B.id_uso = ZU.id_uso
                                INNER JOIN pdm.\"regras_urbanisticas\" R ON R.id_zoneamento_uso = ZU.id_zoneamento_uso
                                WHERE ZU.id_zona = D.id_zona
                            ) item
                        )
                    )
                ) as features
                FROM geo.\"geo_zoneamento_dm\" AS D
                LEFT JOIN pdm.\"zoneamento_pdm\" AS A ON A.id_zona = D.id_zona
                $whereClause
            ) row
        ";

        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch();
        echo $result['geojson'];
        exit;
    }

    // Query principal construindo o GeoJSON diretamente no PostgreSQL para as demais camadas
    $query = "
        SELECT json_build_object(
            'type', 'FeatureCollection',
            'features', COALESCE(json_agg(features), '[]'::json)
        ) as geojson
        FROM (
            SELECT json_build_object(
                'type', 'Feature',
                'geometry', ST_AsGeoJSON(ST_Transform(ST_Force2D(\"$geomColumn\"), 4326))::json,
                'properties', $propertiesSql
            ) as features
            FROM \"$table\"
            $whereClause
        ) row
    ";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $result = $stmt->fetch();

    echo $result['geojson'];

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao processar requisição: ' . $e->getMessage()
    ]);
}
