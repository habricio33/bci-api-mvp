<?php
/**
 * Sidebar Component: MVP BCI
 * Contém as abas de Camadas, Busca e Filtros Cadastrais (Lotes, Edificações, PDM).
 */
?>
<div class="sidebar">
    <div class="sidebar-header">
        <h2>MVP BCI</h2>
    </div>
    <div class="sidebar-tabs">
        <button class="sidebar-tab-btn active" data-tab="tab-layers">Camadas</button>
        <button class="sidebar-tab-btn" data-tab="tab-search">Busca</button>
        <button class="sidebar-tab-btn" data-tab="tab-filters">Filtros</button>
    </div>
    <div class="sidebar-content">
        <!-- Aba de Camadas -->
        <div id="tab-layers" class="sidebar-tab-content active">
            <!-- Mapa Base -->
            <div class="layer-legend">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <h3 style="margin-bottom: 0;">Mapa Base</h3>
                    <span id="zoom-level-badge" class="filter-badge" style="font-size: 0.72rem;">Zoom: 15 / 24</span>
                </div>
                <div class="filter-field">
                    <select id="select-basemap" class="filter-select">
                        <option value="Open Street Map" selected>Open Street Map</option>
                        <option value="Google Hibrído">Google Híbrido</option>
                        <option value="Google Maps">Google Maps</option>
                        <option value="Google Satélite">Google Satélite</option>
                        <option value="OSM Light">OSM Light</option>
                        <option value="Esri World Imagery">Esri World Imagery</option>
                        <option value="Sem Mapa Base (Fundo Escuro)">Sem Mapa Base (Fundo Escuro)</option>
                        <option value="Sem Mapa Base (Fundo Claro)">Sem Mapa Base (Fundo Claro / Branco)</option>
                        <option value="Sem Mapa Base (Grade CAD)">Sem Mapa Base (Grade CAD / Alta Precisão)</option>
                    </select>
                </div>
                <div style="font-size: 0.72rem; color: var(--text-muted); margin-top: 6px; line-height: 1.3;">
                    💡 <em>Dica: Escolha <strong>"Sem Mapa Base"</strong> para focar nos vetores cadastrais com zoom de alta precisão (até nível 24).</em>
                </div>
            </div>

            <!-- Cadastro e Imóveis -->
            <div class="layer-legend">
                <h3>Cadastro e Imóveis</h3>

                <!-- Edificações -->
                <div class="layer-control-container">
                    <label class="layer-toggle-label">
                        <input type="checkbox" id="chk-geo_edificacoes_dm">
                        <span class="custom-checkbox-indicator"></span>
                        <span class="legend-box line-building"></span>
                        <span class="layer-title">Edificações</span>
                    </label>
                    <div class="opacity-slider-wrapper">
                        <span class="slider-label">Opacidade:</span>
                        <input type="range" id="opacity-geo_edificacoes_dm" min="0" max="1" step="0.05" value="0.80" class="custom-slider">
                        <span id="val-opacity-geo_edificacoes_dm" class="slider-value">80%</span>
                    </div>
                </div>

                <!-- Lotes -->
                <div class="layer-control-container" style="margin-top: 15px;">
                    <label class="layer-toggle-label">
                        <input type="checkbox" id="chk-geo_lotes_dm">
                        <span class="custom-checkbox-indicator"></span>
                        <span class="legend-box line-lote"></span>
                        <span class="layer-title">Lotes</span>
                    </label>
                    <div class="opacity-slider-wrapper">
                        <span class="slider-label">Opacidade:</span>
                        <input type="range" id="opacity-geo_lotes_dm" min="0" max="1" step="0.05" value="0.60" class="custom-slider">
                        <span id="val-opacity-geo_lotes_dm" class="slider-value">60%</span>
                    </div>
                </div>

                <!-- Quadras -->
                <div class="layer-control-container" style="margin-top: 15px;">
                    <label class="layer-toggle-label">
                        <input type="checkbox" id="chk-geo_quadras_dm">
                        <span class="custom-checkbox-indicator"></span>
                        <span class="legend-box line-setor"></span>
                        <span class="layer-title">Quadras</span>
                    </label>
                    <div class="opacity-slider-wrapper">
                        <span class="slider-label">Opacidade:</span>
                        <input type="range" id="opacity-geo_quadras_dm" min="0" max="1" step="0.05" value="0.50" class="custom-slider">
                        <span id="val-opacity-geo_quadras_dm" class="slider-value">50%</span>
                    </div>
                </div>
            </div>

            <!-- Divisões e Logradouros -->
            <div class="layer-legend">
                <h3>Divisões e Logradouros</h3>

                <!-- Comunidades -->
                <div class="layer-control-container">
                    <label class="layer-toggle-label">
                        <input type="checkbox" id="chk-geo_comunidades_dm">
                        <span class="custom-checkbox-indicator"></span>
                        <span class="legend-box line-bairro"></span>
                        <span class="layer-title">Comunidades</span>
                    </label>
                    <div class="opacity-slider-wrapper">
                        <span class="slider-label">Opacidade:</span>
                        <input type="range" id="opacity-geo_comunidades_dm" min="0" max="1" step="0.05" value="0.40" class="custom-slider">
                        <span id="val-opacity-geo_comunidades_dm" class="slider-value">40%</span>
                    </div>
                </div>

                <!-- Logradouros -->
                <div class="layer-control-container" style="margin-top: 15px;">
                    <label class="layer-toggle-label">
                        <input type="checkbox" id="chk-geo_logradouros_dm">
                        <span class="custom-checkbox-indicator"></span>
                        <span class="legend-line line-street"></span>
                        <span class="layer-title">Logradouros</span>
                    </label>
                    <div class="opacity-slider-wrapper">
                        <span class="slider-label">Opacidade:</span>
                        <input type="range" id="opacity-geo_logradouros_dm" min="0" max="1" step="0.05" value="0.70" class="custom-slider">
                        <span id="val-opacity-geo_logradouros_dm" class="slider-value">70%</span>
                    </div>
                </div>
            </div>

            <!-- Planejamento Urbano -->
            <div class="layer-legend">
                <h3>Planejamento Urbano</h3>

                <!-- Zoneamento -->
                <div class="layer-control-container">
                    <label class="layer-toggle-label">
                        <input type="checkbox" id="chk-geo_zoneamento_dm">
                        <span class="custom-checkbox-indicator"></span>
                        <span class="legend-box line-zoneamento"></span>
                        <span class="layer-title">Zoneamento</span>
                    </label>
                    <div class="opacity-slider-wrapper">
                        <span class="slider-label">Opacidade:</span>
                        <input type="range" id="opacity-geo_zoneamento_dm" min="0" max="1" step="0.05" value="0.50" class="custom-slider">
                        <span id="val-opacity-geo_zoneamento_dm" class="slider-value">50%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aba de Busca -->
        <div id="tab-search" class="sidebar-tab-content">
            <div class="layer-legend search-section" style="border-bottom: none;">
                <div class="search-field" style="margin-bottom: 15px;">
                    <label for="search-type-select">Tipo de Busca:</label>
                    <select id="search-type-select" onchange="toggleSearchSegment(this.value)">
                        <option value="streets">Logradouros</option>
                        <option value="neighborhoods">Bairros</option>
                        <option value="inscricao">Inscrição Imobiliária</option>
                        <option value="cpf_cnpj">CPF / CNPJ</option>
                    </select>
                </div>

                <!-- Segmento 1: Logradouros -->
                <div id="search-segment-streets">
                    <h3>Busca de Logradouros</h3>
                    <form id="street-search-form" onsubmit="event.preventDefault(); executeStreetSearch();">
                        <div class="search-field">
                            <label for="street-search-input">Nome da Rua:</label>
                            <input type="text" id="street-search-input" placeholder="Digite o nome da rua..." list="ruas-list" autocomplete="off">
                            <datalist id="ruas-list"></datalist>
                        </div>
                        <div class="search-buttons">
                            <button type="submit" class="btn-search">Buscar</button>
                            <button type="button" class="btn-clear-search" onclick="clearStreetSearch()">Limpar</button>
                        </div>
                    </form>

                    <!-- Tabela de Resultados Logradouros -->
                    <div id="street-results-container" style="display: none; margin-top: 15px;">
                        <h4>Trechos Encontrados (<span id="street-results-count">0</span>)</h4>
                        <div class="table-responsive">
                            <table class="street-results-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Pavimento</th>
                                        <th>Tipo</th>
                                        <th>Extensão</th>
                                    </tr>
                                </thead>
                                <tbody id="street-results-tbody">
                                    <!-- Preenchido via Javascript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Segmento 2: Bairros -->
                <div id="search-segment-neighborhoods" style="display: none;">
                    <h3>Busca de Bairros</h3>
                    <form id="neighborhood-search-form" onsubmit="event.preventDefault(); executeNeighborhoodSearch();">
                        <div class="search-field">
                            <label for="neighborhood-search-input">Nome do Bairro:</label>
                            <input type="text" id="neighborhood-search-input" placeholder="Digite o nome do bairro..." autocomplete="off">
                        </div>
                        <div class="search-buttons">
                            <button type="submit" class="btn-search">Buscar</button>
                            <button type="button" class="btn-clear-search" onclick="clearNeighborhoodSearch()">Limpar</button>
                        </div>
                    </form>

                    <!-- Tabela de Resultados Bairros -->
                    <div id="neighborhood-results-container" style="display: none; margin-top: 15px;">
                        <h4>Bairros Encontrados (<span id="neighborhood-results-count">0</span>)</h4>
                        <div class="table-responsive">
                            <table class="street-results-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Bairro</th>
                                    </tr>
                                </thead>
                                <tbody id="neighborhood-results-tbody">
                                    <!-- Preenchido via Javascript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Segmento 3: Inscrição Imobiliária -->
                <div id="search-segment-inscricao" style="display: none;">
                    <h3>Busca por Inscrição Imobiliária</h3>
                    <form id="inscricao-search-form" onsubmit="event.preventDefault(); executeInscricaoSearch();">
                        <div class="search-field">
                            <label for="inscricao-search-input">Inscrição Municipal:</label>
                            <input type="text" id="inscricao-search-input" placeholder="Ex: 01010060107001 ou 0101006..." autocomplete="off">
                        </div>
                        <div class="search-buttons">
                            <button type="submit" class="btn-search">Buscar</button>
                            <button type="button" class="btn-clear-search" onclick="clearInscricaoSearch()">Limpar</button>
                        </div>
                    </form>

                    <!-- Tabela de Resultados Inscrição -->
                    <div id="inscricao-results-container" style="display: none; margin-top: 15px;">
                        <h4>Imóveis Encontrados (<span id="inscricao-results-count">0</span>)</h4>
                        <div class="table-responsive">
                            <table class="street-results-table">
                                <thead>
                                    <tr>
                                        <th>Inscrição</th>
                                        <th>Contribuinte</th>
                                        <th>Endereço / Bairro</th>
                                    </tr>
                                </thead>
                                <tbody id="inscricao-results-tbody">
                                    <!-- Preenchido via Javascript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Segmento 4: CPF / CNPJ -->
                <div id="search-segment-cpf-cnpj" style="display: none;">
                    <h3>Busca por CPF / CNPJ</h3>
                    <form id="cpf-cnpj-search-form" onsubmit="event.preventDefault(); executeCpfCnpjSearch();">
                        <div class="search-field">
                            <label for="cpf-cnpj-search-input">CPF ou CNPJ do Contribuinte:</label>
                            <input type="text" id="cpf-cnpj-search-input" placeholder="Digite o CPF ou CNPJ..." autocomplete="off">
                        </div>
                        <div class="search-buttons">
                            <button type="submit" class="btn-search">Buscar</button>
                            <button type="button" class="btn-clear-search" onclick="clearCpfCnpjSearch()">Limpar</button>
                        </div>
                    </form>

                    <!-- Tabela de Resultados CPF/CNPJ -->
                    <div id="cpf-cnpj-results-container" style="display: none; margin-top: 15px;">
                        <h4>Imóveis Encontrados (<span id="cpf-cnpj-results-count">0</span>)</h4>
                        <div class="table-responsive">
                            <table class="street-results-table">
                                <thead>
                                    <tr>
                                        <th>CPF / CNPJ</th>
                                        <th>Contribuinte</th>
                                        <th>Inscrição</th>
                                        <th>Endereço / Bairro</th>
                                    </tr>
                                </thead>
                                <tbody id="cpf-cnpj-results-tbody">
                                    <!-- Preenchido via Javascript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aba de Filtros -->
        <div id="tab-filters" class="sidebar-tab-content">
            <!-- Seção: Filtro de Lotes (Cadastro Imobiliário) -->
            <div class="layer-legend filter-section">
                <div class="filter-header">
                    <div class="filter-header-title">
                        <span class="legend-box line-lote"></span>
                        <h3>Filtro - Lotes</h3>
                    </div>
                    <span class="filter-badge" id="badge-count-geo_lotes_dm">5.719 lotes</span>
                </div>

                <!-- Barra de Estatísticas Cadastrais -->
                <div class="cadastral-stats-card">
                    <div class="cadastral-stats-header">
                        <span>Preenchimento Cadastral</span>
                        <span id="pct-label-geo_lotes_dm">79.1% com dados</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill fill-lotes" id="progress-geo_lotes_dm" style="width: 79.1%;"></div>
                    </div>
                    <div class="cadastral-stats-details">
                        <span class="stat-item"><strong id="stat-filled-geo_lotes_dm">4.526</strong> com dados</span>
                        <span class="stat-item text-muted"><strong id="stat-blank-geo_lotes_dm">1.193</strong> em branco</span>
                    </div>
                </div>

                <!-- Seletor de Classificação -->
                <div class="filter-field">
                    <label for="filter-criterion-geo_lotes_dm">Classificar / Filtrar por:</label>
                    <select id="filter-criterion-geo_lotes_dm" class="filter-select" onchange="onLotesFilterCriterionChange('geo_lotes_dm', this.value)">
                        <option value="none" selected>Selecione um critério de classificação...</option>
                        <option value="status">Por Status Cadastral</option>
                        <option value="utilizacao">Por Utilização do Imóvel (UTILIZACAO_DO_IMOVEL9027)</option>
                        <option value="ocupacao">Por Tipo de Ocupação (OCUPACAO9025)</option>
                    </select>
                </div>

                <!-- Botões de Ação Rápida de Seleção -->
                <div class="filter-quick-actions" id="actions-geo_lotes_dm" style="display: none;">
                    <button type="button" class="btn-action-sm" onclick="toggleAllFilterClasses('geo_lotes_dm', true)" title="Marcar todos os itens da legenda">Todos</button>
                    <button type="button" class="btn-action-sm" onclick="toggleAllFilterClasses('geo_lotes_dm', false)" title="Desmarcar todos os itens da legenda">Nenhum</button>
                    <button type="button" class="btn-action-sm btn-highlight" onclick="toggleOnlyFilledFilterClasses('geo_lotes_dm')" title="Marcar apenas lotes com informações cadastradas">Apenas com Dados</button>
                </div>

                <!-- Container da Legenda Dinâmica -->
                <div id="legend-container-geo_lotes_dm" class="filter-legend-container">
                    <p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar os lotes.</p>
                </div>

                <!-- Botões de Execução Explícita do Filtro -->
                <div class="filter-execution-box">
                    <div class="filter-status-wrapper">
                        <span id="lotes-filter-status-indicator" class="filter-status-indicator">
                            <span class="status-dot"></span>
                            <span id="lotes-filter-status-text">Filtro Inativo (Padrão)</span>
                        </span>
                    </div>
                    <div class="filter-execution-actions">
                        <button type="button" class="btn-filter-apply" id="btn-apply-lotes-filter" onclick="applyLotesFilterExplicit()" title="Executar e aplicar classificação no mapa e na tabela">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Aplicar Filtro</span>
                        </button>
                        <button type="button" class="btn-filter-clear" id="btn-clear-lotes-filter" onclick="clearLotesFilterExplicit()" title="Limpar e restaurar visualização padrão">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            <span>Limpar Filtros</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Seção: Filtro de Edificações -->
            <div class="layer-legend filter-section" style="margin-top: 20px;">
                <div class="filter-header">
                    <div class="filter-header-title">
                        <span class="legend-box line-building"></span>
                        <h3>Filtro - Edificações</h3>
                    </div>
                    <span class="filter-badge" id="badge-count-geo_edificacoes_dm">4.905 edificações</span>
                </div>

                <!-- Barra de Estatísticas Cadastrais -->
                <div class="cadastral-stats-card">
                    <div class="cadastral-stats-header">
                        <span>Preenchimento Cadastral</span>
                        <span id="pct-label-geo_edificacoes_dm">75.5% com dados</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill fill-edificacoes" id="progress-geo_edificacoes_dm" style="width: 75.5%;"></div>
                    </div>
                    <div class="cadastral-stats-details">
                        <span class="stat-item"><strong id="stat-filled-geo_edificacoes_dm">3.702</strong> com dados</span>
                        <span class="stat-item text-muted"><strong id="stat-blank-geo_edificacoes_dm">1.203</strong> em branco</span>
                    </div>
                </div>

                <!-- Seletor de Classificação -->
                <div class="filter-field">
                    <label for="filter-criterion-geo_edificacoes_dm">Classificar / Filtrar por:</label>
                    <select id="filter-criterion-geo_edificacoes_dm" class="filter-select" onchange="onEdificacoesFilterCriterionChange('geo_edificacoes_dm', this.value)">
                        <option value="none" selected>Selecione um critério de classificação...</option>
                        <option value="tipo_construcao">Por Tipo de Construção (TIPO_DE_CONSTRUCAO10042)</option>
                        <option value="estrutura">Por Estrutura (ESTRUTURA10043)</option>
                        <option value="estado_conservacao">Por Estado de Conservação (ESTADO_DE_CONSERVACAO10044)</option>
                        <option value="situacao_imovel">Por Situação do Imóvel (SITUACAO_DO_IMOVEL10048)</option>
                        <option value="esgoto">Por Rede de Esgoto (ESGOTO12001)</option>
                        <option value="agua">Por Abastecimento de Água (AGUA12002)</option>
                        <option value="lixo">Por Coleta de Lixo (LIXO12006)</option>
                        <option value="status">Por Status Cadastral</option>
                    </select>
                </div>

                <!-- Botões de Ação Rápida de Seleção -->
                <div class="filter-quick-actions" id="actions-geo_edificacoes_dm" style="display: none;">
                    <button type="button" class="btn-action-sm" onclick="toggleAllFilterClasses('geo_edificacoes_dm', true)" title="Marcar todos os itens da legenda">Todos</button>
                    <button type="button" class="btn-action-sm" onclick="toggleAllFilterClasses('geo_edificacoes_dm', false)" title="Desmarcar todos os itens da legenda">Nenhum</button>
                    <button type="button" class="btn-action-sm btn-highlight" onclick="toggleOnlyFilledFilterClasses('geo_edificacoes_dm')" title="Marcar apenas edificações com informações cadastradas">Apenas com Dados</button>
                </div>

                <!-- Container da Legenda Dinâmica -->
                <div id="legend-container-geo_edificacoes_dm" class="filter-legend-container">
                    <p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar as edificações.</p>
                </div>

                <!-- Botões de Execução Explícita do Filtro -->
                <div class="filter-execution-box">
                    <div class="filter-status-wrapper">
                        <span id="edificacoes-filter-status-indicator" class="filter-status-indicator">
                            <span class="status-dot"></span>
                            <span id="edificacoes-filter-status-text">Filtro Inativo (Padrão)</span>
                        </span>
                    </div>
                    <div class="filter-execution-actions">
                        <button type="button" class="btn-filter-apply" id="btn-apply-edificacoes-filter" onclick="applyEdificacoesFilterExplicit()" title="Executar e aplicar classificação no mapa">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Aplicar Filtro</span>
                        </button>
                        <button type="button" class="btn-filter-clear" id="btn-clear-edificacoes-filter" onclick="clearEdificacoesFilterExplicit()" title="Limpar e restaurar visualização padrão">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            <span>Limpar Filtros</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Seção: Filtro de PDM (Zoneamento) -->
            <div class="layer-legend filter-section" style="margin-top: 20px;">
                <div class="filter-header">
                    <div class="filter-header-title">
                        <span class="legend-box line-zoneamento"></span>
                        <h3>Filtro - PDM (Zoneamento)</h3>
                    </div>
                    <span class="filter-badge" id="badge-count-geo_zoneamento_dm">116 zonas</span>
                </div>

                <!-- Barra de Estatísticas Cadastrais -->
                <div class="cadastral-stats-card">
                    <div class="cadastral-stats-header">
                        <span>Classificação PDM</span>
                        <span id="pct-label-geo_zoneamento_dm">99.1% com dados</span>
                    </div>
                    <div class="progress-bar-container">
                        <div class="progress-bar-fill fill-pdm" id="progress-geo_zoneamento_dm" style="width: 99.1%;"></div>
                    </div>
                    <div class="cadastral-stats-details">
                        <span class="stat-item"><strong id="stat-filled-geo_zoneamento_dm">115</strong> com dados</span>
                        <span class="stat-item text-muted"><strong id="stat-blank-geo_zoneamento_dm">1</strong> em branco</span>
                    </div>
                </div>

                <!-- Seletor de Classificação -->
                <div class="filter-field">
                    <label for="filter-criterion-geo_zoneamento_dm">Classificar / Filtrar por:</label>
                    <select id="filter-criterion-geo_zoneamento_dm" class="filter-select" onchange="onPdmFilterCriterionChange('geo_zoneamento_dm', this.value)">
                        <option value="none" selected>Selecione um critério de classificação...</option>
                        <option value="sigla">Por Sigla da Zona (ZOC, ZOP, ZEIS, ZEU, etc.)</option>
                    </select>
                </div>

                <!-- Botões de Ação Rápida de Seleção -->
                <div class="filter-quick-actions" id="actions-geo_zoneamento_dm" style="display: none;">
                    <button type="button" class="btn-action-sm" onclick="toggleAllFilterClasses('geo_zoneamento_dm', true)" title="Marcar todos os itens da legenda">Todos</button>
                    <button type="button" class="btn-action-sm" onclick="toggleAllFilterClasses('geo_zoneamento_dm', false)" title="Desmarcar todos os itens da legenda">Nenhum</button>
                    <button type="button" class="btn-action-sm btn-highlight" onclick="toggleOnlyFilledFilterClasses('geo_zoneamento_dm')" title="Marcar apenas zonas com classificação cadastrada">Apenas com Dados</button>
                </div>

                <!-- Container da Legenda Dinâmica -->
                <div id="legend-container-geo_zoneamento_dm" class="filter-legend-container">
                    <p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar o zoneamento.</p>
                </div>

                <!-- Botões de Execução Explícita do Filtro -->
                <div class="filter-execution-box">
                    <div class="filter-status-wrapper">
                        <span id="pdm-filter-status-indicator" class="filter-status-indicator">
                            <span class="status-dot"></span>
                            <span id="pdm-filter-status-text">Filtro Inativo (Padrão)</span>
                        </span>
                    </div>
                    <div class="filter-execution-actions">
                        <button type="button" class="btn-filter-apply" id="btn-apply-pdm-filter" onclick="applyPdmFilterExplicit()" title="Executar e aplicar classificação no mapa">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            <span>Aplicar Filtro</span>
                        </button>
                        <button type="button" class="btn-filter-clear" id="btn-clear-pdm-filter" onclick="clearPdmFilterExplicit()" title="Limpar e restaurar visualização padrão">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                            <span>Limpar Filtros</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
