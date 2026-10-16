# Documentação Técnica: Integração da Camada de Lotes com GP_IMOVEL

Este documento descreve detalhadamente o funcionamento da integração entre a camada espacial de lotes (`geo_lotes_dm`) e a base de dados cadastral e tributária (`public."GP_IMOVEL"`), cobrindo as regras de negócio, modelagem de dados, endpoints do backend (PHP/PostGIS) e módulos do frontend (JavaScript/Leaflet).

---

## 1. Visão Geral e Regra de Ligação (Chave de Junção)

A arquitetura do sistema separa os dados espaciais (geometrias vetoriais) dos dados tabulares/tributários (Boletim de Cadastro Imobiliário - BCI):

* **Tabela Espacial:** `geo_lotes_dm` (armazenada no PostGIS). Possui as geometrias dos lotes e os campos identificadores `inscimob` ou `inscimob2`.
* **Tabela Tributária/Cadastral:** `public."GP_IMOVEL"` (armazena os dados cadastrais alfanuméricos do BCI, proprietários, endereços, características e áreas). O campo chave é `"INSC_MUNIC"` (Inscrição Municipal).
* **Relação 1 para N (Lote ➔ Unidades):**
  Como um único lote físico pode conter múltiplas unidades imobiliárias (ex.: condomínios verticais ou horizontais, subdivisões cadastrais), a aplicação extrai os **11 primeiros dígitos** da inscrição do lote (`lotePrefix`) e busca todas as unidades imobiliárias no `GP_IMOVEL` via prefixo:
  ```sql
  WHERE "INSC_MUNIC" LIKE :prefix || '%'
  ```
  Para consultas tabulares e filtros diretos (relação 1:1 com a unidade principal ou quando inscrita por completo), a junção direta é realizada por:
  ```sql
  LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC"
  ```

---

## 2. Backend (PHP / SQL / PostGIS)

### A. Identificação por Clique no Mapa e Consulta do BCI
**Arquivo:** `api/identificar.php`

É o principal endpoint responsável por receber as coordenadas geográficas do clique do usuário, realizar a busca espacial e correlacionar com os registros alfanuméricos:

1. **Localização Espacial do Lote (`ST_DWithin`):**
   Executa a busca espacial com tolerância de proximidade de 2 metros no ponto clicado (`lng`, `lat`):
   ```sql
   SELECT id, inscimob, inscimob2, 'geo_lotes_dm' as camada,
          ST_AsGeoJSON(ST_Transform(ST_Force2D(geom), 4326)) as geojson,
          ST_Distance(
              ST_Transform(ST_Force2D(geom), 4326)::geography, 
              ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography
          ) as dist
   FROM geo_lotes_dm
   WHERE ST_DWithin(
       ST_Transform(ST_Force2D(geom), 4326)::geography, 
       ST_SetSRID(ST_Point(:lng, :lat), 4326)::geography, 
       2
   )
   ORDER BY dist ASC 
   LIMIT 1;
   ```

2. **Extração da Chave Cadastral:**
   O PHP normaliza o identificador do lote para obter a raiz municipal de 11 caracteres:
   ```php
   if ($lote && !empty($lote['inscimob'])) {
       $lotePrefix = trim($lote['inscimob']);
   } elseif ($lote && !empty($lote['inscimob2'])) {
       $raw = trim($lote['inscimob2']);
       $lotePrefix = (strlen($raw) >= 11) ? substr($raw, 0, 11) : $raw;
   }
   ```

3. **Consulta das Unidades Vinculadas no `GP_IMOVEL`:**
   ```sql
   SELECT "INSC_MUNIC", "NOMEDOCONTRIBUINTE" 
   FROM public."GP_IMOVEL"
   WHERE "INSC_MUNIC" LIKE :prefix
   ORDER BY "INSC_MUNIC" ASC 
   LIMIT 100;
   ```

4. **Consulta Completa do BCI / Espelho Cadastral (`action=bci`):**
   Retorna todos os mais de 50 atributos cadastrais de `GP_IMOVEL` (proprietário, endereço, CPF/CNPJ, tipo de construção, fachada, áreas, testadas, serviços urbanos) e consulta a foto da fachada correspondente na tabela `public."GP_IMOVEL_FOTOS"`:
   ```sql
   SELECT * FROM public."GP_IMOVEL" WHERE "INSC_MUNIC" = :insc_munic LIMIT 1;
   SELECT * FROM public."GP_IMOVEL_FOTOS" WHERE "INSC_MUNIC" = :insc_munic LIMIT 1;
   ```

---

### B. Tabela de Atributos e Buffer de Lotes
**Arquivo:** `api/tabela_lotes.php`

Cruza a geometria do lote com os dados cadastrais para montar a listagem paginada, filtros rápidos e a exportação em lote (CSV):
```sql
SELECT 
    l.id, 
    l.inscimob2, 
    g."NOMEDOCONTRIBUINTE", 
    g."NOMELOGRADOURO",
    g."NUMEROLOGRADOURO",
    g."NOMEBAIRRO",
    g."AREA_DO_TERRENO13065",
    g."AREA_DA_UNIDADE13066"
FROM geo.geo_lotes_dm l
LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC";
```

---

### C. Filtros Temáticos de Lotes e Terrenos
**Arquivos:** 
* `api/filtros_lotes.php`
* `api/filtro_terrenos.php`

Gera agregações e filtros temáticos dinâmicos (situação do terreno, tipo de ocupação, padrão construtivo):
```sql
FROM geo.geo_lotes_dm l
LEFT JOIN public."GP_IMOVEL" g ON (l.inscimob2 = g."INSC_MUNIC" OR l.inscimob = g."INSC_MUNIC");
```

---

### D. Análise de Divergência de Áreas
**Arquivo:** `api/divergencia_areas.php`

Compara a área cartográfica calculada pelo PostGIS (`ST_Area`) com a área fiscal declarada no cadastro imobiliário:
```sql
SELECT 
    l.id,
    l.inscimob2,
    ST_Area(l.geom) as area_geo,
    g."AREA_DO_TERRENO13065" as area_fiscal,
    ABS(ST_Area(l.geom) - NULLIF(g."AREA_DO_TERRENO13065", '')::numeric) as diferenca
FROM geo.geo_lotes_dm l
LEFT JOIN public."GP_IMOVEL" g ON l.inscimob2 = g."INSC_MUNIC";
```

---

## 3. Frontend (JavaScript / Leaflet)

### A. Módulo de Identificação e BCI
**Arquivo:** `js/modules/module-identify-bci.js`

* **`executeIdentify(latlng)`:**
  * Invocada ao clicar no mapa quando o botão de informação (`#btn-identify`) está ativo.
  * Dispara `fetch('api/identificar.php?lat=' + lat + '&lng=' + lng)`.
  * Cria uma camada de destaque `L.geoJSON` com o polígono do lote retornado (`#0284c7`, azul ciano) e aplica zoom suave (`map.fitBounds`).
  * Se o lote possuir múltiplas unidades no `GP_IMOVEL`, exibe a listagem de seleção (`showIdentifyUnitsList()`).
  * Se possuir apenas 1 unidade, renderiza diretamente os detalhes cadastrais simplificados (`renderUnitDetails()`).
* **`openBciFullModal(inscMunic)`:**
  * Requisita `api/identificar.php?action=bci&insc_munic=` e abre o modal completo do BCI com abas de dados gerais, características construtivas, foto da fachada e croqui com mapa de localização.

---

### B. Módulo de Mapa
**Arquivo:** `js/modules/module-map.js`

* Inicializa o mapa Leaflet e renderiza a camada WMS dos lotes servida pelo GeoServer (`bci:geo_lotes_dm`).
* Registra o listener de clique no mapa para disparar a identificação:
  ```javascript
  map.on('click', function (e) {
      if (identifyModeActive) {
          executeIdentify(e.latlng);
      }
  });
  ```

---

### C. Outros Módulos Relacionados
* **`js/modules/module-table-buffer.js`:** Gerencia a tabela inferior e cálculos de raio (buffer) em torno do lote selecionado.
* **`js/modules/module-filters-lotes.js`:** Interface de filtros por status, ocupação e dimensões dos lotes.
* **`js/modules/module-search.js`:** Permite pesquisar por contribuinte, CPF ou inscrição no `GP_IMOVEL` e localizar o polígono do lote correspondente no mapa.

---

## 4. Diagrama do Fluxo de Execução

```text
[Usuário clica no mapa com ferramenta Identify ativa]
                        │
                        ▼
           [js/modules/module-map.js]
             map.on('click', e.latlng)
                        │
                        ▼
      [js/modules/module-identify-bci.js]
            executeIdentify(latlng)
                        │
                        ▼  HTTP GET
               [api/identificar.php]
                        │
       ┌────────────────┴────────────────┐
       ▼                                 ▼
1. PostGIS (ST_DWithin)          2. public."GP_IMOVEL"
   Busca lote em geo_lotes_dm       Busca unidades pelo
   pelas coordenadas (lat, lng)     prefixo da inscrição
       │                                 │
       └────────────────┬────────────────┘
                        ▼
          JSON: { geojson, units, ... }
                        │
                        ▼
      [js/modules/module-identify-bci.js]
       - Desenha polígono de destaque no mapa (L.geoJSON)
       - Dá zoom suave no lote (map.fitBounds)
       - Abre modal do BCI / unidades imobiliárias
```
