/**
 * ==========================================================================
 * FERRAMENTA DE FILTRO AVANÇADO DE TERRENOS (ÁREA & OCUPAÇÃO)
 * Módulo: js/tools/tool-filtro-terrenos.js
 * ==========================================================================
 */

let filtroTerrenoActive = false;
let filtroTerrenoCurrentQuery = null;
let filtroTerrenoLayer = null;
let isFiltroTerrenoHighlightVisible = false;
let filtroTerrenoHighlightMarker = null;
let filtroTerrenoLegendControl = null;
let filtroTerrenoPage = 1;
let filtroTerrenoLimit = 15;
let filtroTerrenoSearch = '';
let filtroTerrenoDebounce = null;
let filtroTerrenoOptionsLoaded = false;

const btnFiltroTerrenoTool = document.getElementById('btn-filtro-terreno-tool');
const filtroTerrenoPanel = document.getElementById('filtro-terreno-panel');
const btnCloseFiltroTerrenoPanel = document.getElementById('btn-close-filtro-terreno-panel');
const filtroTerrenoForm = document.getElementById('filtro-terreno-form');
const filtroTerrenoOperador = document.getElementById('filtro-terreno-operador');
const filtroTerrenoValor = document.getElementById('filtro-terreno-valor');
const filtroTerrenoValorMax = document.getElementById('filtro-terreno-valor-max');
const filtroTerrenoOcupacao = document.getElementById('filtro-terreno-ocupacao');
const filtroTerrenoBairro = document.getElementById('filtro-terreno-bairro');
const filtroTerrenoEmptyPlaceholder = document.getElementById('filtro-terreno-empty-placeholder');
const filtroTerrenoResultsCard = document.getElementById('filtro-terreno-results-card');
const filtroTerrenoTableSearch = document.getElementById('filtro-terreno-table-search');
const btnFiltroTerrenoSearchClear = document.getElementById('btn-filtro-terreno-search-clear');

if (btnFiltroTerrenoTool) {
    btnFiltroTerrenoTool.addEventListener('click', function (e) {
        e.preventDefault();
        toggleFiltroTerrenoTool();
    });
    L.DomEvent.disableClickPropagation(btnFiltroTerrenoTool);
}

if (filtroTerrenoPanel) {
    L.DomEvent.disableClickPropagation(filtroTerrenoPanel);
    L.DomEvent.disableScrollPropagation(filtroTerrenoPanel);
}

if (btnCloseFiltroTerrenoPanel) {
    btnCloseFiltroTerrenoPanel.addEventListener('click', function (e) {
        e.preventDefault();
        if (filtroTerrenoActive) {
            toggleFiltroTerrenoTool(false);
        }
    });
}

if (filtroTerrenoTableSearch) {
    filtroTerrenoTableSearch.addEventListener('input', function () {
        const val = this.value.trim();
        if (btnFiltroTerrenoSearchClear) {
            btnFiltroTerrenoSearchClear.style.display = val ? 'block' : 'none';
        }
        clearTimeout(filtroTerrenoDebounce);
        filtroTerrenoDebounce = setTimeout(() => {
            filtroTerrenoSearch = val;
            filtroTerrenoPage = 1;
            aplicarFiltroTerrenos(1, false);
        }, 300);
    });
}

function handleFiltroOperadorChange() {
    if (!filtroTerrenoOperador || !filtroTerrenoValorMax) return;
    const op = filtroTerrenoOperador.value;
    if (op === 'BETWEEN') {
        filtroTerrenoValorMax.style.display = 'block';
        filtroTerrenoValorMax.required = true;
    } else {
        filtroTerrenoValorMax.style.display = 'none';
        filtroTerrenoValorMax.required = false;
        filtroTerrenoValorMax.value = '';
    }
}

function toggleFiltroTerrenoTool(forceState) {
    filtroTerrenoActive = (forceState !== undefined) ? forceState : !filtroTerrenoActive;
    const mapContainer = document.getElementById('map');

    if (filtroTerrenoActive) {
        if (btnFiltroTerrenoTool) btnFiltroTerrenoTool.classList.add('filtro-terreno-active');
        if (filtroTerrenoPanel) filtroTerrenoPanel.style.display = 'flex';

        // Desativa ferramentas concorrentes
        if (typeof bufferModeActive !== 'undefined' && bufferModeActive && typeof toggleBufferTool === 'function') toggleBufferTool(false);
        if (typeof divergenciaToolActive !== 'undefined' && divergenciaToolActive && typeof toggleDivergenciaTool === 'function') toggleDivergenciaTool();
        if (typeof selecaoToolActive !== 'undefined' && selecaoToolActive && typeof toggleSelecaoEspacialTool === 'function') toggleSelecaoEspacialTool(false);
        if (typeof identifyModeActive !== 'undefined' && identifyModeActive && typeof btnIdentify !== 'undefined' && btnIdentify) btnIdentify.click();
        if (typeof editModeActive !== 'undefined' && editModeActive && typeof toggleEditTool === 'function') toggleEditTool(false);
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

        loadFiltroTerrenoOptions();
    } else {
        if (btnFiltroTerrenoTool) btnFiltroTerrenoTool.classList.remove('filtro-terreno-active');
        if (filtroTerrenoPanel) filtroTerrenoPanel.style.display = 'none';
        clearFiltroTerrenoLayers();
    }
}

function loadFiltroTerrenoOptions() {
    if (filtroTerrenoOptionsLoaded) return;

    fetch('api/filtro_terrenos.php?action=opcoes')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                filtroTerrenoOptionsLoaded = true;

                // Popula Ocupações
                if (filtroTerrenoOcupacao && data.ocupacoes && data.ocupacoes.length > 0) {
                    const totalAll = data.ocupacoes.reduce((acc, cur) => acc + parseInt(cur.total || 0), 0);
                    filtroTerrenoOcupacao.innerHTML = '<option value="ALL">Todas as Ocupações (' + totalAll.toLocaleString('pt-BR') + ')</option>';
                    data.ocupacoes.forEach(o => {
                        const opt = document.createElement('option');
                        opt.value = o.ocupacao;
                        opt.textContent = `${o.ocupacao} (${Number(o.total).toLocaleString('pt-BR')})`;
                        if (o.ocupacao === 'CONSTRUIDO') opt.selected = true;
                        filtroTerrenoOcupacao.appendChild(opt);
                    });
                }

                // Popula Bairros
                if (filtroTerrenoBairro && data.bairros && data.bairros.length > 0) {
                    filtroTerrenoBairro.innerHTML = '<option value="ALL">Todos os Bairros / Comunidades (' + data.bairros.length + ')</option>';
                    data.bairros.forEach(b => {
                        const opt = document.createElement('option');
                        opt.value = b.bairro;
                        opt.textContent = `${b.bairro} (${Number(b.total).toLocaleString('pt-BR')})`;
                        filtroTerrenoBairro.appendChild(opt);
                    });
                }
            }
        })
        .catch(err => {
            console.error('Erro ao carregar opções de filtro de terrenos:', err);
        });
}

function aplicarFiltroTerrenos(page = 1, reloadGeoJSON = true) {
    filtroTerrenoPage = page;
    const op = filtroTerrenoOperador ? filtroTerrenoOperador.value : '>=';
    const val = filtroTerrenoValor ? filtroTerrenoValor.value.trim() : '';
    const valMax = filtroTerrenoValorMax ? filtroTerrenoValorMax.value.trim() : '';
    const ocup = filtroTerrenoOcupacao ? filtroTerrenoOcupacao.value : 'ALL';
    const bairro = filtroTerrenoBairro ? filtroTerrenoBairro.value : 'ALL';

    const params = new URLSearchParams({
        action: 'filtrar',
        operador_area: op,
        valor_area: val,
        valor_area_max: valMax,
        ocupacao: ocup,
        bairro: bairro,
        search: filtroTerrenoSearch,
        page: filtroTerrenoPage,
        limit: filtroTerrenoLimit
    });

    filtroTerrenoCurrentQuery = params;

    const tbody = document.getElementById('filtro-terreno-table-tbody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center; padding: 18px; color: var(--text-muted);">
                    <div class="spinner" style="display:inline-block; vertical-align:middle; margin-right:6px;"></div>
                    Consultando lotes...
                </td>
            </tr>
        `;
    }

    if (typeof showLoading === 'function') showLoading();

    fetch(`api/filtro_terrenos.php?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            if (typeof hideLoading === 'function') hideLoading();
            if (!data.success) {
                alert(data.message || 'Erro ao consultar lotes.');
                return;
            }

            if (filtroTerrenoEmptyPlaceholder) filtroTerrenoEmptyPlaceholder.style.display = 'none';
            if (filtroTerrenoResultsCard) filtroTerrenoResultsCard.style.display = 'flex';

            renderFiltroTerrenoMetrics(data.metricas, data.distribuicao_ocupacao);
            renderFiltroTerrenoTable(data);

            if (reloadGeoJSON) {
                loadFiltroTerrenoGeoJSON();
            }
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro ao aplicar filtro de terrenos:', err);
            alert('Não foi possível realizar o filtro de terrenos.');
        });
}

function renderFiltroTerrenoMetrics(m, distOcup) {
    const elTot = document.getElementById('kpi-terreno-total');
    const elSubCad = document.getElementById('kpi-terreno-sub-cad');
    const elAreaMedia = document.getElementById('kpi-terreno-area-media');
    const elAreaTotal = document.getElementById('kpi-terreno-area-total');
    const elSubHa = document.getElementById('kpi-terreno-sub-ha');
    const elOcupTop = document.getElementById('kpi-terreno-ocup-top');
    const elSubOcup = document.getElementById('kpi-terreno-sub-ocup');

    if (elTot) elTot.textContent = Number(m.total_lotes).toLocaleString('pt-BR');
    if (elSubCad) elSubCad.textContent = `${Number(m.total_cadastrados).toLocaleString('pt-BR')} cadastrados`;
    if (elAreaMedia) elAreaMedia.textContent = `${Number(m.area_media_terreno).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} m²`;
    if (elAreaTotal) elAreaTotal.textContent = `${Number(m.area_total_terreno).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} m²`;

    const areaHa = (m.area_total_terreno / 10000);
    if (elSubHa) elSubHa.textContent = `${Number(areaHa).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} ha (GIS: ${Number(m.area_total_gis).toLocaleString('pt-BR')} m²)`;

    if (distOcup && distOcup.length > 0) {
        const top = distOcup[0];
        const perc = m.total_lotes > 0 ? Math.round((parseInt(top.total || 0) / m.total_lotes) * 100) : 0;
        if (elOcupTop) elOcupTop.textContent = top.ocupacao;
        if (elSubOcup) elSubOcup.textContent = `${Number(top.total).toLocaleString('pt-BR')} lotes (${perc}%)`;
    } else {
        if (elOcupTop) elOcupTop.textContent = '--';
        if (elSubOcup) elSubOcup.textContent = 'Sem registros';
    }
}

function renderFiltroTerrenoTable(data) {
    const tbody = document.getElementById('filtro-terreno-table-tbody');
    const info = document.getElementById('filtro-terreno-page-info');
    const btnPrev = document.getElementById('btn-filtro-terreno-prev');
    const btnNext = document.getElementById('btn-filtro-terreno-next');

    if (!tbody) return;

    const p = data.paginacao;
    if (info) info.textContent = `Pág. ${p.pagina_atual} de ${p.total_paginas} (${Number(p.total_registros).toLocaleString('pt-BR')})`;
    if (btnPrev) btnPrev.disabled = (p.pagina_atual <= 1);
    if (btnNext) btnNext.disabled = (p.pagina_atual >= p.total_paginas);

    if (!data.itens || data.itens.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" style="text-align:center; padding: 18px; color: var(--text-muted);">Nenhum terreno encontrado para os critérios informados.</td></tr>`;
        return;
    }

    let html = '';
    data.itens.forEach(item => {
        const insc = item.insc_munic || '-';
        const contrib = item.contribuinte || 'Não Informado';
        const logr = item.logradouro !== '-' ? item.logradouro : (item.bairro !== '-' ? item.bairro : '-');
        const areaStr = item.area_terreno > 0 ? `${Number(item.area_terreno).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} m²` : '-';
        const ocup = item.ocupacao || 'Não Informado';

        let badgeClass = 'badge-ocup-outro';
        if (ocup.includes('CONSTRUIDO') && !ocup.includes('NAO')) badgeClass = 'badge-ocup-construido';
        else if (ocup.includes('NAO CONSTRUIDO')) badgeClass = 'badge-ocup-nao-construido';
        else if (ocup.includes('ANDAMENTO')) badgeClass = 'badge-ocup-andamento';

        html += `
            <tr>
                <td><strong>${insc}</strong></td>
                <td title="${contrib}">${contrib}</td>
                <td title="${logr}">${logr}</td>
                <td><span style="color:#10b981; font-weight:600;">${areaStr}</span></td>
                <td><span class="filtro-badge-ocupacao ${badgeClass}">${ocup}</span></td>
                <td style="text-align:center;">
                    <button type="button" class="btn-table-action" onclick="zoomToFiltroLote(${item.id}, ${item.lat}, ${item.lng}, '${insc}')" title="Aproximar neste lote">
                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"></circle>
                            <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                        </svg>
                    </button>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function loadFiltroTerrenoGeoJSON() {
    if (!filtroTerrenoCurrentQuery) return;

    const geoParams = new URLSearchParams(filtroTerrenoCurrentQuery);
    geoParams.set('action', 'geojson');

    fetch(`api/filtro_terrenos.php?${geoParams.toString()}`)
        .then(res => res.json())
        .then(geojson => {
            if (filtroTerrenoLayer) {
                map.removeLayer(filtroTerrenoLayer);
                filtroTerrenoLayer = null;
            }

            if (!map.getPane('filtroTerrenoPane')) {
                map.createPane('filtroTerrenoPane');
                map.getPane('filtroTerrenoPane').style.zIndex = 620;
            }

            if (!geojson || !geojson.features || geojson.features.length === 0) {
                isFiltroTerrenoHighlightVisible = false;
                updateFiltroTerrenoLayerButton();
                return;
            }

            filtroTerrenoLayer = L.geoJSON(geojson, {
                pane: 'filtroTerrenoPane',
                style: function (feature) {
                    const ocup = feature.properties.ocupacao || '';
                    let fill = '#10b981';
                    let stroke = '#059669';
                    if (ocup.includes('NAO CONSTRUIDO')) {
                        fill = '#f59e0b';
                        stroke = '#d97706';
                    } else if (ocup.includes('ANDAMENTO')) {
                        fill = '#8b5cf6';
                        stroke = '#7c3aed';
                    }
                    return {
                        color: stroke,
                        weight: 2,
                        fillColor: fill,
                        fillOpacity: 0.55
                    };
                },
                onEachFeature: function (feature, layer) {
                    const p = feature.properties;
                    layer.on({
                        mouseover: function (e) {
                            const l = e.target;
                            l.setStyle({
                                weight: 3.5,
                                color: '#ffffff',
                                fillOpacity: 0.85
                            });
                            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                                l.bringToFront();
                            }
                        },
                        mouseout: function (e) {
                            if (filtroTerrenoLayer) {
                                filtroTerrenoLayer.resetStyle(e.target);
                            }
                        }
                    });

                    let popupContent = `
                        <div style="font-family:sans-serif; font-size:12px; line-height:1.45; min-width:220px;">
                            <div style="font-weight:700; font-size:13px; color:#10b981; margin-bottom:4px;">
                                🏘️ Lote - Inscrição: ${p.insc_munic}
                            </div>
                            <div><strong>Proprietário:</strong> ${p.contribuinte}</div>
                            <div><strong>Logradouro:</strong> ${p.logradouro}</div>
                            <div><strong>Bairro:</strong> ${p.bairro}</div>
                            <div><strong>Ocupação:</strong> <span style="color:#10b981; font-weight:600;">${p.ocupacao}</span></div>
                            <div><strong>Área do Terreno:</strong> ${p.area_terreno > 0 ? Number(p.area_terreno).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) + ' m²' : '-'}</div>
                            <div><strong>Área GIS:</strong> ${Number(p.area_gis).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} m²</div>
                    `;

                    if (p.insc_munic && p.insc_munic !== '-') {
                        popupContent += `
                            <div style="margin-top:8px; padding-top:6px; border-top:1px solid #e2e8f0; text-align:right;">
                                <button type="button" onclick="handleGerarEspelhoBCI('${p.insc_munic}')" style="background:#10b981; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer;">
                                    Abrir Espelho BCI &rarr;
                                </button>
                            </div>
                        `;
                    }

                    popupContent += `</div>`;
                    layer.bindPopup(popupContent);
                }
            }).addTo(map);

            isFiltroTerrenoHighlightVisible = true;
            updateFiltroTerrenoLayerButton();

            // Zoom nos resultados
            if (filtroTerrenoLayer.getBounds().isValid()) {
                map.fitBounds(filtroTerrenoLayer.getBounds(), { padding: [30, 30], maxZoom: 17 });
            }

            // Legenda flutuante
            renderFiltroTerrenoLegend(geojson.features.length);
        })
        .catch(err => {
            console.error('Erro ao carregar GeoJSON do filtro de terrenos:', err);
        });
}

function renderFiltroTerrenoLegend(count) {
    if (!filtroTerrenoLegendControl) {
        filtroTerrenoLegendControl = L.control({ position: 'bottomright' });
        filtroTerrenoLegendControl.onAdd = function () {
            const div = L.DomUtil.create('div', 'divergencia-map-legend filtro-terreno-map-legend');
            div.innerHTML = `
                <strong style="color: #10b981;">Filtro de Terrenos: ${count} lotes</strong>
                <div class="divergencia-map-legend-item"><span class="legend-swatch swatch-filtro-terreno"></span> Lotes Filtrados (Área / Ocupação)</div>
            `;
            L.DomEvent.disableClickPropagation(div);
            L.DomEvent.disableScrollPropagation(div);
            return div;
        };
        filtroTerrenoLegendControl.addTo(map);
    } else {
        const div = filtroTerrenoLegendControl.getContainer();
        if (div) {
            div.innerHTML = `
                <strong style="color: #10b981;">Filtro de Terrenos: ${count} lotes</strong>
                <div class="divergencia-map-legend-item"><span class="legend-swatch swatch-filtro-terreno"></span> Lotes Filtrados (Área / Ocupação)</div>
            `;
        }
    }
}

function toggleFiltroTerrenoHighlightLayer() {
    if (!filtroTerrenoLayer) {
        if (filtroTerrenoCurrentQuery) {
            loadFiltroTerrenoGeoJSON();
        }
        return;
    }

    if (map.hasLayer(filtroTerrenoLayer)) {
        map.removeLayer(filtroTerrenoLayer);
        isFiltroTerrenoHighlightVisible = false;
    } else {
        map.addLayer(filtroTerrenoLayer);
        isFiltroTerrenoHighlightVisible = true;
    }
    updateFiltroTerrenoLayerButton();
}

function updateFiltroTerrenoLayerButton() {
    const btn = document.getElementById('btn-toggle-filtro-terreno-layer');
    const lbl = document.getElementById('lbl-toggle-filtro-terreno-layer');
    if (btn) btn.classList.toggle('active', isFiltroTerrenoHighlightVisible);
    if (lbl) lbl.textContent = isFiltroTerrenoHighlightVisible ? 'Ocultar Pintura' : 'Pintar Feições';
}

function zoomToFiltroTerrenos() {
    if (filtroTerrenoLayer && filtroTerrenoLayer.getBounds().isValid()) {
        map.fitBounds(filtroTerrenoLayer.getBounds(), { padding: [30, 30], maxZoom: 17 });
    }
}

function zoomToFiltroLote(id, lat, lng, insc) {
    if (lat && lng && !isNaN(lat) && !isNaN(lng)) {
        map.setView([lat, lng], 19);

        if (filtroTerrenoHighlightMarker) {
            map.removeLayer(filtroTerrenoHighlightMarker);
        }

        filtroTerrenoHighlightMarker = L.circleMarker([lat, lng], {
            radius: 12,
            color: '#10b981',
            weight: 3,
            fillColor: '#34d399',
            fillOpacity: 0.6,
            className: 'marker-pulse'
        }).addTo(map);

        setTimeout(() => {
            if (filtroTerrenoHighlightMarker) {
                map.removeLayer(filtroTerrenoHighlightMarker);
                filtroTerrenoHighlightMarker = null;
            }
        }, 3500);

        if (filtroTerrenoLayer) {
            filtroTerrenoLayer.eachLayer(layer => {
                if (layer.feature && layer.feature.properties && layer.feature.properties.id == id) {
                    layer.openPopup();
                }
            });
        }
    } else {
        alert(`Lote ${insc} não possui coordenadas válidas para aproximação.`);
    }
}

function changeFiltroTerrenoPage(delta) {
    aplicarFiltroTerrenos(filtroTerrenoPage + delta, false);
}

function clearFiltroTerrenoSearch() {
    if (filtroTerrenoTableSearch) filtroTerrenoTableSearch.value = '';
    if (btnFiltroTerrenoSearchClear) btnFiltroTerrenoSearchClear.style.display = 'none';
    filtroTerrenoSearch = '';
    filtroTerrenoPage = 1;
    aplicarFiltroTerrenos(1, false);
}

function limparFiltroTerrenos() {
    if (filtroTerrenoForm) filtroTerrenoForm.reset();
    if (filtroTerrenoValor) filtroTerrenoValor.value = '300';
    if (filtroTerrenoOperador) filtroTerrenoOperador.value = '>=';
    handleFiltroOperadorChange();
    if (filtroTerrenoTableSearch) filtroTerrenoTableSearch.value = '';
    if (btnFiltroTerrenoSearchClear) btnFiltroTerrenoSearchClear.style.display = 'none';

    filtroTerrenoSearch = '';
    filtroTerrenoPage = 1;
    filtroTerrenoCurrentQuery = null;

    if (filtroTerrenoResultsCard) filtroTerrenoResultsCard.style.display = 'none';
    if (filtroTerrenoEmptyPlaceholder) filtroTerrenoEmptyPlaceholder.style.display = 'flex';

    clearFiltroTerrenoLayers();
}

function clearFiltroTerrenoLayers() {
    if (filtroTerrenoLayer) {
        map.removeLayer(filtroTerrenoLayer);
        filtroTerrenoLayer = null;
    }
    if (filtroTerrenoHighlightMarker) {
        map.removeLayer(filtroTerrenoHighlightMarker);
        filtroTerrenoHighlightMarker = null;
    }
    if (filtroTerrenoLegendControl) {
        map.removeControl(filtroTerrenoLegendControl);
        filtroTerrenoLegendControl = null;
    }
    isFiltroTerrenoHighlightVisible = false;
    updateFiltroTerrenoLayerButton();
}

function exportFiltroTerrenosCSV() {
    if (!filtroTerrenoCurrentQuery) {
        alert('Aplique um filtro primeiro para exportar os resultados.');
        return;
    }

    const expParams = new URLSearchParams(filtroTerrenoCurrentQuery);
    expParams.set('action', 'export_csv');

    window.location.href = `api/filtro_terrenos.php?${expParams.toString()}`;
}

// Exportações Globais
window.toggleFiltroTerrenoTool = toggleFiltroTerrenoTool;
window.handleFiltroOperadorChange = handleFiltroOperadorChange;
window.aplicarFiltroTerrenos = aplicarFiltroTerrenos;
window.limparFiltroTerrenos = limparFiltroTerrenos;
window.changeFiltroTerrenoPage = changeFiltroTerrenoPage;
window.clearFiltroTerrenoSearch = clearFiltroTerrenoSearch;
window.zoomToFiltroLote = zoomToFiltroLote;
window.zoomToFiltroTerrenos = zoomToFiltroTerrenos;
window.toggleFiltroTerrenoHighlightLayer = toggleFiltroTerrenoHighlightLayer;
window.exportFiltroTerrenosCSV = exportFiltroTerrenosCSV;
