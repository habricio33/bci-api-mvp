<?php
/**
 * Modal do Boletim de Cadastro Imobiliário (BCI) / Espelho Cadastral
 */
?>
<!-- Modal do Boletim de Cadastro Imobiliário (BCI) / Espelho Cadastral -->
<div id="bci-modal" class="modal-backdrop">
    <div class="modal-container bci-modal-container">
        <div class="modal-header bci-modal-header no-print">
            <div class="modal-header-title-group">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #3b82f6;">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                    <polyline points="14 2 14 8 20 8"></polyline>
                    <line x1="16" y1="13" x2="8" y2="13"></line>
                    <line x1="16" y1="17" x2="8" y2="17"></line>
                    <polyline points="10 9 9 9 8 9"></polyline>
                </svg>
                <h3 id="bci-modal-title">Boletim de Cadastro Imobiliário (BCI)</h3>
            </div>
            <div class="bci-header-actions">
                <button type="button" class="btn-bci-print" id="btn-print-bci" title="Imprimir / Salvar PDF">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 6 2 18 2 18 9"></polyline>
                        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
                        <rect x="6" y="14" width="12" height="8"></rect>
                    </svg>
                    <span>Imprimir BCI</span>
                </button>
                <button type="button" id="bci-modal-btn-close" class="modal-header-btn-close" title="Fechar">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        </div>
        <div id="bci-modal-body" class="modal-body bci-modal-body">
            <!-- Preenchido dinamicamente via Javascript -->
        </div>
    </div>
</div>
