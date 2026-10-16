/**
 * ==========================================================================
 * FERRAMENTA DE ANÁLISE DE DIVERGÊNCIA DE ÁREAS (GIS vs TRIBUTÁRIO > 10%)
 * Módulo: js/tools/tool-divergencia.js
 * ==========================================================================
 */

let divergenciaToolActive = false;
let currentDivergenciaSituacao = 'DIVERGÊNCIA';
let currentDivergenciaPage = 1;
let currentDivergenciaLimit = 50;
let currentDivergenciaSearch = '';
let divergenciaSearchDebounce = null;
let divergenciaGeojsonLayer = null;
let isDivergenciaLayerVisible = false;
let divergenciaHighlightMarker = null;
let divergenciaLegendControl = null;

const btnDivergenciaTool = document.getElementById('btn-divergencia-tool');
const divergenciaPanel = document.getElementById('divergencia-panel');
const btnCloseDivergenciaPanel = document.getElementById('btn-close-divergencia-panel');
const divergenciaSituacaoSelect = document.getElementById('divergencia-situacao-select');
const divergenciaSearchInput = document.getElementById('divergencia-search-input');
const btnDivergenciaSearchClear = document.getElementById('btn-divergencia-search-clear');

if (btnDivergenciaTool) {
    btnDivergenciaTool.addEventListener('click', function (e) {
        e.preventDefault();
        toggleDivergenciaTool();
    });
    L.DomEvent.disableClickPropagation(btnDivergenciaTool);
}

if (divergenciaPanel) {
    L.DomEvent.disableClickPropagation(divergenciaPanel);
    L.DomEvent.disableScrollPropagation(divergenciaPanel);
}

if (btnCloseDivergenciaPanel) {
    btnCloseDivergenciaPanel.addEventListener('click', function (e) {
        e.preventDefault();
        if (divergenciaToolActive) {
            toggleDivergenciaTool();
        }
    });
}

if (divergenciaSituacaoSelect) {
    divergenciaSituacaoSelect.addEventListener('change', function () {
        currentDivergenciaSituacao = this.value;
        currentDivergenciaPage = 1;
        loadDivergenciaData();
        if (isDivergenciaLayerVisible) {
            loadDivergenciaGeoJSON();
        }
    });
}

if (divergenciaSearchInput) {
    divergenciaSearchInput.addEventListener('input', function () {
        const val = this.value.trim();
        if (btnDivergenciaSearchClear) {
            btnDivergenciaSearchClear.style.display = val ? 'block' : 'none';
        }
        clearTimeout(divergenciaSearchDebounce);
        divergenciaSearchDebounce = setTimeout(() => {
            currentDivergenciaSearch = val;
            currentDivergenciaPage = 1;
            loadDivergenciaData();
        }, 300);
    });
}

function clearDivergenciaSearch() {
    if (divergenciaSearchInput) divergenciaSearchInput.value = '';
    if (btnDivergenciaSearchClear) btnDivergenciaSearchClear.style.display = 'none';
    currentDivergenciaSearch = '';
    currentDivergenciaPage = 1;
    loadDivergenciaData();
}

function setDivergenciaSituacaoFilter(situacao) {
    currentDivergenciaSituacao = situacao;
    if (divergenciaSituacaoSelect) {
        divergenciaSituacaoSelect.value = situacao;
    }
    currentDivergenciaPage = 1;
    loadDivergenciaData();
    if (isDivergenciaLayerVisible) {
        loadDivergenciaGeoJSON();
    }
}

function toggleDivergenciaTool() {
    divergenciaToolActive = !divergenciaToolActive;

    if (divergenciaToolActive) {
        if (btnDivergenciaTool) btnDivergenciaTool.classList.add('divergencia-active');
        if (divergenciaPanel) divergenciaPanel.style.display = 'flex';

        // Desativa ferramentas concorrentes se estiverem ativas
        if (typeof filtroTerrenoActive !== 'undefined' && filtroTerrenoActive && typeof toggleFiltroTerrenoTool === 'function') toggleFiltroTerrenoTool(false);
        if (typeof selecaoToolActive !== 'undefined' && selecaoToolActive && typeof toggleSelecaoEspacialTool === 'function') toggleSelecaoEspacialTool(false);
        if (typeof bufferModeActive !== 'undefined' && bufferModeActive && typeof toggleBufferTool === 'function') {
            toggleBufferTool();
        }
        if (typeof identifyModeActive !== 'undefined' && identifyModeActive && typeof btnIdentify !== 'undefined' && btnIdentify) {
            btnIdentify.click();
        }
        if (typeof editModeActive !== 'undefined' && editModeActive && typeof toggleEditTool === 'function') {
            toggleEditTool(false);
        }

        loadDivergenciaData();
    } else {
        if (btnDivergenciaTool) btnDivergenciaTool.classList.remove('divergencia-active');
        if (divergenciaPanel) divergenciaPanel.style.display = 'none';
        clearDivergenciaHighlight();
        if (isDivergenciaLayerVisible) {
            toggleDivergenciaLayer();
        }
    }
}

function loadDivergenciaData() {
    const tbody = document.getElementById('divergencia-table-tbody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center; padding: 20px; color: var(--text-muted);">
                    <div class="spinner" style="display:inline-block; vertical-align:middle; margin-right:6px;"></div>
                    Calculando e comparando áreas...
                </td>
            </tr>
        `;
    }

    const params = new URLSearchParams({
        situacao: currentDivergenciaSituacao,
        busca: currentDivergenciaSearch,
        page: currentDivergenciaPage,
        limit: currentDivergenciaLimit
    });

    fetch(`api/divergencia_areas.php?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Erro ao carregar divergências');
            }
            updateDivergenciaKPIs(data.kpis);
            renderDivergenciaTable(data);
        })
        .catch(err => {
            console.error('Erro na consulta de divergências:', err);
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 20px; color: #ef4444;">
                            Erro ao carregar dados de divergência.
                        </td>
                    </tr>
                `;
            }
        });
}

function updateDivergenciaKPIs(kpis) {
    if (!kpis) return;
    const elDiv = document.getElementById('kpi-total-divergencia');
    const elDentro = document.getElementById('kpi-total-dentro-limite');
    const elSemCad = document.getElementById('kpi-total-sem-cadastro');
    const elSemArea = document.getElementById('kpi-total-sem-area');

    if (elDiv) elDiv.textContent = (kpis.total_divergencia || 0).toLocaleString('pt-BR');
    if (elDentro) elDentro.textContent = (kpis.total_dentro_limite || 0).toLocaleString('pt-BR');
    if (elSemCad) elSemCad.textContent = (kpis.total_sem_cadastro || 0).toLocaleString('pt-BR');
    if (elSemArea) elSemArea.textContent = ((kpis.total_sem_area || 0) + (kpis.total_area_zero || 0)).toLocaleString('pt-BR');

    // Atualiza destaque visual dos cards
    ['divergencia', 'dentro', 'sem-cad', 'sem-area'].forEach(c => {
        const el = document.getElementById(`card-kpi-${c}`);
        if (el) el.classList.remove('active');
    });

    if (currentDivergenciaSituacao === 'DIVERGÊNCIA') {
        const el = document.getElementById('card-kpi-divergencia');
        if (el) el.classList.add('active');
    } else if (currentDivergenciaSituacao === 'DENTRO DO LIMITE') {
        const el = document.getElementById('card-kpi-dentro');
        if (el) el.classList.add('active');
    } else if (currentDivergenciaSituacao === 'SEM CADASTRO NO TRIBUTÁRIO') {
        const el = document.getElementById('card-kpi-sem-cad');
        if (el) el.classList.add('active');
    } else if (currentDivergenciaSituacao === 'SEM ÁREA NO TRIBUTÁRIO' || currentDivergenciaSituacao === 'ÁREA TRIBUTÁRIA ZERO') {
        const el = document.getElementById('card-kpi-sem-area');
        if (el) el.classList.add('active');
    }
}

function renderDivergenciaTable(data) {
    const tbody = document.getElementById('divergencia-table-tbody');
    const pageInfo = document.getElementById('divergencia-page-info');
    const btnPrev = document.getElementById('btn-divergencia-prev');
    const btnNext = document.getElementById('btn-divergencia-next');
    if (!tbody) return;

    const items = data.dados || [];
    const filtros = data.filtros || {};
    const totalPages = filtros.total_paginas || 1;
    const totalRows = filtros.total_filtrados || 0;

    if (pageInfo) {
        pageInfo.textContent = `Pág. ${currentDivergenciaPage} de ${totalPages} (${totalRows.toLocaleString('pt-BR')})`;
    }
    if (btnPrev) btnPrev.disabled = currentDivergenciaPage <= 1;
    if (btnNext) btnNext.disabled = currentDivergenciaPage >= totalPages;

    if (items.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center; padding: 20px; color: var(--text-muted); font-style: italic;">
                    Nenhum lote encontrado para os filtros selecionados.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    items.forEach(item => {
        let tagClass = 'dif-tag-muted';
        if (item.situacao === 'DIVERGÊNCIA') tagClass = 'dif-tag-danger';
        else if (item.situacao === 'DENTRO DO LIMITE') tagClass = 'dif-tag-success';
        else if (item.situacao === 'SEM CADASTRO NO TRIBUTÁRIO') tagClass = 'dif-tag-warning';

        const rawItemJson = encodeURIComponent(JSON.stringify(item));
        const contrib = (typeof escapeDomString === 'function') ? escapeDomString(item.contribuinte || '') : (item.contribuinte || '');
        const logr = (typeof escapeDomString === 'function') ? escapeDomString(item.logradouro || '') : (item.logradouro || '');
        const bairro = (typeof escapeDomString === 'function') ? escapeDomString(item.bairro || '') : (item.bairro || '');
        const insc = (typeof escapeDomString === 'function') ? escapeDomString(item.insc_munic || '') : (item.insc_munic || '');

        html += `
            <tr title="Contribuinte: ${contrib} | Logradouro: ${logr} - ${bairro}">
                <td>
                    <strong style="color: var(--text-color);">${insc}</strong>
                </td>
                <td title="Área Exata GIS: ${item.area_gis}">${item.area_gis_formatada}</td>
                <td title="Área Tributário: ${item.area_tributario_raw}">${item.area_tributario_formatada}</td>
                <td class="${tagClass}">${item.diferenca_m2_formatada}</td>
                <td class="${tagClass}"><strong>${item.diferenca_percentual_formatada}</strong></td>
                <td style="text-align:center;">
                    <button type="button" class="btn-zoom-lote" onclick="zoomToDivergenciaLote('${rawItemJson}')" title="Localizar lote no mapa">
                        Zoom
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function changeDivergenciaPage(delta) {
    currentDivergenciaPage = Math.max(1, currentDivergenciaPage + delta);
    loadDivergenciaData();
}

function zoomToDivergenciaLote(itemEncoded) {
    try {
        const item = JSON.parse(decodeURIComponent(itemEncoded));
        if (!item.lat || !item.lng) return;

        if (divergenciaHighlightMarker) {
            map.removeLayer(divergenciaHighlightMarker);
            divergenciaHighlightMarker = null;
        }

        divergenciaHighlightMarker = L.circleMarker([item.lat, item.lng], {
            radius: 14,
            color: '#f43f5e',
            weight: 3.5,
            fillColor: '#fb7185',
            fillOpacity: 0.6
        }).addTo(map);

        let sitBadgeColor = '#94a3b8';
        if (item.situacao === 'DIVERGÊNCIA') sitBadgeColor = '#f43f5e';
        else if (item.situacao === 'DENTRO DO LIMITE') sitBadgeColor = '#10b981';
        else if (item.situacao === 'SEM CADASTRO NO TRIBUTÁRIO') sitBadgeColor = '#f59e0b';

        const contrib = (typeof escapeDomString === 'function') ? escapeDomString(item.contribuinte || '') : (item.contribuinte || '');
        const logr = (typeof escapeDomString === 'function') ? escapeDomString(item.logradouro || '') : (item.logradouro || '');
        const bairro = (typeof escapeDomString === 'function') ? escapeDomString(item.bairro || '') : (item.bairro || '');
        const insc = (typeof escapeDomString === 'function') ? escapeDomString(item.insc_munic || '') : (item.insc_munic || '');

        const popupHtml = `
            <div style="font-size: 12px; line-height: 1.45; min-width: 210px;">
                <div style="font-weight: 700; color: #f43f5e; font-size: 13px; margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 3px;">
                    Divergência de Área - Lote #${item.id}
                </div>
                <div><strong>Inscrição:</strong> ${insc}</div>
                <div><strong>Contribuinte:</strong> ${contrib}</div>
                <div><strong>Endereço:</strong> ${logr} - ${bairro}</div>
                <hr style="border:0; border-top:1px dashed rgba(255,255,255,0.15); margin:6px 0;">
                <div><strong>Área no GIS:</strong> <span style="font-family:monospace; font-weight:700;">${item.area_gis_formatada}</span></div>
                <div><strong>Área no Tributário:</strong> <span style="font-family:monospace; font-weight:700;">${item.area_tributario_formatada}</span></div>
                <div><strong>Diferença (m²):</strong> <span style="color:${sitBadgeColor}; font-weight:700;">${item.diferenca_m2_formatada}</span></div>
                <div><strong>Diferença (%):</strong> <span style="color:${sitBadgeColor}; font-weight:700; font-size:13px;">${item.diferenca_percentual_formatada}</span></div>
                <div style="margin-top: 4px;"><strong>Situação:</strong> <span style="color:${sitBadgeColor}; font-weight:700;">${item.situacao}</span></div>
                <div style="margin-top: 8px;">
                    <button type="button" class="btn-primary" style="width: 100%; padding: 5px 8px; font-size: 11px; cursor: pointer; border-radius: 4px;" onclick="handleGerarEspelhoBCI('${insc}')">Ver Boletim BCI</button>
                </div>
            </div>
        `;

        divergenciaHighlightMarker.bindPopup(popupHtml);

        map.flyTo([item.lat, item.lng], 19, {
            animate: true,
            duration: 1.0
        });

        map.once('moveend', () => {
            if (divergenciaHighlightMarker) {
                divergenciaHighlightMarker.openPopup();
            }
        });
    } catch (e) {
        console.error('Erro ao dar zoom no lote divergente:', e);
    }
}

function clearDivergenciaHighlight() {
    if (divergenciaHighlightMarker) {
        map.removeLayer(divergenciaHighlightMarker);
        divergenciaHighlightMarker = null;
    }
}

function toggleDivergenciaLayer() {
    isDivergenciaLayerVisible = !isDivergenciaLayerVisible;
    const btn = document.getElementById('btn-toggle-divergencia-layer');
    const lbl = document.getElementById('lbl-toggle-divergencia-layer');

    if (!isDivergenciaLayerVisible) {
        if (divergenciaGeojsonLayer) {
            map.removeLayer(divergenciaGeojsonLayer);
            divergenciaGeojsonLayer = null;
        }
        if (divergenciaLegendControl) {
            map.removeControl(divergenciaLegendControl);
            divergenciaLegendControl = null;
        }
        if (btn) btn.classList.remove('active');
        if (lbl) lbl.textContent = 'Pintar Mapa';
        return;
    }

    if (btn) btn.classList.add('active');
    if (lbl) lbl.textContent = 'Remover Cores';

    loadDivergenciaGeoJSON();
}

function loadDivergenciaGeoJSON() {
    if (typeof showLoading === 'function') showLoading();

    // Cria pane prioritário se não existir
    if (!map.getPane('divergenciaLayerPane')) {
        map.createPane('divergenciaLayerPane');
        map.getPane('divergenciaLayerPane').style.zIndex = 620;
    }

    const params = new URLSearchParams({
        geojson: '1',
        situacao: currentDivergenciaSituacao
    });

    fetch(`api/divergencia_areas.php?${params.toString()}`)
        .then(res => {
            if (!res.ok) throw new Error('Falha na resposta do servidor.');
            return res.json();
        })
        .then(geojson => {
            if (typeof hideLoading === 'function') hideLoading();
            if (divergenciaGeojsonLayer) {
                map.removeLayer(divergenciaGeojsonLayer);
                divergenciaGeojsonLayer = null;
            }

            if (!geojson || !geojson.features || geojson.features.length === 0) {
                alert('Nenhum lote encontrado para os critérios selecionados.');
                return;
            }

            divergenciaGeojsonLayer = L.geoJSON(geojson, {
                pane: 'divergenciaLayerPane',
                style: function (feature) {
                    const sit = feature.properties.situacao;
                    let color = '#7c3aed';
                    let fillColor = '#8b5cf6';
                    let fillOpacity = 0.50;
                    let weight = 1.8;

                    if (sit === 'DIVERGÊNCIA') {
                        color = '#e11d48';
                        fillColor = '#f43f5e';
                        fillOpacity = 0.68;
                        weight = 2.2;
                    } else if (sit === 'DENTRO DO LIMITE') {
                        color = '#059669';
                        fillColor = '#10b981';
                        fillOpacity = 0.45;
                        weight = 1.6;
                    } else if (sit === 'SEM CADASTRO NO TRIBUTÁRIO') {
                        color = '#d97706';
                        fillColor = '#f59e0b';
                        fillOpacity = 0.52;
                        weight = 1.6;
                    }

                    return {
                        color: color,
                        weight: weight,
                        fillColor: fillColor,
                        fillOpacity: fillOpacity
                    };
                },
                onEachFeature: function (feature, layer) {
                    const p = feature.properties;
                    let sitColor = '#94a3b8';
                    if (p.situacao === 'DIVERGÊNCIA') sitColor = '#f43f5e';
                    else if (p.situacao === 'DENTRO DO LIMITE') sitColor = '#10b981';
                    else if (p.situacao === 'SEM CADASTRO NO TRIBUTÁRIO') sitColor = '#f59e0b';

                    const difM2Str = p.diferenca_m2 !== null ? `${p.diferenca_m2 > 0 ? '+' : ''}${Number(p.diferenca_m2).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} m²` : '-';
                    const difPercStr = p.diferenca_percentual !== null ? `${p.diferenca_percentual > 0 ? '+' : ''}${Number(p.diferenca_percentual).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}%` : '-';
                    const areaGisStr = p.area_gis ? `${Number(p.area_gis).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} m²` : '-';
                    const areaTribStr = p.area_tributario ? `${Number(p.area_tributario).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} m²` : (p.area_tributario_raw || '-');

                    layer.on({
                        mouseover: function (e) {
                            const l = e.target;
                            l.setStyle({
                                weight: 3.5,
                                color: '#ffffff',
                                fillOpacity: 0.88
                            });
                            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                                l.bringToFront();
                            }
                        },
                        mouseout: function (e) {
                            if (divergenciaGeojsonLayer) {
                                divergenciaGeojsonLayer.resetStyle(e.target);
                            }
                        }
                    });

                    const insc = (typeof escapeDomString === 'function') ? escapeDomString(p.insc_munic || '') : (p.insc_munic || '');
                    const contrib = (typeof escapeDomString === 'function') ? escapeDomString(p.contribuinte || '') : (p.contribuinte || '');
                    const logr = (typeof escapeDomString === 'function') ? escapeDomString(p.logradouro || '') : (p.logradouro || '');
                    const bairro = (typeof escapeDomString === 'function') ? escapeDomString(p.bairro || '') : (p.bairro || '');

                    const popHtml = `
                        <div style="font-size: 12px; line-height: 1.45; min-width: 200px;">
                            <div style="font-weight: 700; color: #f43f5e; font-size: 13px; margin-bottom: 4px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 3px;">
                                Lote #${p.id}
                            </div>
                            <div><strong>Inscrição:</strong> ${insc}</div>
                            <div><strong>Contribuinte:</strong> ${contrib}</div>
                            <div><strong>Endereço:</strong> ${logr} - ${bairro}</div>
                            <hr style="border:0; border-top:1px dashed rgba(255,255,255,0.15); margin:6px 0;">
                            <div><strong>Área GIS:</strong> <span style="font-family:monospace; font-weight:700;">${areaGisStr}</span></div>
                            <div><strong>Área Tributário:</strong> <span style="font-family:monospace; font-weight:700;">${areaTribStr}</span></div>
                            <div><strong>Diferença (m²):</strong> <span style="color:${sitColor}; font-weight:700;">${difM2Str}</span></div>
                            <div><strong>Diferença (%):</strong> <span style="color:${sitColor}; font-weight:700; font-size:13px;">${difPercStr}</span></div>
                            <div style="margin-top: 4px;"><strong>Situação:</strong> <span style="color:${sitColor}; font-weight:700;">${p.situacao}</span></div>
                            <div style="margin-top: 8px;">
                                <button type="button" class="btn-primary" style="width: 100%; padding: 5px 8px; font-size: 11px; cursor: pointer; border-radius: 4px;" onclick="handleGerarEspelhoBCI('${insc}')">Ver Boletim BCI</button>
                            </div>
                        </div>
                    `;
                    layer.bindPopup(popHtml);
                }
            }).addTo(map);

            // Adiciona legenda temática flutuante no mapa
            if (!divergenciaLegendControl) {
                divergenciaLegendControl = L.control({ position: 'bottomright' });
                divergenciaLegendControl.onAdd = function () {
                    const div = L.DomUtil.create('div', 'divergencia-map-legend');
                    div.innerHTML = `
                        <strong>Legenda de Divergências</strong>
                        <div class="divergencia-map-legend-item"><span class="legend-swatch swatch-danger"></span> Divergência (> 10%)</div>
                        <div class="divergencia-map-legend-item"><span class="legend-swatch swatch-success"></span> Dentro do Limite (&le; 10%)</div>
                        <div class="divergencia-map-legend-item"><span class="legend-swatch swatch-warning"></span> Sem Cadastro</div>
                        <div class="divergencia-map-legend-item"><span class="legend-swatch swatch-purple"></span> Sem Área / Zero</div>
                    `;
                    L.DomEvent.disableClickPropagation(div);
                    L.DomEvent.disableScrollPropagation(div);
                    return div;
                };
                divergenciaLegendControl.addTo(map);
            }
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro ao carregar mapa temático de divergências:', err);
            alert('Não foi possível carregar o mapa de divergências.');
        });
}

function exportDivergenciaCSV() {
    const params = new URLSearchParams({
        situacao: currentDivergenciaSituacao,
        busca: currentDivergenciaSearch,
        export: 'csv'
    });
    window.location.href = `api/divergencia_areas.php?${params.toString()}`;
}

// Exportações Globais
window.toggleDivergenciaTool = toggleDivergenciaTool;
window.setDivergenciaSituacaoFilter = setDivergenciaSituacaoFilter;
window.clearDivergenciaSearch = clearDivergenciaSearch;
window.changeDivergenciaPage = changeDivergenciaPage;
window.zoomToDivergenciaLote = zoomToDivergenciaLote;
window.toggleDivergenciaLayer = toggleDivergenciaLayer;
window.exportDivergenciaCSV = exportDivergenciaCSV;
