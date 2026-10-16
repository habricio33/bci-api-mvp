<?php
/**
 * Endpoint para consulta direta do Espelho Cadastral / BCI via API Tributária Oficial.
 */

session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    echo json_encode([
        'error' => true,
        'message' => 'Não autorizado. Por favor, faça login.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../includes/TributarioApiClient.php';

$action = $_GET['action'] ?? 'listar';
$dataInicial = $_GET['dataInicial'] ?? date('Y-m-01'); // Início do mês atual por padrão
$dataFinal = $_GET['dataFinal'] ?? date('Y-m-d');     // Data de hoje
$page = max(1, (int)($_GET['page'] ?? 1));
$size = min(100, max(1, (int)($_GET['size'] ?? 20)));

try {
    $client = new TributarioApiClient();

    if ($action === 'listar') {
        // Consulta o período paginado
        $resultado = $client->consultarPeriodo($dataInicial, $dataFinal, $page, $size);
        
        $itensFormatados = [];
        if (!empty($resultado['items']) && is_array($resultado['items'])) {
            foreach ($resultado['items'] as $item) {
                $itensFormatados[] = [
                    'codigo' => $item['codigo'] ?? null,
                    'codigoCib' => $item['codigoCib'] ?? null,
                    'inscricaoCadastral' => $item['imobiliario']['inscricaoCadastral'] ?? '',
                    'contribuinte' => $item['pessoa']['nome'] ?? '',
                    'documento' => $item['pessoa']['documento'] ?? '',
                    'logradouro' => ($item['logradouro']['nome'] ?? '') . ', ' . ($item['numero'] ?? 'S/N'),
                    'bairro' => $item['bairro']['nome'] ?? '',
                    'situacao' => $item['imobiliario']['situacao']['descricao'] ?? '',
                    'dataInclusao' => $item['dataInclusao'] ?? null,
                    'dataAlteracao' => $item['dataAlteracao'] ?? null,
                    'dataExclusao' => $item['dataExclusao'] ?? null,
                    'raw_item' => $item
                ];
            }
        }

        echo json_encode([
            'success' => true,
            'dataInicial' => $dataInicial,
            'dataFinal' => $dataFinal,
            'page' => $page,
            'size' => $size,
            'totalCount' => $resultado['totalCount'] ?? 0,
            'totalPages' => $resultado['totalPages'] ?? 0,
            'items' => $itensFormatados
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    if ($action === 'espelho') {
        // Gera o espelho cadastral completo para um item específico
        // Permite passar ?codigo=... ou ?inscricao=...
        $codigoBuscado = $_GET['codigo'] ?? '';
        $inscricaoBuscada = $_GET['inscricao'] ?? '';

        if (empty($codigoBuscado) && empty($inscricaoBuscada)) {
            http_response_code(400);
            echo json_encode([
                'error' => true,
                'message' => 'Informe o parâmetro codigo ou inscricao para consultar o espelho.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Busca no período informado (ou busca alargada padrão)
        $buscaInicial = $_GET['dataInicial'] ?? '2020-01-01';
        $buscaFinal = $_GET['dataFinal'] ?? date('Y-m-d');
        
        // Faz consulta inicial
        $resultado = $client->consultarPeriodo($buscaInicial, $buscaFinal, $page, 50);
        
        $itemEncontrado = null;
        if (!empty($resultado['items'])) {
            foreach ($resultado['items'] as $it) {
                $itCod = (string)($it['codigo'] ?? '');
                $itInsc = (string)($it['imobiliario']['inscricaoCadastral'] ?? '');
                
                if (($codigoBuscado && $itCod === (string)$codigoBuscado) ||
                    ($inscricaoBuscada && ($itInsc === (string)$inscricaoBuscada || $itCod === (string)$inscricaoBuscada))) {
                    $itemEncontrado = $it;
                    break;
                }
            }
        }

        if (!$itemEncontrado) {
            echo json_encode([
                'found' => false,
                'message' => 'Imóvel não localizado no período consultado da API Tributária.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // Converte para a estrutura padrão do BCI
        $bciData = $client->converterItemParaBci($itemEncontrado);

        echo json_encode([
            'found' => true,
            'imovel' => $bciData,
            'raw_api' => $itemEncontrado
        ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => true, 'message' => 'Ação não reconhecida.'], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
