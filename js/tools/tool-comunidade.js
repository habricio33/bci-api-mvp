/**
 * ==========================================================================
 * FERRAMENTA DE SELEÇÃO ESPACIAL POR COMUNIDADE (Lotes e Edificações)
 * Módulo: js/tools/tool-comunidade.js
 * ==========================================================================
 */

let selecaoToolActive = false;
let selecaoMapClickMode = false;
let currentSelectedCommunityId = null;
let currentSelectedCommunityData = null;
let selecaoCommunityLayer = null;
let selecaoDestaquesLayer = null;
let isSelecaoDestaquesVisible = false;
let selecaoLegendControl = null;
let selecaoHighlightMarker = null;

let selecaoLotesPage = 1;
let selecaoLotesLimit = 15;
let selecaoLotesSearch = '';
let selecaoLotesDebounce = null;

let selecaoEdifPage = 1;
let selecaoEdifLimit = 15;
let selecaoEdifSearch = '';
let selecaoEdifDebounce = null;

let currentSelecaoTab = 'resumo';
let communitiesListCache = null;

const btnSpatialSelectTool = document.getElementById('btn-spatial-select-tool');
const selecaoEspacialPanel = document.getElementById('selecao-espacial-panel');
const btnCloseSelecaoPanel = document.getElementById('btn-close-selecao-espacial-panel');
const selecaoComunidadeSelect = document.getElementById('selecao-comunidade-select');
const btnSelecaoMapClick = document.getElementById('btn-selecao-map-click');
const selecaoMapInstruction = document.getElementById('selecao-map-instruction');
const selecaoEmptyPlaceholder = document.getElementById('selecao-empty-placeholder');
const selecaoComunidadeCard = document.getElementById('selecao-comunidade-info-card');
const selecaoActionsBar = document.getElementById('selecao-actions-bar');

const selecaoLotesSearchInput = document.getElementById('selecao-lotes-search-input');
const btnSelecaoLotesSearchClear = document.getElementById('btn-selecao-lotes-search-clear');
const selecaoEdifSearchInput = document.getElementById('selecao-edif-search-input');
const btnSelecaoEdifSearchClear = document.getElementById('btn-selecao-edif-search-clear');

if (btnSpatialSelectTool) {
    btnSpatialSelectTool.addEventListener('click', function (e) {
        e.preventDefault();
        toggleSelecaoEspacialTool();
    });
    L.DomEvent.disableClickPropagation(btnSpatialSelectTool);
}

if (selecaoEspacialPanel) {
    L.DomEvent.disableClickPropagation(selecaoEspacialPanel);
    L.DomEvent.disableScrollPropagation(selecaoEspacialPanel);
}

if (btnCloseSelecaoPanel) {
    btnCloseSelecaoPanel.addEventListener('click', function (e) {
        e.preventDefault();
        if (selecaoToolActive) {
            toggleSelecaoEspacialTool(false);
        }
    });
}

if (selecaoComunidadeSelect) {
    selecaoComunidadeSelect.addEventListener('change', function () {
        const val = this.value;
        if (val) {
            selectCommunityById(val);
        }
    });
}

if (btnSelecaoMapClick) {
    btnSelecaoMapClick.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        toggleSelecaoMapClickMode();
    });
    L.DomEvent.disableClickPropagation(btnSelecaoMapClick);
}

if (selecaoLotesSearchInput) {
    selecaoLotesSearchInput.addEventListener('input', function () {
        const val = this.value.trim();
        if (btnSelecaoLotesSearchClear) {
            btnSelecaoLotesSearchClear.style.display = val ? 'block' : 'none';
        }
        clearTimeout(selecaoLotesDebounce);
        selecaoLotesDebounce = setTimeout(() => {
            selecaoLotesSearch = val;
            selecaoLotesPage = 1;
            loadSelecaoLotesTable();
        }, 300);
    });
}

if (selecaoEdifSearchInput) {
    selecaoEdifSearchInput.addEventListener('input', function () {
        const val = this.value.trim();
        if (btnSelecaoEdifSearchClear) {
            btnSelecaoEdifSearchClear.style.display = val ? 'block' : 'none';
        }
        clearTimeout(selecaoEdifDebounce);
        selecaoEdifDebounce = setTimeout(() => {
            selecaoEdifSearch = val;
            selecaoEdifPage = 1;
            loadSelecaoEdifTable();
        }, 300);
    });
}

// Fecha menu de exportação se clicar fora
document.addEventListener('click', function (e) {
    const menu = document.getElementById('selecao-export-menu');
    const btn = document.getElementById('btn-export-selecao-dropdown');
    if (menu && menu.style.display === 'block') {
        if (!menu.contains(e.target) && (!btn || !btn.contains(e.target))) {
            menu.style.display = 'none';
        }
    }
});

function toggleSelecaoEspacialTool(forceState) {
    selecaoToolActive = (forceState !== undefined) ? forceState : !selecaoToolActive;
    const mapContainer = document.getElementById('map');

    if (selecaoToolActive) {
        if (btnSpatialSelectTool) btnSpatialSelectTool.classList.add('selecao-active');
        if (selecaoEspacialPanel) selecaoEspacialPanel.style.display = 'flex';

        // Desativa ferramentas concorrentes
        if (typeof filtroTerrenoActive !== 'undefined' && filtroTerrenoActive && typeof toggleFiltroTerrenoTool === 'function') toggleFiltroTerrenoTool(false);
        if (typeof bufferModeActive !== 'undefined' && bufferModeActive && typeof toggleBufferTool === 'function') toggleBufferTool(false);
        if (typeof divergenciaToolActive !== 'undefined' && divergenciaToolActive && typeof toggleDivergenciaTool === 'function') toggleDivergenciaTool();
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

        loadCommunitiesList();
    } else {
        if (btnSpatialSelectTool) btnSpatialSelectTool.classList.remove('selecao-active');
        if (selecaoEspacialPanel) selecaoEspacialPanel.style.display = 'none';
        if (mapContainer) mapContainer.classList.remove('map-buffer-cursor');
        selecaoMapClickMode = false;
        if (selecaoMapInstruction) selecaoMapInstruction.style.display = 'none';
        if (btnSelecaoMapClick) btnSelecaoMapClick.classList.remove('active');
        const lbl = document.getElementById('lbl-selecao-map-click');
        if (lbl) lbl.textContent = 'Clicar no Mapa';
        
        clearSelecaoLayers();
    }
}

function toggleSelecaoMapClickMode() {
    selecaoMapClickMode = !selecaoMapClickMode;
    const mapContainer = document.getElementById('map');
    const lbl = document.getElementById('lbl-selecao-map-click');

    if (selecaoMapClickMode) {
        // Desativa ferramentas concorrentes para garantir prioridade do clique na comunidade
        if (typeof identifyModeActive !== 'undefined' && identifyModeActive && typeof btnIdentify !== 'undefined' && btnIdentify) {
            btnIdentify.click();
        }
        if (typeof bufferModeActive !== 'undefined' && bufferModeActive && typeof toggleBufferTool === 'function') {
            toggleBufferTool(false);
        }
        if (map) map.closePopup();

        if (btnSelecaoMapClick) btnSelecaoMapClick.classList.add('active');
        if (lbl) lbl.textContent = 'Cancelar Clique';
        if (selecaoMapInstruction) selecaoMapInstruction.style.display = 'flex';
        if (mapContainer) mapContainer.classList.add('map-buffer-cursor');
    } else {
        if (btnSelecaoMapClick) btnSelecaoMapClick.classList.remove('active');
        if (lbl) lbl.textContent = 'Clicar no Mapa';
        if (selecaoMapInstruction) selecaoMapInstruction.style.display = 'none';
        if (mapContainer) mapContainer.classList.remove('map-buffer-cursor');
    }
}

function loadCommunitiesList() {
    if (communitiesListCache && selecaoComunidadeSelect && selecaoComunidadeSelect.options.length > 1) {
        return;
    }

    fetch('api/selecao_comunidade.php?action=listar')
        .then(res => res.json())
        .then(data => {
            if (data.success && data.data) {
                communitiesListCache = data.data;
                if (selecaoComunidadeSelect) {
                    selecaoComunidadeSelect.innerHTML = '<option value="" disabled selected>Selecione uma Comunidade (71)</option>';
                    data.data.forEach(com => {
                        const opt = document.createElement('option');
                        opt.value = com.id;
                        opt.textContent = `${com.comunidade} (${Number(com.area_ha).toLocaleString('pt-BR')} ha)`;
                        selecaoComunidadeSelect.appendChild(opt);
                    });
                }
            }
        })
        .catch(err => {
            console.error('Erro ao listar comunidades:', err);
        });
}

function selectCommunityById(id) {
    if (!id) return;
    if (typeof showLoading === 'function') showLoading();

    fetch(`api/selecao_comunidade.php?action=analisar&id=${encodeURIComponent(id)}`)
        .then(res => {
            if (!res.ok) throw new Error('Erro na resposta do servidor.');
            return res.json();
        })
        .then(data => {
            if (typeof hideLoading === 'function') hideLoading();
            if (!data.success) {
                alert(data.message || 'Comunidade não encontrada.');
                return;
            }
            currentSelectedCommunityId = data.comunidade.id;
            currentSelectedCommunityData = data;
            if (selecaoComunidadeSelect) selecaoComunidadeSelect.value = data.comunidade.id;

            if (selecaoMapClickMode) {
                toggleSelecaoMapClickMode();
            }

            renderCommunityAnalysis(data);
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro ao analisar comunidade:', err);
            alert('Não foi possível carregar os dados da comunidade.');
        });
}

function selectCommunityByLatLng(lat, lng) {
    if (typeof showLoading === 'function') showLoading();
    fetch(`api/selecao_comunidade.php?action=analisar&lat=${lat}&lng=${lng}`)
        .then(res => {
            if (!res.ok) throw new Error('Erro na resposta.');
            return res.json();
        })
        .then(data => {
            if (typeof hideLoading === 'function') hideLoading();
            if (!data.success) {
                alert(data.message || 'Nenhuma comunidade localizada no ponto clicado.');
                return;
            }
            currentSelectedCommunityId = data.comunidade.id;
            currentSelectedCommunityData = data;
            if (selecaoComunidadeSelect) selecaoComunidadeSelect.value = data.comunidade.id;

            if (selecaoMapClickMode) {
                toggleSelecaoMapClickMode();
            }

            renderCommunityAnalysis(data);
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro ao buscar comunidade por coordenada:', err);
            alert('Erro ao localizar comunidade.');
        });
}

function renderCommunityAnalysis(data) {
    const com = data.comunidade;
    const m = data.metricas;
    const dist = data.distribuicoes;

    if (selecaoEmptyPlaceholder) selecaoEmptyPlaceholder.style.display = 'none';
    if (selecaoComunidadeCard) selecaoComunidadeCard.style.display = 'flex';
    if (selecaoActionsBar) selecaoActionsBar.style.display = 'flex';

    const elNome = document.getElementById('selecao-comunidade-nome');
    const elAreaBadge = document.getElementById('selecao-comunidade-area-badge');
    if (elNome) elNome.textContent = com.nome;
    if (elAreaBadge) elAreaBadge.textContent = `${Number(com.area_ha).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} ha`;

    const elTotLotes = document.getElementById('kpi-comunidade-total-lotes');
    const elSubLotes = document.getElementById('kpi-comunidade-sub-lotes');
    if (elTotLotes) elTotLotes.textContent = Number(m.total_lotes).toLocaleString('pt-BR');
    if (elSubLotes) elSubLotes.textContent = `${Number(m.lotes_com_cadastro).toLocaleString('pt-BR')} cadastrados (${m.lotes_sem_cadastro} s/ cad.)`;

    const elTotEdif = document.getElementById('kpi-comunidade-total-edif');
    const elSubEdif = document.getElementById('kpi-comunidade-sub-edif');
    if (elTotEdif) elTotEdif.textContent = Number(m.total_edificacoes).toLocaleString('pt-BR');
    if (elSubEdif) elSubEdif.textContent = `${Number(m.edificacoes_com_agua).toLocaleString('pt-BR')} c/ água | ${m.edificacoes_com_esgoto} esgoto`;

    const elAreaM2 = document.getElementById('kpi-comunidade-area-m2');
    const elSubArea = document.getElementById('kpi-comunidade-sub-area');
    if (elAreaM2) elAreaM2.textContent = `${Number(com.area_km2).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} km²`;
    if (elSubArea) elSubArea.textContent = `${Number(com.area_m2).toLocaleString('pt-BR')} m²`;

    const elDens = document.getElementById('kpi-comunidade-densidade');
    const elSubDens = document.getElementById('kpi-comunidade-sub-dens');
    if (elDens) elDens.textContent = Number(m.densidade_edif_ha).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
    if (elSubDens) elSubDens.textContent = `edif/ha (${m.media_edif_por_lote} edif/lote)`;

    const lblCountLotes = document.getElementById('lbl-count-tab-lotes');
    const lblCountEdif = document.getElementById('lbl-count-tab-edif');
    if (lblCountLotes) lblCountLotes.textContent = Number(m.total_lotes).toLocaleString('pt-BR');
    if (lblCountEdif) lblCountEdif.textContent = Number(m.total_edificacoes).toLocaleString('pt-BR');

    // Distribuição de Tipos de Construção
    const elTipos = document.getElementById('selecao-tipos-construcao-list');
    if (elTipos) {
        if (!dist.tipos_construcao || dist.tipos_construcao.length === 0) {
            elTipos.innerHTML = '<div style="font-size:11px; color:var(--text-muted); padding: 4px 0;">Nenhuma edificação registrada nesta comunidade.</div>';
        } else {
            let htmlTipos = '';
            const maxVal = Math.max(...dist.tipos_construcao.map(t => parseInt(t.total) || 1));
            dist.tipos_construcao.forEach(t => {
                const perc = m.total_edificacoes > 0 ? Math.round(((parseInt(t.total) || 0) / m.total_edificacoes) * 100) : 0;
                const barWidth = Math.round(((parseInt(t.total) || 0) / maxVal) * 100);
                htmlTipos += `
                    <div class="selecao-stat-row">
                        <div class="selecao-stat-labels">
                            <span>${t.tipo}</span>
                            <strong>${Number(t.total).toLocaleString('pt-BR')} (${perc}%)</strong>
                        </div>
                        <div class="selecao-stat-bar-track">
                            <div class="selecao-stat-bar-progress" style="width: ${barWidth}%;"></div>
                        </div>
                    </div>
                `;
            });
            elTipos.innerHTML = htmlTipos;
        }
    }

    // Distribuição de Conservação
    const elCons = document.getElementById('selecao-estado-conservacao-list');
    if (elCons) {
        if (!dist.estado_conservacao || dist.estado_conservacao.length === 0) {
            elCons.innerHTML = '<div style="font-size:11px; color:var(--text-muted); padding: 4px 0;">Nenhuma informação de conservação.</div>';
        } else {
            let htmlCons = '';
            const maxVal = Math.max(...dist.estado_conservacao.map(t => parseInt(t.total) || 1));
            dist.estado_conservacao.forEach(c => {
                const perc = m.total_edificacoes > 0 ? Math.round(((parseInt(c.total) || 0) / m.total_edificacoes) * 100) : 0;
                const barWidth = Math.round(((parseInt(c.total) || 0) / maxVal) * 100);
                htmlCons += `
                    <div class="selecao-stat-row">
                        <div class="selecao-stat-labels">
                            <span>${c.estado}</span>
                            <strong>${Number(c.total).toLocaleString('pt-BR')} (${perc}%)</strong>
                        </div>
                        <div class="selecao-stat-bar-track">
                            <div class="selecao-stat-bar-progress" style="width: ${barWidth}%; background: linear-gradient(90deg, #10b981, #059669);"></div>
                        </div>
                    </div>
                `;
            });
            elCons.innerHTML = htmlCons;
        }
    }

    // Infraestrutura Água e Esgoto
    const elInfraAgua = document.getElementById('selecao-infra-agua-val');
    const elInfraEsgoto = document.getElementById('selecao-infra-esgoto-val');
    const percAgua = m.total_edificacoes > 0 ? Math.round((m.edificacoes_com_agua / m.total_edificacoes) * 100) : 0;
    const percEsgoto = m.total_edificacoes > 0 ? Math.round((m.edificacoes_com_esgoto / m.total_edificacoes) * 100) : 0;
    if (elInfraAgua) elInfraAgua.textContent = `${Number(m.edificacoes_com_agua).toLocaleString('pt-BR')} (${percAgua}%)`;
    if (elInfraEsgoto) elInfraEsgoto.textContent = `${Number(m.edificacoes_com_esgoto).toLocaleString('pt-BR')} (${percEsgoto}%)`;

    // Desenha o limite da comunidade no mapa
    renderSelectedCommunityPolygon(com.geojson);

    // Carrega tabelas
    selecaoLotesPage = 1;
    selecaoEdifPage = 1;
    loadSelecaoLotesTable();
    loadSelecaoEdifTable();

    // Se as feições estavam pintadas, recarrega para a nova comunidade
    if (isSelecaoDestaquesVisible) {
        loadSelecaoDestaquesGeoJSON();
    }
}

function renderSelectedCommunityPolygon(geojson) {
    if (selecaoCommunityLayer) {
        map.removeLayer(selecaoCommunityLayer);
        selecaoCommunityLayer = null;
    }

    if (!map.getPane('selecaoCommunityPane')) {
        map.createPane('selecaoCommunityPane');
        map.getPane('selecaoCommunityPane').style.zIndex = 610;
    }

    if (geojson) {
        selecaoCommunityLayer = L.geoJSON(geojson, {
            pane: 'selecaoCommunityPane',
            interactive: false,
            style: {
                color: '#38bdf8',
                weight: 3.5,
                dashArray: '6, 6',
                fillColor: '#38bdf8',
                fillOpacity: 0.12
            }
        }).addTo(map);

        map.fitBounds(selecaoCommunityLayer.getBounds(), { padding: [30, 30], maxZoom: 17 });
    }
}

function switchSelecaoTab(tabName) {
    currentSelecaoTab = tabName;

    // Atualiza botões
    const btnResumo = document.getElementById('tab-btn-selecao-resumo');
    const btnLotes = document.getElementById('tab-btn-selecao-lotes');
    const btnEdif = document.getElementById('tab-btn-selecao-edificacoes');
    if (btnResumo) btnResumo.classList.toggle('active', tabName === 'resumo');
    if (btnLotes) btnLotes.classList.toggle('active', tabName === 'lotes');
    if (btnEdif) btnEdif.classList.toggle('active', tabName === 'edificacoes');

    // Atualiza seções
    const secResumo = document.getElementById('selecao-tab-resumo');
    const secLotes = document.getElementById('selecao-tab-lotes');
    const secEdif = document.getElementById('selecao-tab-edificacoes');

    if (secResumo) secResumo.style.display = (tabName === 'resumo') ? 'block' : 'none';
    if (secLotes) secLotes.style.display = (tabName === 'lotes') ? 'block' : 'none';
    if (secEdif) secEdif.style.display = (tabName === 'edificacoes') ? 'block' : 'none';
}

function loadSelecaoLotesTable() {
    if (!currentSelectedCommunityId) return;

    const tbody = document.getElementById('selecao-lotes-table-tbody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center; padding: 18px; color: var(--text-muted);">
                    <div class="spinner" style="display:inline-block; vertical-align:middle; margin-right:6px;"></div>
                    Carregando lotes...
                </td>
            </tr>
        `;
    }

    const params = new URLSearchParams({
        action: 'tabela_lotes',
        id: currentSelectedCommunityId,
        page: selecaoLotesPage,
        limit: selecaoLotesLimit,
        busca: selecaoLotesSearch
    });

    fetch(`api/selecao_comunidade.php?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            if (!tbody) return;
            if (!data.success || !data.data || data.data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 18px; color: var(--text-muted);">
                            Nenhum lote encontrado.
                        </td>
                    </tr>
                `;
                updateSelecaoLotesPagination(0, 1, 1);
                return;
            }

            let html = '';
            data.data.forEach(item => {
                const statusBadge = item.has_cadastro
                    ? '<span class="status-badge badge-regular" style="font-size:10px;">Cadastrado</span>'
                    : '<span class="status-badge badge-divergente" style="font-size:10px;">Sem Cad.</span>';

                const areaGisStr = item.area_gis ? `${Number(item.area_gis).toLocaleString('pt-BR', { minimumFractionDigits: 2 })} m²` : '-';
                const inscStr = item.insc_munic || '-';

                html += `
                    <tr>
                        <td style="font-weight:600; font-family:monospace; color:var(--text-main); font-size:11px;">${inscStr}</td>
                        <td style="max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${item.contribuinte}">${item.contribuinte}</td>
                        <td style="max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${item.logradouro}">${item.logradouro}</td>
                        <td style="font-size:11px;">${areaGisStr}</td>
                        <td>${statusBadge}</td>
                        <td style="text-align:center;">
                            <button type="button" class="btn-table-view" title="Localizar lote no mapa" onclick="zoomToSelecaoItem(${item.lat}, ${item.lng}, 'Lote: ${inscStr}<br>Contribuinte: ${item.contribuinte}')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
            updateSelecaoLotesPagination(data.total, data.page, data.total_pages);
        })
        .catch(err => {
            console.error('Erro ao carregar tabela de lotes da comunidade:', err);
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#f43f5e; padding:15px;">Erro ao carregar dados.</td></tr>';
            }
        });
}

function updateSelecaoLotesPagination(total, page, totalPages) {
    const info = document.getElementById('selecao-lotes-page-info');
    const btnPrev = document.getElementById('btn-selecao-lotes-prev');
    const btnNext = document.getElementById('btn-selecao-lotes-next');

    if (info) info.textContent = `Pág. ${page} de ${totalPages || 1} (${Number(total).toLocaleString('pt-BR')})`;
    if (btnPrev) btnPrev.disabled = (page <= 1);
    if (btnNext) btnNext.disabled = (page >= totalPages || totalPages === 0);
}

function changeSelecaoLotesPage(delta) {
    selecaoLotesPage = Math.max(1, selecaoLotesPage + delta);
    loadSelecaoLotesTable();
}

function clearSelecaoLotesSearch() {
    if (selecaoLotesSearchInput) selecaoLotesSearchInput.value = '';
    if (btnSelecaoLotesSearchClear) btnSelecaoLotesSearchClear.style.display = 'none';
    selecaoLotesSearch = '';
    selecaoLotesPage = 1;
    loadSelecaoLotesTable();
}

function loadSelecaoEdifTable() {
    if (!currentSelectedCommunityId) return;

    const tbody = document.getElementById('selecao-edif-table-tbody');
    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" style="text-align:center; padding: 18px; color: var(--text-muted);">
                    <div class="spinner" style="display:inline-block; vertical-align:middle; margin-right:6px;"></div>
                    Carregando edificações...
                </td>
            </tr>
        `;
    }

    const params = new URLSearchParams({
        action: 'tabela_edificacoes',
        id: currentSelectedCommunityId,
        page: selecaoEdifPage,
        limit: selecaoEdifLimit,
        busca: selecaoEdifSearch
    });

    fetch(`api/selecao_comunidade.php?${params.toString()}`)
        .then(res => res.json())
        .then(data => {
            if (!tbody) return;
            if (!data.success || !data.data || data.data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" style="text-align:center; padding: 18px; color: var(--text-muted);">
                            Nenhuma edificação encontrada.
                        </td>
                    </tr>
                `;
                updateSelecaoEdifPagination(0, 1, 1);
                return;
            }

            let html = '';
            data.data.forEach(item => {
                const tipoStr = item.tipo_construcao && item.tipo_construcao !== '-' ? item.tipo_construcao : 'Não Informado';
                const consStr = item.estado_conservacao && item.estado_conservacao !== '-' ? item.estado_conservacao : '-';
                const infraStr = `${item.agua && item.agua.toLowerCase().includes('sim') ? '💧' : '❌'} / ${item.esgoto && item.esgoto.toLowerCase().includes('sim') ? '🚽' : '❌'}`;
                const inscStr = item.insc_munic || '-';

                html += `
                    <tr>
                        <td style="font-weight:600; font-family:monospace; color:var(--text-main); font-size:11px;">${inscStr}</td>
                        <td style="max-width:110px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${item.contribuinte}">${item.contribuinte}</td>
                        <td style="max-width:90px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${tipoStr}">${tipoStr}</td>
                        <td style="font-size:11px;">${consStr}</td>
                        <td style="font-size:11px; text-align:center;">${infraStr}</td>
                        <td style="text-align:center;">
                            <button type="button" class="btn-table-view" title="Localizar edificação no mapa" onclick="zoomToSelecaoItem(${item.lat}, ${item.lng}, 'Edificação: ${inscStr}<br>Tipo: ${tipoStr}')">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                            </button>
                        </td>
                    </tr>
                `;
            });
            tbody.innerHTML = html;
            updateSelecaoEdifPagination(data.total, data.page, data.total_pages);
        })
        .catch(err => {
            console.error('Erro ao carregar tabela de edificações da comunidade:', err);
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:#f43f5e; padding:15px;">Erro ao carregar dados.</td></tr>';
            }
        });
}

function updateSelecaoEdifPagination(total, page, totalPages) {
    const info = document.getElementById('selecao-edif-page-info');
    const btnPrev = document.getElementById('btn-selecao-edif-prev');
    const btnNext = document.getElementById('btn-selecao-edif-next');

    if (info) info.textContent = `Pág. ${page} de ${totalPages || 1} (${Number(total).toLocaleString('pt-BR')})`;
    if (btnPrev) btnPrev.disabled = (page <= 1);
    if (btnNext) btnNext.disabled = (page >= totalPages || totalPages === 0);
}

function changeSelecaoEdifPage(delta) {
    selecaoEdifPage = Math.max(1, selecaoEdifPage + delta);
    loadSelecaoEdifTable();
}

function clearSelecaoEdifSearch() {
    if (selecaoEdifSearchInput) selecaoEdifSearchInput.value = '';
    if (btnSelecaoEdifSearchClear) btnSelecaoEdifSearchClear.style.display = 'none';
    selecaoEdifSearch = '';
    selecaoEdifPage = 1;
    loadSelecaoEdifTable();
}

function zoomToSelecaoItem(lat, lng, popupText) {
    if (!lat || !lng) return;

    if (selecaoHighlightMarker) {
        map.removeLayer(selecaoHighlightMarker);
        selecaoHighlightMarker = null;
    }

    map.setView([lat, lng], Math.max(map.getZoom(), 18));

    selecaoHighlightMarker = L.circleMarker([lat, lng], {
        radius: 8,
        color: '#f8fafc',
        weight: 3,
        fillColor: '#38bdf8',
        fillOpacity: 0.9,
        zIndexOffset: 1000
    }).addTo(map);

    if (popupText) {
        selecaoHighlightMarker.bindPopup(`<div style="font-size:12px; line-height:1.4;">${popupText}</div>`).openPopup();
    }
}

function zoomToSelectedCommunity() {
    if (selecaoCommunityLayer) {
        map.fitBounds(selecaoCommunityLayer.getBounds(), { padding: [30, 30], maxZoom: 17 });
    }
}

function toggleSelecaoDestaquesLayer() {
    isSelecaoDestaquesVisible = !isSelecaoDestaquesVisible;
    const btn = document.getElementById('btn-toggle-selecao-layer');
    const lbl = document.getElementById('lbl-toggle-selecao-layer');

    if (!isSelecaoDestaquesVisible) {
        if (selecaoDestaquesLayer) {
            map.removeLayer(selecaoDestaquesLayer);
            selecaoDestaquesLayer = null;
        }
        if (selecaoLegendControl) {
            map.removeControl(selecaoLegendControl);
            selecaoLegendControl = null;
        }
        if (btn) btn.classList.remove('active');
        if (lbl) lbl.textContent = 'Pintar Feições';
        return;
    }

    if (btn) btn.classList.add('active');
    if (lbl) lbl.textContent = 'Remover Cores';

    loadSelecaoDestaquesGeoJSON();
}

function loadSelecaoDestaquesGeoJSON() {
    if (!currentSelectedCommunityId) return;
    if (typeof showLoading === 'function') showLoading();

    if (!map.getPane('selecaoDestaquesPane')) {
        map.createPane('selecaoDestaquesPane');
        map.getPane('selecaoDestaquesPane').style.zIndex = 625;
    }

    const params = new URLSearchParams({
        action: 'geojson_destaques',
        id: currentSelectedCommunityId,
        tipo: 'ambos'
    });

    fetch(`api/selecao_comunidade.php?${params.toString()}`)
        .then(res => {
            if (!res.ok) throw new Error('Falha na resposta do servidor.');
            return res.json();
        })
        .then(geojson => {
            if (typeof hideLoading === 'function') hideLoading();
            if (selecaoDestaquesLayer) {
                map.removeLayer(selecaoDestaquesLayer);
                selecaoDestaquesLayer = null;
            }

            if (!geojson || !geojson.features || geojson.features.length === 0) {
                alert('Nenhuma feição encontrada nesta comunidade.');
                return;
            }

            selecaoDestaquesLayer = L.geoJSON(geojson, {
                pane: 'selecaoDestaquesPane',
                style: function (feature) {
                    const camada = feature.properties.camada;
                    if (camada === 'edificacoes') {
                        return {
                            color: '#ea580c',
                            weight: 1.8,
                            fillColor: '#f97316',
                            fillOpacity: 0.65
                        };
                    } else {
                        return {
                            color: '#0284c7',
                            weight: 1.6,
                            fillColor: '#38bdf8',
                            fillOpacity: 0.35
                        };
                    }
                },
                onEachFeature: function (feature, layer) {
                    const p = feature.properties;
                    const isEdif = (p.camada === 'edificacoes');

                    layer.on({
                        click: function (e) {
                            if (selecaoToolActive && selecaoMapClickMode) {
                                L.DomEvent.stopPropagation(e);
                                if (layer.closePopup) layer.closePopup();
                                if (map) map.closePopup();
                                selectCommunityByLatLng(e.latlng.lat, e.latlng.lng);
                            }
                        },
                        mouseover: function (e) {
                            const l = e.target;
                            l.setStyle({
                                weight: 3,
                                color: '#ffffff',
                                fillOpacity: 0.85
                            });
                            if (!L.Browser.ie && !L.Browser.opera && !L.Browser.edge) {
                                l.bringToFront();
                            }
                        },
                        mouseout: function (e) {
                            if (selecaoDestaquesLayer) {
                                selecaoDestaquesLayer.resetStyle(e.target);
                            }
                        }
                    });

                    let popupContent = `
                        <div style="font-family:sans-serif; font-size:12px; line-height:1.45; min-width:220px;">
                            <div style="font-weight:700; font-size:13px; color:${isEdif ? '#f97316' : '#0284c7'}; margin-bottom:4px;">
                                ${isEdif ? '🏢 Edificação' : '🏘️ Lote'} - Inscrição: ${p.insc_munic}
                            </div>
                            <div><strong>Contribuinte:</strong> ${p.contribuinte}</div>
                            <div><strong>Logradouro:</strong> ${p.logradouro}</div>
                    `;

                    if (isEdif) {
                        popupContent += `
                            <div><strong>Tipo:</strong> ${p.tipo_construcao || '-'}</div>
                            <div><strong>Água / Esgoto:</strong> ${p.agua || '-'} / ${p.esgoto || '-'}</div>
                            <div><strong>Área Edificada:</strong> ${p.area_total_edificacao || '-'} m²</div>
                        `;
                    } else {
                        popupContent += `
                            <div><strong>Área GIS:</strong> ${p.area_gis ? Number(p.area_gis).toLocaleString('pt-BR') + ' m²' : '-'}</div>
                            <div><strong>Área Tributário:</strong> ${p.area_tributario || '-'} m²</div>
                            <div><strong>Status:</strong> ${p.status}</div>
                        `;
                    }

                    if (p.insc_munic && p.insc_munic !== '-') {
                        popupContent += `
                            <div style="margin-top:8px; padding-top:6px; border-top:1px solid #e2e8f0; text-align:right;">
                                <button type="button" onclick="handleGerarEspelhoBCI('${p.insc_munic}')" style="background:#0284c7; color:#fff; border:none; padding:4px 8px; border-radius:4px; font-size:11px; cursor:pointer;">
                                    Abrir Espelho BCI &rarr;
                                </button>
                            </div>
                        `;
                    }

                    popupContent += `</div>`;
                    layer.bindPopup(popupContent);
                }
            }).addTo(map);

            // Adiciona legenda flutuante no mapa se não existir
            if (!selecaoLegendControl) {
                selecaoLegendControl = L.control({ position: 'bottomright' });
                selecaoLegendControl.onAdd = function () {
                    const div = L.DomUtil.create('div', 'divergencia-map-legend selecao-map-legend');
                    div.innerHTML = `
                        <strong>Seleção Espacial: ${currentSelectedCommunityData ? currentSelectedCommunityData.comunidade.nome : ''}</strong>
                        <div class="divergencia-map-legend-item"><span class="legend-swatch swatch-comunidade"></span> Limite da Comunidade</div>
                        <div class="divergencia-map-legend-item"><span class="legend-swatch swatch-lote-sel"></span> Lotes Interceptados</div>
                        <div class="divergencia-map-legend-item"><span class="legend-swatch swatch-edif-sel"></span> Edificações Interceptadas</div>
                    `;
                    L.DomEvent.disableClickPropagation(div);
                    L.DomEvent.disableScrollPropagation(div);
                    return div;
                };
                selecaoLegendControl.addTo(map);
            }
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro ao carregar destaques da seleção espacial:', err);
            alert('Não foi possível carregar as feições da comunidade no mapa.');
        });
}

function clearSelecaoLayers() {
    if (selecaoCommunityLayer) {
        map.removeLayer(selecaoCommunityLayer);
        selecaoCommunityLayer = null;
    }
    if (selecaoDestaquesLayer) {
        map.removeLayer(selecaoDestaquesLayer);
        selecaoDestaquesLayer = null;
    }
    if (selecaoHighlightMarker) {
        map.removeLayer(selecaoHighlightMarker);
        selecaoHighlightMarker = null;
    }
    if (selecaoLegendControl) {
        map.removeControl(selecaoLegendControl);
        selecaoLegendControl = null;
    }
    isSelecaoDestaquesVisible = false;
    const lbl = document.getElementById('lbl-toggle-selecao-layer');
    const btn = document.getElementById('btn-toggle-selecao-layer');
    if (btn) btn.classList.remove('active');
    if (lbl) lbl.textContent = 'Pintar Feições';
}

function toggleExportSelecaoMenu(event) {
    if (event) event.stopPropagation();
    const menu = document.getElementById('selecao-export-menu');
    if (menu) {
        menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
    }
}

function exportSelecaoCSV(tipo) {
    if (!currentSelectedCommunityId) {
        alert('Selecione uma comunidade primeiro.');
        return;
    }
    const menu = document.getElementById('selecao-export-menu');
    if (menu) menu.style.display = 'none';

    window.location.href = `api/selecao_comunidade.php?action=export_csv&id=${encodeURIComponent(currentSelectedCommunityId)}&tipo=${encodeURIComponent(tipo)}`;
}

// Exportações Globais
window.toggleSelecaoEspacialTool = toggleSelecaoEspacialTool;
window.toggleSelecaoMapClickMode = toggleSelecaoMapClickMode;
window.selectCommunityById = selectCommunityById;
window.selectCommunityByLatLng = selectCommunityByLatLng;
window.switchSelecaoTab = switchSelecaoTab;
window.changeSelecaoLotesPage = changeSelecaoLotesPage;
window.clearSelecaoLotesSearch = clearSelecaoLotesSearch;
window.changeSelecaoEdifPage = changeSelecaoEdifPage;
window.clearSelecaoEdifSearch = clearSelecaoEdifSearch;
window.zoomToSelecaoItem = zoomToSelecaoItem;
window.zoomToSelectedCommunity = zoomToSelectedCommunity;
window.toggleSelecaoDestaquesLayer = toggleSelecaoDestaquesLayer;
window.toggleExportSelecaoMenu = toggleExportSelecaoMenu;
window.exportSelecaoCSV = exportSelecaoCSV;
