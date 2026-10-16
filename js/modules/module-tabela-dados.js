/**
 * ==========================================================================
 * MÓDULO: TABELA DE DADOS PAGINADA E PAINEL INFERIOR
 * Arquivo: js/modules/module-tabela-dados.js
 * ==========================================================================
 */

let currentTableDataType = 'lotes'; // 'lotes' ou 'edificacoes'
let currentLotesTablePage = 1;
let currentLotesTableLimit = 25;
let currentLotesTableSearch = '';
let currentLotesTableSortBy = 'id';
let currentLotesTableSortDir = 'ASC';
let currentLotesTableClass = 'all';
let lotesTableSearchDebounce = null;
let lotesTableHighlightLayer = null;
let isLotesTableInitialLoaded = false;

// Alterna entre modo 'lotes' e 'edificacoes'
function switchTableDataType(type) {
    if (type !== 'edificacoes') type = 'lotes';
    currentTableDataType = type;

    const btnLotes = document.getElementById('btn-type-lotes');
    const btnEdif = document.getElementById('btn-type-edificacoes');
    if (btnLotes) btnLotes.classList.toggle('active', type === 'lotes');
    if (btnEdif) btnEdif.classList.toggle('active', type === 'edificacoes');

    currentLotesTableSortBy = 'id';
    currentLotesTableSortDir = 'ASC';
    currentLotesTablePage = 1;
    currentLotesTableClass = 'all';

    renderTableHead();
    syncTableWithFilters();
    fetchLotesTableData(1);
}

// Renderiza os cabeçalhos da tabela conforme o tipo selecionado
function renderTableHead() {
    const thead = document.getElementById('table-lotes-thead');
    if (!thead) return;

    if (currentTableDataType === 'edificacoes') {
        thead.innerHTML = `
            <tr>
                <th style="width: 50px;" onclick="sortLotesTable('id')">ID ⇕</th>
                <th style="width: 140px;" onclick="sortLotesTable('insc_munic')">INSC_MUNIC ⇕</th>
                <th style="min-width: 160px;" onclick="sortLotesTable('contribuinte')">NOMEDOCONTRIBUINTE ⇕</th>
                <th style="min-width: 150px;" onclick="sortLotesTable('logradouro')">NOMELOGRADOURO ⇕</th>
                <th style="width: 120px;" onclick="sortLotesTable('bairro')">NOMEBAIRRO ⇕</th>
                <th style="width: 140px;" onclick="sortLotesTable('tipo_construcao')">TIPO_DE_CONSTRUCAO ⇕</th>
                <th style="width: 120px;" onclick="sortLotesTable('estrutura')">ESTRUTURA ⇕</th>
                <th style="width: 130px;" onclick="sortLotesTable('estado_conservacao')">ESTADO_CONSERVACAO ⇕</th>
                <th style="width: 130px;" onclick="sortLotesTable('situacao_imovel')">SITUACAO_DO_IMOVEL ⇕</th>
                <th style="width: 85px;" onclick="sortLotesTable('esgoto')">ESGOTO ⇕</th>
                <th style="width: 85px;" onclick="sortLotesTable('agua')">AGUA ⇕</th>
                <th style="width: 120px; text-align: right;" onclick="sortLotesTable('area_unidade')">AREA_UNIDADE (m²) ⇕</th>
                <th style="width: 130px; text-align: right;" onclick="sortLotesTable('area_total_edificacao')">AREA_TOTAL_EDIF. (m²) ⇕</th>
                <th style="width: 110px; text-align: center;">Ações</th>
            </tr>
        `;
    } else {
        thead.innerHTML = `
            <tr>
                <th style="width: 55px;" onclick="sortLotesTable('id')">ID ⇕</th>
                <th style="width: 140px;" onclick="sortLotesTable('inscimob2')">Inscrição 2 ⇕</th>
                <th style="width: 120px;" onclick="sortLotesTable('inscimob')">Insc. Imob. ⇕</th>
                <th style="width: 130px;" onclick="sortLotesTable('status_cadastral')">Status ⇕</th>
                <th style="width: 150px;" onclick="sortLotesTable('utilizacao')">Utilização (9027) ⇕</th>
                <th style="width: 140px;" onclick="sortLotesTable('ocupacao')">Ocupação (9025) ⇕</th>
                <th onclick="sortLotesTable('proprietario')">Proprietário ⇕</th>
                <th onclick="sortLotesTable('logradouro')">Logradouro ⇕</th>
                <th style="width: 130px;" onclick="sortLotesTable('bairro')">Bairro ⇕</th>
                <th style="width: 110px; text-align: right;" onclick="sortLotesTable('area_terreno')">Área (m²) ⇕</th>
                <th style="width: 115px; text-align: center;">Ações</th>
            </tr>
        `;
    }
}

// Alterna (abre ou fecha) o painel inferior de dados
function toggleBottomPanel(forceOpen) {
    const panel = document.getElementById('bottom-lotes-panel');
    const btnTool = document.getElementById('btn-table-tool');
    if (!panel) return;

    if (forceOpen === true) {
        panel.classList.remove('is-hidden');
        panel.classList.remove('collapsed');
        if (btnTool) btnTool.classList.add('active');
        if (!isLotesTableInitialLoaded) {
            renderTableHead();
            fetchLotesTableData(1);
        }
    } else if (forceOpen === false) {
        panel.classList.add('collapsed');
    } else {
        if (panel.classList.contains('is-hidden')) {
            panel.classList.remove('is-hidden');
            panel.classList.remove('collapsed');
            if (btnTool) btnTool.classList.add('active');
            if (!isLotesTableInitialLoaded) {
                renderTableHead();
                fetchLotesTableData(1);
            }
        } else if (panel.classList.contains('collapsed')) {
            panel.classList.remove('collapsed');
            if (btnTool) btnTool.classList.add('active');
        } else {
            panel.classList.add('collapsed');
            if (btnTool) btnTool.classList.remove('active');
        }
    }
}

// Fecha o painel inferior
function closeBottomPanel() {
    const panel = document.getElementById('bottom-lotes-panel');
    const btnTool = document.getElementById('btn-table-tool');
    if (panel) {
        panel.classList.add('is-hidden');
    }
    if (btnTool) {
        btnTool.classList.remove('active');
    }
}

// Sincroniza o seletor de classe da toolbar da tabela com o critério do filtro ativo
function syncTableWithFilters() {
    const layerKey = (currentTableDataType === 'edificacoes') ? 'geo_edificacoes_dm' : 'geo_lotes_dm';
    const filterState = (typeof lotesFilters !== 'undefined') ? lotesFilters[layerKey] : null;
    const isApplied = filterState && filterState.applied;
    const criterion = isApplied ? filterState.criterion : 'all';
    const classSelect = document.getElementById('table-filter-class');
    const filterTag = document.getElementById('bottom-lotes-filter-tag');

    let criterionLabel = 'Sem Filtro Temático';
    if (isApplied) {
        const labelsMap = {
            status: 'Status Cadastral',
            utilizacao: 'Utilização do Imóvel',
            ocupacao: 'Tipo de Ocupação',
            tipo_construcao: 'Tipo de Construção',
            estrutura: 'Estrutura',
            estado_conservacao: 'Estado de Conservação',
            situacao_imovel: 'Situação do Imóvel',
            esgoto: 'Rede de Esgoto',
            agua: 'Rede de Água',
            lixo: 'Coleta de Lixo'
        };
        criterionLabel = labelsMap[filterState.criterion] || filterState.criterion;
    }

    if (filterTag) {
        filterTag.textContent = `Filtro: ${criterionLabel}`;
    }

    if (classSelect) {
        let optionsHtml = '<option value="all">Todas as Classes</option>';
        if (isApplied && filterState && filterState.data && filterState.criterion !== 'none') {
            const list = filterState.data[filterState.criterion] || [];
            list.forEach(item => {
                const isSelected = (currentLotesTableClass === item.valor) ? 'selected' : '';
                optionsHtml += `<option value="${escapeDomString(item.valor)}" ${isSelected}>${escapeDomString(item.valor)} (${item.total.toLocaleString('pt-BR')})</option>`;
            });
        }
        classSelect.innerHTML = optionsHtml;
    }

    currentLotesTableClass = 'all';
    currentLotesTablePage = 1;

    const panel = document.getElementById('bottom-lotes-panel');
    if (panel && !panel.classList.contains('collapsed') && !panel.classList.contains('is-hidden')) {
        fetchLotesTableData(1);
    }
}

// Busca os dados paginados da API
function fetchLotesTableData(page = 1) {
    currentLotesTablePage = Math.max(1, page);
    const layerKey = (currentTableDataType === 'edificacoes') ? 'geo_edificacoes_dm' : 'geo_lotes_dm';
    const filterState = (typeof lotesFilters !== 'undefined') ? lotesFilters[layerKey] : null;
    const isApplied = filterState && filterState.applied;
    const criterion = isApplied ? filterState.criterion : 'all';

    const statusInfo = document.getElementById('table-status-info');
    const tbody = document.getElementById('table-lotes-tbody');
    const colCount = (currentTableDataType === 'edificacoes') ? 14 : 11;
    const labelSingular = (currentTableDataType === 'edificacoes') ? 'edificações' : 'lotes';

    if (statusInfo) statusInfo.textContent = `Carregando dados de ${labelSingular}...`;
    if (tbody && tbody.children.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${colCount}" class="no-features-msg"><div class="spinner" style="margin: 0 auto 8px;"></div>Carregando ${labelSingular}...</td></tr>`;
    }

    let activeClassesStr = '';
    if (isApplied && filterState && filterState.activeClasses && filterState.activeClasses.size > 0) {
        activeClassesStr = Array.from(filterState.activeClasses).join('|||');
    }

    const params = new URLSearchParams({
        type: currentTableDataType,
        page: currentLotesTablePage,
        limit: currentLotesTableLimit,
        search: currentLotesTableSearch,
        criterion: criterion,
        class: isApplied ? currentLotesTableClass : 'all',
        active_classes: activeClassesStr,
        sort_by: currentLotesTableSortBy,
        sort_dir: currentLotesTableSortDir,
        _t: Date.now()
    });

    fetch(`api/tabela_lotes.php?${params.toString()}`)
        .then(res => {
            if (!res.ok) throw new Error('Erro ao consultar tabela de dados');
            return res.json();
        })
        .then(data => {
            isLotesTableInitialLoaded = true;
            if (data.error) {
                if (statusInfo) statusInfo.textContent = 'Erro ao carregar dados.';
                if (tbody) tbody.innerHTML = `<tr><td colspan="${colCount}" class="no-features-msg">${escapeHtml(data.message || 'Erro na consulta.')}</td></tr>`;
                return;
            }

            renderLotesTable(data.data || []);
            renderTablePagination(data.page, data.total_pages, data.total, data.limit);

            const totalFmt = data.total.toLocaleString('pt-BR');
            const bottomBadge = document.getElementById('bottom-lotes-badge');
            if (bottomBadge) bottomBadge.textContent = `${totalFmt} ${labelSingular}`;
            if (statusInfo) statusInfo.textContent = `Total: ${totalFmt} ${labelSingular} encontrado(s)`;
        })
        .catch(err => {
            console.error('Erro na requisição da tabela de dados:', err);
            if (statusInfo) statusInfo.textContent = 'Falha na conexão.';
            if (tbody) tbody.innerHTML = `<tr><td colspan="${colCount}" class="no-features-msg">Não foi possível carregar os dados.</td></tr>`;
        });
}

// Renderiza as linhas da tabela conforme Lotes ou Edificações
function renderLotesTable(rows) {
    const tbody = document.getElementById('table-lotes-tbody');
    if (!tbody) return;

    const colCount = (currentTableDataType === 'edificacoes') ? 14 : 11;
    if (!rows || rows.length === 0) {
        tbody.innerHTML = `<tr><td colspan="${colCount}" class="no-features-msg">Nenhum registro encontrado com os filtros e busca aplicados.</td></tr>`;
        return;
    }

    let html = '';

    if (currentTableDataType === 'edificacoes') {
        rows.forEach(row => {
            const hasCad = row.has_cadastro;
            const inscTarget = escapeDomString(row.insc_munic !== '-' ? row.insc_munic : (row.inscimob || ''));

            const latVal = (row.lat !== null && row.lat !== undefined) ? Number(row.lat) : 'null';
            const lngVal = (row.lng !== null && row.lng !== undefined) ? Number(row.lng) : 'null';

            const esgotoBadge = (row.esgoto && row.esgoto.toLowerCase() === 'sim')
                ? '<span class="badge-table badge-cad-sim">Sim</span>'
                : ((row.esgoto && (row.esgoto.toLowerCase() === 'não' || row.esgoto.toLowerCase() === 'nao')) ? '<span class="badge-table badge-cad-nao">Não</span>' : escapeHtml(row.esgoto || '-'));

            const aguaBadge = (row.agua && row.agua.toLowerCase() === 'sim')
                ? '<span class="badge-table badge-cad-sim">Sim</span>'
                : ((row.agua && (row.agua.toLowerCase() === 'não' || row.agua.toLowerCase() === 'nao')) ? '<span class="badge-table badge-cad-nao">Não</span>' : escapeHtml(row.agua || '-'));

            let bciBtn = '';
            if (hasCad && row.insc_munic && row.insc_munic !== '-') {
                bciBtn = `
                    <button type="button" class="table-btn-action btn-bci" title="Gerar Boletim de Cadastro Imobiliário (BCI)" onclick="openLoteBCI('${inscTarget}')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        BCI
                    </button>
                `;
            }

            html += `
                <tr id="table-row-${row.id}">
                    <td><strong>#${row.id}</strong></td>
                    <td><code style="color: #38bdf8; font-weight:600;">${escapeHtml(row.insc_munic || '-')}</code></td>
                    <td title="${escapeDomString(row.contribuinte || '')}">${escapeHtml(row.contribuinte || '-')}</td>
                    <td title="${escapeDomString(row.logradouro || '')}">${escapeHtml(row.logradouro || '-')}</td>
                    <td>${escapeHtml(row.bairro || '-')}</td>
                    <td>${escapeHtml(row.tipo_construcao || '-')}</td>
                    <td>${escapeHtml(row.estrutura || '-')}</td>
                    <td>${escapeHtml(row.estado_conservacao || '-')}</td>
                    <td>${escapeHtml(row.situacao_imovel || '-')}</td>
                    <td style="text-align: center;">${esgotoBadge}</td>
                    <td style="text-align: center;">${aguaBadge}</td>
                    <td style="text-align: right;">${row.area_unidade && row.area_unidade !== '-' ? escapeHtml(row.area_unidade) : '-'}</td>
                    <td style="text-align: right;">${row.area_total_edificacao && row.area_total_edificacao !== '-' ? escapeHtml(row.area_total_edificacao) : '-'}</td>
                    <td style="text-align: center; white-space: nowrap;">
                        <button type="button" class="table-btn-action" title="Aproximar e Visualizar Edificação no Mapa" onclick="zoomToLote(${latVal}, ${lngVal}, ${row.id}, '${inscTarget}', 'geo_edificacoes_dm')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            Ver
                        </button>
                        ${bciBtn}
                    </td>
                </tr>
            `;
        });
    } else {
        rows.forEach(row => {
            const hasCad = row.has_cadastro;
            const cadBadge = hasCad 
                ? '<span class="badge-table badge-cad-sim">Com Cadastro</span>' 
                : '<span class="badge-table badge-cad-nao">Não Cadastrado</span>';

            const utilText = row.utilizacao || '-';
            const utilBadge = (utilText !== '-' && utilText !== 'Não Informado')
                ? `<span class="badge-table badge-util" title="${escapeDomString(utilText)}">${escapeDomString(utilText)}</span>`
                : `<span style="color: var(--text-muted); font-style: italic;">${escapeDomString(utilText)}</span>`;

            const latVal = (row.lat !== null && row.lat !== undefined) ? Number(row.lat) : 'null';
            const lngVal = (row.lng !== null && row.lng !== undefined) ? Number(row.lng) : 'null';
            const inscTarget = escapeDomString(row.inscimob2 || row.inscimob || '');

            let bciBtn = '';
            if (hasCad && (row.inscimob2 || row.inscimob)) {
                bciBtn = `
                    <button type="button" class="table-btn-action btn-bci" title="Gerar Boletim de Cadastro Imobiliário (BCI)" onclick="openLoteBCI('${inscTarget}')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                        </svg>
                        BCI
                    </button>
                `;
            }

            html += `
                <tr id="table-row-${row.id}">
                    <td><strong>#${row.id}</strong></td>
                    <td><code style="color: #38bdf8; font-weight:600;">${escapeHtml(row.inscimob2 || '-')}</code></td>
                    <td>${escapeHtml(row.inscimob || '-')}</td>
                    <td>${cadBadge}</td>
                    <td>${utilBadge}</td>
                    <td>${escapeHtml(row.ocupacao || '-')}</td>
                    <td title="${escapeDomString(row.proprietario || '')}">${escapeHtml(row.proprietario || '-')}</td>
                    <td title="${escapeDomString(row.logradouro || '')}">${escapeHtml(row.logradouro || '-')}</td>
                    <td>${escapeHtml(row.bairro || '-')}</td>
                    <td style="text-align: right;">${row.area_terreno && row.area_terreno !== '-' ? escapeHtml(row.area_terreno) + ' m²' : '-'}</td>
                    <td style="text-align: center; white-space: nowrap;">
                        <button type="button" class="table-btn-action" title="Aproximar e Visualizar Lote no Mapa" onclick="zoomToLote(${latVal}, ${lngVal}, ${row.id}, '${inscTarget}', 'geo_lotes_dm')">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"></circle>
                                <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                            </svg>
                            Ver
                        </button>
                        ${bciBtn}
                    </td>
                </tr>
            `;
        });
    }

    tbody.innerHTML = html;
}

// Renderiza a barra e os botões de paginação
function renderTablePagination(page, totalPages, total, limit) {
    const infoEl = document.getElementById('table-pagination-info');
    const btnsEl = document.getElementById('table-pagination-buttons');
    if (!infoEl || !btnsEl) return;

    const labelItem = (currentTableDataType === 'edificacoes') ? 'edificações' : 'lotes';

    if (total === 0) {
        infoEl.textContent = 'Exibindo 0 a 0 de 0 registros';
        btnsEl.innerHTML = '';
        return;
    }

    const start = ((page - 1) * limit) + 1;
    const end = Math.min(page * limit, total);
    infoEl.textContent = `Exibindo ${start.toLocaleString('pt-BR')} a ${end.toLocaleString('pt-BR')} de ${total.toLocaleString('pt-BR')} ${labelItem} (Página ${page} de ${totalPages})`;

    let html = '';

    html += `<button type="button" class="btn-page" ${page === 1 ? 'disabled' : ''} onclick="goToLotesPage(1)" title="Primeira Página">«</button>`;
    html += `<button type="button" class="btn-page" ${page === 1 ? 'disabled' : ''} onclick="goToLotesPage(${page - 1})" title="Página Anterior">‹</button>`;

    const maxVisible = 5;
    let startPage = Math.max(1, page - Math.floor(maxVisible / 2));
    let endPage = Math.min(totalPages, startPage + maxVisible - 1);
    if (endPage - startPage < maxVisible - 1) {
        startPage = Math.max(1, endPage - maxVisible + 1);
    }

    if (startPage > 1) {
        html += `<button type="button" class="btn-page" onclick="goToLotesPage(1)">1</button>`;
        if (startPage > 2) html += `<span style="padding: 0 4px; color: var(--text-muted);">...</span>`;
    }

    for (let p = startPage; p <= endPage; p++) {
        const isActive = (p === page) ? 'active' : '';
        html += `<button type="button" class="btn-page ${isActive}" onclick="goToLotesPage(${p})">${p}</button>`;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) html += `<span style="padding: 0 4px; color: var(--text-muted);">...</span>`;
        html += `<button type="button" class="btn-page" onclick="goToLotesPage(${totalPages})">${totalPages}</button>`;
    }

    html += `<button type="button" class="btn-page" ${page === totalPages ? 'disabled' : ''} onclick="goToLotesPage(${page + 1})" title="Próxima Página">›</button>`;
    html += `<button type="button" class="btn-page" ${page === totalPages ? 'disabled' : ''} onclick="goToLotesPage(${totalPages})" title="Última Página">»</button>`;

    btnsEl.innerHTML = html;
}

function goToLotesPage(page) {
    fetchLotesTableData(page);
}

function changeLotesTableLimit(newLimit) {
    currentLotesTableLimit = parseInt(newLimit, 10) || 25;
    fetchLotesTableData(1);
}

function onTableFilterClassChange(val) {
    currentLotesTableClass = val;
    fetchLotesTableData(1);
}

function sortLotesTable(column) {
    if (currentLotesTableSortBy === column) {
        currentLotesTableSortDir = (currentLotesTableSortDir === 'ASC') ? 'DESC' : 'ASC';
    } else {
        currentLotesTableSortBy = column;
        currentLotesTableSortDir = 'ASC';
    }
    fetchLotesTableData(currentLotesTablePage);
}

function clearLotesTableSearch() {
    const input = document.getElementById('bottom-table-search-input');
    const btnClear = document.getElementById('btn-clear-table-search');
    if (input) input.value = '';
    if (btnClear) btnClear.style.display = 'none';
    currentLotesTableSearch = '';
    fetchLotesTableData(1);
}

function zoomToLote(lat, lng, id, insc, layerKey = 'geo_lotes_dm') {
    if (!lat || !lng || isNaN(lat) || isNaN(lng)) {
        alert(`O registro #${id} (${insc || ''}) não possui coordenadas espaciais válidas no banco.`);
        return;
    }

    document.querySelectorAll('#table-lotes-tbody tr').forEach(r => r.classList.remove('row-selected'));
    const rowEl = document.getElementById(`table-row-${id}`);
    if (rowEl) rowEl.classList.add('row-selected');

    const targetLayer = layerKey || 'geo_lotes_dm';
    const chk = document.getElementById(`chk-${targetLayer}`);
    if (chk && !chk.checked) chk.checked = true;
    if (typeof layers !== 'undefined' && layers[targetLayer] && typeof map !== 'undefined' && !map.hasLayer(layers[targetLayer])) {
        map.addLayer(layers[targetLayer]);
    }

    if (lotesTableHighlightLayer && typeof map !== 'undefined') {
        map.removeLayer(lotesTableHighlightLayer);
        lotesTableHighlightLayer = null;
    }

    if (typeof map === 'undefined') return;

    lotesTableHighlightLayer = L.circleMarker([lat, lng], {
        radius: 14,
        color: '#f59e0b',
        weight: 3.5,
        fillColor: '#fbbf24',
        fillOpacity: 0.55
    }).addTo(map);

    const labelItem = (targetLayer === 'geo_edificacoes_dm') ? 'Edificação' : 'Lote';
    let popupHtml = `<div style="font-size: 12px; line-height: 1.4;">`;
    popupHtml += `<strong style="color: #0284c7; font-size: 13px;">${labelItem} #${id}</strong><br><hr style="border:0;border-top:1px solid #3c4d57;margin:4px 0;">`;
    if (insc) popupHtml += `<strong>Inscrição:</strong> ${escapeHtml(insc)}<br>`;
    popupHtml += `<div style="margin-top: 8px;">`;
    if (insc) {
        popupHtml += `<button type="button" class="btn-primary" style="width: 100%; padding: 5px 8px; font-size: 11px; cursor: pointer; border-radius: 4px;" onclick="handleGerarEspelhoBCI('${escapeHtml(insc)}')">Ver Boletim BCI</button>`;
    } else {
        popupHtml += `<button type="button" class="btn-primary" style="width: 100%; padding: 5px 8px; font-size: 11px; cursor: pointer; border-radius: 4px;" onclick="executeIdentify({lat: ${lat}, lng: ${lng}})">Identificar</button>`;
    }
    popupHtml += `</div></div>`;

    lotesTableHighlightLayer.bindPopup(popupHtml);

    map.flyTo([lat, lng], 19, {
        animate: true,
        duration: 1.0
    });

    map.once('moveend', () => {
        if (lotesTableHighlightLayer) lotesTableHighlightLayer.openPopup();
    });
}

// Inicializa listeners do campo de busca rápida da tabela com debounce
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('bottom-table-search-input');
    const btnClear = document.getElementById('btn-clear-table-search');

    if (searchInput) {
        searchInput.addEventListener('input', function () {
            const val = this.value.trim();
            if (btnClear) btnClear.style.display = (val.length > 0) ? 'block' : 'none';

            if (lotesTableSearchDebounce) clearTimeout(lotesTableSearchDebounce);
            lotesTableSearchDebounce = setTimeout(() => {
                currentLotesTableSearch = val;
                fetchLotesTableData(1);
            }, 300);
        });

        searchInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (lotesTableSearchDebounce) clearTimeout(lotesTableSearchDebounce);
                currentLotesTableSearch = this.value.trim();
                fetchLotesTableData(1);
            }
        });
    }
});

// Vincula funções ao window para acesso global e inline no HTML
window.switchTableDataType = switchTableDataType;
window.renderTableHead = renderTableHead;
window.toggleBottomPanel = toggleBottomPanel;
window.closeBottomPanel = closeBottomPanel;
window.syncTableWithFilters = syncTableWithFilters;
window.fetchLotesTableData = fetchLotesTableData;
window.renderLotesTable = renderLotesTable;
window.renderTablePagination = renderTablePagination;
window.goToLotesPage = goToLotesPage;
window.changeLotesTableLimit = changeLotesTableLimit;
window.onTableFilterClassChange = onTableFilterClassChange;
window.sortLotesTable = sortLotesTable;
window.clearLotesTableSearch = clearLotesTableSearch;
window.zoomToLote = zoomToLote;
