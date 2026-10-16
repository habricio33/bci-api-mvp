# Guia de Execução com Docker - MVP BCI (Cadastro Imobiliário Urbano)

Este guia contém todas as instruções para executar, gerenciar e dar manutenção no ambiente Docker isolado do **MVP BCI (Boletim de Cadastro Imobiliário / Cadastro Urbano)**.

---

## 🚀 1. Estrutura dos Serviços

A infraestrutura é orquestrada pelo `docker-compose.yml` e conta com 4 serviços integrados em uma rede interna isolada (`bci_net`), com portas exclusivas configuradas para não conflitar com outros containers locais:

| Serviço | Descrição | Imagem / Base | Porta Host | URL / Host de Acesso | Status |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **`bci_app`** | Aplicação PHP 8.2 + Apache + Drivers PostGIS | `Dockerfile` customizado | `8085` | [http://localhost:8085](http://localhost:8085) | 🟢 Online |
| **`bci_db`** | Banco de Dados PostgreSQL 16 com PostGIS 3.4 | `postgis/postgis:16-3.4` | `5435` | `localhost:5435` | 🟢 Healthy |
| **`bci_pgadmin`** | Interface Web para Gestão do PostgreSQL | `dpage/pgadmin4:latest` | `5055` | [http://localhost:5055](http://localhost:5055) | 🟢 Online |
| **`bci_geoserver`** | Servidor OGC (WFS-T / WMS para QGIS) | `kartoza/geoserver:2.25.0` | `8605` | [http://localhost:8605/geoserver](http://localhost:8605/geoserver) | 🟢 Online |

---

## 🛠️ 2. Como Iniciar a Aplicação

### 2.1. Arquivo de Configuração `.env`
O arquivo `.env` na raiz do projeto já está configurado com as portas e credenciais isoladas:

```env
# Configurações do Banco de Dados PostgreSQL/PostGIS (BCI)
DB_HOST=db
DB_PORT=5432
DB_DATABASE=bci_cadastro
DB_USERNAME=postgres
DB_PASSWORD=postgres

# Porta externa mapeada no host para conexão externa (DBeaver / QGIS)
DB_EXTERNAL_PORT=5435

# Portas dos Serviços Web
APP_PORT=8085
PGADMIN_PORT=5055
GEOSERVER_PORT=8605

# Credenciais do pgAdmin 4
PGADMIN_EMAIL=admin@bci.com
PGADMIN_PASSWORD=admin123

# Credenciais do GeoServer
GEOSERVER_ADMIN_USER=admin
GEOSERVER_ADMIN_PASSWORD=geoserver
```

### 2.2. Comandos para Subir os Containers
Na raiz do projeto (`c:\wamp64\www\MVP-BCI-DOCKER`), execute:

```bash
# Iniciar todos os containers em segundo plano (construindo a imagem se necessário)
docker compose up -d --build
```

Acesse o sistema no navegador:
👉 **[http://localhost:8085](http://localhost:8085)**

# URL: http://localhost:8085 (ou http://localhost:8085/login.php)
# Usuário / Login: admin
# Senha: admin
Perfil: administrador

# Tabela usada: usuario

---

## 🗄️ 3. Banco de Dados e Restauração Automática (PostGIS)

### 3.1. Inicialização Automática no Primeiro Start
O banco de dados foi inicializado automaticamente através do script:
* **Pasta de Inicialização:** `docker/initdb/`
* **Script de Execução:** `docker/initdb/01_restore.sh`
* **Arquivo de Dump:** `docker/initdb/bkp_sig_novo_dm.sql_dump` (143 MB)

O script:
1. Habilita as extensões espaciais `postgis` e `postgis_topology`.
2. Restaura com segurança todas as 35 tabelas e 13.992 registros de imóveis tolerando incompatibilidades de tipos legados.

### 3.2. Estrutura Principal das Tabelas BCI no Banco (`bci_cadastro`)

| Tabela | Descrição / Registros |
| :--- | :--- |
| **`GP_IMOVEL`** | 13.992 imóveis cadastrados com dados de proprietário, logradouro, loteamento, quadra, lote, características construtivas, infraestrutura urbana, áreas e coordenadas. |
| **`GP_CAMPOS_BCI`** | Configurações e definições dinâmicas dos campos do formulário BCI. |
| **`GP_CONFIGURACOES`** | Parâmetros gerais do sistema de cadastro urbano. |
| **`GP_CONFIG_CIB`** | Configurações de integração CIB (Cadastro Imobiliário Brasileiro). |
| **`GP_DISTRITOS`** | Cadastro de distritos municipais. |
| **`GP_IMAGEM` / `GP_IMAGEM_ITENS`** | Imagens e fotos associadas ao cadastro e lotes. |
| **`GP_USUARIO`** | Cadastro de usuários do sistema BCI. |
| **`GP_CAMADA`** | Configurações de camadas geográficas do mapa. |

---

## 🖥️ 4. Conexões Externas (DBeaver / QGIS / pgAdmin)

### 4.1. Conectar via DBeaver / DBeaver / QGIS Desktop
Para conectar qualquer software externo diretamente ao PostgreSQL do container BCI:
* **Host:** `localhost` (ou `127.0.0.1`)
* **Porta:** `5435` *(atenção: porta externa mapeada no `.env`)*
* **Database:** `bci_cadastro`
* **Username:** `postgres`
* **Password:** `postgres`

### 4.2. Conectar via pgAdmin 4 Web
1. Acesse **[http://localhost:5055](http://localhost:5055)** no navegador.
2. Faça login com as credenciais do `.env`:
   - **E-mail:** `admin@bci.com`
   - **Senha:** `admin123`
3. Clique em **Add New Server**:
   - **Name:** `BCI Cadastro DB`
   - Na aba **Connection**:
     - **Host name/address:** `db` *(atenção: comunicação interna na rede Docker usa `db`)*
     - **Port:** `5432`
     - **Maintenance database:** `bci_cadastro`
     - **Username:** `postgres`
     - **Password:** `postgres`

### 4.3 Crie o schema geo e execute o search_path para o usuário postgres no banco de dados bci_cadastro

-- 1. Cria o schema para os dados espaciais
CREATE SCHEMA IF NOT EXISTS geo;

-- 2. Configura o search_path padrão do banco para buscar tanto no public quanto no geo
ALTER DATABASE bci_cadastro SET search_path = public, geo;

-- 3. Configura o search_path para o usuário postgres
ALTER USER postgres SET search_path = public, geo;


---

## 📋 5. Comandos Úteis do Dia a Dia

### Parar os containers (mantendo os dados)
```bash
docker compose stop
```

### Iniciar os containers já existentes
```bash
docker compose start
```

### Reiniciar os containers
```bash
docker compose restart
```

### Parar e remover containers e redes (mantendo os dados do volume)
```bash
docker compose down
```

> ⚠️ **Nota:** Os dados do banco **NÃO** são perdidos com `docker compose down`, pois ficam persistidos no volume `bci_db_data`.

### Visualizar logs em tempo real
```bash
# Logs do Apache/PHP
docker compose logs -f app

# Logs do Banco PostGIS
docker compose logs -f db
```

### Acessar o terminal interativo do container da aplicação
```bash
docker compose exec app bash
```

### Executar comandos psql direto no banco BCI
```bash
docker compose exec db psql -U postgres -d bci_cadastro
```

### Fazer Backup (Exportar Novo Dump) do Banco BCI
```bash
# Gerar dump comprimido customizado
docker compose exec db pg_dump -U postgres -F c bci_cadastro > backup_bci_$(date +%Y%m%d).dump
```

### Recriar todo o ambiente do zero (apagando os dados do volume e reexecutando o dump inicial)
```bash
docker compose down -v
docker compose up -d --build
```