<?php
/**
 * Modais de Edição Alfanumérica / CRUD (Redes e Ramais de Água e Esgoto)
 */
?>
<!-- Modal de Edição Alfanumérica da Rede de Água / Esgoto (CRUD) -->
<div id="edit-rede-agua-modal" class="modal-backdrop">
    <div class="modal-container modal-container-lg">
        <div class="modal-header">
            <div class="modal-header-title-group">
                <svg id="edit-modal-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #3b82f6;">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                </svg>
                <h3 id="edit-modal-title">Editar Trecho da Rede</h3>
            </div>
            <button id="edit-modal-btn-close" class="modal-header-btn-close" title="Fechar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <form id="form-edit-rede-agua" class="crud-form">
            <input type="hidden" id="edit-camada" name="camada" value="vw_rede_agua">
            <input type="hidden" id="edit-id-geo" name="id_geo">
            <input type="hidden" id="edit-id-alfanumerico" name="id_alfanumerico">

            <div class="crud-feature-badge" id="edit-feature-badge">
                <div class="crud-badge-item">
                    <span class="crud-badge-label">ID do Trecho:</span>
                    <strong id="badge-id-geo" class="crud-badge-value">-</strong>
                </div>
                <div class="crud-badge-item">
                    <span class="crud-badge-label">Extensão:</span>
                    <strong id="badge-extensao" class="crud-badge-value">-</strong>
                </div>
                <div class="crud-badge-item">
                    <span class="crud-badge-label">Status:</span>
                    <span id="badge-status" class="crud-status-tag status-new">Novo Cadastro</span>
                </div>
            </div>

            <div class="crud-form-grid">
                <!-- Nome / Identificação da Rede -->
                <div class="form-group grid-col-2">
                    <label for="edit-rede" class="form-label">Identificação / Trecho:</label>
                    <input type="text" id="edit-rede" name="rede" class="form-control" placeholder="Ex: Rede PVC 50 mm - Rua Exemplo">
                </div>

                <!-- Tipo da Rede -->
                <div class="form-group">
                    <label for="edit-tipo" class="form-label">Tipo de Rede:</label>
                    <select id="edit-tipo" name="tipo" class="form-control">
                        <option value="">-- Não Informado --</option>
                        <option value="Adutora">Adutora</option>
                        <option value="Distribuição">Distribuição</option>
                        <option value="Recalque">Recalque</option>
                    </select>
                </div>

                <!-- Material -->
                <div class="form-group">
                    <label for="edit-material" class="form-label">Material da Tubulação:</label>
                    <select id="edit-material" name="material" class="form-control">
                        <option value="">-- Não Informado --</option>
                        <option value="PVC">PVC</option>
                        <option value="Ferro Fundido">Ferro Fundido</option>
                        <option value="PEAD">PEAD</option>
                        <option value="DefoFo">DefoFo</option>
                        <option value="Aço Galvanizado">Aço Galvanizado</option>
                        <option value="PBA">PBA</option>
                        <option value="Concreto">Concreto</option>
                        <option value="Cerâmica">Cerâmica</option>
                        <option value="PRFV">PRFV</option>
                    </select>
                </div>

                <!-- Diâmetro (mm) -->
                <div class="form-group">
                    <label for="edit-diametro" class="form-label">Diâmetro Nominal (mm):</label>
                    <input type="number" step="0.1" min="1" max="2000" id="edit-diametro" name="diametro" class="form-control" placeholder="Ex: 50">
                </div>

                <!-- Profundidade (m) -->
                <div class="form-group">
                    <label for="edit-profundidade" class="form-label">Profundidade da Vala (m):</label>
                    <input type="number" step="0.05" min="0" max="50" id="edit-profundidade" name="profundidade" class="form-control" placeholder="Ex: 1.20">
                </div>

                <!-- Data de Implantação -->
                <div class="form-group">
                    <label for="edit-data-implantacao" class="form-label">Data de Implantação:</label>
                    <input type="date" id="edit-data-implantacao" name="data_implantacao" class="form-control">
                </div>

                <!-- Responsável pela Implantação -->
                <div class="form-group">
                    <label for="edit-responsavel" class="form-label">Responsável pela Implantação:</label>
                    <input type="text" id="edit-responsavel" name="responsavel_implantacao" class="form-control" placeholder="Ex: Equipe SAAE / Empreiteira">
                </div>

                <!-- Observações -->
                <div class="form-group grid-col-2">
                    <label for="edit-observacao" class="form-label">Observações Técnicas:</label>
                    <textarea id="edit-observacao" name="observacao" class="form-control" rows="2" placeholder="Informações adicionais sobre o trecho, manutenções, etc."></textarea>
                </div>
            </div>

            <div id="crud-alert-msg" class="crud-alert" style="display: none;"></div>

            <div class="crud-modal-footer">
                <button type="button" id="btn-delete-rede-agua" class="btn-crud-delete" style="display: none;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span>Excluir Atributos</span>
                </button>
                <div class="crud-footer-right">
                    <button type="button" id="btn-cancel-edit-rede-agua" class="btn-crud-secondary">Cancelar</button>
                    <button type="submit" id="btn-save-rede-agua" class="btn-crud-primary">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <span>Salvar Alterações</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Edição Alfanumérica do Ramal de Água (CRUD) -->
<div id="edit-ramal-agua-modal" class="modal-backdrop">
    <div class="modal-container modal-container-lg">
        <div class="modal-header">
            <div class="modal-header-title-group">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #06b6d4;">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <h3 id="edit-ramal-modal-title">Editar Ramal de Água / Ligação</h3>
            </div>
            <button id="edit-ramal-modal-btn-close" class="modal-header-btn-close" title="Fechar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <form id="form-edit-ramal-agua" class="crud-form">
            <input type="hidden" id="edit-ramal-id-geo" name="id_geo">
            <input type="hidden" id="edit-ramal-id" name="ramal_id">
            <input type="hidden" id="edit-ramal-edificacao-id" name="edificacao_id">

            <div class="crud-feature-badge" id="edit-ramal-feature-badge">
                <div class="crud-badge-item">
                    <span class="crud-badge-label">ID do Imóvel:</span>
                    <strong id="badge-ramal-id-geo" class="crud-badge-value">-</strong>
                </div>
                <div class="crud-badge-item">
                    <span class="crud-badge-label">Área Construída:</span>
                    <strong id="badge-ramal-area" class="crud-badge-value">-</strong>
                </div>
                <div class="crud-badge-item">
                    <span class="crud-badge-label">Status:</span>
                    <span id="badge-ramal-status" class="crud-status-tag status-new">Novo Cadastro</span>
                </div>
            </div>

            <div class="crud-form-grid">
                <!-- Número Postal / Endereço -->
                <div class="form-group">
                    <label for="edit-ramal-numero-postal" class="form-label">Número Postal / Complemento:</label>
                    <input type="text" id="edit-ramal-numero-postal" name="numero_postal" class="form-control" placeholder="Ex: 145 ou 145-A">
                </div>

                <!-- Tipo da Edificação -->
                <div class="form-group">
                    <label for="edit-ramal-tipo-edificacao" class="form-label">Tipo de Imóvel / Edificação:</label>
                    <select id="edit-ramal-tipo-edificacao" name="tipo_edificacao" class="form-control">
                        <option value="">-- Não Informado --</option>
                        <option value="Residencial">Residencial</option>
                        <option value="Comercial">Comercial</option>
                        <option value="Industrial">Industrial</option>
                        <option value="Misto">Misto</option>
                    </select>
                </div>

                <!-- Hidrômetro -->
                <div class="form-group">
                    <label for="edit-ramal-hidrometro" class="form-label">Número do Hidrômetro:</label>
                    <input type="text" id="edit-ramal-hidrometro" name="hidrometro" class="form-control" placeholder="Ex: HD-123456">
                </div>

                <!-- Material do Ramal -->
                <div class="form-group">
                    <label for="edit-ramal-material" class="form-label">Material do Ramal:</label>
                    <select id="edit-ramal-material" name="material" class="form-control">
                        <option value="">-- Não Informado --</option>
                        <option value="PEAD">PEAD</option>
                        <option value="PVC">PVC</option>
                        <option value="Ferro Fundido">Ferro Fundido</option>
                        <option value="Ferro Galvanizado">Ferro Galvanizado</option>
                        <option value="Cobre">Cobre</option>
                        <option value="PPR">PPR</option>
                    </select>
                </div>

                <!-- Diâmetro (mm) -->
                <div class="form-group">
                    <label for="edit-ramal-diametro" class="form-label">Diâmetro Nominal (mm):</label>
                    <input type="number" step="0.1" min="1" max="500" id="edit-ramal-diametro" name="diametro" class="form-control" placeholder="Ex: 20 ou 25">
                </div>

                <!-- Profundidade (m) -->
                <div class="form-group">
                    <label for="edit-ramal-profundidade" class="form-label">Profundidade (m):</label>
                    <input type="number" step="0.05" min="0" max="10" id="edit-ramal-profundidade" name="profundidade" class="form-control" placeholder="Ex: 0.80">
                </div>

                <!-- Tipo de Pavimento -->
                <div class="form-group">
                    <label for="edit-ramal-pavimento" class="form-label">Tipo de Pavimento:</label>
                    <select id="edit-ramal-pavimento" name="tipo_pavimento" class="form-control">
                        <option value="">-- Não Informado --</option>
                        <option value="Asfalto">Asfalto</option>
                        <option value="Paralelepípedo">Paralelepípedo</option>
                        <option value="Bloquete">Bloquete</option>
                        <option value="Concreto">Concreto</option>
                        <option value="Terra">Terra</option>
                        <option value="Calçada / Passeio">Calçada / Passeio</option>
                    </select>
                </div>

                <!-- Extensão do Ramal (m) -->
                <div class="form-group">
                    <label for="edit-ramal-extensao" class="form-label">Extensão do Ramal (m):</label>
                    <input type="number" step="0.1" min="0" max="1000" id="edit-ramal-extensao" name="extensao" class="form-control" placeholder="Ex: 6.00">
                </div>

                <!-- A Direita (m) -->
                <div class="form-group">
                    <label for="edit-ramal-a-direita" class="form-label">Distância À Direita (m):</label>
                    <input type="number" step="0.1" min="0" max="500" id="edit-ramal-a-direita" name="a_direita" class="form-control" placeholder="Ex: 2.50">
                </div>

                <!-- A Esquerda (m) -->
                <div class="form-group">
                    <label for="edit-ramal-a-esquerda" class="form-label">Distância À Esquerda (m):</label>
                    <input type="number" step="0.1" min="0" max="500" id="edit-ramal-a-esquerda" name="a_esquerda" class="form-control" placeholder="Ex: 1.80">
                </div>

                <!-- Data de Implantação -->
                <div class="form-group">
                    <label for="edit-ramal-data-implantacao" class="form-label">Data de Implantação:</label>
                    <input type="date" id="edit-ramal-data-implantacao" name="data_implantacao" class="form-control">
                </div>

                <!-- Observações -->
                <div class="form-group grid-col-2">
                    <label for="edit-ramal-observacao" class="form-label">Observações / Cadastro:</label>
                    <textarea id="edit-ramal-observacao" name="observacao" class="form-control" rows="2" placeholder="Informações adicionais sobre o ramal, ligação, histórico, etc."></textarea>
                </div>
            </div>

            <div id="crud-ramal-alert-msg" class="crud-alert" style="display: none;"></div>

            <div class="crud-modal-footer">
                <button type="button" id="btn-delete-ramal-agua" class="btn-crud-delete" style="display: none;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span>Excluir Ramal</span>
                </button>
                <div class="crud-footer-right">
                    <button type="button" id="btn-cancel-edit-ramal-agua" class="btn-crud-secondary">Cancelar</button>
                    <button type="submit" id="btn-save-ramal-agua" class="btn-crud-primary" style="background: #06b6d4; border-color: #0891b2;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <span>Salvar Alterações</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal de Edição Alfanumérica do Ramal de Esgoto (CRUD) -->
<div id="edit-ramal-esgoto-modal" class="modal-backdrop">
    <div class="modal-container modal-container-lg">
        <div class="modal-header">
            <div class="modal-header-title-group">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="color: #ec4899;">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                    <polyline points="9 22 9 12 15 12 15 22"></polyline>
                </svg>
                <h3 id="edit-ramal-esgoto-modal-title">Editar Ramal de Esgoto / Ligação</h3>
            </div>
            <button id="edit-ramal-esgoto-modal-btn-close" class="modal-header-btn-close" title="Fechar">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <form id="form-edit-ramal-esgoto" class="crud-form">
            <input type="hidden" id="edit-ramal-esgoto-id-geo" name="id_geo">
            <input type="hidden" id="edit-ramal-esgoto-id" name="ramal_id">
            <input type="hidden" id="edit-ramal-esgoto-edificacao-id" name="edificacao_id">

            <div class="crud-feature-badge" id="edit-ramal-esgoto-feature-badge">
                <div class="crud-badge-item">
                    <span class="crud-badge-label">ID do Imóvel:</span>
                    <strong id="badge-ramal-esgoto-id-geo" class="crud-badge-value">-</strong>
                </div>
                <div class="crud-badge-item">
                    <span class="crud-badge-label">Área Construída:</span>
                    <strong id="badge-ramal-esgoto-area" class="crud-badge-value">-</strong>
                </div>
                <div class="crud-badge-item">
                    <span class="crud-badge-label">Status:</span>
                    <span id="badge-ramal-esgoto-status" class="crud-status-tag status-new">Novo Cadastro</span>
                </div>
            </div>

            <div class="crud-form-grid">
                <!-- Número Postal / Endereço -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-numero-postal" class="form-label">Número Postal / Complemento:</label>
                    <input type="text" id="edit-ramal-esgoto-numero-postal" name="numero_postal" class="form-control" placeholder="Ex: 145 ou 145-A">
                </div>

                <!-- Tipo da Edificação -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-tipo-edificacao" class="form-label">Tipo de Imóvel / Edificação:</label>
                    <select id="edit-ramal-esgoto-tipo-edificacao" name="tipo_edificacao" class="form-control">
                        <option value="">-- Não Informado --</option>
                        <option value="Residencial">Residencial</option>
                        <option value="Comercial">Comercial</option>
                        <option value="Industrial">Industrial</option>
                        <option value="Misto">Misto</option>
                    </select>
                </div>

                <!-- Tipo do Ramal de Esgoto (tipo_ramal_esgoto) -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-tipo" class="form-label">Tipo do Ramal (Categoria):</label>
                    <select id="edit-ramal-esgoto-tipo" name="tipo_ramal" class="form-control">
                        <option value="">-- Selecione o Tipo --</option>
                        <option value="Coletor">Coletor</option>
                        <option value="Coletor principal">Coletor principal</option>
                        <option value="Interceptor">Interceptor</option>
                        <option value="Emissário">Emissário</option>
                    </select>
                </div>

                <!-- Material do Ramal -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-material" class="form-label">Material do Ramal:</label>
                    <select id="edit-ramal-esgoto-material" name="material" class="form-control">
                        <option value="">-- Não Informado --</option>
                        <option value="PVC">PVC</option>
                        <option value="PEAD">PEAD</option>
                        <option value="CRM">CRM (Cerâmica)</option>
                        <option value="Concreto">Concreto</option>
                        <option value="Ferro Fundido">Ferro Fundido</option>
                        <option value="PEAD Corrugado">PEAD Corrugado</option>
                    </select>
                </div>

                <!-- Diâmetro (mm) -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-diametro" class="form-label">Diâmetro Nominal (mm):</label>
                    <input type="number" step="0.1" min="1" max="1000" id="edit-ramal-esgoto-diametro" name="diametro" class="form-control" placeholder="Ex: 100 ou 150">
                </div>

                <!-- Profundidade (m) -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-profundidade" class="form-label">Profundidade (m):</label>
                    <input type="number" step="0.05" min="0" max="15" id="edit-ramal-esgoto-profundidade" name="profundidade" class="form-control" placeholder="Ex: 1.20">
                </div>

                <!-- Tipo de Pavimento -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-pavimento" class="form-label">Tipo de Pavimento:</label>
                    <select id="edit-ramal-esgoto-pavimento" name="tipo_pavimento" class="form-control">
                        <option value="">-- Não Informado --</option>
                        <option value="Asfalto">Asfalto</option>
                        <option value="Paralelepípedo">Paralelepípedo</option>
                        <option value="Bloquete">Bloquete</option>
                        <option value="Concreto">Concreto</option>
                        <option value="Terra">Terra</option>
                        <option value="Calçada / Passeio">Calçada / Passeio</option>
                    </select>
                </div>

                <!-- Extensão do Ramal (m) -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-extensao" class="form-label">Extensão do Ramal (m):</label>
                    <input type="number" step="0.1" min="0" max="1000" id="edit-ramal-esgoto-extensao" name="extensao" class="form-control" placeholder="Ex: 5.00">
                </div>

                <!-- A Direita (m) -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-a-direita" class="form-label">Distância À Direita (m):</label>
                    <input type="number" step="0.1" min="0" max="500" id="edit-ramal-esgoto-a-direita" name="a_direita" class="form-control" placeholder="Ex: 2.00">
                </div>

                <!-- A Esquerda (m) -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-a-esquerda" class="form-label">Distância À Esquerda (m):</label>
                    <input type="number" step="0.1" min="0" max="500" id="edit-ramal-esgoto-a-esquerda" name="a_esquerda" class="form-control" placeholder="Ex: 1.50">
                </div>

                <!-- Data de Implantação -->
                <div class="form-group">
                    <label for="edit-ramal-esgoto-data-implantacao" class="form-label">Data de Implantação:</label>
                    <input type="date" id="edit-ramal-esgoto-data-implantacao" name="data_implantacao" class="form-control">
                </div>

                <!-- Observações -->
                <div class="form-group grid-col-2">
                    <label for="edit-ramal-esgoto-observacao" class="form-label">Observações / Cadastro:</label>
                    <textarea id="edit-ramal-esgoto-observacao" name="observacao" class="form-control" rows="2" placeholder="Informações adicionais sobre o ramal de esgoto, conexão à rede coletora, etc."></textarea>
                </div>
            </div>

            <div id="crud-ramal-esgoto-alert-msg" class="crud-alert" style="display: none;"></div>

            <div class="crud-modal-footer">
                <button type="button" id="btn-delete-ramal-esgoto" class="btn-crud-delete" style="display: none;">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span>Excluir Ramal</span>
                </button>
                <div class="crud-footer-right">
                    <button type="button" id="btn-cancel-edit-ramal-esgoto" class="btn-crud-secondary">Cancelar</button>
                    <button type="submit" id="btn-save-ramal-esgoto" class="btn-crud-primary" style="background: #ec4899; border-color: #db2777;">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                            <polyline points="17 21 17 13 7 13 7 21"></polyline>
                            <polyline points="7 3 7 8 15 8"></polyline>
                        </svg>
                        <span>Salvar Alterações</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
