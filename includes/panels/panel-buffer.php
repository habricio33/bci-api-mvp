<!-- Painel Flutuante do Buffer / Área de Influência de Lotes e Edificações -->
<div id="buffer-panel" class="buffer-floating-panel" style="display: none;">
    <div class="buffer-panel-header">
        <div class="buffer-header-title">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <circle cx="12" cy="12" r="10"></circle>
                <circle cx="12" cy="12" r="6"></circle>
                <circle cx="12" cy="12" r="2"></circle>
            </svg>
            <span>Área de Influência (Lotes / Edificações)</span>
        </div>
        <button id="btn-close-buffer-panel" class="buffer-btn-close" title="Fechar e Desativar">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    </div>

    <div class="buffer-panel-body">
        <div class="buffer-instruction-badge" id="buffer-instruction">
            <span class="pulse-dot"></span>
            <span id="buffer-instruction-text">Clique no mapa para posicionar o centro do raio de análise</span>
        </div>

        <div class="buffer-section">
            <label class="buffer-label">Alvo da Análise:</label>
            <div class="buffer-type-btns">
                <button type="button" class="buffer-type-btn active" data-type="lotes">Lotes</button>
                <button type="button" class="buffer-type-btn" data-type="edificacoes">Edificações</button>
                <button type="button" class="buffer-type-btn" data-type="ambos">Ambos</button>
            </div>
        </div>

        <div class="buffer-section">
            <label class="buffer-label">Raio de Influência:</label>
            <div class="buffer-preset-btns">
                <button type="button" class="buffer-preset-btn" data-radius="25">25 m</button>
                <button type="button" class="buffer-preset-btn" data-radius="50">50 m</button>
                <button type="button" class="buffer-preset-btn active" data-radius="100">100 m</button>
                <button type="button" class="buffer-preset-btn" data-radius="200">200 m</button>
            </div>

            <div class="buffer-slider-container">
                <input type="range" id="buffer-radius-slider" min="10" max="500" step="5" value="100"
                    class="custom-slider">
                <div class="buffer-input-wrapper">
                    <input type="number" id="buffer-radius-input" min="5" max="1000" value="100"
                        class="buffer-number-input">
                    <span class="buffer-unit">metros</span>
                </div>
            </div>
        </div>

        <div class="buffer-actions">
            <button type="button" id="btn-clear-buffer" class="btn-action-sm"
                title="Limpar ponto e círculo">Limpar</button>
            <button type="button" id="btn-reposition-buffer" class="btn-action-sm btn-highlight"
                title="Definir novo ponto no mapa">Reposicionar</button>
        </div>

        <!-- Container de Resultados Analíticos -->
        <div id="buffer-results-container" class="buffer-results-container" style="display: none;">
        </div>
    </div>
</div>
