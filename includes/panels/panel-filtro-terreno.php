<!-- Painel Flutuante do Filtro de Terrenos (Área & Ocupação) -->
<div id="filtro-terreno-panel" class="buffer-floating-panel filtro-terreno-floating-panel" style="display: none;">
    <div class="buffer-panel-header filtro-terreno-panel-header">
        <div class="buffer-header-title" style="color: #10b981;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
            <span>Filtro de Terrenos: Área & Ocupação</span>
        </div>
        <button id="btn-close-filtro-terreno-panel" class="buffer-btn-close" title="Fechar e Desativar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <div class="buffer-panel-body">
        <!-- Formulário de Filtros -->
        <form id="filtro-terreno-form" onsubmit="event.preventDefault(); aplicarFiltroTerrenos();">
            <div class="filtro-terreno-form-grid">
                <!-- Campo 1: Área do Terreno (AREA_DO_TERRENO13065) -->
                <div class="filtro-terreno-field-group">
                    <label class="buffer-label">Área do Terreno (m²):</label>
                    <div class="filtro-area-inputs-wrapper">
                        <select id="filtro-terreno-operador" class="divergencia-select filtro-operador-select" onchange="handleFiltroOperadorChange()">
                            <option value=">">&gt; Maior que</option>
                            <option value=">=" selected>&gt;= Maior ou igual</option>
                            <option value="<">&lt; Menor que</option>
                            <option value="<=">&lt;= Menor ou igual</option>
                            <option value="=">= Igual a</option>
                            <option value="BETWEEN">↔ Entre (Faixa)</option>
                        </select>
                        <input type="number" id="filtro-terreno-valor" class="divergencia-search-input filtro-num-input"
                            placeholder="Ex: 300" min="0" step="any" required value="300">
                        <input type="number" id="filtro-terreno-valor-max" class="divergencia-search-input filtro-num-input"
                            placeholder="Máx (m²)" min="0" step="any" style="display: none;">
                    </div>
                </div>

                <!-- Campo 2: Ocupação (OCUPACAO9025) -->
                <div class="filtro-terreno-field-group">
                    <label class="buffer-label">Ocupação do Imóvel:</label>
                    <select id="filtro-terreno-ocupacao" class="divergencia-select">
                        <option value="ALL">Todas as Ocupações</option>
                        <option value="CONSTRUIDO" selected>CONSTRUIDO</option>
                        <option value="NAO CONSTRUIDO">NAO CONSTRUIDO</option>
                        <option value="CONSTRUCAO EM ANDAMENTO">CONSTRUCAO EM ANDAMENTO</option>
                        <option value="CONSTRUCAO PARALIZADA">CONSTRUCAO PARALIZADA</option>
                        <option value="RUINAS">RUINAS</option>
                        <option value="EM DEMOLICAO">EM DEMOLICAO</option>
                        <option value="NAO_INFORMADO">Não Informado (Em branco)</option>
                    </select>
                </div>

                <!-- Campo 3: Bairro / Região (Opcional) -->
                <div class="filtro-terreno-field-group">
                    <label class="buffer-label">Bairro / Região (Opcional):</label>
                    <select id="filtro-terreno-bairro" class="divergencia-select">
                        <option value="ALL">Todos os Bairros / Comunidades</option>
                    </select>
                </div>
            </div>

            <!-- Botões de Ação do Filtro -->
            <div class="filtro-terreno-btn-row">
                <button type="submit" id="btn-filtro-terreno-aplicar" class="btn-action-sm btn-highlight-emerald">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <span>Filtrar e Destacar</span>
                </button>
                <button type="button" id="btn-filtro-terreno-limpar" class="btn-action-sm" onclick="limparFiltroTerrenos()">
                    <span>Limpar</span>
                </button>
            </div>
        </form>

        <!-- Mensagem Inicial / Aguardando Filtro -->
        <div id="filtro-terreno-empty-placeholder" class="selecao-empty-state">
            <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
            <p>Defina a área do terreno e a ocupação desejada acima e clique em <strong>Filtrar e Destacar</strong> para consultar e mapear os lotes.</p>
        </div>

        <!-- Card de Resultados do Filtro -->
        <div id="filtro-terreno-results-card" class="filtro-terreno-results" style="display: none;">
            <!-- KPIs Resumo -->
            <div class="divergencia-kpis filtro-kpis">
                <div class="divergencia-kpi-card kpi-card-emerald">
                    <span class="divergencia-kpi-title">Lotes Encontrados</span>
                    <span class="divergencia-kpi-val" id="kpi-terreno-total">0</span>
                    <span class="divergencia-kpi-sub" id="kpi-terreno-sub-cad">0 cadastrados</span>
                </div>
                <div class="divergencia-kpi-card kpi-card-info">
                    <span class="divergencia-kpi-title">Área Média</span>
                    <span class="divergencia-kpi-val" id="kpi-terreno-area-media">0 m²</span>
                    <span class="divergencia-kpi-sub" id="kpi-terreno-sub-media">área tributária</span>
                </div>
                <div class="divergencia-kpi-card kpi-card-purple">
                    <span class="divergencia-kpi-title">Área Total</span>
                    <span class="divergencia-kpi-val" id="kpi-terreno-area-total">0 m²</span>
                    <span class="divergencia-kpi-sub" id="kpi-terreno-sub-ha">0 ha</span>
                </div>
                <div class="divergencia-kpi-card kpi-card-warning">
                    <span class="divergencia-kpi-title">Ocupação Principal</span>
                    <span class="divergencia-kpi-val" id="kpi-terreno-ocup-top" style="font-size: 0.82rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">--</span>
                    <span class="divergencia-kpi-sub" id="kpi-terreno-sub-ocup">100% dos resultados</span>
                </div>
            </div>

            <!-- Tabela de Lotes Filtrados -->
            <div class="divergencia-search-wrapper" style="margin-top: 4px;">
                <input type="text" id="filtro-terreno-table-search" class="divergencia-search-input"
                    placeholder="Buscar na lista (inscrição, contribuinte, rua...)" autocomplete="off">
                <button type="button" id="btn-filtro-terreno-search-clear" class="btn-clear-table-search" style="display:none;" title="Limpar busca" onclick="clearFiltroTerrenoSearch()">✕</button>
            </div>

            <div class="divergencia-table-wrapper filtro-terreno-table-wrapper">
                <table class="divergencia-table" id="filtro-terreno-table">
                    <thead>
                        <tr>
                            <th>Inscrição</th>
                            <th>Contribuinte</th>
                            <th>Logradouro</th>
                            <th>Área Terreno</th>
                            <th>Ocupação</th>
                            <th style="text-align:center;">Ver</th>
                        </tr>
                    </thead>
                    <tbody id="filtro-terreno-table-tbody">
                        <!-- Preenchido dinamicamente -->
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <div class="divergencia-pagination">
                <button type="button" id="btn-filtro-terreno-prev" class="btn-page-sm" onclick="changeFiltroTerrenoPage(-1)" disabled>&larr; Anterior</button>
                <span id="filtro-terreno-page-info" class="divergencia-page-info">Pág. 1 de 1 (0)</span>
                <button type="button" id="btn-filtro-terreno-next" class="btn-page-sm" onclick="changeFiltroTerrenoPage(1)" disabled>Próxima &rarr;</button>
            </div>

            <!-- Ações Inferiores (Pintar no Mapa, Aproximar, Exportar CSV) -->
            <div class="divergencia-actions selecao-actions" style="margin-top: 6px;">
                <button type="button" id="btn-toggle-filtro-terreno-layer" class="btn-action-sm btn-highlight-emerald" onclick="toggleFiltroTerrenoHighlightLayer()" title="Colorir feições filtradas no mapa">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                        <polyline points="2 17 12 22 22 17"></polyline>
                        <polyline points="2 12 12 17 22 12"></polyline>
                    </svg>
                    <span id="lbl-toggle-filtro-terreno-layer">Pintar Feições</span>
                </button>
                <button type="button" class="btn-action-sm" onclick="zoomToFiltroTerrenos()" title="Aproximar o mapa nos terrenos filtrados">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"></circle>
                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                    </svg>
                    <span>Aproximar</span>
                </button>
                <button type="button" id="btn-export-filtro-terreno" class="btn-action-sm" onclick="exportFiltroTerrenosCSV()" title="Exportar dados filtrados para CSV">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Exportar CSV</span>
                </button>
            </div>
        </div>
    </div>
</div>
