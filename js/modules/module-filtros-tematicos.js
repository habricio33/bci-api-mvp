/**
 * ==========================================================================
 * MÓDULO: FILTROS TEMÁTICOS, ESTILIZAÇÃO DINÂMICA E LEGENDAS INTERATIVAS
 * Arquivo: js/modules/module-filtros-tematicos.js
 * ==========================================================================
 */

const lotesFilters = {
    "geo_lotes_dm": {
        applied: false,
        criterion: "none",
        activeClasses: new Set(),
        colorPalette: {},
        data: null
    },
    "geo_edificacoes_dm": {
        applied: false,
        criterion: "none",
        activeClasses: new Set(),
        colorPalette: {},
        data: null
    },
    "geo_zoneamento_dm": {
        applied: false,
        criterion: "none",
        activeClasses: new Set(),
        colorPalette: {},
        data: null
    }
};

// Aliases para compatibilidade
const networkFilters = lotesFilters;
const edificacoesFilters = lotesFilters;
const pdmFilters = lotesFilters;

// Paletas de cores temáticas para os lotes, edificações e zoneamento (PDM)
const filterColorPalettes = {
    "geo_lotes_dm": {
        "status": {
            "Com Cadastro": "#10b981",              // Verde Esmeralda
            "Não Cadastrado (Em branco)": "#64748b" // Cinza Neutro / Slate
        },
        "utilizacao": {
            "RESIDENCIAL": "#3b82f6",       // Azul Royal
            "TERRENO S/ USO": "#f59e0b",    // Âmbar / Laranja
            "COMERCIO/SERVICO": "#8b5cf6",  // Violeta
            "INDUSTRIAL": "#ef4444",        // Vermelho
            "AGRO-PECUARIO": "#10b981",     // Verde
            "Não Informado": "#64748b"      // Cinza Neutro
        },
        "ocupacao": {
            "CONSTRUIDO": "#06b6d4",               // Ciano
            "NAO CONSTRUIDO": "#eab308",           // Amarelo
            "CONSTRUCAO EM ANDAMENTO": "#f97316",  // Laranja
            "RUINAS": "#a855f7",                   // Roxo
            "CONSTRUCAO PARALIZADA": "#ec4899",    // Rosa
            "EM DEMOLICAO": "#f43f5e",             // Carmim
            "Não Informado": "#64748b"             // Cinza Neutro
        }
    },
    "geo_edificacoes_dm": {
        "status": {
            "Com Cadastro": "#10b981",
            "Não Cadastrado (Em branco)": "#64748b"
        },
        "tipo_construcao": {
            "ALVENARIA": "#3b82f6",       // Azul
            "MADEIRA": "#d97706",         // Âmbar
            "CHOCA/BARRACO": "#ef4444",   // Vermelho
            "TAIPA": "#854d0e",           // Marrom
            "Não Informado": "#64748b"    // Cinza
        },
        "estrutura": {
            "CONCRETO": "#6366f1",        // Índigo
            "ALVENARIA": "#3b82f6",       // Azul
            "MADEIRA": "#d97706",         // Âmbar
            "METALICA": "#06b6d4",        // Ciano
            "Não Informado": "#64748b"    // Cinza
        },
        "estado_conservacao": {
            "NOVA/OTIMO": "#10b981",      // Verde
            "BOM": "#06b6d4",             // Ciano
            "REGULAR": "#f59e0b",         // Amarelo
            "MAU": "#ef4444",             // Vermelho
            "Não Informado": "#64748b"    // Cinza
        },
        "situacao_imovel": {
            "OCUPADO": "#10b981",         // Verde
            "FECHADO/VAGO": "#f59e0b",    // Laranja
            "FECHADO/ABANDONADO": "#ef4444", // Vermelho
            "Não Informado": "#64748b"    // Cinza
        },
        "esgoto": {
            "SIM": "#10b981",             // Verde
            "NAO": "#ef4444",             // Vermelho
            "Não Informado": "#64748b"    // Cinza
        },
        "agua": {
            "SIM": "#06b6d4",             // Ciano
            "NAO": "#ef4444",             // Vermelho
            "Não Informado": "#64748b"    // Cinza
        },
        "lixo": {
            "SIM": "#8b5cf6",             // Violeta
            "NAO": "#ef4444",             // Vermelho
            "Não Informado": "#64748b"    // Cinza
        }
    },
    "geo_zoneamento_dm": {
        "sigla": {
            "ZOC": "#10b981",              // Verde Esmeralda (Zona de Ocupação Controlada)
            "ZOP": "#3b82f6",              // Azul Royal (Zona de Ocupação Preferencial)
            "ZEC": "#14b8a6",              // Verde Água / Teal (Zona de Expansão Controlada)
            "ZEIS": "#ec4899",             // Rosa / Pink (Zona Especial de Interesse Social)
            "ER": "#f97316",               // Laranja (Especial de Recuperação)
            "ZIT": "#8b5cf6",              // Violeta (Zona de Interesse Turístico)
            "ED": "#eab308",               // Amarelo Ouro (Eixo de Dinamização)
            "EP": "#ef4444",               // Vermelho (Eixo Principal)
            "ZE": "#6366f1",               // Índigo (Zona Especial)
            "ZEU": "#06b6d4",              // Ciano (Zona de Expansão Urbana)
            "ZRU": "#84cc16",              // Verde Lima (Zona Rurbana)
            "Não Informado": "#64748b"     // Cinza Neutro / Não Classificado
        }
    }
};

function getThematicColor(tipo, criterion, value, isBlank) {
    if (isBlank || String(value).includes('Não Informado') || String(value).includes('branco') || String(value).includes('Não Cadastrado') || String(value).includes('Não Classificado')) {
        return '#64748b';
    }
    const predefined = filterColorPalettes[tipo]?.[criterion]?.[value];
    if (predefined) return predefined;

    // Fallback gerador de cor determinística (HSL)
    let hash = 0;
    for (let i = 0; i < value.length; i++) {
        hash = value.charCodeAt(i) + ((hash << 5) - hash);
    }
    const hue = Math.abs(hash % 360);
    return `hsl(${hue}, 75%, 55%)`;
}

function getFeatureValueForCriterion(tipo, feature, criterion) {
    const props = feature.properties || {};
    if (tipo === 'geo_lotes_dm') {
        if (criterion === 'status') {
            if (props.status_cadastral) return props.status_cadastral;
            return props.has_cadastro ? 'Com Cadastro' : 'Não Cadastrado (Em branco)';
        }
        if (criterion === 'utilizacao') {
            const u = props.utilizacao || props.ULTILIZACAO_DO_IMOVEL9027 || '';
            return (u && String(u).trim() !== '') ? String(u).trim() : 'Não Informado';
        }
        if (criterion === 'ocupacao') {
            const o = props.ocupacao || props.OCUPACAO9025 || '';
            return (o && String(o).trim() !== '') ? String(o).trim() : 'Não Informado';
        }
    }
    if (tipo === 'geo_edificacoes_dm') {
        if (criterion === 'status') {
            if (props.status_cadastral) return props.status_cadastral;
            return props.has_cadastro ? 'Com Cadastro' : 'Não Cadastrado (Em branco)';
        }
        if (criterion === 'tipo_construcao' || criterion === 'TIPO_DE_CONSTRUCAO10042') {
            const v = props.tipo_construcao || props.TIPO_DE_CONSTRUCAO10042 || '';
            return (v && String(v).trim() !== '') ? String(v).trim() : 'Não Informado';
        }
        if (criterion === 'estrutura' || criterion === 'ESTRUTURA10043') {
            const v = props.estrutura || props.ESTRUTURA10043 || '';
            return (v && String(v).trim() !== '') ? String(v).trim() : 'Não Informado';
        }
        if (criterion === 'estado_conservacao' || criterion === 'ESTADO_DE_CONSERVACAO10044') {
            const v = props.estado_conservacao || props.ESTADO_DE_CONSERVACAO10044 || '';
            return (v && String(v).trim() !== '') ? String(v).trim() : 'Não Informado';
        }
        if (criterion === 'situacao_imovel' || criterion === 'SITUACAO_DO_IMOVEL10048') {
            const v = props.situacao_imovel || props.SITUACAO_DO_IMOVEL10048 || '';
            return (v && String(v).trim() !== '') ? String(v).trim() : 'Não Informado';
        }
        if (criterion === 'esgoto' || criterion === 'ESGOTO12001') {
            const v = props.esgoto || props.ESGOTO12001 || '';
            return (v && String(v).trim() !== '') ? String(v).trim() : 'Não Informado';
        }
        if (criterion === 'agua' || criterion === 'AGUA12002') {
            const v = props.agua || props.AGUA12002 || '';
            return (v && String(v).trim() !== '') ? String(v).trim() : 'Não Informado';
        }
        if (criterion === 'lixo' || criterion === 'LIXO12006') {
            const v = props.lixo || props.LIXO12006 || '';
            return (v && String(v).trim() !== '') ? String(v).trim() : 'Não Informado';
        }
    }
    if (tipo === 'geo_zoneamento_dm') {
        if (criterion === 'sigla') {
            const s = props.sigla || props.SIGLA || '';
            return (s && String(s).trim() !== '' && String(s).trim() !== 'Não Informado') ? String(s).trim() : 'Não Informado';
        }
    }
    return 'Padrão';
}

function initLotesFilters() {
    fetch('api/filtros_lotes.php')
        .then(res => {
            if (!res.ok) throw new Error('Erro ao carregar dados de filtros dos lotes');
            return res.json();
        })
        .then(data => {
            const tipo = 'geo_lotes_dm';
            if (!data[tipo]) return;
            lotesFilters[tipo].data = data[tipo];
            const stats = data[tipo].stats;

            const badgeEl = document.getElementById(`badge-count-${tipo}`);
            if (badgeEl) badgeEl.textContent = `${stats.total.toLocaleString('pt-BR')} lotes`;

            const pctLabel = document.getElementById(`pct-label-${tipo}`);
            if (pctLabel) pctLabel.textContent = `${stats.pct_com_informacao}% com dados`;

            const progressFill = document.getElementById(`progress-${tipo}`);
            if (progressFill) progressFill.style.width = `${stats.pct_com_informacao}%`;

            const statFilled = document.getElementById(`stat-filled-${tipo}`);
            if (statFilled) statFilled.textContent = stats.com_informacao.toLocaleString('pt-BR');

            const statBlank = document.getElementById(`stat-blank-${tipo}`);
            if (statBlank) statBlank.textContent = stats.sem_informacao.toLocaleString('pt-BR');

            lotesFilters[tipo].applied = false;
            lotesFilters[tipo].criterion = 'none';

            const actionsEl = document.getElementById(`actions-${tipo}`);
            if (actionsEl) actionsEl.style.display = 'none';

            const legendContainer = document.getElementById(`legend-container-${tipo}`);
            if (legendContainer) {
                legendContainer.innerHTML = '<p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar os lotes.</p>';
            }

            updateLotesFilterStatusIndicator();
        })
        .catch(err => console.error('Erro ao inicializar filtros de lotes:', err));
}

function initEdificacoesFilters() {
    fetch('api/filtros_edificacoes.php')
        .then(res => {
            if (!res.ok) throw new Error('Erro ao carregar dados de filtros de edificações');
            return res.json();
        })
        .then(data => {
            const tipo = 'geo_edificacoes_dm';
            if (!data[tipo]) return;
            lotesFilters[tipo].data = data[tipo];
            const stats = data[tipo].stats;

            const badgeEl = document.getElementById(`badge-count-${tipo}`);
            if (badgeEl) badgeEl.textContent = `${stats.total.toLocaleString('pt-BR')} edificações`;

            const pctLabel = document.getElementById(`pct-label-${tipo}`);
            if (pctLabel) pctLabel.textContent = `${stats.pct_com_informacao}% com dados`;

            const progressFill = document.getElementById(`progress-${tipo}`);
            if (progressFill) progressFill.style.width = `${stats.pct_com_informacao}%`;

            const statFilled = document.getElementById(`stat-filled-${tipo}`);
            if (statFilled) statFilled.textContent = stats.com_informacao.toLocaleString('pt-BR');

            const statBlank = document.getElementById(`stat-blank-${tipo}`);
            if (statBlank) statBlank.textContent = stats.sem_informacao.toLocaleString('pt-BR');

            lotesFilters[tipo].applied = false;
            lotesFilters[tipo].criterion = 'none';

            const actionsEl = document.getElementById(`actions-${tipo}`);
            if (actionsEl) actionsEl.style.display = 'none';

            const legendContainer = document.getElementById(`legend-container-${tipo}`);
            if (legendContainer) {
                legendContainer.innerHTML = '<p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar as edificações.</p>';
            }

            updateEdificacoesFilterStatusIndicator();
        })
        .catch(err => console.error('Erro ao inicializar filtros de edificações:', err));
}

function initPdmFilters() {
    fetch('api/filtros_pdm.php')
        .then(res => {
            if (!res.ok) throw new Error('Erro ao carregar dados de filtros do PDM');
            return res.json();
        })
        .then(data => {
            const tipo = 'geo_zoneamento_dm';
            if (!data[tipo]) return;
            lotesFilters[tipo].data = data[tipo];
            const stats = data[tipo].stats;

            const badgeEl = document.getElementById(`badge-count-${tipo}`);
            if (badgeEl) badgeEl.textContent = `${stats.total.toLocaleString('pt-BR')} zonas`;

            const pctLabel = document.getElementById(`pct-label-${tipo}`);
            if (pctLabel) pctLabel.textContent = `${stats.pct_com_informacao}% com dados`;

            const progressFill = document.getElementById(`progress-${tipo}`);
            if (progressFill) progressFill.style.width = `${stats.pct_com_informacao}%`;

            const statFilled = document.getElementById(`stat-filled-${tipo}`);
            if (statFilled) statFilled.textContent = stats.com_informacao.toLocaleString('pt-BR');

            const statBlank = document.getElementById(`stat-blank-${tipo}`);
            if (statBlank) statBlank.textContent = stats.sem_informacao.toLocaleString('pt-BR');

            lotesFilters[tipo].applied = false;
            lotesFilters[tipo].criterion = 'none';

            const actionsEl = document.getElementById(`actions-${tipo}`);
            if (actionsEl) actionsEl.style.display = 'none';

            const legendContainer = document.getElementById(`legend-container-${tipo}`);
            if (legendContainer) {
                legendContainer.innerHTML = '<p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar o zoneamento.</p>';
            }

            updatePdmFilterStatusIndicator();
        })
        .catch(err => console.error('Erro ao inicializar filtros do PDM:', err));
}

function onLotesFilterCriterionChange(tipo, criterion) {
    if (!lotesFilters[tipo]) return;

    const actionsEl = document.getElementById(`actions-${tipo}`);
    const legendContainer = document.getElementById(`legend-container-${tipo}`);

    if (criterion === 'none' || !criterion) {
        lotesFilters[tipo].criterion = 'none';
        lotesFilters[tipo].applied = false;
        lotesFilters[tipo].activeClasses.clear();
        lotesFilters[tipo].colorPalette = {};

        if (actionsEl) actionsEl.style.display = 'none';
        if (legendContainer) {
            legendContainer.innerHTML = '<p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar os lotes.</p>';
        }

        applyLotesStyle();
        if (typeof loadLayerData === 'function') loadLayerData(tipo);
        if (typeof syncTableWithFilters === 'function') syncTableWithFilters();
        updateLotesFilterStatusIndicator();
        return;
    }

    if (actionsEl) actionsEl.style.display = 'none';
    if (legendContainer) {
        legendContainer.innerHTML = '<p class="filter-placeholder">Critério selecionado. Clique no botão <strong>"Aplicar Filtro"</strong> abaixo para carregar a legenda e aplicar ao mapa.</p>';
    }
    updateLotesFilterStatusIndicator(true);
}

function onEdificacoesFilterCriterionChange(tipo, criterion) {
    if (!lotesFilters[tipo]) return;

    const actionsEl = document.getElementById(`actions-${tipo}`);
    const legendContainer = document.getElementById(`legend-container-${tipo}`);

    if (criterion === 'none' || !criterion) {
        lotesFilters[tipo].criterion = 'none';
        lotesFilters[tipo].applied = false;
        lotesFilters[tipo].activeClasses.clear();
        lotesFilters[tipo].colorPalette = {};

        if (actionsEl) actionsEl.style.display = 'none';
        if (legendContainer) {
            legendContainer.innerHTML = '<p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar as edificações.</p>';
        }

        applyEdificacoesStyle();
        if (typeof loadLayerData === 'function') loadLayerData(tipo);
        updateEdificacoesFilterStatusIndicator();
        return;
    }

    if (actionsEl) actionsEl.style.display = 'none';
    if (legendContainer) {
        legendContainer.innerHTML = '<p class="filter-placeholder">Critério selecionado. Clique no botão <strong>"Aplicar Filtro"</strong> abaixo para carregar a legenda e aplicar ao mapa.</p>';
    }
    updateEdificacoesFilterStatusIndicator(true);
}

function onPdmFilterCriterionChange(tipo, criterion) {
    if (!lotesFilters[tipo]) return;

    const actionsEl = document.getElementById(`actions-${tipo}`);
    const legendContainer = document.getElementById(`legend-container-${tipo}`);

    if (criterion === 'none' || !criterion) {
        lotesFilters[tipo].criterion = 'none';
        lotesFilters[tipo].applied = false;
        lotesFilters[tipo].activeClasses.clear();
        lotesFilters[tipo].colorPalette = {};

        if (actionsEl) actionsEl.style.display = 'none';
        if (legendContainer) {
            legendContainer.innerHTML = '<p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar o zoneamento.</p>';
        }

        applyPdmStyle();
        if (typeof loadLayerData === 'function') loadLayerData(tipo);
        updatePdmFilterStatusIndicator();
        return;
    }

    if (actionsEl) actionsEl.style.display = 'none';
    if (legendContainer) {
        legendContainer.innerHTML = '<p class="filter-placeholder">Critério selecionado. Clique no botão <strong>"Aplicar Filtro"</strong> abaixo para carregar a legenda e aplicar ao mapa.</p>';
    }
    updatePdmFilterStatusIndicator(true);
}

function renderFilterLegend(tipo, criterion) {
    const filterState = lotesFilters[tipo];
    if (!filterState || !filterState.data) return;
    const data = filterState.data;

    const items = data[criterion] || [];
    const legendContainer = document.getElementById(`legend-container-${tipo}`);
    if (!legendContainer) return;

    filterState.activeClasses = new Set(items.map(i => i.valor));
    filterState.colorPalette = {};

    let html = '<div class="filter-legend-list">';

    items.forEach(item => {
        const isBlank = item.is_blank || String(item.valor).includes('Não Informado') || String(item.valor).includes('branco') || String(item.valor).includes('Não Cadastrado') || String(item.valor).includes('Não Classificado');
        const color = getThematicColor(tipo, criterion, item.valor, isBlank);
        filterState.colorPalette[item.valor] = color;

        const isBlankClass = isBlank ? 'is-blank' : '';
        const swatchBlankClass = isBlank ? 'swatch-blank' : '';
        const cleanVal = cleanDomId(item.valor);
        const displayLabel = item.label || item.valor;

        html += `
            <div class="filter-legend-item ${isBlankClass}" id="legend-item-${tipo}-${cleanVal}" onclick="toggleFilterClass('${tipo}', '${escapeDomString(item.valor)}')">
                <div class="filter-legend-left">
                    <input type="checkbox" class="filter-legend-checkbox" id="chk-legend-${tipo}-${cleanVal}" checked onclick="event.stopPropagation(); toggleFilterClass('${tipo}', '${escapeDomString(item.valor)}')">
                    <span class="filter-swatch ${swatchBlankClass}" style="background-color: ${color};"></span>
                    <span class="filter-legend-text" title="${escapeDomString(displayLabel)}">${escapeDomString(displayLabel)}</span>
                </div>
                <span class="filter-count-badge">${item.total.toLocaleString('pt-BR')}</span>
            </div>
        `;
    });

    html += '</div>';
    legendContainer.innerHTML = html;
}

function renderLotesFilterLegend(tipo, criterion) {
    renderFilterLegend(tipo, criterion);
}
function renderEdificacoesFilterLegend(tipo, criterion) {
    renderFilterLegend(tipo, criterion);
}
function renderPdmFilterLegend(tipo, criterion) {
    renderFilterLegend(tipo, criterion);
}

function toggleFilterClass(tipo, value) {
    const filterState = lotesFilters[tipo];
    if (!filterState) return;
    const cleanVal = cleanDomId(value);

    if (filterState.activeClasses.has(value)) {
        filterState.activeClasses.delete(value);
    } else {
        filterState.activeClasses.add(value);
    }

    const chk = document.getElementById(`chk-legend-${tipo}-${cleanVal}`);
    const item = document.getElementById(`legend-item-${tipo}-${cleanVal}`);
    const isChecked = filterState.activeClasses.has(value);

    if (chk) chk.checked = isChecked;
    if (item) item.classList.toggle('disabled', !isChecked);

    if (filterState.applied) {
        if (tipo === 'geo_lotes_dm') {
            applyLotesStyle();
            if (typeof loadLayerData === 'function') loadLayerData(tipo);
            if (typeof syncTableWithFilters === 'function') syncTableWithFilters();
            updateLotesFilterStatusIndicator();
        } else if (tipo === 'geo_edificacoes_dm') {
            applyEdificacoesStyle();
            if (typeof loadLayerData === 'function') loadLayerData(tipo);
            updateEdificacoesFilterStatusIndicator();
        } else if (tipo === 'geo_zoneamento_dm') {
            applyPdmStyle();
            if (typeof loadLayerData === 'function') loadLayerData(tipo);
            updatePdmFilterStatusIndicator();
        }
    } else {
        if (tipo === 'geo_lotes_dm') {
            updateLotesFilterStatusIndicator(true);
        } else if (tipo === 'geo_edificacoes_dm') {
            updateEdificacoesFilterStatusIndicator(true);
        } else if (tipo === 'geo_zoneamento_dm') {
            updatePdmFilterStatusIndicator(true);
        }
    }
}

function toggleAllFilterClasses(tipo, enableAll) {
    const filterState = lotesFilters[tipo];
    if (!filterState || !filterState.data) return;
    const data = filterState.data;

    const items = data[filterState.criterion] || [];

    items.forEach(item => {
        const cleanVal = cleanDomId(item.valor);
        const chk = document.getElementById(`chk-legend-${tipo}-${cleanVal}`);
        const itemEl = document.getElementById(`legend-item-${tipo}-${cleanVal}`);
        if (enableAll) {
            filterState.activeClasses.add(item.valor);
            if (chk) chk.checked = true;
            if (itemEl) itemEl.classList.remove('disabled');
        } else {
            filterState.activeClasses.delete(item.valor);
            if (chk) chk.checked = false;
            if (itemEl) itemEl.classList.add('disabled');
        }
    });

    if (filterState.applied) {
        if (tipo === 'geo_lotes_dm') {
            applyLotesStyle();
            if (typeof loadLayerData === 'function') loadLayerData(tipo);
            if (typeof syncTableWithFilters === 'function') syncTableWithFilters();
            updateLotesFilterStatusIndicator();
        } else if (tipo === 'geo_edificacoes_dm') {
            applyEdificacoesStyle();
            if (typeof loadLayerData === 'function') loadLayerData(tipo);
            updateEdificacoesFilterStatusIndicator();
        } else if (tipo === 'geo_zoneamento_dm') {
            applyPdmStyle();
            if (typeof loadLayerData === 'function') loadLayerData(tipo);
            updatePdmFilterStatusIndicator();
        }
    } else {
        if (tipo === 'geo_lotes_dm') {
            updateLotesFilterStatusIndicator(true);
        } else if (tipo === 'geo_edificacoes_dm') {
            updateEdificacoesFilterStatusIndicator(true);
        } else if (tipo === 'geo_zoneamento_dm') {
            updatePdmFilterStatusIndicator(true);
        }
    }
}

function toggleOnlyFilledFilterClasses(tipo) {
    const filterState = lotesFilters[tipo];
    if (!filterState || !filterState.data) return;
    const data = filterState.data;

    const items = data[filterState.criterion] || [];

    items.forEach(item => {
        const isBlank = item.is_blank || String(item.valor).includes('Não Informado') || String(item.valor).includes('branco') || String(item.valor).includes('Não Cadastrado') || String(item.valor).includes('Não Classificado');
        const cleanVal = cleanDomId(item.valor);
        const chk = document.getElementById(`chk-legend-${tipo}-${cleanVal}`);
        const itemEl = document.getElementById(`legend-item-${tipo}-${cleanVal}`);

        if (isBlank) {
            filterState.activeClasses.delete(item.valor);
            if (chk) chk.checked = false;
            if (itemEl) itemEl.classList.add('disabled');
        } else {
            filterState.activeClasses.add(item.valor);
            if (chk) chk.checked = true;
            if (itemEl) itemEl.classList.remove('disabled');
        }
    });

    if (filterState.applied) {
        if (tipo === 'geo_lotes_dm') {
            applyLotesStyle();
            if (typeof loadLayerData === 'function') loadLayerData(tipo);
            if (typeof syncTableWithFilters === 'function') syncTableWithFilters();
            updateLotesFilterStatusIndicator();
        } else if (tipo === 'geo_edificacoes_dm') {
            applyEdificacoesStyle();
            if (typeof loadLayerData === 'function') loadLayerData(tipo);
            updateEdificacoesFilterStatusIndicator();
        } else if (tipo === 'geo_zoneamento_dm') {
            applyPdmStyle();
            if (typeof loadLayerData === 'function') loadLayerData(tipo);
            updatePdmFilterStatusIndicator();
        }
    } else {
        if (tipo === 'geo_lotes_dm') {
            updateLotesFilterStatusIndicator(true);
        } else if (tipo === 'geo_edificacoes_dm') {
            updateEdificacoesFilterStatusIndicator(true);
        } else if (tipo === 'geo_zoneamento_dm') {
            updatePdmFilterStatusIndicator(true);
        }
    }
}

function applyLotesFilterExplicit() {
    const tipo = 'geo_lotes_dm';
    const filterState = lotesFilters[tipo];
    if (!filterState) return;

    const selectEl = document.getElementById(`filter-criterion-${tipo}`);
    const selectedCriterion = selectEl ? selectEl.value : filterState.criterion;

    if (!selectedCriterion || selectedCriterion === 'none') {
        alert('Por favor, selecione um critério de classificação para aplicar o filtro.');
        return;
    }

    const isDifferentCriterion = (filterState.criterion !== selectedCriterion) || !filterState.applied || !filterState.activeClasses || filterState.activeClasses.size === 0;

    filterState.criterion = selectedCriterion;
    filterState.applied = true;

    const actionsEl = document.getElementById(`actions-${tipo}`);
    if (actionsEl) actionsEl.style.display = 'flex';

    if (isDifferentCriterion) {
        renderFilterLegend(tipo, selectedCriterion);
    }

    const chk = document.getElementById(`chk-${tipo}`);
    if (chk && !chk.checked) chk.checked = true;
    if (typeof layers !== 'undefined' && layers[tipo] && typeof map !== 'undefined' && !map.hasLayer(layers[tipo])) {
        map.addLayer(layers[tipo]);
    }

    applyLotesStyle();
    if (typeof loadLayerData === 'function') loadLayerData(tipo);
    if (typeof syncTableWithFilters === 'function') syncTableWithFilters();
    updateLotesFilterStatusIndicator();
}

function clearLotesFilterExplicit() {
    const tipo = 'geo_lotes_dm';
    const filterState = lotesFilters[tipo];
    if (!filterState) return;

    filterState.applied = false;
    filterState.criterion = 'none';
    filterState.activeClasses.clear();
    filterState.colorPalette = {};

    const selectEl = document.getElementById(`filter-criterion-${tipo}`);
    if (selectEl) selectEl.value = 'none';

    const actionsEl = document.getElementById(`actions-${tipo}`);
    if (actionsEl) actionsEl.style.display = 'none';

    const legendContainer = document.getElementById(`legend-container-${tipo}`);
    if (legendContainer) {
        legendContainer.innerHTML = '<p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar os lotes.</p>';
    }

    applyLotesStyle();
    if (typeof loadLayerData === 'function') loadLayerData(tipo);
    if (typeof syncTableWithFilters === 'function') syncTableWithFilters();
    updateLotesFilterStatusIndicator();
}

function updateLotesFilterStatusIndicator(isPending = false) {
    const indicator = document.getElementById('lotes-filter-status-indicator');
    const textEl = document.getElementById('lotes-filter-status-text');
    if (!indicator || !textEl) return;

    const filterState = lotesFilters['geo_lotes_dm'];

    if (isPending) {
        indicator.className = 'filter-status-indicator pending';
        textEl.textContent = 'Critério selecionado (Clique em Aplicar)';
        return;
    }

    if (!filterState || !filterState.applied || filterState.criterion === 'none') {
        indicator.className = 'filter-status-indicator';
        textEl.textContent = 'Filtro Inativo (Padrão)';
        return;
    }

    let critLabel = 'Status Cadastral';
    if (filterState.criterion === 'utilizacao') critLabel = 'Utilização';
    else if (filterState.criterion === 'ocupacao') critLabel = 'Ocupação';

    indicator.className = 'filter-status-indicator active';
    textEl.textContent = `Filtro Ativo: ${critLabel}`;
}

function applyEdificacoesFilterExplicit() {
    const tipo = 'geo_edificacoes_dm';
    const filterState = lotesFilters[tipo];
    if (!filterState) return;

    const selectEl = document.getElementById(`filter-criterion-${tipo}`);
    const selectedCriterion = selectEl ? selectEl.value : filterState.criterion;

    if (!selectedCriterion || selectedCriterion === 'none') {
        alert('Por favor, selecione um critério de classificação para aplicar o filtro de edificações.');
        return;
    }

    const isDifferentCriterion = (filterState.criterion !== selectedCriterion) || !filterState.applied || !filterState.activeClasses || filterState.activeClasses.size === 0;

    filterState.criterion = selectedCriterion;
    filterState.applied = true;

    const actionsEl = document.getElementById(`actions-${tipo}`);
    if (actionsEl) actionsEl.style.display = 'flex';

    if (isDifferentCriterion) {
        renderFilterLegend(tipo, selectedCriterion);
    }

    const chk = document.getElementById(`chk-${tipo}`);
    if (chk && !chk.checked) chk.checked = true;
    if (typeof layers !== 'undefined' && layers[tipo] && typeof map !== 'undefined' && !map.hasLayer(layers[tipo])) {
        map.addLayer(layers[tipo]);
    }

    applyEdificacoesStyle();
    if (typeof loadLayerData === 'function') loadLayerData(tipo);
    if (typeof syncTableWithFilters === 'function') syncTableWithFilters();
    updateEdificacoesFilterStatusIndicator();
}

function clearEdificacoesFilterExplicit() {
    const tipo = 'geo_edificacoes_dm';
    const filterState = lotesFilters[tipo];
    if (!filterState) return;

    filterState.applied = false;
    filterState.criterion = 'none';
    filterState.activeClasses.clear();
    filterState.colorPalette = {};

    const selectEl = document.getElementById(`filter-criterion-${tipo}`);
    if (selectEl) selectEl.value = 'none';

    const actionsEl = document.getElementById(`actions-${tipo}`);
    if (actionsEl) actionsEl.style.display = 'none';

    const legendContainer = document.getElementById(`legend-container-${tipo}`);
    if (legendContainer) {
        legendContainer.innerHTML = '<p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar as edificações.</p>';
    }

    applyEdificacoesStyle();
    if (typeof loadLayerData === 'function') loadLayerData(tipo);
    if (typeof syncTableWithFilters === 'function') syncTableWithFilters();
    updateEdificacoesFilterStatusIndicator();
}

function updateEdificacoesFilterStatusIndicator(isPending = false) {
    const indicator = document.getElementById('edificacoes-filter-status-indicator');
    const textEl = document.getElementById('edificacoes-filter-status-text');
    if (!indicator || !textEl) return;

    const filterState = lotesFilters['geo_edificacoes_dm'];

    if (isPending) {
        indicator.className = 'filter-status-indicator pending';
        textEl.textContent = 'Critério selecionado (Clique em Aplicar)';
        return;
    }

    if (!filterState || !filterState.applied || filterState.criterion === 'none') {
        indicator.className = 'filter-status-indicator';
        textEl.textContent = 'Filtro Inativo (Padrão)';
        return;
    }

    const labels = {
        'tipo_construcao': 'Tipo de Construção',
        'estrutura': 'Estrutura',
        'estado_conservacao': 'Estado de Conservação',
        'situacao_imovel': 'Situação do Imóvel',
        'esgoto': 'Rede de Esgoto',
        'agua': 'Abastecimento de Água',
        'lixo': 'Coleta de Lixo',
        'status': 'Status Cadastral'
    };

    const critLabel = labels[filterState.criterion] || filterState.criterion;
    indicator.className = 'filter-status-indicator active';
    textEl.textContent = `Filtro Ativo: ${critLabel}`;
}

function applyPdmFilterExplicit() {
    const tipo = 'geo_zoneamento_dm';
    const filterState = lotesFilters[tipo];
    if (!filterState) return;

    const selectEl = document.getElementById(`filter-criterion-${tipo}`);
    const selectedCriterion = selectEl ? selectEl.value : filterState.criterion;

    if (!selectedCriterion || selectedCriterion === 'none') {
        alert('Por favor, selecione um critério de classificação para aplicar o filtro de zoneamento.');
        return;
    }

    const isDifferentCriterion = (filterState.criterion !== selectedCriterion) || !filterState.applied || !filterState.activeClasses || filterState.activeClasses.size === 0;

    filterState.criterion = selectedCriterion;
    filterState.applied = true;

    const actionsEl = document.getElementById(`actions-${tipo}`);
    if (actionsEl) actionsEl.style.display = 'flex';

    if (isDifferentCriterion) {
        renderFilterLegend(tipo, selectedCriterion);
    }

    const chk = document.getElementById(`chk-${tipo}`);
    if (chk && !chk.checked) chk.checked = true;
    if (typeof layers !== 'undefined' && layers[tipo] && typeof map !== 'undefined' && !map.hasLayer(layers[tipo])) {
        map.addLayer(layers[tipo]);
    }

    applyPdmStyle();
    if (typeof loadLayerData === 'function') loadLayerData(tipo);
    updatePdmFilterStatusIndicator();
}

function clearPdmFilterExplicit() {
    const tipo = 'geo_zoneamento_dm';
    const filterState = lotesFilters[tipo];
    if (!filterState) return;

    filterState.applied = false;
    filterState.criterion = 'none';
    filterState.activeClasses.clear();
    filterState.colorPalette = {};

    const selectEl = document.getElementById(`filter-criterion-${tipo}`);
    if (selectEl) selectEl.value = 'none';

    const actionsEl = document.getElementById(`actions-${tipo}`);
    if (actionsEl) actionsEl.style.display = 'none';

    const legendContainer = document.getElementById(`legend-container-${tipo}`);
    if (legendContainer) {
        legendContainer.innerHTML = '<p class="filter-placeholder">Selecione um critério de classificação acima e clique em "Aplicar Filtro" para visualizar a legenda e filtrar o zoneamento.</p>';
    }

    applyPdmStyle();
    if (typeof loadLayerData === 'function') loadLayerData(tipo);
    updatePdmFilterStatusIndicator();
}

function updatePdmFilterStatusIndicator(isPending = false) {
    const indicator = document.getElementById('pdm-filter-status-indicator');
    const textEl = document.getElementById('pdm-filter-status-text');
    if (!indicator || !textEl) return;

    const filterState = lotesFilters['geo_zoneamento_dm'];

    if (isPending) {
        indicator.className = 'filter-status-indicator pending';
        textEl.textContent = 'Critério selecionado (Clique em Aplicar)';
        return;
    }

    if (!filterState || !filterState.applied || filterState.criterion === 'none') {
        indicator.className = 'filter-status-indicator';
        textEl.textContent = 'Filtro Inativo (Padrão)';
        return;
    }

    const critLabel = filterState.criterion === 'sigla' ? 'Sigla da Zona' : filterState.criterion;
    indicator.className = 'filter-status-indicator active';
    textEl.textContent = `Filtro Ativo: ${critLabel}`;
}

function resetLotesFilter(tipo) {
    if (tipo === 'geo_edificacoes_dm') {
        clearEdificacoesFilterExplicit();
    } else if (tipo === 'geo_zoneamento_dm') {
        clearPdmFilterExplicit();
    } else {
        clearLotesFilterExplicit();
    }
}

function applyPdmStyle() {
    const key = 'geo_zoneamento_dm';
    if (typeof layers === 'undefined' || !layers[key] || typeof map === 'undefined' || !map.hasLayer(layers[key])) return;

    const filterState = lotesFilters[key];
    const slider = document.getElementById(`opacity-${key}`);
    const opacityVal = slider ? parseFloat(slider.value) : 0.5;
    const criterion = filterState ? filterState.criterion : 'none';

    layers[key].eachLayer(function (parentLayer) {
        if (typeof parentLayer.eachLayer === 'function') {
            parentLayer.eachLayer(function (layer) {
                styleIndividualPdmLayer(layer, key, filterState, criterion, opacityVal);
            });
        } else {
            styleIndividualPdmLayer(parentLayer, key, filterState, criterion, opacityVal);
        }
    });
}

function styleIndividualPdmLayer(layer, key, filterState, criterion, opacityVal) {
    if (!layer.feature || typeof layer.setStyle !== 'function' || typeof layersConfig === 'undefined') return;
    const feature = layer.feature;
    const config = layersConfig[key];

    if (!filterState || !filterState.applied || criterion === 'none') {
        layer.setStyle({
            color: config.color,
            weight: config.weight,
            opacity: opacityVal,
            fillColor: config.fillColor || config.color,
            fillOpacity: (config.fillOpacity || 0.15) * opacityVal,
            dashArray: config.dashArray || null
        });
        return;
    }

    const val = getFeatureValueForCriterion(key, feature, criterion);
    const isVisible = filterState.activeClasses ? filterState.activeClasses.has(val) : true;

    if (!isVisible) {
        layer.setStyle({ opacity: 0, fillOpacity: 0 });
        return;
    }

    const isBlank = val.includes('Não Informado') || val.includes('branco') || val.includes('Não Cadastrado') || val.includes('Não Classificado');
    const thematicColor = filterState.colorPalette[val] || getThematicColor(key, criterion, val, isBlank);

    if (isBlank) {
        layer.setStyle({
            color: thematicColor,
            weight: 1.5,
            opacity: opacityVal,
            fillColor: thematicColor,
            fillOpacity: Math.max(0.12, 0.20 * opacityVal),
            dashArray: "3, 4"
        });
    } else {
        layer.setStyle({
            color: thematicColor,
            weight: 2.5,
            opacity: opacityVal,
            fillColor: thematicColor,
            fillOpacity: Math.max(0.30, 0.55 * opacityVal),
            dashArray: null
        });
    }
}

function applyLotesStyle() {
    const key = 'geo_lotes_dm';
    if (typeof layers === 'undefined' || !layers[key] || typeof map === 'undefined' || !map.hasLayer(layers[key])) return;

    const filterState = lotesFilters[key];
    const slider = document.getElementById(`opacity-${key}`);
    const opacityVal = slider ? parseFloat(slider.value) : 0.6;
    const criterion = filterState ? filterState.criterion : 'none';

    layers[key].eachLayer(function (parentLayer) {
        if (typeof parentLayer.eachLayer === 'function') {
            parentLayer.eachLayer(function (layer) {
                styleIndividualLoteLayer(layer, key, filterState, criterion, opacityVal);
            });
        } else {
            styleIndividualLoteLayer(parentLayer, key, filterState, criterion, opacityVal);
        }
    });

    if (typeof layers[key].bringToFront === 'function') {
        layers[key].bringToFront();
    }
}

function styleIndividualLoteLayer(layer, key, filterState, criterion, opacityVal) {
    if (!layer.feature || typeof layer.setStyle !== 'function' || typeof layersConfig === 'undefined') return;
    const feature = layer.feature;
    const config = layersConfig[key];

    if (!filterState || !filterState.applied || criterion === 'none') {
        layer.setStyle({
            color: config.color,
            weight: config.weight,
            opacity: opacityVal,
            fillColor: config.fillColor || config.color,
            fillOpacity: (config.fillOpacity || 0.55) * opacityVal,
            dashArray: config.dashArray || null
        });
        return;
    }

    const val = getFeatureValueForCriterion(key, feature, criterion);
    const isVisible = filterState.activeClasses ? filterState.activeClasses.has(val) : true;

    if (!isVisible) {
        layer.setStyle({ opacity: 0, fillOpacity: 0 });
        return;
    }

    const isBlank = val.includes('Não Informado') || val.includes('branco') || val.includes('Não Cadastrado');
    const thematicColor = filterState.colorPalette[val] || getThematicColor(key, criterion, val, isBlank);

    if (isBlank) {
        layer.setStyle({
            color: thematicColor,
            weight: 1.5,
            opacity: opacityVal,
            fillColor: thematicColor,
            fillOpacity: Math.max(0.18, 0.25 * opacityVal),
            dashArray: "3, 4"
        });
    } else {
        layer.setStyle({
            color: thematicColor,
            weight: 2.2,
            opacity: opacityVal,
            fillColor: thematicColor,
            fillOpacity: Math.max(0.45, 0.70 * opacityVal),
            dashArray: null
        });
    }
}

function applyEdificacoesStyle() {
    const key = 'geo_edificacoes_dm';
    if (typeof layers === 'undefined' || !layers[key] || typeof map === 'undefined' || !map.hasLayer(layers[key])) return;

    const filterState = lotesFilters[key];
    const slider = document.getElementById(`opacity-${key}`);
    const opacityVal = slider ? parseFloat(slider.value) : 0.8;
    const criterion = filterState ? filterState.criterion : 'none';

    layers[key].eachLayer(function (parentLayer) {
        if (typeof parentLayer.eachLayer === 'function') {
            parentLayer.eachLayer(function (layer) {
                styleIndividualEdificacaoLayer(layer, key, filterState, criterion, opacityVal);
            });
        } else {
            styleIndividualEdificacaoLayer(parentLayer, key, filterState, criterion, opacityVal);
        }
    });

    if (typeof layers[key].bringToFront === 'function') {
        layers[key].bringToFront();
    }
}

function styleIndividualEdificacaoLayer(layer, key, filterState, criterion, opacityVal) {
    if (!layer.feature || typeof layer.setStyle !== 'function' || typeof layersConfig === 'undefined') return;
    const feature = layer.feature;
    const config = layersConfig[key];

    if (!filterState || !filterState.applied || criterion === 'none') {
        layer.setStyle({
            color: config.color,
            weight: config.weight,
            opacity: opacityVal,
            fillColor: config.fillColor || config.color,
            fillOpacity: (config.fillOpacity || 0.8) * opacityVal,
            dashArray: config.dashArray || null
        });
        return;
    }

    const val = getFeatureValueForCriterion(key, feature, criterion);
    const isVisible = filterState.activeClasses ? filterState.activeClasses.has(val) : true;

    if (!isVisible) {
        layer.setStyle({ opacity: 0, fillOpacity: 0 });
        return;
    }

    const isBlank = val.includes('Não Informado') || val.includes('branco') || val.includes('Não Cadastrado');
    const thematicColor = filterState.colorPalette[val] || getThematicColor(key, criterion, val, isBlank);

    if (isBlank) {
        layer.setStyle({
            color: thematicColor,
            weight: 1.5,
            opacity: opacityVal,
            fillColor: thematicColor,
            fillOpacity: Math.max(0.18, 0.25 * opacityVal),
            dashArray: "3, 4"
        });
    } else {
        layer.setStyle({
            color: thematicColor,
            weight: 2,
            opacity: opacityVal,
            fillColor: thematicColor,
            fillOpacity: Math.max(0.45, 0.75 * opacityVal),
            dashArray: null
        });
    }
}

function onNetworkFilterCriterionChange(tipo, criterion) {
    onLotesFilterCriterionChange(tipo, criterion);
}
function resetNetworkFilter(tipo) {
    resetLotesFilter(tipo);
}
function initNetworkFilters() {
    initLotesFilters();
}

function cleanDomId(str) {
    return String(str).replace(/[^a-zA-Z0-9_-]/g, '_');
}

// Inicializa os filtros temáticos quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', function () {
    initLotesFilters();
    initEdificacoesFilters();
    initPdmFilters();
});

// Vincula funções ao window para acesso global e inline no HTML
window.lotesFilters = lotesFilters;
window.filterColorPalettes = filterColorPalettes;
window.getThematicColor = getThematicColor;
window.getFeatureValueForCriterion = getFeatureValueForCriterion;
window.initLotesFilters = initLotesFilters;
window.initEdificacoesFilters = initEdificacoesFilters;
window.initPdmFilters = initPdmFilters;
window.initNetworkFilters = initNetworkFilters;
window.onLotesFilterCriterionChange = onLotesFilterCriterionChange;
window.onEdificacoesFilterCriterionChange = onEdificacoesFilterCriterionChange;
window.onPdmFilterCriterionChange = onPdmFilterCriterionChange;
window.onNetworkFilterCriterionChange = onNetworkFilterCriterionChange;
window.renderFilterLegend = renderFilterLegend;
window.renderLotesFilterLegend = renderLotesFilterLegend;
window.renderEdificacoesFilterLegend = renderEdificacoesFilterLegend;
window.renderPdmFilterLegend = renderPdmFilterLegend;
window.toggleFilterClass = toggleFilterClass;
window.toggleAllFilterClasses = toggleAllFilterClasses;
window.toggleOnlyFilledFilterClasses = toggleOnlyFilledFilterClasses;
window.applyLotesFilterExplicit = applyLotesFilterExplicit;
window.clearLotesFilterExplicit = clearLotesFilterExplicit;
window.updateLotesFilterStatusIndicator = updateLotesFilterStatusIndicator;
window.applyEdificacoesFilterExplicit = applyEdificacoesFilterExplicit;
window.clearEdificacoesFilterExplicit = clearEdificacoesFilterExplicit;
window.updateEdificacoesFilterStatusIndicator = updateEdificacoesFilterStatusIndicator;
window.applyPdmFilterExplicit = applyPdmFilterExplicit;
window.clearPdmFilterExplicit = clearPdmFilterExplicit;
window.updatePdmFilterStatusIndicator = updatePdmFilterStatusIndicator;
window.resetLotesFilter = resetLotesFilter;
window.resetNetworkFilter = resetNetworkFilter;
window.applyPdmStyle = applyPdmStyle;
window.styleIndividualPdmLayer = styleIndividualPdmLayer;
window.applyLotesStyle = applyLotesStyle;
window.styleIndividualLoteLayer = styleIndividualLoteLayer;
window.applyEdificacoesStyle = applyEdificacoesStyle;
window.styleIndividualEdificacaoLayer = styleIndividualEdificacaoLayer;
window.cleanDomId = cleanDomId;
