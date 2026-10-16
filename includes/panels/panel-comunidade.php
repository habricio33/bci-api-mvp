<!-- Painel Flutuante de Seleção Espacial por Comunidade -->
<div id="selecao-espacial-panel" class="buffer-floating-panel selecao-espacial-floating-panel" style="display: none;">
    <div class="buffer-panel-header selecao-espacial-panel-header">
        <div class="buffer-header-title" style="color: #38bdf8;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                <polyline points="2 17 12 22 22 17"></polyline>
                <polyline points="2 12 12 17 22 12"></polyline>
                <circle cx="12" cy="12" r="2.5" fill="currentColor"></circle>
            </svg>
            <span>Seleção Espacial: Comunidades</span>
        </div>
        <button id="btn-close-selecao-espacial-panel" class="buffer-btn-close" title="Fechar e Desativar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <div class="buffer-panel-body">
        <!-- Modo de Seleção / Dropdown e Botão de Clique no Mapa -->
        <div class="selecao-espacial-control-row">
            <div class="selecao-espacial-select-wrapper">
                <label class="buffer-label">Escolha a Comunidade:</label>
                <select id="selecao-comunidade-select" class="divergencia-select">
                    <option value="" disabled selected>Carregando comunidades...</option>
                </select>
            </div>
            <button type="button" id="btn-selecao-map-click" class="btn-action-sm btn-highlight" title="Clique no mapa para escolher a comunidade visualmente">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M12 2a8 8 0 0 0-8 8c0 5.25 8 12 8 12s8-6.75 8-12a8 8 0 0 0-8-8z"></path>
                    <circle cx="12" cy="10" r="3"></circle>
                </svg>
                <span id="lbl-selecao-map-click">Clicar no Mapa</span>
            </button>
        </div>

        <!-- Badge de Instrução de Clique no Mapa (Quando ativado) -->
        <div class="buffer-instruction-badge" id="selecao-map-instruction" style="display: none;">
            <span class="pulse-dot"></span>
            <span>Clique em qualquer comunidade no mapa para selecioná-la</span>
        </div>

        <!-- Mensagem Inicial / Aguardando Seleção -->
        <div id="selecao-empty-placeholder" class="selecao-empty-state">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                <polyline points="2 17 12 22 22 17"></polyline>
                <polyline points="2 12 12 17 22 12"></polyline>
            </svg>
            <p>Selecione uma comunidade no menu acima ou clique no mapa para quantificar seus lotes e edificações.</p>
        </div>

        <!-- Card de Comunidade Selecionada -->
        <div id="selecao-comunidade-info-card" class="selecao-comunidade-card" style="display: none;">
            <div class="selecao-comunidade-header">
                <div class="comunidade-title-group">
                    <span class="comunidade-badge-tag">Comunidade</span>
                    <h4 id="selecao-comunidade-nome" class="comunidade-name-heading">--</h4>
                </div>
                <span id="selecao-comunidade-area-badge" class="comunidade-area-pill">-- ha</span>
            </div>

            <!-- KPIs Resumo -->
            <div class="divergencia-kpis selecao-kpis">
                <div class="divergencia-kpi-card kpi-card-info active" id="kpi-card-lotes-comunidade" onclick="switchSelecaoTab('lotes')">
                    <span class="divergencia-kpi-title">Total de Lotes</span>
                    <span class="divergencia-kpi-val" id="kpi-comunidade-total-lotes">--</span>
                    <span class="divergencia-kpi-sub" id="kpi-comunidade-sub-lotes">-- cadastrados</span>
                </div>
                <div class="divergencia-kpi-card kpi-card-warning" id="kpi-card-edif-comunidade" onclick="switchSelecaoTab('edificacoes')">
                    <span class="divergencia-kpi-title">Edificações</span>
                    <span class="divergencia-kpi-val" id="kpi-comunidade-total-edif">--</span>
                    <span class="divergencia-kpi-sub" id="kpi-comunidade-sub-edif">-- com água</span>
                </div>
                <div class="divergencia-kpi-card kpi-card-success" id="kpi-card-area-comunidade" onclick="switchSelecaoTab('resumo')">
                    <span class="divergencia-kpi-title">Área Total</span>
                    <span class="divergencia-kpi-val" id="kpi-comunidade-area-m2">--</span>
                    <span class="divergencia-kpi-sub" id="kpi-comunidade-sub-area">-- km²</span>
                </div>
                <div class="divergencia-kpi-card kpi-card-purple" id="kpi-card-dens-comunidade" onclick="switchSelecaoTab('resumo')">
                    <span class="divergencia-kpi-title">Densidade</span>
                    <span class="divergencia-kpi-val" id="kpi-comunidade-densidade">--</span>
                    <span class="divergencia-kpi-sub" id="kpi-comunidade-sub-dens">edif / ha</span>
                </div>
            </div>

            <!-- Tabs de Navegação -->
            <div class="selecao-tab-nav">
                <button type="button" class="selecao-tab-btn active" id="tab-btn-selecao-resumo" onclick="switchSelecaoTab('resumo')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
                    <span>Resumo Geral</span>
                </button>
                <button type="button" class="selecao-tab-btn" id="tab-btn-selecao-lotes" onclick="switchSelecaoTab('lotes')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3h18v18H3z"/></svg>
                    <span>Lotes (<span id="lbl-count-tab-lotes">0</span>)</span>
                </button>
                <button type="button" class="selecao-tab-btn" id="tab-btn-selecao-edificacoes" onclick="switchSelecaoTab('edificacoes')">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-3"/></svg>
                    <span>Edificações (<span id="lbl-count-tab-edif">0</span>)</span>
                </button>
            </div>

            <!-- Tab 1: Resumo Analítico -->
            <div id="selecao-tab-resumo" class="selecao-tab-content active">
                <div class="selecao-summary-sections">
                    <div class="selecao-summary-box">
                        <div class="selecao-summary-title">🏗️ Tipos de Construção</div>
                        <div id="selecao-tipos-construcao-list" class="selecao-stat-list">
                            <!-- Preenchido dinamicamente -->
                        </div>
                    </div>
                    <div class="selecao-summary-box">
                        <div class="selecao-summary-title">🛡️ Estado de Conservação</div>
                        <div id="selecao-estado-conservacao-list" class="selecao-stat-list">
                            <!-- Preenchido dinamicamente -->
                        </div>
                    </div>
                    <div class="selecao-summary-box">
                        <div class="selecao-summary-title">💧 Infraestrutura Básica (Edificações)</div>
                        <div class="selecao-infra-grid">
                            <div class="selecao-infra-item">
                                <span class="infra-label">Rede de Água:</span>
                                <span id="selecao-infra-agua-val" class="infra-val">--</span>
                            </div>
                            <div class="selecao-infra-item">
                                <span class="infra-label">Rede de Esgoto:</span>
                                <span id="selecao-infra-esgoto-val" class="infra-val">--</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab 2: Lotes -->
            <div id="selecao-tab-lotes" class="selecao-tab-content" style="display:none;">
                <div class="divergencia-search-wrapper">
                    <input type="text" id="selecao-lotes-search-input" class="divergencia-search-input"
                        placeholder="Buscar inscrição, contribuinte, rua..." autocomplete="off">
                    <button type="button" id="btn-selecao-lotes-search-clear" class="btn-clear-table-search" style="display:none;" title="Limpar busca" onclick="clearSelecaoLotesSearch()">✕</button>
                </div>
                <div class="divergencia-table-wrapper">
                    <table class="divergencia-table" id="selecao-lotes-table">
                        <thead>
                            <tr>
                                <th>Inscrição</th>
                                <th>Contribuinte</th>
                                <th>Logradouro</th>
                                <th>Área GIS</th>
                                <th>Cadastro</th>
                                <th style="text-align:center;">Ver</th>
                            </tr>
                        </thead>
                        <tbody id="selecao-lotes-table-tbody">
                            <!-- Inserido dinamicamente -->
                        </tbody>
                    </table>
                </div>
                <div class="divergencia-pagination">
                    <button type="button" id="btn-selecao-lotes-prev" class="btn-page-sm" onclick="changeSelecaoLotesPage(-1)" disabled>&larr; Anterior</button>
                    <span id="selecao-lotes-page-info" class="divergencia-page-info">Pág. 1 de 1 (0)</span>
                    <button type="button" id="btn-selecao-lotes-next" class="btn-page-sm" onclick="changeSelecaoLotesPage(1)" disabled>Próxima &rarr;</button>
                </div>
            </div>

            <!-- Tab 3: Edificações -->
            <div id="selecao-tab-edificacoes" class="selecao-tab-content" style="display:none;">
                <div class="divergencia-search-wrapper">
                    <input type="text" id="selecao-edif-search-input" class="divergencia-search-input"
                        placeholder="Buscar inscrição, contribuinte, tipo..." autocomplete="off">
                    <button type="button" id="btn-selecao-edif-search-clear" class="btn-clear-table-search" style="display:none;" title="Limpar busca" onclick="clearSelecaoEdifSearch()">✕</button>
                </div>
                <div class="divergencia-table-wrapper">
                    <table class="divergencia-table" id="selecao-edif-table">
                        <thead>
                            <tr>
                                <th>Inscrição</th>
                                <th>Contribuinte</th>
                                <th>Tipo</th>
                                <th>Conservação</th>
                                <th>Água / Esgoto</th>
                                <th style="text-align:center;">Ver</th>
                            </tr>
                        </thead>
                        <tbody id="selecao-edif-table-tbody">
                            <!-- Inserido dinamicamente -->
                        </tbody>
                    </table>
                </div>
                <div class="divergencia-pagination">
                    <button type="button" id="btn-selecao-edif-prev" class="btn-page-sm" onclick="changeSelecaoEdifPage(-1)" disabled>&larr; Anterior</button>
                    <span id="selecao-edif-page-info" class="divergencia-page-info">Pág. 1 de 1 (0)</span>
                    <button type="button" id="btn-selecao-edif-next" class="btn-page-sm" onclick="changeSelecaoEdifPage(1)" disabled>Próxima &rarr;</button>
                </div>
            </div>
        </div>

        <!-- Ações Inferiores (Destaques no Mapa, Zoom e Exportações) -->
        <div class="divergencia-actions selecao-actions" id="selecao-actions-bar" style="display:none;">
            <button type="button" id="btn-toggle-selecao-layer" class="btn-action-sm btn-highlight" onclick="toggleSelecaoDestaquesLayer()" title="Colorir lotes e edificações desta comunidade no mapa">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                    <polyline points="2 17 12 22 22 17"></polyline>
                    <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
                <span id="lbl-toggle-selecao-layer">Pintar Feições</span>
            </button>
            <button type="button" class="btn-action-sm" onclick="zoomToSelectedCommunity()" title="Aproximar o mapa nesta comunidade">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"></circle>
                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                </svg>
                <span>Aproximar</span>
            </button>
            <div class="export-dropdown-wrapper" style="position: relative;">
                <button type="button" id="btn-export-selecao-dropdown" class="btn-action-sm" onclick="toggleExportSelecaoMenu(event)" title="Exportar dados para planilha Excel / CSV">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    <span>Exportar CSV ▾</span>
                </button>
                <div id="selecao-export-menu" class="selecao-export-menu" style="display:none;">
                    <a href="javascript:void(0)" onclick="exportSelecaoCSV('lotes')">Exportar Lotes (CSV)</a>
                    <a href="javascript:void(0)" onclick="exportSelecaoCSV('edificacoes')">Exportar Edificações (CSV)</a>
                </div>
            </div>
        </div>
    </div>
</div>
