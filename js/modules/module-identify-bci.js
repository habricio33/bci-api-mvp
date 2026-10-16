/**
 * ==========================================================================
 * MÓDULO: IDENTIFICAÇÃO ESPACIAL (INFO) & BOLETIM DE CADASTRO IMOBILIÁRIO (BCI)
 * Arquivo: js/modules/module-identify-bci.js
 * ==========================================================================
 */

let identifyModeActive = false;
let currentIdentifyUnits = [];
let currentIdentifyLote = null;
let identifyHighlightLayer = null;
let bciCroquiMapInstance = null;

// Função para limpar o destaque do lote selecionado
function clearIdentifyHighlight() {
    if (identifyHighlightLayer && typeof map !== 'undefined') {
        map.removeLayer(identifyHighlightLayer);
        identifyHighlightLayer = null;
    }
}

// Toggle para ativar/desativar modo de identificação
function toggleIdentifyTool(forceState) {
    const btnIdentify = document.getElementById('btn-identify');
    const mapContainer = document.getElementById('map');

    identifyModeActive = typeof forceState === 'boolean' ? forceState : !identifyModeActive;

    if (btnIdentify) {
        btnIdentify.classList.toggle('active', identifyModeActive);
    }

    if (identifyModeActive) {
        if (mapContainer) mapContainer.classList.add('map-help-cursor');

        // Desativa outras ferramentas concorrentes
        if (typeof bufferModeActive !== 'undefined' && bufferModeActive && typeof toggleBufferTool === 'function') {
            toggleBufferTool();
        }
        if (typeof editModeActive !== 'undefined' && editModeActive && typeof toggleEditTool === 'function') {
            toggleEditTool(false);
        }
        if (typeof selecaoToolActive !== 'undefined' && selecaoToolActive && typeof toggleSelecaoEspacialTool === 'function') {
            toggleSelecaoEspacialTool();
        }
        if (typeof divergenciaToolActive !== 'undefined' && divergenciaToolActive && typeof toggleDivergenciaTool === 'function') {
            toggleDivergenciaTool();
        }
        if (typeof filtroTerrenoToolActive !== 'undefined' && filtroTerrenoToolActive && typeof toggleFiltroTerrenoTool === 'function') {
            toggleFiltroTerrenoTool();
        }
    } else {
        if (mapContainer) mapContainer.classList.remove('map-help-cursor');
        clearIdentifyHighlight();
    }
}

// Inicializa listeners do botão e modal de identify
document.addEventListener('DOMContentLoaded', function () {
    const btnIdentify = document.getElementById('btn-identify');
    const identifyModal = document.getElementById('identify-modal');
    const modalBtnBack = document.getElementById('modal-btn-back');
    const modalBtnClose = document.getElementById('modal-btn-close');

    if (btnIdentify) {
        btnIdentify.addEventListener('click', function (e) {
            L.DomEvent.stopPropagation(e);
            toggleIdentifyTool();
        });
        L.DomEvent.disableClickPropagation(btnIdentify);
    }

    if (modalBtnBack) {
        modalBtnBack.addEventListener('click', function () {
            showIdentifyUnitsList();
        });
    }

    if (modalBtnClose && identifyModal) {
        modalBtnClose.addEventListener('click', function () {
            identifyModal.classList.remove('show');
        });
    }

    if (identifyModal) {
        identifyModal.addEventListener('click', function (e) {
            if (e.target === identifyModal) {
                identifyModal.classList.remove('show');
            }
        });
    }
});

// Função unificada para executar a identificação espacial exclusiva de Lotes e Edificações
function executeIdentify(latlng) {
    if (typeof map !== 'undefined') {
        map.closePopup();
    }

    if (typeof showLoading === 'function') showLoading();

    const lat = latlng.lat;
    const lng = latlng.lng;
    const url = `api/identificar.php?lat=${lat}&lng=${lng}`;

    const identifyModal = document.getElementById('identify-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    const modalBtnBack = document.getElementById('modal-btn-back');

    fetch(url)
        .then(response => {
            if (!response.ok) throw new Error('Erro ao identificar feição.');
            return response.json();
        })
        .then(data => {
            if (typeof hideLoading === 'function') hideLoading();

            if (!data.found) {
                clearIdentifyHighlight();
                if (modalBtnBack) modalBtnBack.style.display = 'none';
                if (modalTitle) modalTitle.textContent = 'Identificação de Imóvel';
                if (identifyModal) identifyModal.classList.add('show');
                if (modalBody) {
                    modalBody.innerHTML = `
                        <div class="no-features-msg">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 12px; display: block; color: var(--text-muted);">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            ${escapeHtml(data.message || 'Nenhum lote ou edificação encontrado no local clicado.')}
                        </div>
                    `;
                }
                return;
            }

            // Remove destaque anterior se houver
            clearIdentifyHighlight();

            // Destaca o lote / feição selecionada no mapa
            if (data.geojson && typeof map !== 'undefined') {
                identifyHighlightLayer = L.geoJSON(data.geojson, {
                    style: function () {
                        return {
                            color: '#0284c7', // Azul ciano vibrante
                            weight: 4,
                            opacity: 0.95,
                            fillColor: '#38bdf8',
                            fillOpacity: 0.35
                        };
                    }
                }).addTo(map);

                // Aproxima com zoom suave centralizando no lote
                const bounds = identifyHighlightLayer.getBounds();
                if (bounds && bounds.isValid()) {
                    map.fitBounds(bounds, {
                        maxZoom: 19,
                        padding: [50, 50],
                        animate: true,
                        duration: 1.0
                    });
                }
            }

            currentIdentifyUnits = data.units || [];
            currentIdentifyLote = data.lote_prefix || '';

            if (data.units_count === 0) {
                if (modalBtnBack) modalBtnBack.style.display = 'none';
                if (modalTitle) modalTitle.textContent = `Lote: ${data.lote_prefix || 'Identificado'}`;
                if (identifyModal) identifyModal.classList.add('show');
                if (modalBody) {
                    modalBody.innerHTML = `
                        <div class="no-features-msg">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 12px; display: block; color: var(--text-muted);">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                <polyline points="9 22 9 12 15 12 15 22"></polyline>
                            </svg>
                            Lote identificado no mapa (Inscrição: <strong>${escapeHtml(data.lote_prefix)}</strong>), porém não há registros cadastrais de unidades no GP_IMOVEL.
                        </div>
                    `;
                }
                return;
            }

            // Se o lote possuir mais de 1 unidade/edificação, exibe a lista para escolha
            if (data.units_count > 1) {
                showIdentifyUnitsList();
            } else {
                // Se possuir apenas 1 unidade, exibe diretamente as informações
                if (data.single_unit_details) {
                    renderUnitDetails(data.single_unit_details);
                } else if (data.units && data.units[0]) {
                    fetchAndShowUnitDetails(data.units[0].INSC_MUNIC);
                }
            }
        })
        .catch(error => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro de identificação:', error);
        });
}

// Modal 1: Exibir lista de unidades encontradas no lote clicado
function showIdentifyUnitsList() {
    const identifyModal = document.getElementById('identify-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    const modalBtnBack = document.getElementById('modal-btn-back');

    if (modalBtnBack) modalBtnBack.style.display = 'none';
    if (modalTitle) modalTitle.textContent = `Unidades do Lote: ${currentIdentifyLote || ''}`;
    if (identifyModal) identifyModal.classList.add('show');

    if (!currentIdentifyUnits || currentIdentifyUnits.length === 0) {
        if (modalBody) modalBody.innerHTML = `<div class="no-features-msg">Nenhuma unidade encontrada para este lote.</div>`;
        return;
    }

    let html = `
        <div class="identify-units-summary">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="2" y="7" width="20" height="14" rx="2" ry="2"></rect>
                <path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"></path>
            </svg>
            <span>Este lote possui <strong>${currentIdentifyUnits.length} unidades</strong> cadastradas. Selecione uma unidade para ver as informações:</span>
        </div>
        <div class="identify-unit-list">
    `;

    currentIdentifyUnits.forEach((unit) => {
        const insc = escapeHtml(unit.INSC_MUNIC || '');
        const nome = escapeHtml(unit.NOMEDOCONTRIBUINTE || 'Não informado');
        const isOficial = (unit.ORIGEM_DADOS === 'API_TRIBUTARIA_OFICIAL');
        const situacao = unit.SITUACAO ? escapeHtml(unit.SITUACAO) : '';

        html += `
            <div class="identify-unit-card" onclick="fetchAndShowUnitDetails('${insc}')">
                <div class="unit-card-main">
                    <div class="unit-card-insc">
                        <span class="unit-badge-tag">Inscrição:</span>
                        <span class="unit-badge-val">${insc || 'S/N'}</span>
                        ${isOficial ? `<span style="margin-left: 6px; font-size: 10px; font-weight: 700; background: #ecfdf5; color: #047857; padding: 2px 7px; border-radius: 10px; border: 1px solid #a7f3d0;">● Oficial API</span>` : ''}
                        ${situacao ? `<span style="margin-left: 4px; font-size: 10px; font-weight: 600; color: ${situacao === 'Baixado' ? '#dc2626' : '#2563eb'};">(${situacao})</span>` : ''}
                    </div>
                    <div class="unit-card-nome">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <span>${nome}</span>
                    </div>
                </div>
                <div class="unit-card-arrow" title="Ver Informações">
                    <span>Selecionar</span>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="9 18 15 12 9 6"></polyline>
                    </svg>
                </div>
            </div>
        `;
    });

    html += `</div>`;
    if (modalBody) modalBody.innerHTML = html;
}

// Busca os detalhes simplificados da unidade selecionada via API
function fetchAndShowUnitDetails(inscMunic) {
    if (typeof showLoading === 'function') showLoading();

    const identifyModal = document.getElementById('identify-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    const modalBtnBack = document.getElementById('modal-btn-back');

    fetch(`api/identificar.php?action=detalhes&insc_munic=${encodeURIComponent(inscMunic)}`)
        .then(response => {
            if (!response.ok) throw new Error('Erro ao carregar detalhes do imóvel.');
            return response.json();
        })
        .then(data => {
            if (typeof hideLoading === 'function') hideLoading();
            if (data.found && data.imovel) {
                renderUnitDetails(data.imovel);
            } else {
                if (modalTitle) modalTitle.textContent = 'Detalhes do Imóvel';
                if (modalBtnBack) {
                    modalBtnBack.style.display = (currentIdentifyUnits && currentIdentifyUnits.length > 1) ? 'flex' : 'none';
                }
                if (modalBody) modalBody.innerHTML = `<div class="no-features-msg">${escapeHtml(data.message || 'Dados do imóvel não localizados.')}</div>`;
                if (identifyModal) identifyModal.classList.add('show');
            }
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro ao buscar detalhes da unidade:', err);
        });
}

// Modal 2: Exibir detalhamento simplificado e organizado em blocos do imóvel selecionado
function renderUnitDetails(imovel) {
    if (!imovel) return;

    const identifyModal = document.getElementById('identify-modal');
    const modalTitle = document.getElementById('modal-title');
    const modalBody = document.getElementById('modal-body');
    const modalBtnBack = document.getElementById('modal-btn-back');

    if (modalTitle) modalTitle.textContent = `Imóvel: ${imovel.INSC_MUNIC || 'Detalhes'}`;

    if (modalBtnBack) {
        modalBtnBack.style.display = (currentIdentifyUnits && currentIdentifyUnits.length > 1) ? 'flex' : 'none';
    }

    if (identifyModal) identifyModal.classList.add('show');

    // Concatenação inteligente de Logradouro e Município/Estado
    const tipoLogra = (imovel.NOMETIPOLOGRA || '').trim();
    const nomeLogra = (imovel.NOMELOGRADOURO || '').trim();
    const logradouroCompleto = [tipoLogra, nomeLogra].filter(Boolean).join(' ') || '<span style="color: var(--text-muted); font-style: italic;">Não informado</span>';

    const municipio = (imovel.NOMEMUNICIPIO || '').trim();
    const sigla = (imovel.SIGLA || '').trim();
    const municipioEstado = [municipio, sigla].filter(Boolean).join(' - ') || '<span style="color: var(--text-muted); font-style: italic;">Não informado</span>';

    function formatVal(val, isArea = false) {
        if (val === null || val === undefined || String(val).trim() === '') {
            return '<span style="color: var(--text-muted); font-style: italic;">Não informado</span>';
        }
        let s = escapeHtml(String(val).trim());
        if (isArea && !s.toLowerCase().includes('m²') && !s.toLowerCase().includes('m2')) {
            s += ' m²';
        }
        return s;
    }

    const html = `
        <div class="imovel-details-view">
            <!-- Bloco 1: Dados Principais -->
            <div class="details-section">
                <div class="section-header">
                    <div class="section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="4" width="18" height="16" rx="2"></rect>
                            <line x1="7" y1="8" x2="17" y2="8"></line>
                            <line x1="7" y1="12" x2="17" y2="12"></line>
                            <line x1="7" y1="16" x2="12" y2="16"></line>
                        </svg>
                        <span>Dados Principais</span>
                    </div>
                </div>
                <div class="details-grid grid-3">
                    <div class="detail-field">
                        <span class="field-label">ID do Imóvel</span>
                        <span class="field-value"><span class="field-badge-id">#${escapeHtml(imovel.ID || '-')}</span></span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">Inscrição Municipal</span>
                        <span class="field-value"><span class="field-badge-insc">${escapeHtml(imovel.INSC_MUNIC || '-')}</span></span>
                    </div>
                    <div class="detail-field span-desktop-1">
                        <span class="field-label">Contribuinte (Nome)</span>
                        <span class="field-value field-contribuinte">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                            <strong>${escapeHtml(imovel.NOMEDOCONTRIBUINTE || 'Não informado')}</strong>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Bloco 2: Localização -->
            <div class="details-section">
                <div class="section-header">
                    <div class="section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path>
                            <circle cx="12" cy="10" r="3"></circle>
                        </svg>
                        <span>Localização</span>
                    </div>
                </div>
                <div class="details-grid grid-location">
                    <div class="detail-field span-2">
                        <span class="field-label">Logradouro</span>
                        <span class="field-value">${logradouroCompleto}</span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">Número</span>
                        <span class="field-value">${formatVal(imovel.NUMEROLOGRADOURO)}</span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">Bairro</span>
                        <span class="field-value">${formatVal(imovel.NOMEBAIRRO)}</span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">CEP</span>
                        <span class="field-value">${formatVal(imovel.CEP)}</span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">Município / Estado</span>
                        <span class="field-value">${municipioEstado}</span>
                    </div>
                </div>
            </div>

            <!-- Bloco 3: Dados Cadastrais -->
            <div class="details-section">
                <div class="section-header">
                    <div class="section-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
                            <polyline points="2 17 12 22 22 17"></polyline>
                            <polyline points="2 12 12 17 22 12"></polyline>
                        </svg>
                        <span>Dados Cadastrais</span>
                    </div>
                </div>
                <div class="details-grid grid-cadastral">
                    <div class="detail-field">
                        <span class="field-label">Área do Terreno</span>
                        <span class="field-value highlight-val">${formatVal(imovel.AREA_DO_TERRENO13065, true)}</span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">Área da Unidade</span>
                        <span class="field-value highlight-val">${formatVal(imovel.AREA_DA_UNIDADE13066, true)}</span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">Área Total da Edificação</span>
                        <span class="field-value highlight-val">${formatVal(imovel.AREA_TOTAL_DA_EDIFICACAO13067, true)}</span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">Caracterização</span>
                        <span class="field-value">${formatVal(imovel.CARACTERIZACAO10035)}</span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">Ocupação</span>
                        <span class="field-value">${formatVal(imovel.OCUPACAO9025)}</span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">Utilização do Imóvel</span>
                        <span class="field-value">${formatVal(imovel.ULTILIZACAO_DO_IMOVEL9027)}</span>
                    </div>
                    <div class="detail-field">
                        <span class="field-label">Uso do Imóvel</span>
                        <span class="field-value">${formatVal(imovel.USO_DO_IMOVEL9032)}</span>
                    </div>
                </div>
            </div>

            <!-- Rodapé: Ação BCI -->
            <div class="details-footer">
                <button type="button" class="btn-espelho-bci" onclick="handleGerarEspelhoBCI('${escapeHtml(imovel.INSC_MUNIC || '')}')">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                    <span>Gerar Espelho Cadastral (BCI)</span>
                </button>
            </div>
        </div>
    `;

    if (modalBody) modalBody.innerHTML = html;
}

// Ação de geração e exibição do Espelho Cadastral (BCI)
function handleGerarEspelhoBCI(inscMunic) {
    if (!inscMunic) {
        alert('Inscrição municipal não informada para geração do BCI.');
        return;
    }

    if (typeof showLoading === 'function') showLoading();

    fetch(`api/identificar.php?action=bci&insc_munic=${encodeURIComponent(inscMunic)}`)
        .then(response => {
            if (!response.ok) throw new Error('Erro ao carregar dados do BCI.');
            return response.json();
        })
        .then(data => {
            if (typeof hideLoading === 'function') hideLoading();

            if (!data.found || !data.imovel) {
                alert(data.message || 'Dados do imóvel não localizados para geração do BCI.');
                return;
            }

            renderBciDocument(data.imovel, data.foto, data.croqui);
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro ao gerar BCI:', err);
            alert('Não foi possível gerar o Espelho Cadastral (BCI). Tente novamente.');
        });
}

// Inicializa o mini-mapa Leaflet para o Croqui de Localização
function initBciCroquiMap(croquiData) {
    const container = document.getElementById('bci-croqui-map');
    if (!container) return;

    if (bciCroquiMapInstance) {
        try {
            bciCroquiMapInstance.remove();
        } catch (e) {
            console.warn('Erro ao destruir mapa anterior do croqui:', e);
        }
        bciCroquiMapInstance = null;
    }

    if (!croquiData || !croquiData.has_geom || !croquiData.lat || !croquiData.lng) {
        container.innerHTML = `
            <div class="bci-croqui-empty">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="margin-bottom: 6px; color: #94a3b8;">
                    <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon>
                    <line x1="8" y1="2" x2="8" y2="18"></line>
                    <line x1="16" y1="6" x2="16" y2="22"></line>
                </svg>
                <span>Croqui de localização não disponível para esta inscrição no mapa vetorial.</span>
            </div>
        `;
        return;
    }

    bciCroquiMapInstance = L.map('bci-croqui-map', {
        attributionControl: false,
        zoomControl: false,
        dragging: false,
        touchZoom: false,
        doubleClickZoom: false,
        scrollWheelZoom: false,
        boxZoom: false,
        keyboard: false
    }).setView([croquiData.lat, croquiData.lng], 18);

    // Camada Base OSM (OpenStreetMap)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        subdomains: ['a', 'b', 'c']
    }).addTo(bciCroquiMapInstance);

    // Renderiza Lotes Vizinhos no Croqui
    if (croquiData.vizinhos_lotes && croquiData.vizinhos_lotes.length > 0) {
        croquiData.vizinhos_lotes.forEach(lote => {
            if (lote.is_selected) return;
            if (lote.geojson) {
                L.geoJSON(lote.geojson, {
                    style: {
                        color: '#64748b',
                        weight: 1.5,
                        opacity: 0.85,
                        fillColor: '#e2e8f0',
                        fillOpacity: 0.45
                    }
                }).addTo(bciCroquiMapInstance);
            }
        });
    }

    // Renderiza Edificações Vizinhas no Croqui
    if (croquiData.edificacoes && croquiData.edificacoes.length > 0) {
        croquiData.edificacoes.forEach(edif => {
            if (edif.geojson) {
                L.geoJSON(edif.geojson, {
                    style: {
                        color: '#475569',
                        weight: 1,
                        opacity: 0.9,
                        fillColor: '#94a3b8',
                        fillOpacity: 0.6
                    }
                }).addTo(bciCroquiMapInstance);
            }
        });
    }

    // Renderiza Lote Selecionado com destaque vermelho técnico
    let selectedLotLayer = null;
    if (croquiData.lote_selecionado) {
        selectedLotLayer = L.geoJSON(croquiData.lote_selecionado, {
            style: {
                color: '#dc2626',
                weight: 3.5,
                opacity: 1,
                fillColor: '#ef4444',
                fillOpacity: 0.45
            }
        }).addTo(bciCroquiMapInstance);
    }

    if (selectedLotLayer) {
        const bounds = selectedLotLayer.getBounds();
        if (bounds && bounds.isValid()) {
            bciCroquiMapInstance.fitBounds(bounds.pad(0.9), { animate: false });
        }
    }

    setTimeout(() => {
        if (bciCroquiMapInstance) {
            bciCroquiMapInstance.invalidateSize();
            if (selectedLotLayer) {
                const bounds = selectedLotLayer.getBounds();
                if (bounds && bounds.isValid()) {
                    bciCroquiMapInstance.fitBounds(bounds.pad(0.9), { animate: false });
                }
            }
        }
    }, 250);
}

// Renderiza a folha oficial do Boletim de Cadastro Imobiliário (BCI)
function renderBciDocument(imovel, foto, croqui) {
    const bciModal = document.getElementById('bci-modal');
    const bciModalBody = document.getElementById('bci-modal-body');
    const btnPrintBci = document.getElementById('btn-print-bci');
    const btnCloseBci = document.getElementById('bci-modal-btn-close');

    if (!bciModal || !bciModalBody) return;

    const now = new Date();
    const dataFormatada = now.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    const horaFormatada = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    const dataEmissaoCompleta = `${dataFormatada} às ${horaFormatada}`;

    // Prefeitura
    const nomeEmpresa = (imovel.NOMEDAEMPRESA || 'PREFEITURA MUNICIPAL DE').trim();
    const nomeMunicipio = (imovel.NOMEMUNICIPIO || '').trim();
    const prefeituraNome = [nomeEmpresa, nomeMunicipio].filter(Boolean).join(' ').replace(/\s+/g, ' ').trim();

    // Endereço e Localização
    const tipoLogra = (imovel.NOMETIPOLOGRA || '').trim();
    const nomeLogra = (imovel.NOMELOGRADOURO || '').trim();
    const logradouroCompleto = [tipoLogra, nomeLogra].filter(Boolean).join(' ') || 'Não informado';
    const numLogra = (imovel.NUMEROLOGRADOURO || 'S/N').trim();
    const compl = (imovel.COMPLEMENTO || '').trim();
    const bairro = (imovel.NOMEBAIRRO || '').trim();
    const cep = (imovel.CEP || '').trim();
    const estado = (imovel.SIGLA || imovel.NOMEDOESTADO || '').trim();
    const municipioEstado = [nomeMunicipio, estado].filter(Boolean).join(' - ') || 'Não informado';

    // Foto da Fachada
    const fotoUrl = (foto && foto.url) ? foto.url : `api/foto_imovel.php?insc_munic=${encodeURIComponent(imovel.INSC_MUNIC || '')}`;

    function getVal(val, suffix = '') {
        if (val === null || val === undefined || String(val).trim() === '') {
            return '<span class="bci-empty">Não informado</span>';
        }
        let s = escapeHtml(String(val).trim());
        if (suffix && !s.toLowerCase().includes(suffix.trim().toLowerCase())) {
            s += suffix;
        }
        return s;
    }

    const html = `
        <div class="bci-document-sheet" id="bci-printable-area">
            <!-- Cabeçalho Oficial do BCI -->
            <div class="bci-header">
                <div class="bci-header-logo">
                    <img src="img/logo-dm.png" alt="Logo da Prefeitura" class="bci-logo-img">
                </div>
                <div class="bci-header-info">
                    <h1 class="bci-prefeitura-name">${escapeHtml(prefeituraNome || 'PREFEITURA MUNICIPAL')}</h1>
                    <h2 class="bci-title">Boletim de Cadastro Imobiliário (BCI)</h2>
                    <div style="display:flex; align-items:center; gap:10px; margin-top:2px;">
                        <span class="bci-emission-date">Data de Emissão: <strong>${dataEmissaoCompleta}</strong></span>
                        ${(imovel.ORIGEM_DADOS === 'API_TRIBUTARIA_OFICIAL') ? `
                            <span style="display:inline-flex; align-items:center; gap:5px; background:#ecfdf5; color:#047857; font-size:11px; font-weight:700; padding:2px 8px; border-radius:12px; border:1px solid #a7f3d0;">
                                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#10b981;"></span>
                                Oficial API Tributária (PMDM)
                            </span>
                        ` : ''}
                    </div>
                </div>
            </div>

            <!-- Bloco 1: Identificação -->
            <div class="bci-block">
                <div class="bci-block-header">
                    <span class="bci-block-num">1</span>
                    <h3>Identificação</h3>
                </div>
                <div class="bci-grid bci-grid-4">
                    <div class="bci-field">
                        <span class="bci-label">Inscrição Municipal</span>
                        <span class="bci-val bci-val-bold bci-insc-badge">${getVal(imovel.INSC_MUNIC)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Contribuinte</span>
                        <span class="bci-val bci-val-bold">${getVal(imovel.NOMEDOCONTRIBUINTE)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">CPF / CNPJ</span>
                        <span class="bci-val">${getVal(imovel.CPF_CNPJ)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Situação Cadastral</span>
                        <span class="bci-val">${imovel.SITUACAO_CADASTRAL ? `<strong style="color: ${imovel.SITUACAO_CADASTRAL === 'Baixado' ? '#dc2626' : '#059669'};">${escapeHtml(imovel.SITUACAO_CADASTRAL)}</strong>` : '<span class="bci-empty">Não informado</span>'}</span>
                    </div>
                </div>
            </div>

            <!-- Bloco Especial: Memorial / Processo da API se houver -->
            ${imovel.MEMORIAL ? `
            <div class="bci-block" style="border-left: 4px solid #0284c7; background: #f0f9ff; margin-bottom: 12px; border-radius: 4px;">
                <div class="bci-block-header" style="background: transparent; border-bottom: 1px solid #e0f2fe; padding-bottom: 6px;">
                    <span class="bci-block-num" style="background: #0284c7;">M</span>
                    <h3 style="color: #0369a1;">Memorial Descritivo / Histórico Oficial (API Tributária)</h3>
                </div>
                <div style="padding: 10px 14px; font-size: 12px; color: #0c4a6e; line-height: 1.5; white-space: pre-wrap;">
                    ${escapeHtml(imovel.MEMORIAL)}
                    ${imovel.PROCESSO_EXCLUSAO ? `<div style="margin-top: 6px; font-weight: 600;">Processo de Baixa: ${escapeHtml(imovel.PROCESSO_EXCLUSAO)} (${escapeHtml(imovel.DATA_EXCLUSAO || '')})</div>` : ''}
                    ${imovel.PROCESSO_INCLUSAO ? `<div style="margin-top: 6px; font-weight: 600;">Processo de Inclusão: ${escapeHtml(imovel.PROCESSO_INCLUSAO)} (${escapeHtml(imovel.DATA_INCLUSAO || '')})</div>` : ''}
                </div>
            </div>
            ` : ''}

            <!-- Bloco 2: Localização do Imóvel e Fachada -->
            <div class="bci-block">
                <div class="bci-block-header">
                    <span class="bci-block-num">2</span>
                    <h3>Localização do Imóvel</h3>
                </div>
                <div class="bci-location-layout">
                    <!-- Dados de Endereço -->
                    <div class="bci-location-fields">
                        <div class="bci-grid bci-grid-3">
                            <div class="bci-field span-2">
                                <span class="bci-label">Logradouro</span>
                                <span class="bci-val bci-val-bold">${escapeHtml(logradouroCompleto)}</span>
                            </div>
                            <div class="bci-field">
                                <span class="bci-label">Número</span>
                                <span class="bci-val">${getVal(imovel.NUMEROLOGRADOURO)}</span>
                            </div>
                            <div class="bci-field">
                                <span class="bci-label">Complemento</span>
                                <span class="bci-val">${getVal(imovel.COMPLEMENTO)}</span>
                            </div>
                            <div class="bci-field">
                                <span class="bci-label">Bairro</span>
                                <span class="bci-val">${getVal(imovel.NOMEBAIRRO)}</span>
                            </div>
                            <div class="bci-field">
                                <span class="bci-label">CEP</span>
                                <span class="bci-val">${getVal(imovel.CEP)}</span>
                            </div>
                            <div class="bci-field span-3">
                                <span class="bci-label">Município / Estado</span>
                                <span class="bci-val">${escapeHtml(municipioEstado)}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Foto da Fachada -->
                    <div class="bci-facade-container">
                        <div class="bci-facade-frame" title="Clique para abrir a foto em tamanho real" onclick="window.open('${fotoUrl}', '_blank')">
                            <img src="${fotoUrl}" alt="Fachada do Imóvel" class="bci-facade-img" loading="lazy">
                            <div class="bci-facade-caption">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                                    <circle cx="12" cy="13" r="4"></circle>
                                </svg>
                                <span>Foto da Fachada</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bloco 3: Informações Gerais do Terreno -->
            <div class="bci-block">
                <div class="bci-block-header">
                    <span class="bci-block-num">3</span>
                    <h3>Informações Gerais do Terreno</h3>
                </div>
                <div class="bci-grid bci-grid-4">
                    <div class="bci-field">
                        <span class="bci-label">Ocupação</span>
                        <span class="bci-val">${getVal(imovel.OCUPACAO9025)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Situação do Terreno</span>
                        <span class="bci-val">${getVal(imovel.SITUACAO_DO_TERRENO9026)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Utilização do Imóvel</span>
                        <span class="bci-val">${getVal(imovel.ULTILIZACAO_DO_IMOVEL9027)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Pedologia</span>
                        <span class="bci-val">${getVal(imovel.PEDOLOGIA9028)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Topografia</span>
                        <span class="bci-val">${getVal(imovel.TOPOGRAFIA9029)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Limitação</span>
                        <span class="bci-val">${getVal(imovel.LIMITACAO9030)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Patrimônio</span>
                        <span class="bci-val">${getVal(imovel.PATRIMONIO9031)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Uso do Imóvel</span>
                        <span class="bci-val">${getVal(imovel.USO_DO_IMOVEL9032)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Imune IPTU</span>
                        <span class="bci-val">${getVal(imovel.IMUNE_ISENTO_IPTU_NAO_CALCULAR9033)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Isento TSU</span>
                        <span class="bci-val">${getVal(imovel.ISENTO_TSU9034)}</span>
                    </div>
                </div>
            </div>

            <!-- Bloco 4: Informações Gerais da Edificação -->
            <div class="bci-block">
                <div class="bci-block-header">
                    <span class="bci-block-num">4</span>
                    <h3>Informações Gerais da Edificação</h3>
                </div>
                <div class="bci-grid bci-grid-4">
                    <div class="bci-field">
                        <span class="bci-label">Caracterização</span>
                        <span class="bci-val">${getVal(imovel.CARACTERIZACAO10035)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Revestimento Externo</span>
                        <span class="bci-val">${getVal(imovel.REVESTIMENTO_EXTERNO10036)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Situação da Construção</span>
                        <span class="bci-val">${getVal(imovel.SITUACAO_CONSTRUCAO10046)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Fachada</span>
                        <span class="bci-val">${getVal(imovel.FACHADA10047)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Cobertura</span>
                        <span class="bci-val">${getVal(imovel.COBERTURA10039)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Situação do Imóvel</span>
                        <span class="bci-val">${getVal(imovel.SITUACAO_DO_IMOVEL10048)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Posição</span>
                        <span class="bci-val">${getVal(imovel.POSICAO10041)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Tipo de Construção</span>
                        <span class="bci-val">${getVal(imovel.TIPO_DE_CONSTRUCAO10042)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Estrutura</span>
                        <span class="bci-val">${getVal(imovel.ESTRUTURA10043)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Estado de Conservação</span>
                        <span class="bci-val">${getVal(imovel.ESTADO_DE_CONSERVACAO10044)}</span>
                    </div>
                </div>
            </div>

            <!-- Bloco 5: Serviços Urbanos no Logradouro -->
            <div class="bci-block">
                <div class="bci-block-header">
                    <span class="bci-block-num">5</span>
                    <h3>Serviços Urbanos no Logradouro</h3>
                </div>
                <div class="bci-grid bci-grid-4">
                    <div class="bci-field">
                        <span class="bci-label">Esgoto</span>
                        <span class="bci-val">${getVal(imovel.ESGOTO11049)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Água</span>
                        <span class="bci-val">${getVal(imovel.AGUA11050)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Iluminação Pública</span>
                        <span class="bci-val">${getVal(imovel.ILUMINACAO_PUBLICA11051)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Calçamento</span>
                        <span class="bci-val">${getVal(imovel.CALCAMENTO11052)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Limpeza Pública</span>
                        <span class="bci-val">${getVal(imovel.LIMPEZA_PUBLICA11053)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Rede Telefônica</span>
                        <span class="bci-val">${getVal(imovel.REDE_TELEFONICA11055)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Guias e Sarjetas</span>
                        <span class="bci-val">${getVal(imovel.GUIAS_SARJETAS11056)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Coleta de Lixo</span>
                        <span class="bci-val">${getVal(imovel.COLETA_DE_LIXO11057 || imovel.LIMPEZA_PUBLICA11053)}</span>
                    </div>
                </div>
            </div>

            <!-- Bloco 6: Serviços Urbanos na Unidade -->
            <div class="bci-block">
                <div class="bci-block-header">
                    <span class="bci-block-num">6</span>
                    <h3>Serviços Urbanos na Unidade</h3>
                </div>
                <div class="bci-grid bci-grid-4">
                    <div class="bci-field">
                        <span class="bci-label">Esgoto</span>
                        <span class="bci-val">${getVal(imovel.ESGOTO12001)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Água</span>
                        <span class="bci-val">${getVal(imovel.AGUA12002)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Água de Poço</span>
                        <span class="bci-val">${getVal(imovel.AGUA_DE_POCO12003)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Luz / Força</span>
                        <span class="bci-val">${getVal(imovel.LUZ_FORCA12004)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Telefone</span>
                        <span class="bci-val">${getVal(imovel.TELEFONE12005)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Coleta de Lixo</span>
                        <span class="bci-val">${getVal(imovel.LIXO12006)}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Fossa</span>
                        <span class="bci-val">${getVal(imovel.FOSSA12007)}</span>
                    </div>
                </div>
            </div>

            <!-- Bloco 7: Dimensões da Unidade -->
            <div class="bci-block">
                <div class="bci-block-header">
                    <span class="bci-block-num">7</span>
                    <h3>Dimensões da Unidade</h3>
                </div>
                <div class="bci-grid bci-grid-4">
                    <div class="bci-field">
                        <span class="bci-label">Área do Terreno</span>
                        <span class="bci-val bci-val-bold">${getVal(imovel.AREA_DO_TERRENO13065, ' m²')}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Área da Unidade</span>
                        <span class="bci-val bci-val-bold">${getVal(imovel.AREA_DA_UNIDADE13066, ' m²')}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Área Total da Edificação</span>
                        <span class="bci-val bci-val-bold">${getVal(imovel.AREA_TOTAL_DA_EDIFICACAO13067, ' m²')}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Testada do Imóvel</span>
                        <span class="bci-val">${getVal(imovel.TESTADA_DO_IMOVEL13068, ' m')}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Testada da Iluminação</span>
                        <span class="bci-val">${getVal(imovel.TESTADA_ILUMINACAO13069, ' m')}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Testada de Calçamento</span>
                        <span class="bci-val">${getVal(imovel.TESTADA_CALCAMENTO13070, ' m')}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Testada Limpeza</span>
                        <span class="bci-val">${getVal(imovel.TESTADA_LIMPEZA13071, ' m')}</span>
                    </div>
                    <div class="bci-field">
                        <span class="bci-label">Número de Unidades</span>
                        <span class="bci-val">${getVal(imovel.N_DE_UNIDADES13072)}</span>
                    </div>
                </div>
            </div>

            <!-- Bloco 8: Mapa de Localização (Croqui) -->
            <div class="bci-block bci-croqui-block">
                <div class="bci-block-header">
                    <span class="bci-block-num">8</span>
                    <h3>Mapa de Localização (Croqui)</h3>
                </div>
                <div class="bci-croqui-wrapper">
                    <div id="bci-croqui-map-container" class="bci-croqui-map-container">
                        <div id="bci-croqui-map" class="bci-croqui-map"></div>
                        <div class="bci-north-arrow" title="Norte Cartográfico">
                            <svg width="26" height="26" viewBox="0 0 40 40" fill="none">
                                <circle cx="20" cy="20" r="18" fill="#ffffff" stroke="#334155" stroke-width="1.5"/>
                                <polygon points="20,5 25,20 20,16" fill="#ef4444"/>
                                <polygon points="20,5 15,20 20,16" fill="#b91c1c"/>
                                <polygon points="20,35 25,20 20,24" fill="#94a3b8"/>
                                <polygon points="20,35 15,20 20,24" fill="#64748b"/>
                                <text x="20" y="4" font-size="8" font-weight="900" text-anchor="middle" fill="#0f172a">N</text>
                            </svg>
                        </div>
                        <div class="bci-croqui-legend">
                            <div class="croqui-legend-item"><span class="legend-color-box selected-lot"></span> Imóvel Selecionado</div>
                            <div class="croqui-legend-item"><span class="legend-color-box other-lots"></span> Lotes Vizinhos</div>
                            <div class="croqui-legend-item"><span class="legend-color-box buildings"></span> Edificações</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Rodapé Oficial do Documento -->
            <div class="bci-footer">
                <div class="bci-footer-generated">Documento gerado em: <strong>${dataEmissaoCompleta}</strong></div>
                <div class="bci-footer-system">Sistema de Informações Geográficas - GeoPortal Módulo BCI</div>
            </div>
        </div>
    `;

    bciModalBody.innerHTML = html;
    bciModal.classList.add('show');

    // Inicializa o mini-mapa com o lote destacado e vizinhança
    initBciCroquiMap(croqui);

    // Vincula botão de impressão
    if (btnPrintBci) {
        btnPrintBci.onclick = function () {
            window.print();
        };
    }

    // Vincula fechamento do modal
    if (btnCloseBci) {
        btnCloseBci.onclick = function () {
            bciModal.classList.remove('show');
            if (bciCroquiMapInstance) {
                bciCroquiMapInstance.remove();
                bciCroquiMapInstance = null;
            }
        };
    }

    bciModal.onclick = function (e) {
        if (e.target === bciModal) {
            bciModal.classList.remove('show');
            if (bciCroquiMapInstance) {
                bciCroquiMapInstance.remove();
                bciCroquiMapInstance = null;
            }
        }
    };
}

// Abre o espelho cadastral (BCI) a partir da inscrição
function openLoteBCI(insc) {
    if (!insc || insc === '-') {
        alert('Inscrição não localizada para este lote.');
        return;
    }
    handleGerarEspelhoBCI(insc);
}

// Vincula funções ao objeto window para acesso global e inline no HTML
window.identifyModeActive = identifyModeActive;
window.toggleIdentifyTool = toggleIdentifyTool;
window.executeIdentify = executeIdentify;
window.clearIdentifyHighlight = clearIdentifyHighlight;
window.showIdentifyUnitsList = showIdentifyUnitsList;
window.fetchAndShowUnitDetails = fetchAndShowUnitDetails;
window.renderUnitDetails = renderUnitDetails;
window.handleGerarEspelhoBCI = handleGerarEspelhoBCI;
window.renderBciDocument = renderBciDocument;
window.openLoteBCI = openLoteBCI;
