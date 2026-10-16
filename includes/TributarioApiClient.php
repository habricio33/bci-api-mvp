<?php
/**
 * Cliente de Integração com a API do Sistema Tributário da Prefeitura de Domingos Martins.
 * Gerencia autenticação, token JWT, consultas e conversão para o formato padrão do BCI.
 */

require_once __DIR__ . '/../config/env.php';

class TributarioApiClient {
    private string $baseUrl;
    private string $usuario;
    private string $senha;
    private string $cacheFile;

    public function __construct() {
        loadEnv();
        $this->baseUrl = rtrim(env('TRIBUTARIO_API_BASE_URL', 'https://gpi.domingosmartins.es.gov.br/tributario-api-rest/api/rest/v1/recadastramento-imobiliario'), '/');
        $this->usuario = env('TRIBUTARIO_API_USER', 'es_pmdm_geo_el');
        $this->senha = env('TRIBUTARIO_API_PASS', '38d32522');
        $this->cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tributario_api_token_' . md5($this->usuario) . '.json';
    }

    /**
     * Obtém o Token JWT válido (do cache ou autenticando novamente).
     */
    public function getToken(): string {
        if (file_exists($this->cacheFile)) {
            $data = json_decode(file_get_contents($this->cacheFile), true);
            if ($data && !empty($data['token']) && !empty($data['expires_at'])) {
                // Buffer de segurança de 2 minutos antes de expirar
                if (time() < ($data['expires_at'] - 120)) {
                    return $data['token'];
                }
            }
        }

        return $this->autenticar();
    }

    /**
     * Realiza login no endpoint POST /authenticate e armazena o token.
     */
    private function autenticar(): string {
        $url = $this->baseUrl . '/authenticate';
        $payload = json_encode([
            'usuario' => $this->usuario,
            'senha' => $this->senha
        ], JSON_UNESCAPED_UNICODE);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Falha de conexão com a API Tributária: " . $curlError);
        }

        if ($httpCode !== 200) {
            throw new Exception("Erro de autenticação na API Tributária (HTTP {$httpCode}): " . substr($response, 0, 300));
        }

        $json = json_decode($response, true);
        if (!$json || empty($json['token'])) {
            throw new Exception("Resposta inválida da API Tributária ao autenticar.");
        }

        $token = $json['token'];

        // Tenta extrair a data de expiração do JWT (payload base64)
        $expiresAt = time() + 3600; // Padrão: 1 hora
        $jwtParts = explode('.', $token);
        if (count($jwtParts) === 3) {
            $jwtPayload = json_decode(base64_decode(strtr($jwtParts[1], '-_', '+/')), true);
            if ($jwtPayload && !empty($jwtPayload['exp'])) {
                $expiresAt = (int)$jwtPayload['exp'];
            }
        }

        @file_put_contents($this->cacheFile, json_encode([
            'token' => $token,
            'expires_at' => $expiresAt,
            'cached_at' => date('Y-m-d H:i:s')
        ]));

        return $token;
    }

    /**
     * Consulta inscrições municipais por período com paginação.
     *
     * @param string $dataInicial Formato YYYY-MM-DD
     * @param string $dataFinal Formato YYYY-MM-DD
     * @param int $page Página (iniciando em 1)
     * @param int $size Quantidade por página (máximo recomendado: 100)
     * @return array Array associativo com ['items', 'totalCount', 'totalPages']
     */
    public function consultarPeriodo(string $dataInicial, string $dataFinal, int $page = 1, int $size = 100): array {
        $token = $this->getToken();

        $params = http_build_query([
            'dataInicial' => $dataInicial,
            'dataFinal' => $dataFinal,
            'page' => $page,
            'size' => $size
        ]);

        $url = $this->baseUrl . '/consulta/inscricao-municipal-periodo?' . $params;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_HTTPGET => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json'
            ],
            CURLOPT_TIMEOUT => 45,
            CURLOPT_SSL_VERIFYPEER => true
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            throw new Exception("Erro ao consultar período na API Tributária: " . $curlError);
        }

        // Se o token expirou no servidor, limpa o cache e tenta mais uma vez
        if ($httpCode === 401 || $httpCode === 403) {
            @unlink($this->cacheFile);
            $token = $this->getToken();
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_HTTPGET => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $token,
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 45
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
        }

        if ($httpCode !== 200) {
            throw new Exception("API Tributária retornou erro HTTP {$httpCode}: " . substr($response, 0, 400));
        }

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new Exception("Resposta inválida (não JSON) da API Tributária.");
        }

        return $data;
    }

    /**
     * Converte o item retornado pela API oficial no formato de dados
     * compatível com a visualização do Boletim de Cadastro Imobiliário (BCI).
     */
    public function converterItemParaBci(array $item): array {
        $imob = $item['imobiliario'] ?? [];
        $pessoa = $item['pessoa'] ?? [];
        $logra = $item['logradouro'] ?? [];
        $tipoLogra = $logra['tipo'] ?? [];
        $bairro = $item['bairro'] ?? [];
        $cidade = $item['cidade'] ?? [];

        // Monta o dicionário de campos do Boletim (características BCI)
        $boletimMap = [];
        if (!empty($imob['boletim']) && is_array($imob['boletim'])) {
            foreach ($imob['boletim'] as $b) {
                $campoKey = (string)($b['campo'] ?? '');
                $primeiroItem = $b['boletim'][0] ?? null;
                $valorDesc = $primeiroItem['descricao'] ?? '';
                if ($campoKey !== '') {
                    $boletimMap[$campoKey] = trim((string)$valorDesc);
                }
            }
        }

        // Inscrição municipal padronizada
        $inscricaoCadastral = $imob['inscricaoCadastral'] ?? (string)($item['codigo'] ?? '');

        return [
            // Identificação e Contribuinte
            'ID' => $item['codigo'] ?? null,
            'INSC_MUNIC' => $inscricaoCadastral,
            'CODIGO_REDUZIDO' => $item['codigo'] ?? null,
            'NOMEDAEMPRESA' => 'PREFEITURA MUNICIPAL DE',
            'NOMEMUNICIPIO' => $cidade['nome'] ?? 'Domingos Martins',
            'SIGLA' => $cidade['tipo']['sigla'] ?? 'ES',
            'NOMEDOESTADO' => $cidade['tipo']['descricao'] ?? 'Espírito Santo',
            'NOMEDOCONTRIBUINTE' => $pessoa['nome'] ?? '',
            'CPF_CNPJ' => $pessoa['documento'] ?? '',

            // Endereço
            'NOMETIPOLOGRA' => $tipoLogra['descricao'] ?? $tipoLogra['sigla'] ?? '',
            'NOMELOGRADOURO' => $logra['nome'] ?? '',
            'NUMEROLOGRADOURO' => (string)($item['numero'] ?? 'S/N'),
            'COMPLEMENTO' => $item['complemento'] ?? '',
            'NOMEBAIRRO' => $bairro['nome'] ?? '',
            'CEP' => $item['cep'] ?? $logra['cep'] ?? '',

            // Dados Cadastrais / Loteamento
            'DISTRITO' => $imob['distrito'] ?? '',
            'SETOR' => $imob['setor'] ?? '',
            'QUADRA' => $imob['quadra'] ?? '',
            'LOTE' => $imob['lote'] ?? '',
            'UNIDADE' => $imob['unidade'] ?? '',
            'EDIFICACAO' => $imob['edificacao'] ?? '',
            'SITUACAO_CADASTRAL' => $imob['situacao']['descricao'] ?? '',

            // Atributos de Terreno / Ocupação (Boletim)
            'OCUPACAO9025' => $boletimMap['9025'] ?? '',
            'SITUACAO_DO_TERRENO9026' => $boletimMap['9026'] ?? '',
            'ULTILIZACAO_DO_IMOVEL9027' => $boletimMap['9027'] ?? '',
            'PEDOLOGIA9028' => $boletimMap['9028'] ?? '',
            'TOPOGRAFIA9029' => $boletimMap['9029'] ?? '',
            'LIMITACAO9030' => $boletimMap['9030'] ?? '',
            'PATRIMONIO9031' => $boletimMap['9031'] ?? '',
            'USO_DO_IMOVEL9032' => $boletimMap['9032'] ?? '',
            'IMUNE_ISENTO_IPTU_NAO_CALCULAR9033' => $boletimMap['9033'] ?? '',
            'ISENTO_TSU9034' => $boletimMap['9034'] ?? '',

            // Características Construtivas (Boletim)
            'CARACTERIZACAO10035' => $boletimMap['10035'] ?? '',
            'REVESTIMENTO_EXTERNO10036' => $boletimMap['10036'] ?? '',
            'SITUACAO_CONSTRUCAO10046' => $boletimMap['10046'] ?? '',
            'FACHADA10047' => $boletimMap['10047'] ?? '',
            'COBERTURA10039' => $boletimMap['10039'] ?? '',
            'SITUACAO_DO_IMOVEL10048' => $boletimMap['10048'] ?? '',
            'POSICAO10041' => $boletimMap['10041'] ?? '',
            'TIPO_DE_CONSTRUCAO10042' => $boletimMap['10042'] ?? '',
            'ESTRUTURA10043' => $boletimMap['10043'] ?? '',
            'ESTADO_DE_CONSERVACAO10044' => $boletimMap['10044'] ?? '',

            // Infraestrutura e Serviços Públicos (Boletim)
            'ESGOTO11049' => $boletimMap['11049'] ?? '',
            'AGUA11050' => $boletimMap['11050'] ?? '',
            'ILUMINACAO_PUBLICA11051' => $boletimMap['11051'] ?? '',
            'CALCAMENTO11052' => $boletimMap['11052'] ?? '',
            'LIMPEZA_PUBLICA11053' => $boletimMap['11053'] ?? '',
            'REDE_TELEFONICA11055' => $boletimMap['11055'] ?? '',
            'GUIAS_SARJETAS11056' => $boletimMap['11056'] ?? '',
            'COLETA_DE_LIXO11057' => $boletimMap['11057'] ?? '',

            // Áreas e Testadas (Boletim)
            'AREA_DO_TERRENO13065' => $boletimMap['13065'] ?? '',
            'AREA_DA_UNIDADE13066' => $boletimMap['13066'] ?? '',
            'AREA_TOTAL_DA_EDIFICACAO13067' => $boletimMap['13067'] ?? '',
            'TESTADA_DO_IMOVEL13068' => $boletimMap['13068'] ?? '',
            'TESTADA_ILUMINACAO13069' => $boletimMap['13069'] ?? '',
            'TESTADA_CALCAMENTO13070' => $boletimMap['13070'] ?? '',
            'TESTADA_LIMPEZA13071' => $boletimMap['13071'] ?? '',
            'N_DE_UNIDADES13072' => $boletimMap['13072'] ?? '',

            // Campos Novos e Oficiais da API
            'CODIGO_CIB' => $item['codigoCib'] ?? '',
            'MEMORIAL' => $item['memorial'] ?? '',
            'DATA_INCLUSAO' => $item['dataInclusao'] ?? '',
            'DATA_ALTERACAO' => $item['dataAlteracao'] ?? '',
            'DATA_EXCLUSAO' => $item['dataExclusao'] ?? '',
            'PROCESSO_INCLUSAO' => $item['processoInclusao'] ?? '',
            'PROCESSO_EXCLUSAO' => $item['processoExclusao'] ?? '',
            'ORIGEM_DADOS' => 'API_TRIBUTARIA_OFICIAL'
        ];
    }

    /**
     * Salva ou atualiza um item retornado da API na tabela tributario_api_imovel.
     */
    public function salvarItemNoBanco(array $item, ?PDO $db = null): array {
        if ($db === null) {
            require_once __DIR__ . '/../config/db.php';
            $db = getDBConnection();
        }

        $bci = $this->converterItemParaBci($item);
        $inscMunic = $bci['INSC_MUNIC'];
        $codigo = $item['codigo'] ?? null;
        $situacao = $item['imobiliario']['situacao']['descricao'] ?? '';
        $dataInclusao = !empty($item['dataInclusao']) ? $item['dataInclusao'] : null;
        $dataAlteracao = !empty($item['dataAlteracao']) ? $item['dataAlteracao'] : null;
        $dataExclusao = !empty($item['dataExclusao']) ? $item['dataExclusao'] : null;

        $stmt = $db->prepare('
            INSERT INTO public.tributario_api_imovel 
                (insc_munic, codigo, dados_bci, dados_api_raw, data_inclusao, data_alteracao, data_exclusao, situacao, atualizado_em)
            VALUES 
                (:insc, :codigo, :bci::jsonb, :raw::jsonb, :dt_inc, :dt_alt, :dt_exc, :situacao, CURRENT_TIMESTAMP)
            ON CONFLICT (insc_munic) DO UPDATE SET
                codigo = EXCLUDED.codigo,
                dados_bci = EXCLUDED.dados_bci,
                dados_api_raw = EXCLUDED.dados_api_raw,
                data_inclusao = EXCLUDED.data_inclusao,
                data_alteracao = EXCLUDED.data_alteracao,
                data_exclusao = EXCLUDED.data_exclusao,
                situacao = EXCLUDED.situacao,
                atualizado_em = CURRENT_TIMESTAMP
        ');

        $stmt->execute([
            ':insc' => $inscMunic,
            ':codigo' => $codigo,
            ':bci' => json_encode($bci, JSON_UNESCAPED_UNICODE),
            ':raw' => json_encode($item, JSON_UNESCAPED_UNICODE),
            ':dt_inc' => $dataInclusao,
            ':dt_alt' => $dataAlteracao,
            ':dt_exc' => $dataExclusao,
            ':situacao' => $situacao
        ]);

        return $bci;
    }

    /**
     * Sincroniza um período da API tributária gravando todos os registros na tabela local.
     *
     * @param string $dataInicial Formato YYYY-MM-DD
     * @param string $dataFinal Formato YYYY-MM-DD
     * @param int $maxPages Quantidade máxima de páginas a processar (0 para sincronizar todas até o fim)
     * @param int $startPage Página inicial (padrão: 1)
     * @param int $size Quantidade por página (padrão: 50, máx: 100)
     * @param PDO|null $db Conexão PDO opcional
     * @return array
     */
    public function sincronizarPeriodo(
        string $dataInicial, 
        string $dataFinal, 
        int $maxPages = 20, 
        int $startPage = 1, 
        int $size = 50, 
        ?PDO $db = null
    ): array {
        if ($db === null) {
            require_once __DIR__ . '/../config/db.php';
            $db = getDBConnection();
        }

        @set_time_limit(900);

        $totalSalvos = 0;
        $page = max(1, $startPage);
        $totalPages = null;
        $totalCount = 0;
        $paginasProcessadas = 0;
        $size = max(10, min(100, $size));

        // Se maxPages for <= 0, sincroniza até a última página disponível na API
        $limitPages = ($maxPages > 0) ? ($page + $maxPages - 1) : PHP_INT_MAX;

        while ($page <= $limitPages && ($totalPages === null || $page <= $totalPages)) {
            $resp = $this->consultarPeriodo($dataInicial, $dataFinal, $page, $size);
            $totalPages = (int)($resp['totalPages'] ?? 1);
            $totalCount = (int)($resp['totalCount'] ?? 0);
            $items = $resp['items'] ?? [];

            if (empty($items)) {
                break;
            }

            foreach ($items as $it) {
                $this->salvarItemNoBanco($it, $db);
                $totalSalvos++;
            }

            $paginasProcessadas++;
            $page++;
        }

        return [
            'success' => true,
            'total_salvos' => $totalSalvos,
            'data_inicial' => $dataInicial,
            'data_final' => $dataFinal,
            'start_page' => $startPage,
            'last_page' => $page - 1,
            'paginas_processadas' => $paginasProcessadas,
            'total_pages_api' => $totalPages,
            'total_count_api' => $totalCount
        ];
    }

    /**
     * Busca dados oficiais salvos na tabela tributario_api_imovel.
     */
    public static function buscarOficialLocal(string $inscMunic, ?PDO $db = null): ?array {
        if ($db === null) {
            require_once __DIR__ . '/../config/db.php';
            $db = getDBConnection();
        }

        $stmt = $db->prepare('
            SELECT dados_bci, situacao, atualizado_em 
            FROM public.tributario_api_imovel 
            WHERE insc_munic = :insc OR codigo::text = :insc
            LIMIT 1
        ');
        $stmt->execute([':insc' => $inscMunic]);
        $row = $stmt->fetch();

        if ($row && !empty($row['dados_bci'])) {
            $data = json_decode($row['dados_bci'], true);
            $data['ORIGEM_DADOS'] = 'API_TRIBUTARIA_OFICIAL';
            $data['SITUACAO_CADASTRAL'] = $row['situacao'] ?? ($data['SITUACAO_CADASTRAL'] ?? '');
            $data['ATUALIZADO_EM'] = $row['atualizado_em'] ?? '';
            return $data;
        }

        return null;
    }
}
