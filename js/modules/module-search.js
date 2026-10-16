/**
 * ==========================================================================
 * MÓDULO: BUSCA INTELIGENTE (LOGRADOUROS, BAIRROS, INSCRIÇÃO E CPF/CNPJ)
 * Arquivo: js/modules/module-search.js
 * ==========================================================================
 */

let searchHighlightLayer = null;
let autocompleteTimeout = null;

// Função para popular os dropdowns de Categoria, Material e Diâmetro (se existirem na página)
function populateSearchDropdowns() {
    const selectCat = document.getElementById('search-categoria');
    const selectMat = document.getElementById('search-material');
    const selectDia = document.getElementById('search-diametro');

    // Se nenhum dropdown de redes existir nesta tela (ex: BCI), não faz requisições desnecessárias
    if (!selectCat && !selectMat && !selectDia) {
        return;
    }

    // 1. Categoria (tipo da edificação)
    if (selectCat) {
        fetch('api/dominios.php?campo=categoria&tipo=edificacao')
            .then(res => res.json())
            .then(data => {
                if (!Array.isArray(data)) return;
                data.forEach(val => {
                    const opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = val;
                    selectCat.appendChild(opt);
                });
            }).catch(err => console.error('Erro ao buscar categorias:', err));
    }

    // 2. Material (redes e ramais)
    if (selectMat) {
        const tabelasMateriais = ['vw_rede_agua', 'vw_rede_esgoto', 'vw_edificacao_ramal_agua', 'vw_edificacao_ramal_esgoto'];
        Promise.all(tabelasMateriais.map(t => fetch(`api/dominios.php?campo=material&tipo=${t}`).then(res => res.json())))
            .then(results => {
                const materiais = new Set();
                results.forEach(arr => {
                    if (Array.isArray(arr)) arr.forEach(val => materiais.add(val));
                });
                Array.from(materiais).sort().forEach(val => {
                    const opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = val;
                    selectMat.appendChild(opt);
                });
            }).catch(err => console.error('Erro ao buscar materiais:', err));
    }

    // 3. Diâmetro (redes e ramais)
    if (selectDia) {
        const tabelasDiametros = ['vw_rede_agua', 'vw_rede_esgoto', 'vw_edificacao_ramal_agua', 'vw_edificacao_ramal_esgoto'];
        Promise.all(tabelasDiametros.map(t => fetch(`api/dominios.php?campo=diametro&tipo=${t}`).then(res => res.json())))
            .then(results => {
                const diametros = new Set();
                results.forEach(arr => {
                    if (Array.isArray(arr)) {
                        arr.forEach(val => {
                            if (val) diametros.add(parseFloat(val));
                        });
                    }
                });
                Array.from(diametros).sort((a, b) => a - b).forEach(val => {
                    const opt = document.createElement('option');
                    opt.value = val;
                    opt.textContent = val + ' mm';
                    selectDia.appendChild(opt);
                });
            }).catch(err => console.error('Erro ao buscar diâmetros:', err));
    }
}

// Inicializa listeners de busca e autocomplete
document.addEventListener('DOMContentLoaded', function () {
    const streetSearchInput = document.getElementById('street-search-input');
    if (streetSearchInput) {
        streetSearchInput.addEventListener('input', function () {
            clearTimeout(autocompleteTimeout);
            const val = this.value;
            if (val.length < 2) {
                const datalist = document.getElementById('ruas-list');
                if (datalist) datalist.innerHTML = '';
                return;
            }

            autocompleteTimeout = setTimeout(() => {
                fetch(`api/autocomplete.php?q=${encodeURIComponent(val)}`)
                    .then(res => res.json())
                    .then(data => {
                        const datalist = document.getElementById('ruas-list');
                        if (!datalist) return;
                        datalist.innerHTML = '';
                        if (Array.isArray(data)) {
                            data.forEach(rua => {
                                const opt = document.createElement('option');
                                opt.value = rua;
                                datalist.appendChild(opt);
                            });
                        }
                    });
            }, 300);
        });
    }

    populateSearchDropdowns();
});

// Executa a busca segmentada de logradouros
function executeStreetSearch() {
    const input = document.getElementById('street-search-input');
    const val = input ? input.value : '';
    if (!val || val.trim().length === 0) return;

    if (typeof showLoading === 'function') showLoading();

    fetch(`api/busca_ruas.php?q=${encodeURIComponent(val)}`)
        .then(res => res.json())
        .then(data => {
            if (typeof hideLoading === 'function') hideLoading();
            renderStreetResults(data);
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro na busca de ruas:', err);
            alert('Falha ao processar busca de ruas.');
        });
}

// Renderiza os resultados em formato de tabela
function renderStreetResults(data) {
    const container = document.getElementById('street-results-container');
    const countEl = document.getElementById('street-results-count');
    const tbody = document.getElementById('street-results-tbody');

    if (!container || !countEl || !tbody) return;

    tbody.innerHTML = '';
    countEl.textContent = data.length;
    container.style.display = 'block';

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="no-features-msg">Nenhum trecho encontrado.</td></tr>';
        return;
    }

    data.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>#${item.id}</strong></td>
            <td>${item.surface || '-'}</td>
            <td>${item.highway || '-'}</td>
            <td>${item.extensao || '-'} m</td>
        `;

        tr.addEventListener('click', function () {
            document.querySelectorAll('.street-results-table tbody tr').forEach(r => r.classList.remove('active-row'));
            tr.classList.add('active-row');
            highlightAndZoomToStreetSegment(item);
        });

        tbody.appendChild(tr);
    });
}

// Destaca e move o mapa para o segmento selecionado
function highlightAndZoomToStreetSegment(item) {
    if (searchHighlightLayer && typeof map !== 'undefined') {
        map.removeLayer(searchHighlightLayer);
    }

    // Garante que a camada de logradouros esteja visível no mapa
    const chk = document.getElementById('chk-geo_logradouros_dm') || document.getElementById('chk-logradouro_osm');
    if (chk && !chk.checked) {
        chk.checked = true;
        chk.dispatchEvent(new Event('change'));
    }

    if (typeof map === 'undefined') return;

    // Cria o destaque da geometria
    searchHighlightLayer = L.geoJSON(item.geojson, {
        style: function () {
            return {
                color: '#facc15', // Amarelo brilhante
                weight: 6,
                opacity: 0.95
            };
        }
    }).addTo(map);

    let popupContent = '<div style="font-size:12px; min-width: 180px; max-height: 250px; overflow-y: auto;">';
    popupContent += `<strong>Logradouro (Busca)</strong><br><hr style="border:0;border-top:1px solid #3c4d57;margin:5px 0;">`;
    popupContent += `<strong>Nome:</strong> ${escapeHtml(item.name || '-')}<br>`;
    popupContent += `<strong>Pavimento:</strong> ${escapeHtml(item.surface || '-')}<br>`;
    popupContent += `<strong>Tipo:</strong> ${escapeHtml(item.highway || '-')}<br>`;
    popupContent += `<strong>Extensão:</strong> ${item.extensao || '-'} m<br>`;
    popupContent += '</div>';

    searchHighlightLayer.bindPopup(popupContent);

    map.flyTo([item.lat, item.lng], 17, {
        animate: true,
        duration: 1.2
    });

    map.once('moveend', () => {
        if (searchHighlightLayer) searchHighlightLayer.openPopup();
    });
}

// Limpa busca de logradouros
function clearStreetSearch() {
    const form = document.getElementById('street-search-form');
    if (form) form.reset();

    const datalist = document.getElementById('ruas-list');
    if (datalist) datalist.innerHTML = '';

    const container = document.getElementById('street-results-container');
    if (container) container.style.display = 'none';

    const tbody = document.getElementById('street-results-tbody');
    if (tbody) tbody.innerHTML = '';

    if (searchHighlightLayer && typeof map !== 'undefined') {
        map.removeLayer(searchHighlightLayer);
        searchHighlightLayer = null;
    }
}

// Alterna entre os segmentos de busca (Logradouros vs Bairros vs Inscrição vs CPF/CNPJ)
function toggleSearchSegment(type) {
    clearStreetSearch();
    clearNeighborhoodSearch();
    clearInscricaoSearch();
    clearCpfCnpjSearch();

    const segStreets = document.getElementById('search-segment-streets');
    const segNeighborhoods = document.getElementById('search-segment-neighborhoods');
    const segInscricao = document.getElementById('search-segment-inscricao');
    const segCpfCnpj = document.getElementById('search-segment-cpf-cnpj');

    if (segStreets) segStreets.style.display = 'none';
    if (segNeighborhoods) segNeighborhoods.style.display = 'none';
    if (segInscricao) segInscricao.style.display = 'none';
    if (segCpfCnpj) segCpfCnpj.style.display = 'none';

    if (type === 'streets' && segStreets) {
        segStreets.style.display = 'block';
    } else if (type === 'neighborhoods' && segNeighborhoods) {
        segNeighborhoods.style.display = 'block';
    } else if (type === 'inscricao' && segInscricao) {
        segInscricao.style.display = 'block';
    } else if (type === 'cpf_cnpj' && segCpfCnpj) {
        segCpfCnpj.style.display = 'block';
    }
}

// Executa a busca segmentada por Inscrição Imobiliária
function executeInscricaoSearch() {
    const input = document.getElementById('inscricao-search-input');
    const val = input ? input.value : '';
    if (!val || val.trim().length === 0) return;

    if (typeof showLoading === 'function') showLoading();

    fetch(`api/busca_inscricao.php?q=${encodeURIComponent(val.trim())}`)
        .then(res => res.json())
        .then(data => {
            if (typeof hideLoading === 'function') hideLoading();
            if (data.error) {
                alert(data.message || 'Erro ao buscar imóveis por inscrição.');
                return;
            }
            renderInscricaoResults(data);
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro na busca por inscrição:', err);
            alert('Falha ao processar busca por inscrição.');
        });
}

// Renderiza os resultados da busca por Inscrição Imobiliária
function renderInscricaoResults(data) {
    const container = document.getElementById('inscricao-results-container');
    const countEl = document.getElementById('inscricao-results-count');
    const tbody = document.getElementById('inscricao-results-tbody');

    if (!container || !tbody || !countEl) return;

    tbody.innerHTML = '';
    countEl.textContent = data.length;
    container.style.display = 'block';

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="no-features-msg">Nenhum imóvel encontrado com esta inscrição.</td></tr>';
        return;
    }

    data.forEach(item => {
        const tr = document.createElement('tr');
        const endParts = [item.logradouro, item.numero ? `Nº ${item.numero}` : '', item.bairro ? `(${item.bairro})` : ''].filter(Boolean);
        const end = endParts.join(' ') || '<span style="color:var(--text-muted); font-style:italic;">Não informado</span>';

        tr.innerHTML = `
            <td><strong>${escapeHtml(item.insc_munic)}</strong></td>
            <td>${escapeHtml(item.contribuinte)}</td>
            <td>${end}</td>
        `;

        tr.addEventListener('click', function () {
            document.querySelectorAll('#search-segment-inscricao .street-results-table tbody tr').forEach(r => r.classList.remove('active-row'));
            tr.classList.add('active-row');
            highlightAndZoomToImovel(item);
        });

        tbody.appendChild(tr);
    });
}

// Limpa busca por Inscrição Imobiliária
function clearInscricaoSearch() {
    const form = document.getElementById('inscricao-search-form');
    if (form) form.reset();

    const container = document.getElementById('inscricao-results-container');
    if (container) container.style.display = 'none';

    const tbody = document.getElementById('inscricao-results-tbody');
    if (tbody) tbody.innerHTML = '';

    if (searchHighlightLayer && typeof map !== 'undefined') {
        map.removeLayer(searchHighlightLayer);
        searchHighlightLayer = null;
    }
}

// Executa a busca segmentada por CPF / CNPJ
function executeCpfCnpjSearch() {
    const input = document.getElementById('cpf-cnpj-search-input');
    const val = input ? input.value : '';
    if (!val || val.trim().length === 0) return;

    if (typeof showLoading === 'function') showLoading();

    fetch(`api/busca_cpf.php?q=${encodeURIComponent(val.trim())}`)
        .then(res => res.json())
        .then(data => {
            if (typeof hideLoading === 'function') hideLoading();
            if (data.error) {
                alert(data.message || 'Erro ao buscar imóveis por CPF/CNPJ.');
                return;
            }
            renderCpfCnpjResults(data);
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro na busca por CPF/CNPJ:', err);
            alert('Falha ao processar busca por CPF/CNPJ.');
        });
}

// Renderiza os resultados da busca por CPF / CNPJ
function renderCpfCnpjResults(data) {
    const container = document.getElementById('cpf-cnpj-results-container');
    const countEl = document.getElementById('cpf-cnpj-results-count');
    const tbody = document.getElementById('cpf-cnpj-results-tbody');

    if (!container || !tbody || !countEl) return;

    tbody.innerHTML = '';
    countEl.textContent = data.length;
    container.style.display = 'block';

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="no-features-msg">Nenhum imóvel encontrado para este CPF/CNPJ.</td></tr>';
        return;
    }

    data.forEach(item => {
        const tr = document.createElement('tr');
        const endParts = [item.logradouro, item.numero ? `Nº ${item.numero}` : '', item.bairro ? `(${item.bairro})` : ''].filter(Boolean);
        const end = endParts.join(' ') || '<span style="color:var(--text-muted); font-style:italic;">Não informado</span>';

        tr.innerHTML = `
            <td><strong>${escapeHtml(item.cpf_cnpj || 'Não informado')}</strong></td>
            <td>${escapeHtml(item.contribuinte)}</td>
            <td>${escapeHtml(item.insc_munic)}</td>
            <td>${end}</td>
        `;

        tr.addEventListener('click', function () {
            document.querySelectorAll('#search-segment-cpf-cnpj .street-results-table tbody tr').forEach(r => r.classList.remove('active-row'));
            tr.classList.add('active-row');
            highlightAndZoomToImovel(item);
        });

        tbody.appendChild(tr);
    });
}

// Limpa busca por CPF / CNPJ
function clearCpfCnpjSearch() {
    const form = document.getElementById('cpf-cnpj-search-form');
    if (form) form.reset();

    const container = document.getElementById('cpf-cnpj-results-container');
    if (container) container.style.display = 'none';

    const tbody = document.getElementById('cpf-cnpj-results-tbody');
    if (tbody) tbody.innerHTML = '';

    if (searchHighlightLayer && typeof map !== 'undefined') {
        map.removeLayer(searchHighlightLayer);
        searchHighlightLayer = null;
    }
}

// Destaca e aproxima o mapa para o imóvel selecionado na busca (Inscrição ou CPF/CNPJ)
function highlightAndZoomToImovel(item) {
    if (searchHighlightLayer && typeof map !== 'undefined') {
        map.removeLayer(searchHighlightLayer);
        searchHighlightLayer = null;
    }

    if (!item) return;

    if (item.geojson && item.lat && item.lng && typeof map !== 'undefined') {
        // Garante que a camada de lotes esteja ativa para contextualizar no mapa
        const chkLotes = document.getElementById('chk-geo_lotes_dm');
        if (chkLotes && !chkLotes.checked) {
            chkLotes.checked = true;
            chkLotes.dispatchEvent(new Event('change'));
        }

        searchHighlightLayer = L.geoJSON(item.geojson, {
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

        const endParts = [item.logradouro, item.numero ? `Nº ${item.numero}` : '', item.bairro ? `- ${item.bairro}` : ''].filter(Boolean);
        const endCompleto = endParts.join(' ');

        let popupContent = '<div style="font-size:12px; min-width: 200px; max-width: 270px; line-height: 1.4;">';
        popupContent += `<strong style="color: #0284c7; font-size: 13px;">Imóvel Localizado</strong><br><hr style="border:0;border-top:1px solid #3c4d57;margin:5px 0;">`;
        popupContent += `<strong>Inscrição:</strong> ${escapeHtml(item.insc_munic)}<br>`;
        popupContent += `<strong>Contribuinte:</strong> ${escapeHtml(item.contribuinte)}<br>`;
        if (item.cpf_cnpj && item.cpf_cnpj !== 'Não informado') {
            popupContent += `<strong>CPF/CNPJ:</strong> ${escapeHtml(item.cpf_cnpj)}<br>`;
        }
        if (endCompleto) {
            popupContent += `<strong>Endereço:</strong> ${escapeHtml(endCompleto)}<br>`;
        }
        popupContent += `
            <div style="margin-top: 10px; display: flex; gap: 6px;">
                <button type="button" class="btn-primary" style="flex: 1; padding: 6px 8px; font-size: 11px; cursor: pointer; border-radius: 4px;" onclick="fetchAndShowUnitDetails('${escapeHtml(item.insc_munic)}')">
                    Ver Detalhes (BCI)
                </button>
            </div>
        `;
        popupContent += '</div>';

        searchHighlightLayer.bindPopup(popupContent);

        const bounds = searchHighlightLayer.getBounds();
        if (bounds && bounds.isValid()) {
            map.flyToBounds(bounds, {
                maxZoom: 19,
                padding: [50, 50],
                animate: true,
                duration: 1.2
            });

            map.once('moveend', () => {
                if (searchHighlightLayer) searchHighlightLayer.openPopup();
            });
        }
    } else {
        if (typeof fetchAndShowUnitDetails === 'function') {
            fetchAndShowUnitDetails(item.insc_munic);
        }
    }
}

// Executa a busca segmentada de bairros
function executeNeighborhoodSearch() {
    const input = document.getElementById('neighborhood-search-input');
    const val = input ? input.value : '';
    if (!val || val.trim().length === 0) return;

    if (typeof showLoading === 'function') showLoading();

    fetch(`api/busca_bairros.php?q=${encodeURIComponent(val)}`)
        .then(res => res.json())
        .then(data => {
            if (typeof hideLoading === 'function') hideLoading();
            renderNeighborhoodResults(data);
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro na busca de bairros:', err);
            alert('Falha ao processar busca de bairros.');
        });
}

// Renderiza os bairros na tabela de resultados
function renderNeighborhoodResults(data) {
    const container = document.getElementById('neighborhood-results-container');
    const countEl = document.getElementById('neighborhood-results-count');
    const tbody = document.getElementById('neighborhood-results-tbody');

    if (!container || !countEl || !tbody) return;

    tbody.innerHTML = '';
    countEl.textContent = data.length;
    container.style.display = 'block';

    if (data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" class="no-features-msg">Nenhum bairro encontrado.</td></tr>';
        return;
    }

    data.forEach(item => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><strong>#${item.id}</strong></td>
            <td>${escapeHtml(item.bairro || item.comunidade || '')}</td>
        `;

        tr.addEventListener('click', function () {
            document.querySelectorAll('#search-segment-neighborhoods .street-results-table tbody tr').forEach(r => r.classList.remove('active-row'));
            tr.classList.add('active-row');
            highlightAndZoomToNeighborhood(item);
        });

        tbody.appendChild(tr);
    });
}

// Destaca e move o mapa para o bairro selecionado
function highlightAndZoomToNeighborhood(item) {
    if (searchHighlightLayer && typeof map !== 'undefined') {
        map.removeLayer(searchHighlightLayer);
    }

    // Garante que a camada de comunidades/bairros esteja ativa no mapa
    const chk = document.getElementById('chk-geo_comunidades_dm') || document.getElementById('chk-bairros');
    if (chk && !chk.checked) {
        chk.checked = true;
        chk.dispatchEvent(new Event('change'));
    }

    if (typeof map === 'undefined') return;

    searchHighlightLayer = L.geoJSON(item.geojson, {
        style: function () {
            return {
                color: '#facc15', // Amarelo brilhante
                weight: 4,
                opacity: 0.9,
                fillColor: '#facc15',
                fillOpacity: 0.25
            };
        }
    }).addTo(map);

    let popupContent = '<div style="font-size:12px; min-width: 180px; max-height: 250px; overflow-y: auto;">';
    popupContent += `<strong>Bairro (Busca)</strong><br><hr style="border:0;border-top:1px solid #3c4d57;margin:5px 0;">`;
    popupContent += `<strong>Comunidade:</strong> ${escapeHtml(item.bairro || item.comunidade || '-')}<br>`;
    popupContent += '</div>';

    searchHighlightLayer.bindPopup(popupContent);

    const bounds = searchHighlightLayer.getBounds();
    if (bounds && bounds.isValid()) {
        map.flyToBounds(bounds, {
            padding: [30, 30],
            animate: true,
            duration: 1.2
        });

        map.once('moveend', () => {
            if (searchHighlightLayer) searchHighlightLayer.openPopup();
        });
    }
}

// Limpa busca de bairros
function clearNeighborhoodSearch() {
    const form = document.getElementById('neighborhood-search-form');
    if (form) form.reset();

    const container = document.getElementById('neighborhood-results-container');
    if (container) container.style.display = 'none';

    const tbody = document.getElementById('neighborhood-results-tbody');
    if (tbody) tbody.innerHTML = '';

    if (searchHighlightLayer && typeof map !== 'undefined') {
        map.removeLayer(searchHighlightLayer);
        searchHighlightLayer = null;
    }
}

// Vincula funções ao window para acesso global e inline no HTML
window.populateSearchDropdowns = populateSearchDropdowns;
window.executeStreetSearch = executeStreetSearch;
window.renderStreetResults = renderStreetResults;
window.highlightAndZoomToStreetSegment = highlightAndZoomToStreetSegment;
window.clearStreetSearch = clearStreetSearch;
window.toggleSearchSegment = toggleSearchSegment;
window.executeInscricaoSearch = executeInscricaoSearch;
window.renderInscricaoResults = renderInscricaoResults;
window.clearInscricaoSearch = clearInscricaoSearch;
window.executeCpfCnpjSearch = executeCpfCnpjSearch;
window.renderCpfCnpjResults = renderCpfCnpjResults;
window.clearCpfCnpjSearch = clearCpfCnpjSearch;
window.highlightAndZoomToImovel = highlightAndZoomToImovel;
window.executeNeighborhoodSearch = executeNeighborhoodSearch;
window.renderNeighborhoodResults = renderNeighborhoodResults;
window.highlightAndZoomToNeighborhood = highlightAndZoomToNeighborhood;
window.clearNeighborhoodSearch = clearNeighborhoodSearch;
