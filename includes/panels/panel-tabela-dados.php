<!-- Painel Inferior Colapsável de Lotes e Edificações com Paginação -->
<div id="bottom-lotes-panel" class="bottom-data-panel is-hidden">
    <!-- Barra Superior / Cabeçalho do Painel -->
    <div class="bottom-panel-header" onclick="toggleBottomPanel()">
        <div class="bottom-panel-title-area">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18" />
            </svg>
            <span class="bottom-panel-title">Tabela de Dados:</span>
            
            <!-- Seletor Segmentado: Lotes / Edificações -->
            <div class="table-type-segmented" onclick="event.stopPropagation()">
                <button type="button" class="btn-table-type active" id="btn-type-lotes" onclick="switchTableDataType('lotes')">
                    Lotes
                </button>
                <button type="button" class="btn-table-type" id="btn-type-edificacoes" onclick="switchTableDataType('edificacoes')">
                    Edificações
                </button>
            </div>

            <span id="bottom-lotes-badge" class="filter-badge">5.719 lotes</span>
            <span id="bottom-lotes-filter-tag" class="bottom-panel-tag">Sem Filtro Ativo</span>
        </div>

        <div class="bottom-panel-actions" onclick="event.stopPropagation()">
            <div class="table-quick-search-box">
                <input type="text" id="bottom-table-search-input" class="bottom-table-search-input"
                    placeholder="Buscar nos registros..." autocomplete="off">
                <button type="button" class="btn-clear-table-search" id="btn-clear-table-search"
                    title="Limpar busca" style="display:none;" onclick="clearLotesTableSearch()">✕</button>
            </div>
            <button type="button" class="btn-panel-action" id="btn-refresh-table" title="Recarregar dados"
                onclick="fetchLotesTableData(currentLotesTablePage)">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <path
                        d="M23 4v6h-6M1 20v-6h6M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15" />
                </svg>
            </button>
            <button type="button" class="btn-panel-action btn-panel-toggle-icon"
                id="btn-collapse-expand-panel" title="Minimizar / Expandir Painel"
                onclick="toggleBottomPanel()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </button>
            <button type="button" class="btn-panel-action btn-panel-close-icon" title="Fechar Tabela"
                onclick="closeBottomPanel()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                    stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
    </div>

    <!-- Conteúdo do Painel (Tabela e Paginação) -->
    <div class="bottom-panel-content">
        <!-- Toolbar de Controles da Tabela -->
        <div class="bottom-table-toolbar">
            <div class="toolbar-left">
                <label for="table-page-size">Exibir:</label>
                <select id="table-page-size" class="filter-select-sm"
                    onchange="changeLotesTableLimit(this.value)">
                    <option value="10">10 por página</option>
                    <option value="25" selected>25 por página</option>
                    <option value="50">50 por página</option>
                    <option value="100">100 por página</option>
                </select>

                <label for="table-filter-class" style="margin-left: 12px;">Filtrar classe:</label>
                <select id="table-filter-class" class="filter-select-sm"
                    onchange="onTableFilterClassChange(this.value)">
                    <option value="all">Todas as Classes</option>
                </select>
            </div>

            <div class="toolbar-right">
                <span id="table-status-info" class="table-status-text">Carregando dados...</span>
            </div>
        </div>

        <!-- Tabela Responsiva com Scroll -->
        <div class="bottom-table-wrapper">
            <table class="table-lotes" id="table-lotes-grid">
                <thead id="table-lotes-thead">
                    <!-- Cabeçalho renderizado dinamicamente -->
                </thead>
                <tbody id="table-lotes-tbody">
                    <!-- Preenchido via Javascript -->
                </tbody>
            </table>
        </div>

        <!-- Rodapé de Paginação -->
        <div class="bottom-table-pagination">
            <div class="pagination-info" id="table-pagination-info">
                Exibindo 0 a 0 de 0 registros
            </div>
            <div class="pagination-controls" id="table-pagination-buttons">
                <!-- Botões de página gerados dinamicamente -->
            </div>
        </div>
    </div>
</div>
