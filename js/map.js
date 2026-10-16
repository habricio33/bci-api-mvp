/**
 * ==========================================================================
 * NÚCLEO CARTOGRÁFICO PRINCIPAL (GEOPORTAL BCI)
 * Arquivo: js/map.js
 * 
 * Responsabilidades:
 * - Inicialização do Mapa Leaflet, Basemaps e Projeções SIRGAS 2000 / WGS 84
 * - Definição do Catálogo de Camadas Geográficas e Carregamento Dinâmico por BBox
 * - Controle de Coordenadas, Escala, Zoom e Barra Lateral
 * - Ferramentas de Medição (Distância e Área)
 * - Delegação Central de Eventos de Clique no Mapa
 * ==========================================================================
 */

// Inicializa o mapa centralizado em Guanhães - MG com suporte a zoom ultra aproximado (até 24)
const map = L.map('map', {
    zoomControl: true,
    maxZoom: 24,
    minZoom: 6
}).setView([-20.360730, -40.660915], 15);

// Barra de Escala Métrica no canto inferior esquerdo
L.control.scale({ imperial: false, metric: true, position: 'bottomleft' }).addTo(map);

// Adiciona o basemap padrão inicial
basemaps["Open Street Map"].addTo(map);

// ==========================================================================
// SISTEMAS DE COORDENADAS E PROJEÇÕES CARTOGRÁFICAS
// ==========================================================================
if (typeof proj4 !== 'undefined') {
    proj4.defs("EPSG:4326", "+proj=longlat +datum=WGS84 +no_defs +type=crs");
    proj4.defs("EPSG:31984", "+proj=utm +zone=24 +south +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs +type=crs");
}

/**
 * Converte coordenadas geográficas (Lat/Lon em WGS84) para UTM SIRGAS 2000 Fuso 24S (EPSG:31984).
 */
function transformLatLonToUtm23S(lat, lon) {
    if (typeof proj4 !== 'undefined') {
        try {
            const p = proj4('EPSG:4326', 'EPSG:31984', [lon, lat]);
            return { easting: p[0], northing: p[1] };
        } catch (e) {
            console.warn('Erro na projeção Proj4js, usando fallback matemático:', e);
        }
    }

    // Fallback matemático padrão para UTM Fuso 23S (Elipsoide GRS80 / SIRGAS 2000)
    const a = 6378137.0;
    const f = 1 / 298.257222101;
    const k0 = 0.9996;
    const lon0 = -45.0 * (Math.PI / 180);
    const latRad = lat * (Math.PI / 180);
    const lonRad = lon * (Math.PI / 180);
    const e2 = 2 * f - f * f;
    const e_prime2 = e2 / (1 - e2);

    const N = a / Math.sqrt(1 - e2 * Math.sin(latRad) * Math.sin(latRad));
    const T = Math.tan(latRad) * Math.tan(latRad);
    const C = e_prime2 * Math.cos(latRad) * Math.cos(latRad);
    const A = Math.cos(latRad) * (lonRad - lon0);

    const M = a * (
        (1 - e2 / 4 - 3 * e2 * e2 / 64 - 5 * e2 * e2 * e2 / 256) * latRad
        - (3 * e2 / 8 + 3 * e2 * e2 / 32 + 45 * e2 * e2 * e2 / 1024) * Math.sin(2 * latRad)
        + (15 * e2 * e2 / 256 + 45 * e2 * e2 * e2 / 1024) * Math.sin(4 * latRad)
        - (35 * e2 * e2 * e2 / 3072) * Math.sin(6 * latRad)
    );

    const easting = 500000 + k0 * N * (
        A + (1 - T + C) * Math.pow(A, 3) / 6
        + (5 - 18 * T + T * T + 72 * C - 58 * e_prime2) * Math.pow(A, 5) / 120
    );

    let northing = k0 * (
        M + N * Math.tan(latRad) * (
            Math.pow(A, 2) / 2
            + (5 - T + 9 * C + 4 * C * C) * Math.pow(A, 4) / 24
            + (61 - 58 * T + T * T + 600 * C - 330 * e_prime2) * Math.pow(A, 6) / 720
        )
    );

    if (lat < 0) {
        northing += 10000000.0;
    }

    return { easting, northing };
}

function formatCoordNumber(val, decimals = 2) {
    return val.toLocaleString('pt-BR', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals
    });
}

// ==========================================================================
// CONTROLE DE EXIBIÇÃO DE COORDENADAS (MOUSE POSITION)
// ==========================================================================
let coordsModeActive = false;
const btnCoordsTool = document.getElementById('btn-coords-tool');

const MousePositionControl = L.Control.extend({
    options: {
        position: 'bottomright'
    },

    onAdd: function (mapInstance) {
        const container = L.DomUtil.create('div', 'leaflet-control-mouseposition');
        container.id = 'mouse-position-box';
        container.style.display = 'none';
        container.innerHTML = `
            <div class="mousepos-header">
                <span class="mousepos-title">Coordenadas do Cursor</span>
                <span class="mousepos-datum">SIRGAS 2000 / Fuso 24S</span>
            </div>
            <div class="mousepos-body">
                <div class="mousepos-row">
                    <span class="mousepos-label">UTM E:</span>
                    <span class="mousepos-val" id="mousepos-easting">---</span>
                </div>
                <div class="mousepos-row">
                    <span class="mousepos-label">UTM N:</span>
                    <span class="mousepos-val" id="mousepos-northing">---</span>
                </div>
                <div class="mousepos-row sub-row">
                    <span class="mousepos-label">Lat:</span>
                    <span class="mousepos-val" id="mousepos-lat">---</span>
                </div>
                <div class="mousepos-row sub-row">
                    <span class="mousepos-label">Lon:</span>
                    <span class="mousepos-val" id="mousepos-lng">---</span>
                </div>
            </div>
        `;

        L.DomEvent.disableClickPropagation(container);
        L.DomEvent.disableScrollPropagation(container);
        return container;
    },

    updateCoordinates: function (latlng) {
        if (!latlng) return;
        const eastingEl = document.getElementById('mousepos-easting');
        const northingEl = document.getElementById('mousepos-northing');
        const latEl = document.getElementById('mousepos-lat');
        const lngEl = document.getElementById('mousepos-lng');

        if (!eastingEl || !northingEl) return;

        const utm = transformLatLonToUtm23S(latlng.lat, latlng.lng);

        eastingEl.textContent = `${formatCoordNumber(utm.easting)} m`;
        northingEl.textContent = `${formatCoordNumber(utm.northing)} m`;
        if (latEl) latEl.textContent = latlng.lat.toFixed(6) + '°';
        if (lngEl) lngEl.textContent = latlng.lng.toFixed(6) + '°';
    }
});

const mousePositionCtrl = new MousePositionControl();
mousePositionCtrl.addTo(map);

function toggleCoordinatesTool() {
    coordsModeActive = !coordsModeActive;
    const box = document.getElementById('mouse-position-box');

    if (btnCoordsTool) {
        btnCoordsTool.classList.toggle('active', coordsModeActive);
    }

    if (coordsModeActive) {
        if (box) box.style.display = 'block';
        map.on('mousemove', onMapMouseMoveCoords);
        mousePositionCtrl.updateCoordinates(map.getCenter());
    } else {
        if (box) box.style.display = 'none';
        map.off('mousemove', onMapMouseMoveCoords);
    }
}

function onMapMouseMoveCoords(e) {
    if (coordsModeActive && mousePositionCtrl) {
        mousePositionCtrl.updateCoordinates(e.latlng);
    }
}

if (btnCoordsTool) {
    btnCoordsTool.addEventListener('click', function (e) {
        L.DomEvent.stopPropagation(e);
        toggleCoordinatesTool();
    });
    L.DomEvent.disableClickPropagation(btnCoordsTool);
}

// ==========================================================================
// CONFIGURAÇÃO DO CATÁLOGO DE CAMADAS VETORIAIS
// ==========================================================================
const layersConfig = {
    // 1. Logradouros
    "geo_logradouros_dm": {
        name: "Logradouros",
        color: "#f59e0b",
        weight: 3,
        type: "line",
        popupTitle: "Logradouro",
        fields: { "name": "Nome", "surface": "Pavimento", "highway": "Tipo" }
    },
    // 2. Zoneamento Urbano (PDM)
    "geo_zoneamento_dm": {
        name: "Zoneamento Urbano",
        color: "#6366f1",
        weight: 2,
        fillColor: "#6366f1",
        fillOpacity: 0.25,
        type: "polygon",
        popupTitle: "Zoneamento Urbano (PDM)",
        fields: { "sigla": "Sigla da Zona", "nome": "Denominação da Zona" }
    },
    // 3. Comunidades / Bairros
    "geo_comunidades_dm": {
        name: "Comunidades e Bairros",
        color: "#e11d48",
        weight: 3,
        fillColor: "#e11d48",
        fillOpacity: 0.15,
        type: "polygon",
        popupTitle: "Comunidade / Bairro",
        fields: { "comunidade": "Nome", "regiao": "Região Administrativa" }
    },
    // 4. Lotes
    "geo_lotes_dm": {
        name: "Lotes Cadastrais",
        color: "#0284c7",
        weight: 2.2,
        fillColor: "#0284c7",
        fillOpacity: 0.55,
        type: "polygon",
        popupTitle: "Lote Cadastral",
        fields: { "id": "ID", "inscimob": "Inscrição Imobiliária", "inscimob2": "Inscrição Reduzida" }
    },
    // 5. Edificações
    "geo_edificacoes_dm": {
        name: "Edificações",
        color: "#9333ea",
        weight: 1.5,
        fillColor: "#9333ea",
        fillOpacity: 0.75,
        type: "polygon",
        popupTitle: "Edificação",
        fields: { "id": "ID", "tipo": "Tipo", "area_total_edificacao": "Área Total" }
    },
    // 6. Rede de Água
    "vw_rede_agua": {
        name: "Rede de Água",
        color: "#2563eb",
        weight: 3.5,
        type: "line",
        popupTitle: "Rede de Água",
        fields: { "rede": "Rede", "tipo": "Tipo", "diametro": "Diâmetro", "material": "Material" }
    },
    // 7. Rede de Esgoto
    "vw_rede_esgoto": {
        name: "Rede de Esgoto",
        color: "#ea580c",
        weight: 3.5,
        type: "line",
        popupTitle: "Rede de Esgoto",
        fields: { "rede": "Rede", "tipo": "Tipo", "diametro": "Diâmetro", "material": "Material" }
    },
    // 8. Ramal Predial de Água
    "vw_edificacao_ramal_agua": {
        name: "Ramal Predial de Água",
        color: "#0284c7",
        weight: 2.5,
        type: "polygon",
        popupTitle: "Ramal Predial de Água",
        fields: { "numero_postal": "Nº Postal", "hidrometro": "Hidrômetro", "diametro": "Diâmetro", "material": "Material" }
    },
    // 9. Ramal Predial de Esgoto
    "vw_edificacao_ramal_esgoto": {
        name: "Ramal Predial de Esgoto",
        color: "#c2410c",
        weight: 2.5,
        type: "polygon",
        popupTitle: "Ramal Predial de Esgoto",
        fields: { "numero_postal": "Nº Postal", "tipo_ramal": "Tipo Ramal", "diametro": "Diâmetro", "material": "Material" }
    }
};

// Instancia os FeatureGroups do Leaflet para cada camada
const layers = {};
Object.keys(layersConfig).forEach(key => {
    layers[key] = L.featureGroup();
    // Ativa por padrão as camadas principais
    if (['geo_logradouros_dm', 'geo_comunidades_dm', 'geo_lotes_dm', 'geo_edificacoes_dm'].includes(key)) {
        layers[key].addTo(map);
    }
});

// ==========================================================================
// CONTROLE DE BASEMAPS E NÍVEL DE ZOOM
// ==========================================================================
const basemapSelect = document.getElementById('basemap-select');
if (basemapSelect) {
    basemapSelect.addEventListener('change', function () {
        const selected = this.value;
        Object.keys(basemaps).forEach(name => {
            if (map.hasLayer(basemaps[name])) {
                map.removeLayer(basemaps[name]);
            }
        });
        if (basemaps[selected]) {
            basemaps[selected].addTo(map);
        }
        applyBasemapBackground(selected);
    });
}

function applyBasemapBackground(name) {
    const mapEl = document.getElementById('map');
    if (!mapEl) return;
    if (name === 'Google Satélite' || name === 'ESRI Satélite' || name === 'CartoDB Dark') {
        mapEl.style.backgroundColor = '#0b0f19';
    } else {
        mapEl.style.backgroundColor = '#e5e7eb';
    }
}

map.on('baselayerchange', function (e) {
    if (basemapSelect && basemapSelect.value !== e.name) {
        basemapSelect.value = e.name;
    }
    applyBasemapBackground(e.name);
});

const zoomBadge = document.getElementById('zoom-level-badge');
function updateZoomBadge() {
    if (zoomBadge) {
        zoomBadge.textContent = `Zoom: ${map.getZoom()} / 24`;
    }
}
map.on('zoomend', updateZoomBadge);
updateZoomBadge();

// ==========================================================================
// CARREGAMENTO DINÂMICO VIA BBOX
// ==========================================================================
function showLoading() {
    const el = document.getElementById('loading');
    if (el) el.style.display = 'flex';
}

function hideLoading() {
    const el = document.getElementById('loading');
    if (el) el.style.display = 'none';
}

function loadLayerData(key) {
    if (!map.hasLayer(layers[key])) {
        return;
    }

    showLoading();

    const bbox = map.getBounds().toBBoxString();
    const url = `api/camada.php?tipo=${key}&bbox=${bbox}&_t=${Date.now()}`;

    fetch(url, { cache: 'no-store' })
        .then(response => {
            if (!response.ok) throw new Error(`Erro ao carregar ${key}`);
            return response.json();
        })
        .then(data => {
            layers[key].clearLayers();

            const config = layersConfig[key];
            const slider = document.getElementById(`opacity-${key}`);
            const opacityVal = slider ? parseFloat(slider.value) : (config.fillOpacity || 0.6);
            const isLotes = (key === 'geo_lotes_dm');
            const isEdificacoes = (key === 'geo_edificacoes_dm');
            const isZoneamento = (key === 'geo_zoneamento_dm');
            const filterState = (typeof lotesFilters !== 'undefined' && (isLotes || isEdificacoes || isZoneamento)) ? lotesFilters[key] : null;

            L.geoJSON(data, {
                filter: function (feature) {
                    if ((isLotes || isEdificacoes || isZoneamento) && filterState && filterState.applied && filterState.criterion !== 'none' && filterState.activeClasses && typeof getFeatureValueForCriterion === 'function') {
                        const val = getFeatureValueForCriterion(key, feature, filterState.criterion);
                        return filterState.activeClasses.has(val);
                    }
                    return true;
                },
                style: function (feature) {
                    let color = config.color;
                    let fillColor = config.fillColor || color;
                    let weight = config.weight;
                    let fillOpacity = config.fillOpacity !== undefined ? (config.fillOpacity * opacityVal) : 0.55 * opacityVal;
                    let dashArray = config.dashArray || null;

                    if ((isLotes || isEdificacoes || isZoneamento) && filterState && filterState.applied && filterState.criterion !== 'none' && typeof getFeatureValueForCriterion === 'function' && typeof getThematicColor === 'function') {
                        const val = getFeatureValueForCriterion(key, feature, filterState.criterion);
                        const isBlank = val.includes('Não Informado') || val.includes('branco') || val.includes('Não Cadastrado') || val.includes('Não Classificado');
                        const thematicColor = filterState.colorPalette[val] || getThematicColor(key, filterState.criterion, val, isBlank);

                        color = thematicColor;
                        fillColor = thematicColor;

                        if (isBlank) {
                            dashArray = "3, 4";
                            weight = 1.5;
                            fillOpacity = Math.max(0.12, 0.20 * opacityVal);
                        } else {
                            weight = isZoneamento ? 2.5 : 2;
                            fillOpacity = isZoneamento ? Math.max(0.30, 0.55 * opacityVal) : Math.max(0.45, 0.70 * opacityVal);
                            dashArray = null;
                        }
                    }

                    return {
                        color: color,
                        weight: weight,
                        opacity: opacityVal,
                        fillColor: fillColor,
                        fillOpacity: fillOpacity,
                        fill: config.type === "polygon",
                        dashArray: dashArray
                    };
                },
                pointToLayer: function (feature, latlng) {
                    return L.circleMarker(latlng, {
                        radius: 6,
                        fillColor: config.color,
                        color: "#fff",
                        weight: 1.5,
                        opacity: opacityVal,
                        fillOpacity: opacityVal
                    });
                },
                onEachFeature: function (feature, layer) {
                    layer.on('click', function (e) {
                        if (typeof selecaoToolActive !== 'undefined' && selecaoToolActive && typeof selecaoMapClickMode !== 'undefined' && selecaoMapClickMode) {
                            L.DomEvent.stopPropagation(e);
                            if (layer.closePopup) layer.closePopup();
                            if (map) map.closePopup();
                            if ((key === 'geo_comunidades_dm' || key === 'bairros') && feature.properties && (feature.properties.id2 || feature.properties.id)) {
                                selectCommunityById(feature.properties.id2 || feature.properties.id);
                            } else {
                                selectCommunityByLatLng(e.latlng.lat, e.latlng.lng);
                            }
                            return;
                        }
                        if (typeof bufferModeActive !== 'undefined' && bufferModeActive) {
                            L.DomEvent.stopPropagation(e);
                            setBufferCenter(e.latlng);
                            return;
                        }
                        if (typeof editModeActive !== 'undefined' && editModeActive) {
                            L.DomEvent.stopPropagation(e);
                            if (key === 'vw_edificacao_ramal_agua') {
                                openEditRamalAgua(e.latlng, feature);
                            } else if (key === 'vw_edificacao_ramal_esgoto') {
                                openEditRamalEsgoto(e.latlng, feature);
                            } else if (key === 'vw_rede_agua' || key === 'vw_rede_esgoto') {
                                openEditRede(e.latlng, feature, key);
                            } else {
                                openEditRede(e.latlng, null, 'vw_rede_agua');
                            }
                            return;
                        }
                        if (typeof identifyModeActive !== 'undefined' && identifyModeActive) {
                            L.DomEvent.stopPropagation(e);
                            executeIdentify(e.latlng);
                        }
                    });

                    let props = feature.properties || {};
                    let popupContent = '<div style="font-size:12px; min-width: 180px; max-height: 250px; overflow-y: auto;">';
                    popupContent += `<strong>${config.popupTitle}</strong><br><hr style="border:0;border-top:1px solid #3c4d57;margin:5px 0;">`;

                    Object.keys(config.fields).forEach(field => {
                        const label = config.fields[field];
                        let val = props[field];
                        if (val === null || val === undefined || String(val).trim() === '') {
                            val = '<span style="color: #94a3b8; font-style: italic;">Não informado (Em branco)</span>';
                        } else if (field === 'diametro') {
                            val = `${val} mm`;
                        }
                        popupContent += `<strong>${label}:</strong> ${val}<br>`;
                    });

                    popupContent += '</div>';
                    layer.bindPopup(popupContent);

                    if ((key === "bairros" || key === "geo_comunidades_dm") && (props.comunidade || props.bairro || props.nome)) {
                        layer.bindTooltip(props.comunidade || props.bairro || props.nome, {
                            permanent: true,
                            direction: 'center',
                            className: 'bairro-label'
                        });
                    }
                }
            }).addTo(layers[key]);

            if (isLotes && typeof layers[key].bringToFront === 'function') {
                layers[key].bringToFront();
            }
        })
        .catch(error => {
            console.error(`Erro ao carregar dados da camada ${key}:`, error);
        })
        .finally(() => {
            hideLoading();
        });
}

function loadAllActiveLayers() {
    Object.keys(layersConfig).forEach(key => {
        loadLayerData(key);
    });
}

map.on('moveend', loadAllActiveLayers);

// ==========================================================================
// SINCRONIZAÇÃO E EVENTOS DA SIDEBAR (CHECKBOXES E SLIDERS)
// ==========================================================================
Object.keys(layersConfig).forEach(key => {
    const config = layersConfig[key];

    const chk = document.getElementById(`chk-${key}`);
    if (chk) {
        chk.addEventListener('change', function () {
            if (this.checked) {
                if (!map.hasLayer(layers[key])) {
                    map.addLayer(layers[key]);
                }
            } else {
                if (map.hasLayer(layers[key])) {
                    map.removeLayer(layers[key]);
                }
            }
        });
    }

    const slider = document.getElementById(`opacity-${key}`);
    const valLabel = document.getElementById(`val-opacity-${key}`);

    if (slider && valLabel) {
        slider.addEventListener('input', function () {
            const val = parseFloat(this.value);
            valLabel.textContent = `${Math.round(val * 100)}%`;

            if (key === 'geo_lotes_dm' && typeof applyLotesStyle === 'function') {
                applyLotesStyle();
            } else if (key === 'geo_edificacoes_dm' && typeof applyEdificacoesStyle === 'function') {
                applyEdificacoesStyle();
            } else if (key === 'geo_zoneamento_dm' && typeof applyPdmStyle === 'function') {
                applyPdmStyle();
            } else {
                layers[key].eachLayer(function (layer) {
                    if (typeof layer.setStyle === 'function') {
                        layer.setStyle({
                            opacity: val,
                            fillOpacity: config.fillOpacity !== undefined ? (config.fillOpacity * val) : 0.2 * val
                        });
                    }
                });
            }
        });
    }
});

map.on('overlayadd', function (e) {
    Object.keys(layersConfig).forEach(key => {
        if (layersConfig[key].name === e.name) {
            const chk = document.getElementById(`chk-${key}`);
            if (chk) chk.checked = true;
            loadLayerData(key);
        }
    });
});

map.on('overlayremove', function (e) {
    Object.keys(layersConfig).forEach(key => {
        if (layersConfig[key].name === e.name) {
            const chk = document.getElementById(`chk-${key}`);
            if (chk) chk.checked = false;
        }
    });
});

// Carga inicial de camadas
loadAllActiveLayers();

// Controle de colapso da Barra Lateral
const btnToggleSidebar = document.getElementById('toggle-sidebar');
if (btnToggleSidebar) {
    btnToggleSidebar.addEventListener('click', function () {
        const sidebar = document.querySelector('.sidebar');
        if (sidebar) sidebar.classList.toggle('collapsed');
        setTimeout(() => {
            map.invalidateSize();
        }, 310);
    });
}

// Controle de Abas da Sidebar
document.querySelectorAll('.sidebar-tab-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.sidebar-tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.sidebar-tab-content').forEach(c => c.classList.remove('active'));

        this.classList.add('active');
        const tabId = this.getAttribute('data-tab');
        const contentEl = document.getElementById(tabId);
        if (contentEl) contentEl.classList.add('active');
    });
});

// Evita a abertura de popups nativos quando ferramentas ativas
map.on('popupopen', function (e) {
    if ((typeof identifyModeActive !== 'undefined' && identifyModeActive) ||
        (typeof bufferModeActive !== 'undefined' && bufferModeActive) ||
        (typeof editModeActive !== 'undefined' && editModeActive)) {
        map.closePopup();
    }
});

// Delegação de cliques no mapa
map.on('click', function (e) {
    if (coordsModeActive && mousePositionCtrl) {
        mousePositionCtrl.updateCoordinates(e.latlng);
    }
    if (typeof selecaoToolActive !== 'undefined' && selecaoToolActive && typeof selecaoMapClickMode !== 'undefined' && selecaoMapClickMode) {
        selectCommunityByLatLng(e.latlng.lat, e.latlng.lng);
        return;
    }
    if (typeof bufferModeActive !== 'undefined' && bufferModeActive) {
        setBufferCenter(e.latlng);
        return;
    }
    if (typeof editModeActive !== 'undefined' && editModeActive) {
        const hasRamalAgua = map.hasLayer(layers['vw_edificacao_ramal_agua']);
        const hasRamalEsgoto = map.hasLayer(layers['vw_edificacao_ramal_esgoto']);
        const hasAgua = map.hasLayer(layers['vw_rede_agua']);
        const hasEsgoto = map.hasLayer(layers['vw_rede_esgoto']);

        if (hasRamalAgua && !hasRamalEsgoto && !hasAgua && !hasEsgoto) {
            openEditRamalAgua(e.latlng);
        } else if (hasRamalEsgoto && !hasRamalAgua && !hasAgua && !hasEsgoto) {
            openEditRamalEsgoto(e.latlng);
        } else if (!hasAgua && hasEsgoto) {
            openEditRede(e.latlng, null, 'vw_rede_esgoto');
        } else {
            openEditRede(e.latlng, null, 'vw_rede_agua');
        }
        return;
    }
    if (typeof identifyModeActive !== 'undefined' && identifyModeActive) {
        executeIdentify(e.latlng);
    }
});

// ==========================================================================
// FERRAMENTAS DE MEDIÇÃO (DISTÂNCIA E ÁREA)
// ==========================================================================
let measureModeActive = false;
let measureType = null;
let measurePoints = [];
let measurePolyline = null;
let measurePolygon = null;
let measureMarkers = [];
let measureTooltip = null;

const btnMeasureDist = document.getElementById('btn-measure-dist');
const btnMeasureArea = document.getElementById('btn-measure-area');

if (btnMeasureDist) {
    btnMeasureDist.addEventListener('click', function (e) {
        L.DomEvent.stopPropagation(e);
        toggleMeasureTool('distance');
    });
    L.DomEvent.disableClickPropagation(btnMeasureDist);
}

if (btnMeasureArea) {
    btnMeasureArea.addEventListener('click', function (e) {
        L.DomEvent.stopPropagation(e);
        toggleMeasureTool('area');
    });
    L.DomEvent.disableClickPropagation(btnMeasureArea);
}

function toggleMeasureTool(type) {
    const mapContainer = document.getElementById('map');

    if (measureModeActive && measureType === type) {
        if (btnMeasureDist) btnMeasureDist.classList.remove('active');
        if (btnMeasureArea) btnMeasureArea.classList.remove('active');
        if (mapContainer) mapContainer.classList.remove('map-measure-cursor');
        measureModeActive = false;
        measureType = null;
        stopMeasure();
        return;
    }

    measureModeActive = true;
    measureType = type;

    if (type === 'distance') {
        if (btnMeasureDist) btnMeasureDist.classList.add('active');
        if (btnMeasureArea) btnMeasureArea.classList.remove('active');
    } else {
        if (btnMeasureDist) btnMeasureDist.classList.remove('active');
        if (btnMeasureArea) btnMeasureArea.classList.add('active');
    }

    if (typeof identifyModeActive !== 'undefined' && identifyModeActive && typeof toggleIdentifyTool === 'function') {
        toggleIdentifyTool(false);
    }
    if (typeof bufferModeActive !== 'undefined' && bufferModeActive && typeof toggleBufferTool === 'function') {
        toggleBufferTool();
    }
    if (typeof editModeActive !== 'undefined' && editModeActive && typeof toggleEditTool === 'function') {
        toggleEditTool(false);
    }

    if (mapContainer) mapContainer.classList.add('map-measure-cursor');
    stopMeasure();
    startMeasure();
}

function startMeasure() {
    measurePoints = [];
    measureMarkers = [];
    if (measurePolyline) {
        map.removeLayer(measurePolyline);
        measurePolyline = null;
    }
    if (measurePolygon) {
        map.removeLayer(measurePolygon);
        measurePolygon = null;
    }
    map.on('click', onMeasureClick);
    map.on('mousemove', onMeasureMouseMove);
    document.addEventListener('keydown', onMeasureKeyDown);
}

function stopMeasure() {
    map.off('click', onMeasureClick);
    map.off('mousemove', onMeasureMouseMove);
    document.removeEventListener('keydown', onMeasureKeyDown);

    if (measurePolyline) {
        map.removeLayer(measurePolyline);
        measurePolyline = null;
    }
    if (measurePolygon) {
        map.removeLayer(measurePolygon);
        measurePolygon = null;
    }
    measureMarkers.forEach(m => map.removeLayer(m));
    measureMarkers = [];
    if (measureTooltip) {
        map.removeLayer(measureTooltip);
        measureTooltip = null;
    }
}

function calculateSphericalArea(latlngs) {
    const radius = 6378137;
    const len = latlngs.length;
    if (len < 3) return 0;

    let area = 0;
    for (let i = 0; i < len; i++) {
        const p1 = latlngs[i];
        const p2 = latlngs[(i + 1) % len];

        const lambda1 = p1.lng * Math.PI / 180;
        const lambda2 = p2.lng * Math.PI / 180;
        const phi1 = p1.lat * Math.PI / 180;
        const phi2 = p2.lat * Math.PI / 180;

        area += (lambda2 - lambda1) * (2 + Math.sin(phi1) + Math.sin(phi2));
    }

    area = Math.abs(area * radius * radius / 2.0);
    return area;
}

function onMeasureClick(e) {
    const latlng = e.latlng;
    measurePoints.push(latlng);

    const colorTheme = measureType === 'distance' ? '#3b82f6' : '#10b981';

    const marker = L.circleMarker(latlng, {
        radius: 4,
        color: colorTheme,
        fillColor: colorTheme,
        fillOpacity: 1,
        weight: 2
    }).addTo(map);
    measureMarkers.push(marker);

    if (measureType === 'distance') {
        if (measurePoints.length === 1) {
            measurePolyline = L.polyline([latlng, latlng], {
                color: '#3b82f6',
                weight: 3,
                dashArray: '5, 5'
            }).addTo(map);
        } else {
            measurePolyline.setLatLngs(measurePoints);
        }
    } else if (measureType === 'area') {
        if (measurePoints.length === 1) {
            measurePolyline = L.polyline([latlng, latlng], {
                color: '#10b981',
                weight: 2,
                dashArray: '3, 3'
            }).addTo(map);
        } else {
            measurePolyline.setLatLngs(measurePoints);
        }

        if (measurePoints.length >= 3) {
            if (!measurePolygon) {
                measurePolygon = L.polygon(measurePoints, {
                    color: '#10b981',
                    fillColor: '#10b981',
                    fillOpacity: 0.15,
                    weight: 0
                }).addTo(map);
            } else {
                measurePolygon.setLatLngs(measurePoints);
            }
        }
    }

    updateMeasureTooltip(latlng);
}

function onMeasureMouseMove(e) {
    if (measurePoints.length === 0) return;

    const latlng = e.latlng;
    const tempPoints = [...measurePoints, latlng];
    if (measurePolyline) measurePolyline.setLatLngs(tempPoints);

    if (measureType === 'distance') {
        let totalDist = 0;
        for (let i = 0; i < tempPoints.length - 1; i++) {
            totalDist += tempPoints[i].distanceTo(tempPoints[i + 1]);
        }
        updateMeasureTooltip(latlng, totalDist, 0);
    } else if (measureType === 'area') {
        if (measurePoints.length >= 2) {
            if (!measurePolygon) {
                measurePolygon = L.polygon(tempPoints, {
                    color: '#10b981',
                    fillColor: '#10b981',
                    fillOpacity: 0.15,
                    weight: 0
                }).addTo(map);
            } else {
                measurePolygon.setLatLngs(tempPoints);
            }
        }

        let area = 0;
        if (tempPoints.length >= 3) {
            area = calculateSphericalArea(tempPoints);
        }
        updateMeasureTooltip(latlng, 0, area);
    }
}

function updateMeasureTooltip(latlng, distance = 0, area = 0) {
    if (!measureTooltip) {
        measureTooltip = L.tooltip({
            permanent: true,
            className: 'measure-tooltip',
            direction: 'right',
            offset: [15, 0]
        });
    }

    let tooltipContent = '';

    if (measureType === 'distance') {
        const distText = distance < 1000
            ? `${distance.toFixed(1)} m`
            : `${(distance / 1000).toFixed(2)} km`;
        tooltipContent = `<strong>Distância:</strong> ${distText}`;
    } else if (measureType === 'area') {
        const areaText = area < 10000
            ? `${area.toFixed(1)} m²`
            : `${(area / 10000).toFixed(2)} ha`;
        tooltipContent = `<strong>Área:</strong> ${areaText}`;
    }

    tooltipContent += `<br><span style="font-size:10px;color:#94a3b8;">ESC para limpar / Clique para desenhar</span>`;

    measureTooltip
        .setLatLng(latlng)
        .setContent(tooltipContent)
        .addTo(map);
}

function onMeasureKeyDown(e) {
    if (e.key === 'Escape') {
        stopMeasure();
        startMeasure();
    }
}

// ==========================================================================
// UTILITÁRIOS GLOBAIS
// ==========================================================================
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeDomString(str) {
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

// Vincula funções e objetos principais ao window para interoperabilidade
window.map = map;
window.layers = layers;
window.layersConfig = layersConfig;
window.loadLayerData = loadLayerData;
window.loadAllActiveLayers = loadAllActiveLayers;
window.showLoading = showLoading;
window.hideLoading = hideLoading;
window.escapeHtml = escapeHtml;
window.escapeDomString = escapeDomString;
window.measureModeActive = measureModeActive;
window.measureType = measureType;
window.toggleMeasureTool = toggleMeasureTool;
window.stopMeasure = stopMeasure;
window.toggleCoordinatesTool = toggleCoordinatesTool;
window.transformLatLonToUtm23S = transformLatLonToUtm23S;
window.formatCoordNumber = formatCoordNumber;
