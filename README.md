# 🏛️ GeoPortal Módulo BCI — Cadastro Imobiliário & Gestão Territorial

Sistema de Informações Geográficas (WebGIS) desenvolvido para cadastro imobiliário multifinalitário, emissão do **Boletim de Cadastro Imobiliário (BCI)**, gestão da infraestrutura urbana (redes de água, esgoto, ramais prediais, zoneamento e logradouros) e análise espacial da **Prefeitura Municipal de Domingos Martins / ES**.

---

## 📌 Principais Módulos e Funcionalidades

### 🏛️ 1. Módulo de Cadastro Imobiliário Multifinalitário (BCI)
- **Identificação Espacial Inteligente (Identify):**
  - Identificação de feições restrita e otimizada exclusivamente para **Lotes** (`geo_lotes_dm`) e **Edificações** (`geo_edificacoes_dm`).
  - **Suporte a Múltiplas Unidades:** Detecção automática quando um mesmo lote possui mais de uma edificação/unidade (ex: condomínios e vilas), abrindo um modal seletor listando Inscrição Municipal e Contribuinte.
  - **Destaque e Zoom Automático:** O lote clicado é destacado no mapa com contorno de alta visibilidade e o mapa realiza aproximação suave e enquadramento (`map.fitBounds`).
- **Detalhamento Alfanumérico em Blocos:**
  - Modal estilizado e responsivo com dados organizados em: *Dados Principais*, *Localização* e *Dados Cadastrais*.
- **Espelho Cadastral Oficial (BCI):**
  - **Cabeçalho Oficial:** Brasão municipal (`img/logo-dm.png`), nome da prefeitura concatenado (`NOMEDAEMPRESA` + `NOMEMUNICIPIO`), título e data/hora de emissão.
  - **Bloco 1 — Identificação:** Inscrição Municipal, Contribuinte e CPF/CNPJ.
  - **Bloco 2 — Localização do Imóvel & Foto da Fachada:** Logradouro completo, Número, Complemento, Bairro, CEP, Município/UF e card com a foto da fachada (ampliação com clique).
  - **Bloco 3 — Informações Gerais do Terreno:** Ocupação, Situação, Utilização, Pedologia, Topografia, Limitação, Patrimônio, Uso, Isenções de IPTU e TSU.
  - **Bloco 4 — Informações Gerais da Edificação:** Caracterização, Revestimento Externo, Situação da Construção, Fachada, Cobertura, Situação do Imóvel, Posição, Tipo de Construção, Estrutura e Estado de Conservação.
  - **Bloco 5 — Serviços Urbanos no Logradouro:** Esgoto, Água, Iluminação Pública, Calçamento, Limpeza Pública, Telefonia, Guias/Sarjetas e Coleta de Lixo.
  - **Bloco 6 — Serviços Urbanos na Unidade:** Esgoto, Água, Água de Poço, Luz/Força, Telefone, Coleta de Lixo e Fossa.
  - **Bloco 7 — Dimensões da Unidade:** Área do Terreno, Área da Unidade, Área Total da Edificação, Testadas (Imóvel, Iluminação, Calçamento, Limpeza) e Quantidade de Unidades.
  - **Bloco 8 — Mapa de Localização (Croqui Cartográfico):** Mini-mapa integrado com camada OpenStreetMap (OSM), exibindo o lote em destaque vermelho cadastral (`#dc2626`), lotes vizinhos da quadra, edificações e Rosa dos Ventos.
  - **Impressão A4 & Exportação PDF:** Formatação dedicada via `@media print` para impressão do documento técnico oficial sem cortes ou quebras indesejadas de página.

---

### 📸 2. Gerenciador de Fotos de Fachada (`api/foto_imovel.php`)
- **Arquitetura Desacoplada:** Relacionamento direto com a tabela `public."GP_IMOVEL_FOTOS"`.
- **Normalização de Caminhos:** Trata automaticamente caminhos absolutos legados do Windows (`C:\wamp64\...`), caminhos relativos ou URLs externas.
- **Cache HTTP & Desempenho:** Envio de cabeçalhos `ETag` e `Cache-Control` para carregamento instantâneo.
- **Fallback Automático:** Emissão de SVG técnico caso o imóvel não possua foto cadastrada.

---

### 💧 3. Módulo de Redes de Saneamento & Infraestrutura
- **Visualização e Tematização:** Redes de água e esgoto, ramais prediais e zoneamento urbano.
- **Filtros Dinâmicos:** Filtros por material, diâmetro nominal e status operacional.
- **Edição Alfanumérica (CRUD):** Ferramenta integrada de cadastro e atualização de atributos de tubulações e ramais diretamente pelo mapa.

---

### 📐 4. Ferramentas Cartográficas e Análise Espacial
- **Simulação de Intervenção / Área de Influência (Buffer):** Análise espacial em PostGIS no ponto clicado, calculando tubulações afetadas por diâmetro e material, contagem de ramais desabastecidos e exportação de relatórios em CSV.
- **Régua de Medição:** Cálculo dinâmico de distâncias lineares e áreas poligonais.
- **Coordenadas em Tempo Real:** Rastreamento do cursor com conversão instantânea entre **WGS 84 (Geográficas)** e **SIRGAS 2000 / UTM Zona 24S (EPSG:31984)**.
- **Sistema de Busca Especializada:** Pesquisa segmentada e instantânea por:
  - **Inscrição Imobiliária:** Consulta rápida por inscrição municipal com zoom no lote/edificação e abertura de detalhes cadastrais.
  - **CPF / CNPJ:** Localização de imóveis por documento do contribuinte (com ou sem máscara) e visualização integrada.
  - **Logradouros:** Busca de ruas com autocompletar e destaque do eixo viário.
  - **Bairros:** Busca por comunidades e bairros municipais com enquadramento espacial.

---

### 🛰️ 5. Interoperabilidade OGC / QGIS
- Servidor **GeoServer** configurado para publicação de camadas WMS e WFS com suporte a edição vetorial transacional (**WFS-T**) via QGIS Desktop.

---

## 🛠️ Tecnologias Utilizadas

| Camada | Tecnologia | Descrição |
| :--- | :--- | :--- |
| **Backend** | PHP 8.2 (Apache, PDO PostGIS) | Endpoints REST, consultas espaciais e entrega de dados/mídia |
| **Banco de Dados** | PostgreSQL 16 + PostGIS 3.4 | Armazenamento espacial de lotes, edificações, redes e tabelas cadastrais |
| **Frontend** | HTML5, CSS3 Vanilla, JavaScript ES6+ | Interface WebGIS responsiva com foco em usabilidade técnica |
| **Mapeamento** | Leaflet.js 1.9+, Proj4js, OpenStreetMap | Renderização de camadas vetoriais e reprojeção de coordenadas |
| **Servidor OGC** | GeoServer 2.25.0 | Serviços padronizados WMS / WFS / WFS-T |
| **Administração DB** | pgAdmin 4 | Interface web para administração do PostgreSQL |
| **Containers** | Docker & Docker Compose | Orquestração do ambiente completo de desenvolvimento e produção |

---

## 📂 Estrutura do Projeto

```text
MVP-BCI-DOCKER/
├── api/                        # Endpoints da aplicação
│   ├── busca_inscricao.php     # Busca de imóveis por Inscrição Municipal
│   ├── busca_cpf.php           # Busca de imóveis por CPF / CNPJ do contribuinte
│   ├── busca_ruas.php          # Busca de logradouros
│   ├── busca_bairros.php       # Busca de bairros e comunidades
│   ├── camada.php              # Fornecimento de camadas vetoriais em GeoJSON
│   ├── foto_imovel.php         # Normalizador e entrega de fotos de fachada
│   ├── identificar.php         # Endpoint de Identify espacial e geração de dados do BCI
│   ├── crud_rede_agua.php      # Edição e cadastro alfanumérico de redes
│   └── filtros_redes.php       # Consultas filtradas por atributos
├── config/                     # Conexão com banco de dados e configurações
├── css/                        # Estilos da aplicação (layout, modais, tema escuro e impressão A4)
├── docker/                     # Scripts de inicialização e dump do banco de dados
├── img/                        # Imagens, logos municipais e fotos de fachada
│   ├── fotos_fachada/          # Diretório padrão de fotos das fachadas dos imóveis
│   └── logo-dm.png             # Brasão oficial da Prefeitura
├── js/                         # Lógica do mapa e ferramentas
│   ├── basemaps.js             # Provedores de mapas base
│   └── map.js                  # Lógica do WebGIS, BCI, medições, buffer e interações
├── docker-compose.yml          # Orquestração dos containers
├── Dockerfile                  # Configuração da imagem PHP 8.2 + Apache
├── index.php                   # Interface principal do GeoPortal
└── README.md                   # Documentação do projeto
```

---

## 🚀 Como Executar o Projeto

### Pré-requisitos
- [Docker](https://www.docker.com/) e [Docker Compose](https://docs.docker.com/compose/) instalados.

### 1. Clonar o Repositório
```bash
git clone <url-do-repositorio>
cd MVP-BCI-DOCKER
```

### 2. Iniciar os Containers
Execute o comando para construir e inicializar todos os serviços:
```bash
docker compose up -d --build
```

---

## 🌐 URLs e Acessos Padrão

Após a inicialização dos containers, os serviços estarão disponíveis nas seguintes portas:

| Serviço | URL | Credenciais Padrão |
| :--- | :--- | :--- |
| **GeoPortal (Aplicação Web)** | [http://localhost:8085](http://localhost:8085) | Usuário autenticado na base |
| **pgAdmin 4 (Gestão DB)** | [http://localhost:5055](http://localhost:5055) | `admin@bci.com` / `admin123` |
| **GeoServer (Serviços OGC)** | [http://localhost:8605/geoserver](http://localhost:8605/geoserver) | `admin` / `geoserver` |
| **PostgreSQL / PostGIS** | `localhost:5435` | `postgres` / `postgres` (DB: `bci_cadastro`) |

---

## 📖 Comandos Úteis do Docker

```bash
# Visualizar logs da aplicação em tempo real
docker compose logs -f app

# Visualizar status de todos os containers
docker compose ps

# Reiniciar todos os serviços
docker compose restart

# Parar os serviços mantendo os volumes de dados
docker compose down

# Parar os serviços removendo os volumes (Atenção: limpa o banco de dados)
docker compose down -v
```

---

## 📄 Licença e Uso
Desenvolvido para modernização e gestão cadastral técnica municipal. Todos os direitos reservados.
