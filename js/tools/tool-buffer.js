/**
 * ==========================================================================
 * FERRAMENTA DE SIMULAÇÃO DE INTERVENÇÃO / BUFFER OPERACIONAL (Lotes e Edificações)
 * Módulo: js/tools/tool-buffer.js
 * ==========================================================================
 */

let bufferModeActive = false;
let bufferCenterPoint = null;
let currentBufferRadius = 100; // metros (padrão inicial)
let currentBufferType = 'lotes'; // 'lotes', 'edificacoes', 'ambos'
let bufferCircle = null;
let bufferMarker = null;
let bufferHighlightLayer = null;
let bufferAnalysisTimeout = null;
let lastBufferAnalysisData = null;

const btnBufferTool = document.getElementById('btn-buffer-tool');
const bufferPanel = document.getElementById('buffer-panel');
const btnCloseBufferPanel = document.getElementById('btn-close-buffer-panel');
const bufferInstructionText = document.getElementById('buffer-instruction-text');
const bufferInstructionBadge = document.getElementById('buffer-instruction');
const bufferRadiusSlider = document.getElementById('buffer-radius-slider');
const bufferRadiusInput = document.getElementById('buffer-radius-input');
const btnClearBuffer = document.getElementById('btn-clear-buffer');
const btnRepositionBuffer = document.getElementById('btn-reposition-buffer');
const bufferPresetBtns = document.querySelectorAll('.buffer-preset-btn');
const bufferTypeBtns = document.querySelectorAll('.buffer-type-btn');
const bufferResultsContainer = document.getElementById('buffer-results-container');

if (btnBufferTool) {
    btnBufferTool.addEventListener('click', function (e) {
        e.preventDefault();
        toggleBufferTool();
    });
    L.DomEvent.disableClickPropagation(btnBufferTool);
}

if (bufferPanel) {
    L.DomEvent.disableClickPropagation(bufferPanel);
    L.DomEvent.disableScrollPropagation(bufferPanel);
}

if (btnCloseBufferPanel) {
    btnCloseBufferPanel.addEventListener('click', function (e) {
        e.preventDefault();
        if (bufferModeActive) {
            toggleBufferTool();
        }
    });
}

function toggleBufferTool() {
    bufferModeActive = !bufferModeActive;
    const mapContainer = document.getElementById('map');

    if (bufferModeActive) {
        if (btnBufferTool) btnBufferTool.classList.add('buffer-active');
        if (bufferPanel) bufferPanel.style.display = 'flex';
        if (mapContainer) mapContainer.classList.add('map-buffer-cursor');

        // Desativa ferramentas concorrentes
        if (typeof filtroTerrenoActive !== 'undefined' && filtroTerrenoActive && typeof toggleFiltroTerrenoTool === 'function') toggleFiltroTerrenoTool(false);
        if (typeof selecaoToolActive !== 'undefined' && selecaoToolActive && typeof toggleSelecaoEspacialTool === 'function') toggleSelecaoEspacialTool(false);
        if (typeof identifyModeActive !== 'undefined' && identifyModeActive && typeof btnIdentify !== 'undefined' && btnIdentify) {
            btnIdentify.click();
        }
        if (typeof editModeActive !== 'undefined' && editModeActive && typeof toggleEditTool === 'function') {
            toggleEditTool(false);
        }
        if (typeof measureModeActive !== 'undefined' && measureModeActive) {
            const btnDist = document.getElementById('btn-measure-dist');
            const btnArea = document.getElementById('btn-measure-area');
            if (btnDist) btnDist.classList.remove('active');
            if (btnArea) btnArea.classList.remove('active');
            if (mapContainer) mapContainer.classList.remove('map-measure-cursor');
            measureModeActive = false;
            if (typeof measureType !== 'undefined') measureType = null;
            if (typeof stopMeasure === 'function') stopMeasure();
        }

        updateBufferUI();
    } else {
        if (btnBufferTool) btnBufferTool.classList.remove('buffer-active');
        if (bufferPanel) bufferPanel.style.display = 'none';
        if (mapContainer) mapContainer.classList.remove('map-buffer-cursor');
        clearBufferIntervention();
    }
}

function setBufferCenter(latlng) {
    if (!latlng) return;
    bufferCenterPoint = latlng;

    // Criação ou reposicionamento do marcador de epicentro
    if (!bufferMarker) {
        const customIcon = L.divIcon({
            className: 'buffer-center-marker',
            html: `
                <div class="buffer-center-icon" title="Arraste para reposicionar o centro da análise espacial">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"></circle>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </div>
            `,
            iconSize: [26, 26],
            iconAnchor: [13, 13]
        });

        bufferMarker = L.marker(latlng, {
            icon: customIcon,
            draggable: true,
            zIndexOffset: 1000
        }).addTo(map);

        bufferMarker.on('drag', function (e) {
            bufferCenterPoint = e.target.getLatLng();
            if (bufferCircle) {
                bufferCircle.setLatLng(bufferCenterPoint);
            }
            updateBufferUI();
        });

        bufferMarker.on('dragend', function (e) {
            bufferCenterPoint = e.target.getLatLng();
            if (bufferCircle) {
                bufferCircle.setLatLng(bufferCenterPoint);
            }
            updateBufferUI();
            scheduleBufferAnalysis();
        });
    } else {
        bufferMarker.setLatLng(latlng);
    }

    // Criação ou reposicionamento do círculo de raio
    if (!bufferCircle) {
        bufferCircle = L.circle(latlng, {
            radius: currentBufferRadius,
            color: '#06b6d4',
            weight: 2.5,
            dashArray: '6, 6',
            fillColor: '#06b6d4',
            fillOpacity: 0.15,
            interactive: false
        }).addTo(map);
    } else {
        bufferCircle.setLatLng(latlng);
        bufferCircle.setRadius(currentBufferRadius);
    }

    updateBufferUI();
    scheduleBufferAnalysis();
}

function updateBufferRadius(newRadius) {
    currentBufferRadius = Math.max(5, Math.min(1000, Number(newRadius) || 100));

    if (bufferRadiusSlider) bufferRadiusSlider.value = currentBufferRadius;
    if (bufferRadiusInput) bufferRadiusInput.value = currentBufferRadius;

    // Atualiza classes ativas dos botões de preset
    bufferPresetBtns.forEach(btn => {
        if (Number(btn.dataset.radius) === currentBufferRadius) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    if (bufferCircle) {
        bufferCircle.setRadius(currentBufferRadius);
    }

    updateBufferUI();
    if (bufferCenterPoint) {
        scheduleBufferAnalysis();
    }
}

function updateBufferUI() {
    if (!bufferInstructionBadge || !bufferInstructionText) return;

    if (bufferCenterPoint) {
        bufferInstructionBadge.classList.add('active-point');
        const lat = bufferCenterPoint.lat.toFixed(5);
        const lng = bufferCenterPoint.lng.toFixed(5);
        bufferInstructionText.innerHTML = `Centro definido (<strong>${lat}, ${lng}</strong>) &bull; Raio: <strong>${currentBufferRadius} m</strong><br><span style="font-size: 0.68rem; color: #94a3b8;">Arraste o marcador ou clique no mapa para reposicionar.</span>`;
    } else {
        bufferInstructionBadge.classList.remove('active-point');
        bufferInstructionText.textContent = 'Clique no mapa para posicionar o centro do raio de análise';
    }
}

function scheduleBufferAnalysis() {
    clearTimeout(bufferAnalysisTimeout);
    bufferAnalysisTimeout = setTimeout(executeBufferAnalysis, 250);
}

function executeBufferAnalysis() {
    if (!bufferCenterPoint || !bufferResultsContainer) return;

    bufferResultsContainer.style.display = 'flex';
    bufferResultsContainer.innerHTML = `
        <div class="buffer-loading-spinner">
            <div class="spinner"></div>
            <span>Analisando lotes e edificações no raio...</span>
        </div>
    `;

    const lat = bufferCenterPoint.lat;
    const lng = bufferCenterPoint.lng;
    const url = `api/buffer_intervencao.php?lat=${lat}&lng=${lng}&raio=${currentBufferRadius}&tipo=${currentBufferType}`;

    fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro ao consultar buffer de lotes e edificações.');
            }
            return response.json();
        })
        .then(data => {
            lastBufferAnalysisData = data;
            renderBufferResults(data);
            renderBufferHighlights(data.geojson_destaques);
        })
        .catch(err => {
            console.error('Erro na análise do buffer:', err);
            bufferResultsContainer.innerHTML = `
                <div style="font-size: 0.74rem; color: #ef4444; padding: 10px; text-align: center;">
                    Ocorreu um erro ao calcular os dados da área de influência. Tente novamente.
                </div>
            `;
        });
}

function renderBufferResults(data) {
    if (!bufferResultsContainer) return;

    const sumario = data.sumario || {};
    const lotes = data.lotes || [];
    const edificacoes = data.edificacoes || [];
    const tipo = (data.params && data.params.tipo) ? data.params.tipo : currentBufferType;

    let html = '';

    // 1. KPIs
    html += `<div class="buffer-kpis">`;

    if (tipo === 'lotes' || tipo === 'ambos') {
        html += `
            <div class="buffer-kpi-card">
                <span class="buffer-kpi-title">Lotes Interceptados</span>
                <span class="buffer-kpi-value kpi-cyan">${sumario.total_lotes || 0}</span>
                <span style="font-size: 0.66rem; color: #94a3b8;">${sumario.lotes_com_cadastro || 0} com cadastro</span>
            </div>
            <div class="buffer-kpi-card">
                <span class="buffer-kpi-title">Área Total Interceptada</span>
                <span class="buffer-kpi-value">${(sumario.area_total_lotes_m2 || 0).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} m²</span>
                <span style="font-size: 0.66rem; color: #94a3b8;">${sumario.lotes_sem_cadastro || 0} não cadastrados</span>
            </div>
        `;
    }

    if (tipo === 'edificacoes' || tipo === 'ambos') {
        html += `
            <div class="buffer-kpi-card">
                <span class="buffer-kpi-title">Edificações no Raio</span>
                <span class="buffer-kpi-value kpi-amber">${sumario.total_edificacoes || 0}</span>
                <span style="font-size: 0.66rem; color: #94a3b8;">${sumario.edificacoes_com_cadastro || 0} com cadastro</span>
            </div>
            <div class="buffer-kpi-card">
                <span class="buffer-kpi-title">Atendimento Saneamento</span>
                <span class="buffer-kpi-value kpi-green" style="font-size: 0.92rem; margin-top: 2px;">
                    Água: ${sumario.edificacoes_com_agua || 0} &bull; Esgoto: ${sumario.edificacoes_com_esgoto || 0}
                </span>
                <span style="font-size: 0.66rem; color: #94a3b8;">${sumario.edificacoes_sem_cadastro || 0} sem cadastro</span>
            </div>
        `;
    }

    html += `</div>`;

    // 2. Detalhamento de Lotes Interceptados
    if ((tipo === 'lotes' || tipo === 'ambos') && lotes.length > 0) {
        html += `
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <div style="font-size: 0.72rem; font-weight: 600; color: var(--text-color); display: flex; justify-content: space-between; align-items: center;">
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 2px; background: #06b6d4;"></span>
                        Lotes no Raio
                    </span>
                    <span style="color: #06b6d4; font-weight: 700;">${lotes.length}</span>
                </div>
                <div class="buffer-customers-table-wrapper">
                    <table class="buffer-customers-table">
                        <thead>
                            <tr>
                                <th>Inscrição</th>
                                <th>Contribuinte</th>
                                <th>Bairro</th>
                                <th>Área Int.</th>
                                <th>Dist.</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        lotes.forEach(l => {
            const hasCad = l.has_cadastro;
            const badgeClass = hasCad ? 'badge-cadastrado-sm' : 'badge-nao-cadastrado-sm';
            const insc = (typeof escapeDomString === 'function') ? escapeDomString(l.insc_munic || '-') : (l.insc_munic || '-');
            const contrib = (typeof escapeDomString === 'function') ? escapeDomString(l.contribuinte || '-') : (l.contribuinte || '-');
            const bairro = (typeof escapeDomString === 'function') ? escapeDomString(l.bairro || '-') : (l.bairro || '-');
            const area = l.area_interceptada_m2 ? `${Number(l.area_interceptada_m2).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} m²` : '-';
            const dist = `${l.distancia_centro_m} m`;

            html += `
                <tr title="Inscrição: ${insc} | Contribuinte: ${contrib} | Logradouro: ${(typeof escapeDomString === 'function') ? escapeDomString(l.logradouro || '-') : (l.logradouro || '-')}">
                    <td>
                        <strong class="${badgeClass}">${insc}</strong>
                    </td>
                    <td>${contrib}</td>
                    <td>${bairro}</td>
                    <td>${area}</td>
                    <td>${dist}</td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // 3. Detalhamento de Edificações Interceptadas
    if ((tipo === 'edificacoes' || tipo === 'ambos') && edificacoes.length > 0) {
        html += `
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <div style="font-size: 0.72rem; font-weight: 600; color: var(--text-color); display: flex; justify-content: space-between; align-items: center;">
                    <span style="display: flex; align-items: center; gap: 5px;">
                        <span style="display: inline-block; width: 8px; height: 8px; border-radius: 2px; background: #f59e0b;"></span>
                        Edificações no Raio
                    </span>
                    <span style="color: #f59e0b; font-weight: 700;">${edificacoes.length}</span>
                </div>
                <div class="buffer-customers-table-wrapper">
                    <table class="buffer-customers-table">
                        <thead>
                            <tr>
                                <th>Inscrição</th>
                                <th>Contribuinte</th>
                                <th>Tipo / Est.</th>
                                <th>Água / Esgoto</th>
                                <th>Dist.</th>
                            </tr>
                        </thead>
                        <tbody>
        `;

        edificacoes.forEach(ed => {
            const hasCad = ed.has_cadastro;
            const badgeClass = hasCad ? 'badge-cadastrado-sm' : 'badge-nao-cadastrado-sm';
            const insc = (typeof escapeDomString === 'function') ? escapeDomString(ed.insc_munic || '-') : (ed.insc_munic || '-');
            const contrib = (typeof escapeDomString === 'function') ? escapeDomString(ed.contribuinte || '-') : (ed.contribuinte || '-');
            const tipoEstrutura = `${(typeof escapeDomString === 'function') ? escapeDomString(ed.tipo_construcao || '-') : (ed.tipo_construcao || '-')}`;
            const saneamento = `A: ${(typeof escapeDomString === 'function') ? escapeDomString(ed.agua || '-') : (ed.agua || '-')} | E: ${(typeof escapeDomString === 'function') ? escapeDomString(ed.esgoto || '-') : (ed.esgoto || '-')}`;
            const dist = `${ed.distancia_centro_m} m`;

            html += `
                <tr title="Inscrição: ${insc} | Contribuinte: ${contrib} | Conservação: ${(typeof escapeDomString === 'function') ? escapeDomString(ed.estado_conservacao || '-') : (ed.estado_conservacao || '-')} | Logradouro: ${(typeof escapeDomString === 'function') ? escapeDomString(ed.logradouro || '-') : (ed.logradouro || '-')}">
                    <td>
                        <strong class="${badgeClass}">${insc}</strong>
                    </td>
                    <td>${contrib}</td>
                    <td>${tipoEstrutura}</td>
                    <td>${saneamento}</td>
                    <td>${dist}</td>
                </tr>
            `;
        });

        html += `
                        </tbody>
                    </table>
                </div>
            </div>
        `;
    }

    // Se nenhum lote e nenhuma edificação encontrados
    if (lotes.length === 0 && edificacoes.length === 0) {
        html += `
            <div style="font-size: 0.72rem; color: var(--text-muted); font-style: italic; text-align: center; padding: 8px;">
                Nenhum lote ou edificação interceptado no raio selecionado.
            </div>
        `;
    }

    // 4. Botão de Exportação CSV
    if (lotes.length > 0 || edificacoes.length > 0) {
        html += `
            <button type="button" id="btn-export-buffer-csv" class="buffer-btn-export" title="Exportar dados para Excel/CSV">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                    <polyline points="7 10 12 15 17 10"></polyline>
                    <line x1="12" y1="15" x2="12" y2="3"></line>
                </svg>
                <span>Exportar Relatório Espacial (CSV)</span>
            </button>
        `;
    }

    bufferResultsContainer.innerHTML = html;

    // Vincula evento de exportação
    const btnExport = document.getElementById('btn-export-buffer-csv');
    if (btnExport) {
        btnExport.addEventListener('click', exportBufferToCSV);
    }
}

function renderBufferHighlights(geojson) {
    if (bufferHighlightLayer) {
        map.removeLayer(bufferHighlightLayer);
        bufferHighlightLayer = null;
    }

    if (!geojson || !geojson.features || geojson.features.length === 0) return;

    bufferHighlightLayer = L.geoJSON(geojson, {
        style: function (feature) {
            const tipo = feature.properties.tipo_camada;
            if (tipo === 'geo_lotes_dm') {
                return {
                    color: '#06b6d4', // Ciano luminoso para lotes
                    weight: 2.5,
                    dashArray: '4, 4',
                    fillColor: '#06b6d4',
                    fillOpacity: 0.35
                };
            } else if (tipo === 'geo_edificacoes_dm') {
                return {
                    color: '#f59e0b', // Âmbar luminoso para edificações
                    weight: 2.5,
                    fillColor: '#f59e0b',
                    fillOpacity: 0.55
                };
            }
            return {
                color: '#38bdf8',
                weight: 2,
                opacity: 0.9
            };
        },
        onEachFeature: function (feature, layer) {
            const props = feature.properties || {};
            let desc = '';

            if (props.tipo_camada === 'geo_lotes_dm') {
                const statusColor = props.has_cadastro ? '#34d399' : '#f87171';
                desc = `
                    <div style="font-family: inherit; font-size: 0.78rem; line-height: 1.4;">
                        <div style="font-weight: 700; color: #06b6d4; font-size: 0.85rem; margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 3px;">
                            Lote Interceptado (Buffer)
                        </div>
                        <div><strong>Inscrição:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.insc_munic || '-') : (props.insc_munic || '-')}</div>
                        <div><strong>Status:</strong> <span style="color: ${statusColor}; font-weight: 600;">${(typeof escapeDomString === 'function') ? escapeDomString(props.status_cadastral || '-') : (props.status_cadastral || '-')}</span></div>
                        <div><strong>Contribuinte:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.contribuinte || '-') : (props.contribuinte || '-')}</div>
                        <div><strong>Logradouro:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.logradouro || '-') : (props.logradouro || '-')}</div>
                        <div><strong>Bairro:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.bairro || '-') : (props.bairro || '-')}</div>
                        <div><strong>Área Terreno:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.area_terreno || '-') : (props.area_terreno || '-')} m²</div>
                    </div>
                `;
            } else if (props.tipo_camada === 'geo_edificacoes_dm') {
                const statusColor = props.has_cadastro ? '#34d399' : '#f87171';
                desc = `
                    <div style="font-family: inherit; font-size: 0.78rem; line-height: 1.4;">
                        <div style="font-weight: 700; color: #f59e0b; font-size: 0.85rem; margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 3px;">
                            Edificação Interceptada (Buffer)
                        </div>
                        <div><strong>Inscrição:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.insc_munic || '-') : (props.insc_munic || '-')}</div>
                        <div><strong>Status:</strong> <span style="color: ${statusColor}; font-weight: 600;">${(typeof escapeDomString === 'function') ? escapeDomString(props.status_cadastral || '-') : (props.status_cadastral || '-')}</span></div>
                        <div><strong>Contribuinte:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.contribuinte || '-') : (props.contribuinte || '-')}</div>
                        <div><strong>Logradouro:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.logradouro || '-') : (props.logradouro || '-')}</div>
                        <div><strong>Bairro:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.bairro || '-') : (props.bairro || '-')}</div>
                        <div><strong>Tipo / Cons.:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.tipo_construcao || '-') : (props.tipo_construcao || '-')} / ${(typeof escapeDomString === 'function') ? escapeDomString(props.conservacao || '-') : (props.conservacao || '-')}</div>
                        <div><strong>Água:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.agua || '-') : (props.agua || '-')} | <strong>Esgoto:</strong> ${(typeof escapeDomString === 'function') ? escapeDomString(props.esgoto || '-') : (props.esgoto || '-')}</div>
                    </div>
                `;
            }

            if (desc) {
                layer.bindPopup(desc);
            }
        }
    }).addTo(map);
}

function exportBufferToCSV() {
    if (!lastBufferAnalysisData) return;

    const lotes = lastBufferAnalysisData.lotes || [];
    const edificacoes = lastBufferAnalysisData.edificacoes || [];
    const raio = lastBufferAnalysisData.params ? lastBufferAnalysisData.params.raio_m : currentBufferRadius;
    const tipo = lastBufferAnalysisData.params ? lastBufferAnalysisData.params.tipo : currentBufferType;

    let csv = '\uFEFF'; // UTF-8 BOM para compatibilidade com Microsoft Excel
    csv += `RELATÓRIO DE ANÁLISE ESPACIAL (ÁREA DE INFLUÊNCIA / BUFFER) - PREFEITURA / SAAE\n`;
    csv += `Data da Consulta;${new Date().toLocaleString('pt-BR')}\n`;
    csv += `Tipo de Análise;${tipo.toUpperCase()}\n`;
    csv += `Raio de Influência;${raio} metros\n`;
    csv += `Total de Lotes Interceptados;${lotes.length}\n`;
    csv += `Total de Edificações no Raio;${edificacoes.length}\n\n`;

    // 1. Seção de Lotes
    if (lotes.length > 0) {
        csv += `--- LOTES INTERCEPTADOS ---\n`;
        csv += `Inscrição Imobiliária;Status Cadastral;Contribuinte;Logradouro;Bairro;Utilização;Ocupação;Área do Terreno;Área Interceptada (m²);Distância do Centro (m)\n`;
        lotes.forEach(l => {
            csv += `"${l.insc_munic || '-'}";"${l.status_cadastral || '-'}";"${l.contribuinte || '-'}";"${l.logradouro || '-'}";"${l.bairro || '-'}";"${l.utilizacao || '-'}";"${l.ocupacao || '-'}";"${l.area_terreno || '-'}";"${String(l.area_interceptada_m2 || '0').replace('.', ',')}";"${String(l.distancia_centro_m || '0').replace('.', ',')}"\n`;
        });
        csv += `\n`;
    }

    // 2. Seção de Edificações
    if (edificacoes.length > 0) {
        csv += `--- EDIFICAÇÕES INTERCEPTADAS ---\n`;
        csv += `Inscrição Imobiliária;Status Cadastral;Contribuinte;Logradouro;Bairro;Tipo Construção;Estrutura;Estado Conservação;Situação Imóvel;Água;Esgoto;Área Unidade;Área Total Edificação;Distância do Centro (m)\n`;
        edificacoes.forEach(ed => {
            csv += `"${ed.insc_munic || '-'}";"${ed.status_cadastral || '-'}";"${ed.contribuinte || '-'}";"${ed.logradouro || '-'}";"${ed.bairro || '-'}";"${ed.tipo_construcao || '-'}";"${ed.estrutura || '-'}";"${ed.estado_conservacao || '-'}";"${ed.situacao_imovel || '-'}";"${ed.agua || '-'}";"${ed.esgoto || '-'}";"${ed.area_unidade || '-'}";"${ed.area_total_edificacao || '-'}";"${String(ed.distancia_centro_m || '0').replace('.', ',')}"\n`;
        });
    }

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `buffer_lotes_edificacoes_raio_${raio}m_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}

function clearBufferIntervention() {
    if (bufferMarker) {
        map.removeLayer(bufferMarker);
        bufferMarker = null;
    }
    if (bufferCircle) {
        map.removeLayer(bufferCircle);
        bufferCircle = null;
    }
    if (bufferHighlightLayer) {
        map.removeLayer(bufferHighlightLayer);
        bufferHighlightLayer = null;
    }
    bufferCenterPoint = null;
    lastBufferAnalysisData = null;
    if (bufferResultsContainer) {
        bufferResultsContainer.style.display = 'none';
        bufferResultsContainer.innerHTML = '';
    }
    updateBufferUI();
}

// Configuração dos eventos do slider, inputs e botões de tipo de rede
if (bufferRadiusSlider) {
    bufferRadiusSlider.addEventListener('input', function () {
        updateBufferRadius(this.value);
    });
}

if (bufferRadiusInput) {
    bufferRadiusInput.addEventListener('change', function () {
        updateBufferRadius(this.value);
    });
}

bufferPresetBtns.forEach(btn => {
    btn.addEventListener('click', function () {
        const rad = Number(this.dataset.radius);
        updateBufferRadius(rad);
    });
});

bufferTypeBtns.forEach(btn => {
    btn.addEventListener('click', function () {
        bufferTypeBtns.forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        currentBufferType = this.dataset.type;
        if (bufferCenterPoint) {
            scheduleBufferAnalysis();
        }
    });
});

if (btnClearBuffer) {
    btnClearBuffer.addEventListener('click', function () {
        clearBufferIntervention();
    });
}

if (btnRepositionBuffer) {
    btnRepositionBuffer.addEventListener('click', function () {
        if (bufferInstructionBadge) bufferInstructionBadge.classList.remove('active-point');
        if (bufferInstructionText) bufferInstructionText.textContent = 'Clique no novo local desejado no mapa';
    });
}

// Exportações Globais
window.toggleBufferTool = toggleBufferTool;
window.setBufferCenter = setBufferCenter;
window.updateBufferRadius = updateBufferRadius;
window.clearBufferIntervention = clearBufferIntervention;
