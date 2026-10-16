<?php
/**
 * Endpoint para identificação espacial exclusiva de Lotes e Edificações.
 * Consulta o cadastro imobiliário (GP_IMOVEL) para listar unidades e exibir detalhes.
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

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/TributarioApiClient.php';

$db = getDBConnection();

$action = $_GET['action'] ?? '';
$inscMunic = $_GET['insc_munic'] ?? '';

// ROTA 1: Obter detalhes simplificados ou dados completos do BCI (Oficial Tributário ou GP_IMOVEL)
if ($action === 'detalhes' || $action === 'bci' || (!empty($inscMunic) && empty($_GET['lat']))) {
    if (empty($inscMunic)) {
        http_response_code(400);
        echo json_encode([
            'error' => true,
            'message' => 'Parâmetro insc_munic é obrigatório para consultar detalhes ou BCI.'
        ]);
        exit;
    }

    try {
        // 1. Tenta buscar primeiro dados oficiais da API Tributária
        $imovel = TributarioApiClient::buscarOficialLocal($inscMunic, $db);

        // 2. Se não encontrado na base oficial sincronizada, recorre à base GP_IMOVEL legada
        if (!$imovel) {
            if ($action === 'bci') {
                $queryBci = '
                    SELECT 
                        "ID",
                        "INSC_MUNIC",
                        "NOMEDAEMPRESA",
                        "NOMEMUNICIPIO",
                        "NOMEDOCONTRIBUINTE",
                        "CPF_CNPJ",
                        "NOMETIPOLOGRA",
                        "NOMELOGRADOURO",
                        "NUMEROLOGRADOURO",
                        "NOMEBAIRRO",
                        "CEP",
                        "COMPLEMENTO",
                        "SIGLA",
                        "NOMEDOESTADO",
                        "OCUPACAO9025",
                        "SITUACAO_DO_TERRENO9026",
                        "ULTILIZACAO_DO_IMOVEL9027",
                        "PEDOLOGIA9028",
                        "TOPOGRAFIA9029",
                        "LIMITACAO9030",
                        "PATRIMONIO9031",
                        "USO_DO_IMOVEL9032",
                        "IMUNE_ISENTO_IPTU_NAO_CALCULAR9033",
                        "ISENTO_TSU9034",
                        "CARACTERIZACAO10035",
                        "REVESTIMENTO_EXTERNO10036",
                        "SITUACAO_CONSTRUCAO10046",
                        "FACHADA10047",
                        "COBERTURA10039",
                        "SITUACAO_DO_IMOVEL10048",
                        "POSICAO10041",
                        "TIPO_DE_CONSTRUCAO10042",
                        "ESTRUTURA10043",
                        "ESTADO_DE_CONSERVACAO10044",
                        "ESGOTO11049",
                        "AGUA11050",
                        "ILUMINACAO_PUBLICA11051",
                        "CALCAMENTO11052",
                        "LIMPEZA_PUBLICA11053",
                        "REDE_TELEFONICA11055",
                        "GUIAS_SARJETAS11056",
                        "COLETA_DE_LIXO11057",
                        "ESGOTO12001",
                        "AGUA12002",
                        "AGUA_DE_POCO12003",
                        "LUZ_FORCA12004",
                        "TELEFONE12005",
                        "LIXO12006",
                        "FOSSA12007",
                        "AREA_DO_TERRENO13065",
                        "AREA_DA_UNIDADE13066",
                        "AREA_TOTAL_DA_EDIFICACAO13067",
                        "TESTADA_DO_IMOVEL13068",
                        "TESTADA_ILUMINACAO13069",
                        "TESTADA_CALCAMENTO13070",
                        "TESTADA_LIMPEZA13071",
                        "N_DE_UNIDADES13072"
                    FROM public."GP_IMOVEL"
                    WHERE "INSC_MUNIC" = :insc_munic
                    LIMIT 1
                ';
                $stmt = $db->prepare($queryBci);
            } else {
                $queryDetails = '
                    SELECT 
                        "ID",
                        "INSC_MUNIC", 	  
                        "NOMEDOCONTRIBUINTE",	  
                        "NOMETIPOLOGRA", 	  
                        "NOMELOGRADOURO", 
                        "NUMEROLOGRADOURO",	  
                        "NOMEBAIRRO", 	  
                        "CEP",	   
                        "NOMEMUNICIPIO",	   
                        "SIGLA", 	     	  
                        "CARACTERIZACAO10035", 	  
                        "AREA_DO_TERRENO13065", 
                        "AREA_DA_UNIDADE13066", 
                        "AREA_TOTAL_DA_EDIFICACAO13067", 	   
                        "OCUPACAO9025", 	   
                        "ULTILIZACAO_DO_IMOVEL9027", 	    
                        "USO_DO_IMOVEL9032" 	   	            
                    FROM public."GP_IMOVEL"    
                    WHERE "INSC_MUNIC" = :insc_munic
                    LIMIT 1
                ';
                $stmt = $db->prepare($queryDetails);
            }

            $stmt->execute([':insc_munic' => $inscMunic]);
            $imovel = $stmt->fetch();
            if ($imovel) {
                $imovel['ORIGEM_DADOS'] = 'BASE_LOCAL_LEGADA';
            }
        }

        if ($imovel) {
            // Consulta foto da fachada na tabela GP_IMOVEL_FOTOS
            $stmtFoto = $db->prepare('
                SELECT "ID", "INSC_MUNIC", "NOME_FOTO", "STATUS"
                FROM public."GP_IMOVEL_FOTOS"
                WHERE "INSC_MUNIC" = :insc AND ("STATUS" = \'A\' OR "STATUS" IS NULL)
                ORDER BY "ID" DESC
                LIMIT 1
            ');
            $stmtFoto->execute([':insc' => $inscMunic]);
            $fotoRow = $stmtFoto->fetch();

            $fotoData = null;
            if ($fotoRow && !empty($fotoRow['NOME_FOTO'])) {
                $fotoData = [
                    'id' => $fotoRow['ID'],
                    'insc_munic' => $fotoRow['INSC_MUNIC'],
                    'nome_foto' => basename(str_replace('\\', '/', $fotoRow['NOME_FOTO'])),
                    'url' => 'api/foto_imovel.php?insc_munic=' . urlencode($inscMunic),
                    'has_foto' => true
                ];
            } else {
                $fotoData = [
                    'id' => null,
                    'insc_munic' => $inscMunic,
                    'nome_foto' => null,
                    'url' => 'api/foto_imovel.php?insc_munic=' . urlencode($inscMunic),
                    'has_foto' => false
                ];
            }

            // Consulta dados espaciais para o Croqui / Mapa de Localização
            $croquiData = [
                'has_geom' => false,
                'lat' => null,
                'lng' => null,
                'lote_selecionado' => null,
                'vizinhos_lotes' => [],
                'edificacoes' => []
            ];

            if ($action === 'bci') {
                $lotePrefix = (strlen($inscMunic) >= 11) ? substr($inscMunic, 0, 11) : $inscMunic;
                $stmtLoteCroqui = $db->prepare('
                    SELECT id, inscimob, inscimob2,
                           ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
                           ST_X(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lng,
                           ST_Y(ST_Centroid(ST_Transform(ST_Force2D(geom), 4326))) as lat
                    FROM geo_lotes_dm
                    WHERE inscimob = :prefix OR inscimob2 LIKE :prefix_like
                    LIMIT 1
                ');
                $stmtLoteCroqui->execute([':prefix' => $lotePrefix, ':prefix_like' => $lotePrefix . '%']);
                $loteCroqui = $stmtLoteCroqui->fetch();

                if ($loteCroqui && !empty($loteCroqui['geojson'])) {
                    $croquiData['has_geom'] = true;
                    $croquiData['lat'] = (float)$loteCroqui['lat'];
                    $croquiData['lng'] = (float)$loteCroqui['lng'];
                    $croquiData['lote_selecionado'] = json_decode($loteCroqui['geojson'], true);

                    // Lotes Vizinhos no raio de 120m
                    $stmtVizinhos = $db->prepare('
                        SELECT id, inscimob,
                               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
                               (CASE WHEN inscimob = :prefix THEN true ELSE false END) as is_selected
                        FROM geo_lotes_dm
                        WHERE ST_DWithin(
                            ST_Transform(ST_Force2D(geom), 4326)::geography,
                            ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography,
                            120
                        )
                        LIMIT 60
                    ');
                    $stmtVizinhos->execute([':prefix' => $lotePrefix, ':lng' => $loteCroqui['lng'], ':lat' => $loteCroqui['lat']]);
                    $vizinhosRaw = $stmtVizinhos->fetchAll();
                    foreach ($vizinhosRaw as $v) {
                        $croquiData['vizinhos_lotes'][] = [
                            'id' => $v['id'],
                            'inscimob' => $v['inscimob'],
                            'is_selected' => (bool)$v['is_selected'],
                            'geojson' => json_decode($v['geojson'], true)
                        ];
                    }

                    // Edificações Vizinhas no raio de 120m
                    $stmtEdifs = $db->prepare('
                        SELECT id, inscimob,
                               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson
                        FROM geo_edificacoes_dm
                        WHERE ST_DWithin(
                            ST_Transform(ST_Force2D(geom), 4326)::geography,
                            ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography,
                            120
                        )
                        LIMIT 60
                    ');
                    $stmtEdifs->execute([':lng' => $loteCroqui['lng'], ':lat' => $loteCroqui['lat']]);
                    $edifsRaw = $stmtEdifs->fetchAll();
                    foreach ($edifsRaw as $e) {
                        $croquiData['edificacoes'][] = [
                            'id' => $e['id'],
                            'inscimob' => $e['inscimob'],
                            'geojson' => json_decode($e['geojson'], true)
                        ];
                    }
                }
            }

            echo json_encode([
                'found' => true,
                'imovel' => $imovel,
                'foto' => $fotoData,
                'croqui' => $croquiData
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        } else {
            echo json_encode([
                'found' => false,
                'message' => 'Nenhum registro encontrado no GP_IMOVEL para a inscrição informada.'
            ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'error' => true,
            'message' => 'Erro ao consultar dados do imóvel: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }
    exit;
}

// ROTA 2: Identificação espacial por clique no mapa (Lat / Lng)
$lat = isset($_GET['lat']) ? (float)$_GET['lat'] : null;
$lng = isset($_GET['lng']) ? (float)$_GET['lng'] : null;

if ($lat === null || $lng === null) {
    http_response_code(400);
    echo json_encode([
        'error' => true,
        'message' => 'Parâmetros lat e lng são obrigatórios para identificação no mapa.'
    ]);
    exit;
}

try {
    // 1. Busca Lote espacialmente (tolerância 2m)
    $stmtLote = $db->prepare("
        SELECT id, inscimob, inscimob2, 'geo_lotes_dm' as camada,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
               ST_Distance(ST_Transform(ST_Force2D(geom), 4326)::geography, ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography) as dist
        FROM geo_lotes_dm
        WHERE ST_DWithin(ST_Transform(ST_Force2D(geom), 4326)::geography, ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography, 2)
        ORDER BY dist ASC
        LIMIT 1
    ");
    $stmtLote->execute([':lng' => $lng, ':lat' => $lat]);
    $lote = $stmtLote->fetch();

    // 2. Busca Edificação espacialmente (tolerância 2m)
    $stmtEdif = $db->prepare("
        SELECT id, inscimob, caract, beiral, fator, obs, 'geo_edificacoes_dm' as camada,
               ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
               ST_Distance(ST_Transform(ST_Force2D(geom), 4326)::geography, ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography) as dist
        FROM geo_edificacoes_dm
        WHERE ST_DWithin(ST_Transform(ST_Force2D(geom), 4326)::geography, ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography, 2)
        ORDER BY dist ASC
        LIMIT 1
    ");
    $stmtEdif->execute([':lng' => $lng, ':lat' => $lat]);
    $edif = $stmtEdif->fetch();

    if (!$lote && !$edif) {
        echo json_encode([
            'found' => false,
            'message' => 'Nenhum lote ou edificação encontrado no local clicado.'
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // 3. Extrai a raiz/prefixo da inscrição do lote (11 caracteres)
    $lotePrefix = null;
    if ($lote && !empty($lote['inscimob'])) {
        $lotePrefix = trim($lote['inscimob']);
    } elseif ($lote && !empty($lote['inscimob2'])) {
        $raw = trim($lote['inscimob2']);
        $lotePrefix = (strlen($raw) >= 11) ? substr($raw, 0, 11) : $raw;
    } elseif ($edif && !empty($edif['inscimob'])) {
        $raw = trim($edif['inscimob']);
        $lotePrefix = (strlen($raw) >= 11) ? substr($raw, 0, 11) : $raw;
    }

    // Se encontrou edificação mas não o lote no ponto do clique, busca o polígono do lote pela inscrição
    if (!$lote && $lotePrefix) {
        $stmtLoteByPrefix = $db->prepare("
            SELECT id, inscimob, inscimob2, 'geo_lotes_dm' as camada,
                   ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson
            FROM geo_lotes_dm
            WHERE inscimob = :prefix OR inscimob2 LIKE :prefix_like
            LIMIT 1
        ");
        $stmtLoteByPrefix->execute([':prefix' => $lotePrefix, ':prefix_like' => $lotePrefix . '%']);
        $lote = $stmtLoteByPrefix->fetch();
    }

    $loteGeojson = ($lote && !empty($lote['geojson'])) ? json_decode($lote['geojson'], true) : null;
    $edifGeojson = ($edif && !empty($edif['geojson'])) ? json_decode($edif['geojson'], true) : null;
    $primaryGeojson = $loteGeojson ?? $edifGeojson;

    if (empty($lotePrefix)) {
        echo json_encode([
            'found' => true,
            'lote_prefix' => null,
            'feature_clicked' => $edif ? 'edificacao' : 'lote',
            'units_count' => 0,
            'units' => [],
            'geojson' => $primaryGeojson,
            'lote_geojson' => $loteGeojson,
            'edif_geojson' => $edifGeojson,
            'message' => 'Lote ou edificação encontrado, mas sem código de inscrição cadastrado.'
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    // 4. Consulta as unidades imobiliárias pelo prefixo do lote
    $stmtUnits = $db->prepare('
        SELECT "INSC_MUNIC", "NOMEDOCONTRIBUINTE" 
        FROM public."GP_IMOVEL"
        WHERE "INSC_MUNIC" LIKE :prefix
        ORDER BY "INSC_MUNIC" ASC
        LIMIT 100
    ');
    $stmtUnits->execute([':prefix' => $lotePrefix . '%']);
    $units = $stmtUnits->fetchAll();

    // Verifica se há registros na base oficial da API Tributária para este lote
    $stmtTribUnits = $db->prepare('
        SELECT insc_munic, situacao, dados_bci->>\'NOMEDOCONTRIBUINTE\' as contribuinte
        FROM public.tributario_api_imovel
        WHERE insc_munic LIKE :prefix
        ORDER BY insc_munic ASC
    ');
    $stmtTribUnits->execute([':prefix' => $lotePrefix . '%']);
    $tribUnits = $stmtTribUnits->fetchAll();

    $tribMap = [];
    foreach ($tribUnits as $tu) {
        $tribMap[$tu['insc_munic']] = $tu;
    }

    $mergedUnits = [];
    $seenInsc = [];
    foreach ($units as $u) {
        $insc = $u['INSC_MUNIC'];
        $seenInsc[$insc] = true;
        if (isset($tribMap[$insc])) {
            $u['NOMEDOCONTRIBUINTE'] = $tribMap[$insc]['contribuinte'] ?: $u['NOMEDOCONTRIBUINTE'];
            $u['SITUACAO'] = $tribMap[$insc]['situacao'] ?? null;
            $u['ORIGEM_DADOS'] = 'API_TRIBUTARIA_OFICIAL';
        } else {
            $u['ORIGEM_DADOS'] = 'BASE_LOCAL_LEGADA';
        }
        $mergedUnits[] = $u;
    }

    // Inclui eventuais unidades que existam apenas na base oficial da API
    foreach ($tribUnits as $tu) {
        if (!isset($seenInsc[$tu['insc_munic']])) {
            $mergedUnits[] = [
                'INSC_MUNIC' => $tu['insc_munic'],
                'NOMEDOCONTRIBUINTE' => $tu['contribuinte'] ?? 'Contribuinte Oficial',
                'SITUACAO' => $tu['situacao'] ?? null,
                'ORIGEM_DADOS' => 'API_TRIBUTARIA_OFICIAL'
            ];
        }
    }

    $units = $mergedUnits;
    $unitsCount = count($units);

    $singleUnitDetails = null;

    // Se houver exatamente 1 unidade, busca os detalhes completos diretamente
    if ($unitsCount === 1) {
        $targetInsc = $units[0]['INSC_MUNIC'];
        $singleUnitDetails = TributarioApiClient::buscarOficialLocal($targetInsc, $db);

        if (!$singleUnitDetails) {
            $querySingleDetails = '
                SELECT 
                    "ID",
                    "INSC_MUNIC", 	  
                    "NOMEDOCONTRIBUINTE",	  
                    "NOMETIPOLOGRA", 	  
                    "NOMELOGRADOURO", 
                    "NUMEROLOGRADOURO",	  
                    "NOMEBAIRRO", 	  
                    "CEP",	   
                    "NOMEMUNICIPIO",	   
                    "SIGLA", 	     	  
                    "CARACTERIZACAO10035", 	  
                    "AREA_DO_TERRENO13065", 
                    "AREA_DA_UNIDADE13066", 
                    "AREA_TOTAL_DA_EDIFICACAO13067", 	   
                    "OCUPACAO9025", 	   
                    "ULTILIZACAO_DO_IMOVEL9027", 	    
                    "USO_DO_IMOVEL9032" 	   	            
                FROM public."GP_IMOVEL"    
                WHERE "INSC_MUNIC" = :insc_munic
                LIMIT 1
            ';
            $stmtSingle = $db->prepare($querySingleDetails);
            $stmtSingle->execute([':insc_munic' => $targetInsc]);
            $singleUnitDetails = $stmtSingle->fetch();
            if ($singleUnitDetails) {
                $singleUnitDetails['ORIGEM_DADOS'] = 'BASE_LOCAL_LEGADA';
            }
        }
    }

    echo json_encode([
        'found' => true,
        'lote_prefix' => $lotePrefix,
        'feature_clicked' => $edif ? 'edificacao' : 'lote',
        'units_count' => $unitsCount,
        'units' => $units,
        'single_unit_details' => $singleUnitDetails,
        'geojson' => $primaryGeojson,
        'lote_geojson' => $loteGeojson,
        'edif_geojson' => $edifGeojson
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Erro ao identificar feição: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
}
