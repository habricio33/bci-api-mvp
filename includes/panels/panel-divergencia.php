<!-- Painel Flutuante de Divergência de Áreas (GIS vs Tributário > 10%) -->
<div id="divergencia-panel" class="buffer-floating-panel divergencia-floating-panel" style="display: none;">
    <div class="buffer-panel-header divergencia-panel-header">
        <div class="buffer-header-title" style="color: #f43f5e;">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M16 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"></path>
                <path d="M2 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"></path>
                <path d="M7 21h10"></path>
                <path d="M12 3v18"></path>
                <path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"></path>
            </svg>
            <span>Divergência de Áreas (> 10%)</span>
        </div>
        <button id="btn-close-divergencia-panel" class="buffer-btn-close" title="Fechar e Desativar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <div class="buffer-panel-body">
        <!-- Resumo KPIs Clicáveis -->
        <div class="divergencia-kpis">
            <div class="divergencia-kpi-card kpi-card-danger active" id="card-kpi-divergencia" onclick="setDivergenciaSituacaoFilter('DIVERGÊNCIA')">
                <span class="divergencia-kpi-title">Divergência (> 10%)</span>
                <span class="divergencia-kpi-val" id="kpi-total-divergencia">--</span>
            </div>
            <div class="divergencia-kpi-card kpi-card-success" id="card-kpi-dentro" onclick="setDivergenciaSituacaoFilter('DENTRO DO LIMITE')">
                <span class="divergencia-kpi-title">Dentro do Limite</span>
                <span class="divergencia-kpi-val" id="kpi-total-dentro-limite">--</span>
            </div>
            <div class="divergencia-kpi-card kpi-card-warning" id="card-kpi-sem-cad" onclick="setDivergenciaSituacaoFilter('SEM CADASTRO NO TRIBUTÁRIO')">
                <span class="divergencia-kpi-title">Sem Cadastro</span>
                <span class="divergencia-kpi-val" id="kpi-total-sem-cadastro">--</span>
            </div>
            <div class="divergencia-kpi-card kpi-card-muted" id="card-kpi-sem-area" onclick="setDivergenciaSituacaoFilter('SEM ÁREA NO TRIBUTÁRIO')">
                <span class="divergencia-kpi-title">Sem Área / Zero</span>
                <span class="divergencia-kpi-val" id="kpi-total-sem-area">--</span>
            </div>
        </div>

        <!-- Filtros e Busca Rápida -->
        <div class="divergencia-filter-row">
            <select id="divergencia-situacao-select" class="divergencia-select">
                <option value="DIVERGÊNCIA" selected>Somente Divergências (> 10%)</option>
                <option value="TODOS">Todos os Lotes (5.719)</option>
                <option value="DENTRO DO LIMITE">Dentro do Limite (&le; 10%)</option>
                <option value="SEM CADASTRO NO TRIBUTÁRIO">Sem Cadastro no Tributário</option>
                <option value="SEM ÁREA NO TRIBUTÁRIO">Sem Área no Tributário</option>
                <option value="ÁREA TRIBUTÁRIA ZERO">Área Tributária Zero</option>
            </select>
        </div>

        <div class="divergencia-search-wrapper">
            <input type="text" id="divergencia-search-input" class="divergencia-search-input"
                placeholder="Buscar inscrição, contribuinte, rua..." autocomplete="off">
            <button type="button" id="btn-divergencia-search-clear" class="btn-clear-table-search" style="display:none;" title="Limpar busca" onclick="clearDivergenciaSearch()">✕</button>
        </div>

        <!-- Tabela de Registros -->
        <div class="divergencia-table-wrapper">
            <table class="divergencia-table" id="divergencia-table">
                <thead>
                    <tr>
                        <th>Inscrição</th>
                        <th>Área GIS</th>
                        <th>Área Trib.</th>
                        <th>Diferença</th>
                        <th>Dif. %</th>
                        <th style="text-align:center;">Ver</th>
                    </tr>
                </thead>
                <tbody id="divergencia-table-tbody">
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 20px; color: var(--text-muted);">
                            Carregando dados de divergência...
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Paginação -->
        <div class="divergencia-pagination">
            <button type="button" id="btn-divergencia-prev" class="btn-page-sm" onclick="changeDivergenciaPage(-1)" disabled>&larr; Anterior</button>
            <span id="divergencia-page-info" class="divergencia-page-info">Pág. 1 de 1 (0)</span>
            <button type="button" id="btn-divergencia-next" class="btn-page-sm" onclick="changeDivergenciaPage(1)" disabled>Próxima &rarr;</button>
        </div>

        <!-- Ações Inferiores (Destaque no Mapa e Exportação CSV) -->
        <div class="divergencia-actions">
            <button type="button" id="btn-toggle-divergencia-layer" class="btn-action-sm btn-highlight" onclick="toggleDivergenciaLayer()" title="Colorir lotes no mapa conforme divergência de área">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                    <polyline points="2 17 12 22 22 17"></polyline>
                    <polyline points="2 12 12 17 22 12"></polyline>
                </svg>
                <span id="lbl-toggle-divergencia-layer">Pintar Mapa</span>
            </button>
            <button type="button" id="btn-export-divergencia-csv" class="btn-action-sm" onclick="exportDivergenciaCSV()" title="Baixar relatório completo em CSV">
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
