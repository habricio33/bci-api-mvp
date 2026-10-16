/**
 * ==========================================================================
 * MÓDULO: CRUD E EDIÇÃO DE REDES DE ÁGUA E ESGOTO E RAMAIS PREDIAIS
 * Arquivo: js/modules/module-crud-redes.js
 * ==========================================================================
 */

let editModeActive = false;

const REDE_TIPOS_CONFIG = {
    'vw_rede_agua': ['Adutora', 'Distribuição', 'Recalque'],
    'vw_rede_esgoto': ['Coletor', 'Coletor principal', 'Interceptor', 'Emissário']
};

function toggleEditTool(forceState) {
    editModeActive = typeof forceState === 'boolean' ? forceState : !editModeActive;
    const btnEditTool = document.getElementById('btn-edit-tool');
    const mapContainer = document.getElementById('map');

    if (btnEditTool) {
        btnEditTool.classList.toggle('active', editModeActive);
        btnEditTool.classList.toggle('edit-active', editModeActive);
    }

    if (editModeActive) {
        if (mapContainer) mapContainer.classList.add('map-edit-cursor');

        // Desativa outras ferramentas concorrentes
        if (typeof identifyModeActive !== 'undefined' && identifyModeActive && typeof toggleIdentifyTool === 'function') {
            toggleIdentifyTool(false);
        }
        if (typeof bufferModeActive !== 'undefined' && bufferModeActive && typeof toggleBufferTool === 'function') {
            toggleBufferTool();
        }
        if (typeof measureModeActive !== 'undefined' && measureModeActive && typeof stopMeasure === 'function') {
            const btnMeasureDist = document.getElementById('btn-measure-dist');
            const btnMeasureArea = document.getElementById('btn-measure-area');
            if (btnMeasureDist) btnMeasureDist.classList.remove('active');
            if (btnMeasureArea) btnMeasureArea.classList.remove('active');
            mapContainer.classList.remove('map-measure-cursor');
            window.measureModeActive = false;
            window.measureType = null;
            stopMeasure();
        }

        // Garante que pelo menos uma camada editável esteja visível no mapa se nenhuma estiver
        const chkAgua = document.getElementById('chk-vw_rede_agua');
        const chkEsgoto = document.getElementById('chk-vw_rede_esgoto');
        const chkRamalAgua = document.getElementById('chk-vw_edificacao_ramal_agua');
        const chkRamalEsgoto = document.getElementById('chk-vw_edificacao_ramal_esgoto');
        const isAguaActive = chkAgua && chkAgua.checked;
        const isEsgotoActive = chkEsgoto && chkEsgoto.checked;
        const isRamalAguaActive = chkRamalAgua && chkRamalAgua.checked;
        const isRamalEsgotoActive = chkRamalEsgoto && chkRamalEsgoto.checked;

        if (!isAguaActive && !isEsgotoActive && !isRamalAguaActive && !isRamalEsgotoActive) {
            if (chkAgua) {
                chkAgua.checked = true;
                if (typeof layers !== 'undefined' && layers['vw_rede_agua'] && typeof map !== 'undefined' && !map.hasLayer(layers['vw_rede_agua'])) {
                    map.addLayer(layers['vw_rede_agua']);
                }
                if (typeof loadLayerData === 'function') {
                    loadLayerData('vw_rede_agua');
                }
            }
        }
    } else {
        if (mapContainer) mapContainer.classList.remove('map-edit-cursor');
    }
}

function openEditRede(latlng, directFeature, layerKey = 'vw_rede_agua') {
    if (typeof map !== 'undefined') map.closePopup();
    hideCrudAlert();

    const lat = latlng.lat;
    const lng = latlng.lng;
    if (typeof showLoading === 'function') showLoading();

    let idGeoDirect = null;
    if (directFeature && directFeature.properties) {
        idGeoDirect = directFeature.properties.id_geo || directFeature.properties.id_rede_agua || directFeature.properties.id_rede_esgoto || directFeature.properties.id;
    }

    const endpoint = layerKey === 'vw_rede_esgoto' ? 'api/crud_rede_esgoto.php' : 'api/crud_rede_agua.php';
    const url = idGeoDirect
        ? `${endpoint}?id_geo=${idGeoDirect}`
        : `${endpoint}?lat=${lat}&lng=${lng}`;

    fetch(url)
        .then(res => {
            if (!res.ok) throw new Error('Erro ao consultar trecho de rede.');
            return res.json();
        })
        .then(res => {
            if (typeof hideLoading === 'function') hideLoading();
            if (!res.success || !res.data) {
                if (!idGeoDirect && layerKey === 'vw_rede_agua' && typeof layers !== 'undefined' && typeof map !== 'undefined' && map.hasLayer(layers['vw_rede_esgoto'])) {
                    openEditRede(latlng, null, 'vw_rede_esgoto');
                    return;
                }
                const redeNome = layerKey === 'vw_rede_esgoto' ? 'rede de esgoto' : 'rede de água';
                alert(res.message || `Nenhum trecho de ${redeNome} encontrado próximo ao clique.`);
                return;
            }

            populateAndShowEditModal(res.data, layerKey);
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error(`Erro ao abrir edição de ${layerKey}:`, err);
            const redeNome = layerKey === 'vw_rede_esgoto' ? 'rede de esgoto' : 'rede de água';
            alert(`Não foi possível identificar o trecho de ${redeNome} para edição.`);
        });
}

function openEditRedeAgua(latlng, directFeature) {
    return openEditRede(latlng, directFeature, 'vw_rede_agua');
}

function populateAndShowEditModal(data, layerKey = 'vw_rede_agua') {
    const isEsgoto = (layerKey === 'vw_rede_esgoto');
    const editRedeAguaModal = document.getElementById('edit-rede-agua-modal');
    const btnDeleteRedeAgua = document.getElementById('btn-delete-rede-agua');

    const editCamadaInput = document.getElementById('edit-camada');
    if (editCamadaInput) {
        editCamadaInput.value = layerKey;
    }

    const modalTitleEl = document.getElementById('edit-modal-title');
    const modalIconEl = document.getElementById('edit-modal-icon');
    if (modalTitleEl) {
        modalTitleEl.textContent = isEsgoto ? 'Editar Trecho da Rede de Esgoto' : 'Editar Trecho da Rede de Água';
    }
    if (modalIconEl) {
        modalIconEl.style.color = isEsgoto ? '#f97316' : '#3b82f6';
    }

    const selectTipo = document.getElementById('edit-tipo');
    if (selectTipo) {
        selectTipo.innerHTML = '<option value="">-- Não Informado --</option>';
        const availableTipos = REDE_TIPOS_CONFIG[layerKey] || [];
        availableTipos.forEach(tipo => {
            const opt = document.createElement('option');
            opt.value = tipo;
            opt.textContent = tipo;
            selectTipo.appendChild(opt);
        });
        selectTipo.value = data.tipo || '';
    }

    document.getElementById('edit-id-geo').value = data.id_geo || '';
    document.getElementById('edit-id-alfanumerico').value = data.id_alfanumerico || '';

    document.getElementById('badge-id-geo').textContent = `#${data.id_geo}`;
    document.getElementById('badge-extensao').textContent = data.extensao_m ? `${parseFloat(data.extensao_m).toLocaleString('pt-BR', { minimumFractionDigits: 1 })} m` : '-';

    const statusBadge = document.getElementById('badge-status');
    if (data.id_alfanumerico) {
        statusBadge.textContent = 'Cadastrado';
        statusBadge.className = 'crud-status-tag status-saved';
        if (btnDeleteRedeAgua) btnDeleteRedeAgua.style.display = 'inline-flex';
    } else {
        statusBadge.textContent = 'Novo Cadastro';
        statusBadge.className = 'crud-status-tag status-new';
        if (btnDeleteRedeAgua) btnDeleteRedeAgua.style.display = 'none';
    }

    document.getElementById('edit-rede').value = data.rede || '';
    document.getElementById('edit-material').value = data.material || '';
    document.getElementById('edit-diametro').value = (data.diametro !== null && data.diametro !== undefined) ? data.diametro : '';
    document.getElementById('edit-profundidade').value = (data.profundidade !== null && data.profundidade !== undefined) ? data.profundidade : '';
    document.getElementById('edit-data-implantacao').value = data.data_implantacao || '';
    document.getElementById('edit-responsavel').value = data.responsavel_implantacao || '';
    document.getElementById('edit-observacao').value = data.observacao || '';

    hideCrudAlert();
    if (editRedeAguaModal) {
        editRedeAguaModal.classList.add('show');
    }
}

function closeEditModal() {
    const editRedeAguaModal = document.getElementById('edit-rede-agua-modal');
    if (editRedeAguaModal) {
        editRedeAguaModal.classList.remove('show');
    }
    hideCrudAlert();
}

function showCrudAlert(msg, type = 'success') {
    const crudAlertMsg = document.getElementById('crud-alert-msg');
    if (!crudAlertMsg) return;
    crudAlertMsg.textContent = msg;
    crudAlertMsg.className = `crud-alert alert-${type}`;
    crudAlertMsg.style.display = 'flex';
}

function hideCrudAlert() {
    const crudAlertMsg = document.getElementById('crud-alert-msg');
    if (!crudAlertMsg) return;
    crudAlertMsg.style.display = 'none';
    crudAlertMsg.textContent = '';
}

function openEditRamalAgua(latlng, directFeature) {
    if (typeof map !== 'undefined') map.closePopup();
    hideCrudRamalAlert();

    const lat = latlng.lat;
    const lng = latlng.lng;
    if (typeof showLoading === 'function') showLoading();

    let idGeoDirect = null;
    if (directFeature && directFeature.properties) {
        idGeoDirect = directFeature.properties.id_geo || directFeature.properties.id;
    }

    const url = idGeoDirect
        ? `api/crud_ramal_agua.php?id_geo=${idGeoDirect}`
        : `api/crud_ramal_agua.php?lat=${lat}&lng=${lng}`;

    fetch(url)
        .then(res => {
            if (!res.ok) throw new Error('Erro ao consultar ramal de água.');
            return res.json();
        })
        .then(res => {
            if (typeof hideLoading === 'function') hideLoading();
            if (!res.success || !res.data) {
                alert(res.message || 'Nenhum imóvel / ramal de água encontrado próximo ao clique.');
                return;
            }

            populateAndShowEditRamalModal(res.data);
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro ao abrir edição de ramal de água:', err);
            alert('Não foi possível identificar o ramal de água para edição.');
        });
}

function populateAndShowEditRamalModal(data) {
    const editRamalAguaModal = document.getElementById('edit-ramal-agua-modal');
    const btnDeleteRamalAgua = document.getElementById('btn-delete-ramal-agua');

    document.getElementById('edit-ramal-id-geo').value = data.id_geo || '';
    document.getElementById('edit-ramal-id').value = data.ramal_id || '';
    document.getElementById('edit-ramal-edificacao-id').value = data.edificacao_id || '';

    document.getElementById('badge-ramal-id-geo').textContent = `#${data.id_geo}`;
    document.getElementById('badge-ramal-area').textContent = data.area_m2 ? `${parseFloat(data.area_m2).toLocaleString('pt-BR', { minimumFractionDigits: 1 })} m²` : '-';

    const statusBadge = document.getElementById('badge-ramal-status');
    if (data.ramal_id) {
        statusBadge.textContent = 'Cadastrado';
        statusBadge.className = 'crud-status-tag status-saved';
        if (btnDeleteRamalAgua) btnDeleteRamalAgua.style.display = 'inline-flex';
    } else {
        statusBadge.textContent = 'Novo Cadastro';
        statusBadge.className = 'crud-status-tag status-new';
        if (btnDeleteRamalAgua) btnDeleteRamalAgua.style.display = 'none';
    }

    document.getElementById('edit-ramal-numero-postal').value = data.numero_postal || '';
    document.getElementById('edit-ramal-tipo-edificacao').value = data.tipo_edificacao || '';
    document.getElementById('edit-ramal-hidrometro').value = data.hidrometro || '';
    document.getElementById('edit-ramal-material').value = data.material || '';
    document.getElementById('edit-ramal-diametro').value = (data.diametro !== null && data.diametro !== undefined) ? data.diametro : '';
    document.getElementById('edit-ramal-profundidade').value = (data.profundidade !== null && data.profundidade !== undefined) ? data.profundidade : '';
    document.getElementById('edit-ramal-pavimento').value = data.tipo_pavimento || '';
    document.getElementById('edit-ramal-extensao').value = (data.extensao !== null && data.extensao !== undefined) ? data.extensao : '';
    document.getElementById('edit-ramal-a-direita').value = (data.a_direita !== null && data.a_direita !== undefined) ? data.a_direita : '';
    document.getElementById('edit-ramal-a-esquerda').value = (data.a_esquerda !== null && data.a_esquerda !== undefined) ? data.a_esquerda : '';
    document.getElementById('edit-ramal-data-implantacao').value = data.data_implantacao || '';
    document.getElementById('edit-ramal-observacao').value = data.observacao || '';

    hideCrudRamalAlert();
    if (editRamalAguaModal) {
        editRamalAguaModal.classList.add('show');
    }
}

function closeEditRamalModal() {
    const editRamalAguaModal = document.getElementById('edit-ramal-agua-modal');
    if (editRamalAguaModal) {
        editRamalAguaModal.classList.remove('show');
    }
    hideCrudRamalAlert();
}

function showCrudRamalAlert(msg, type = 'success') {
    const crudRamalAlertMsg = document.getElementById('crud-ramal-alert-msg');
    if (!crudRamalAlertMsg) return;
    crudRamalAlertMsg.textContent = msg;
    crudRamalAlertMsg.className = `crud-alert alert-${type}`;
    crudRamalAlertMsg.style.display = 'flex';
}

function hideCrudRamalAlert() {
    const crudRamalAlertMsg = document.getElementById('crud-ramal-alert-msg');
    if (!crudRamalAlertMsg) return;
    crudRamalAlertMsg.style.display = 'none';
    crudRamalAlertMsg.textContent = '';
}

function openEditRamalEsgoto(latlng, directFeature) {
    if (typeof map !== 'undefined') map.closePopup();
    hideCrudRamalEsgotoAlert();

    const lat = latlng.lat;
    const lng = latlng.lng;
    if (typeof showLoading === 'function') showLoading();

    let idGeoDirect = null;
    if (directFeature && directFeature.properties) {
        idGeoDirect = directFeature.properties.id_geo || directFeature.properties.id;
    }

    const url = idGeoDirect
        ? `api/crud_ramal_esgoto.php?id_geo=${idGeoDirect}`
        : `api/crud_ramal_esgoto.php?lat=${lat}&lng=${lng}`;

    fetch(url)
        .then(res => {
            if (!res.ok) throw new Error('Erro ao consultar ramal de esgoto.');
            return res.json();
        })
        .then(res => {
            if (typeof hideLoading === 'function') hideLoading();
            if (!res.success || !res.data) {
                alert(res.message || 'Nenhum imóvel / ramal de esgoto encontrado próximo ao clique.');
                return;
            }

            populateAndShowEditRamalEsgotoModal(res.data);
        })
        .catch(err => {
            if (typeof hideLoading === 'function') hideLoading();
            console.error('Erro ao abrir edição de ramal de esgoto:', err);
            alert('Não foi possível identificar o ramal de esgoto para edição.');
        });
}

function populateAndShowEditRamalEsgotoModal(data) {
    const editRamalEsgotoModal = document.getElementById('edit-ramal-esgoto-modal');
    const btnDeleteRamalEsgoto = document.getElementById('btn-delete-ramal-esgoto');

    document.getElementById('edit-ramal-esgoto-id-geo').value = data.id_geo || '';
    document.getElementById('edit-ramal-esgoto-id').value = data.ramal_id || '';
    document.getElementById('edit-ramal-esgoto-edificacao-id').value = data.edificacao_id || '';

    document.getElementById('badge-ramal-esgoto-id-geo').textContent = `#${data.id_geo}`;
    document.getElementById('badge-ramal-esgoto-area').textContent = data.area_m2 ? `${parseFloat(data.area_m2).toLocaleString('pt-BR', { minimumFractionDigits: 1 })} m²` : '-';

    const statusBadge = document.getElementById('badge-ramal-esgoto-status');
    if (data.ramal_id) {
        statusBadge.textContent = 'Cadastrado';
        statusBadge.className = 'crud-status-tag status-saved';
        if (btnDeleteRamalEsgoto) btnDeleteRamalEsgoto.style.display = 'inline-flex';
    } else {
        statusBadge.textContent = 'Novo Cadastro';
        statusBadge.className = 'crud-status-tag status-new';
        if (btnDeleteRamalEsgoto) btnDeleteRamalEsgoto.style.display = 'none';
    }

    document.getElementById('edit-ramal-esgoto-numero-postal').value = data.numero_postal || '';
    document.getElementById('edit-ramal-esgoto-tipo-edificacao').value = data.tipo_edificacao || '';
    document.getElementById('edit-ramal-esgoto-tipo').value = data.tipo_ramal || '';
    document.getElementById('edit-ramal-esgoto-material').value = data.material || '';
    document.getElementById('edit-ramal-esgoto-diametro').value = (data.diametro !== null && data.diametro !== undefined) ? data.diametro : '';
    document.getElementById('edit-ramal-esgoto-profundidade').value = (data.profundidade !== null && data.profundidade !== undefined) ? data.profundidade : '';
    document.getElementById('edit-ramal-esgoto-pavimento').value = data.tipo_pavimento || '';
    document.getElementById('edit-ramal-esgoto-extensao').value = (data.extensao !== null && data.extensao !== undefined) ? data.extensao : '';
    document.getElementById('edit-ramal-esgoto-a-direita').value = (data.a_direita !== null && data.a_direita !== undefined) ? data.a_direita : '';
    document.getElementById('edit-ramal-esgoto-a-esquerda').value = (data.a_esquerda !== null && data.a_esquerda !== undefined) ? data.a_esquerda : '';
    document.getElementById('edit-ramal-esgoto-data-implantacao').value = data.data_implantacao || '';
    document.getElementById('edit-ramal-esgoto-observacao').value = data.observacao || '';

    hideCrudRamalEsgotoAlert();
    if (editRamalEsgotoModal) {
        editRamalEsgotoModal.classList.add('show');
    }
}

function closeEditRamalEsgotoModal() {
    const editRamalEsgotoModal = document.getElementById('edit-ramal-esgoto-modal');
    if (editRamalEsgotoModal) {
        editRamalEsgotoModal.classList.remove('show');
    }
    hideCrudRamalEsgotoAlert();
}

function showCrudRamalEsgotoAlert(msg, type = 'success') {
    const crudRamalEsgotoAlertMsg = document.getElementById('crud-ramal-esgoto-alert-msg');
    if (!crudRamalEsgotoAlertMsg) return;
    crudRamalEsgotoAlertMsg.textContent = msg;
    crudRamalEsgotoAlertMsg.className = `crud-alert alert-${type}`;
    crudRamalEsgotoAlertMsg.style.display = 'flex';
}

function hideCrudRamalEsgotoAlert() {
    const crudRamalEsgotoAlertMsg = document.getElementById('crud-ramal-esgoto-alert-msg');
    if (!crudRamalEsgotoAlertMsg) return;
    crudRamalEsgotoAlertMsg.style.display = 'none';
    crudRamalEsgotoAlertMsg.textContent = '';
}

// Inicializa listeners do DOM para o CRUD
document.addEventListener('DOMContentLoaded', function () {
    const btnEditTool = document.getElementById('btn-edit-tool');
    const btnCloseEditModal = document.getElementById('edit-modal-btn-close');
    const btnCancelEditModal = document.getElementById('btn-cancel-edit-rede-agua');
    const editRedeAguaModal = document.getElementById('edit-rede-agua-modal');
    const formEditRedeAgua = document.getElementById('form-edit-rede-agua');
    const btnDeleteRedeAgua = document.getElementById('btn-delete-rede-agua');
    const btnSaveRedeAgua = document.getElementById('btn-save-rede-agua');

    const btnCloseEditRamalModal = document.getElementById('edit-ramal-modal-btn-close');
    const btnCancelEditRamalModal = document.getElementById('btn-cancel-edit-ramal-agua');
    const editRamalAguaModal = document.getElementById('edit-ramal-agua-modal');
    const formEditRamalAgua = document.getElementById('form-edit-ramal-agua');
    const btnDeleteRamalAgua = document.getElementById('btn-delete-ramal-agua');
    const btnSaveRamalAgua = document.getElementById('btn-save-ramal-agua');

    const btnCloseEditRamalEsgotoModal = document.getElementById('edit-ramal-esgoto-modal-btn-close');
    const btnCancelEditRamalEsgotoModal = document.getElementById('btn-cancel-edit-ramal-esgoto');
    const editRamalEsgotoModal = document.getElementById('edit-ramal-esgoto-modal');
    const formEditRamalEsgoto = document.getElementById('form-edit-ramal-esgoto');
    const btnDeleteRamalEsgoto = document.getElementById('btn-delete-ramal-esgoto');
    const btnSaveRamalEsgoto = document.getElementById('btn-save-ramal-esgoto');

    if (btnEditTool) {
        btnEditTool.addEventListener('click', function (e) {
            L.DomEvent.stopPropagation(e);
            toggleEditTool();
        });
        L.DomEvent.disableClickPropagation(btnEditTool);
    }

    if (btnCloseEditModal) btnCloseEditModal.addEventListener('click', closeEditModal);
    if (btnCancelEditModal) btnCancelEditModal.addEventListener('click', closeEditModal);
    if (editRedeAguaModal) {
        editRedeAguaModal.addEventListener('click', function (e) {
            if (e.target === editRedeAguaModal) closeEditModal();
        });
    }

    if (formEditRedeAgua) {
        formEditRedeAgua.addEventListener('submit', function (e) {
            e.preventDefault();

            const idGeo = document.getElementById('edit-id-geo').value;
            if (!idGeo) {
                showCrudAlert('ID do trecho não encontrado.', 'error');
                return;
            }

            const editCamadaInput = document.getElementById('edit-camada');
            const camada = editCamadaInput ? editCamadaInput.value : 'vw_rede_agua';
            const isEsgoto = (camada === 'vw_rede_esgoto');
            const endpoint = isEsgoto ? 'api/crud_rede_esgoto.php' : 'api/crud_rede_agua.php';

            const payload = {
                id_geo: parseInt(idGeo),
                rede: document.getElementById('edit-rede').value.trim(),
                tipo: document.getElementById('edit-tipo').value,
                material: document.getElementById('edit-material').value,
                diametro: document.getElementById('edit-diametro').value !== '' ? parseFloat(document.getElementById('edit-diametro').value) : null,
                profundidade: document.getElementById('edit-profundidade').value !== '' ? parseFloat(document.getElementById('edit-profundidade').value) : null,
                data_implantacao: document.getElementById('edit-data-implantacao').value || null,
                responsavel_implantacao: document.getElementById('edit-responsavel').value.trim(),
                observacao: document.getElementById('edit-observacao').value.trim()
            };

            if (btnSaveRedeAgua) {
                btnSaveRedeAgua.disabled = true;
                btnSaveRedeAgua.innerHTML = '<span>Salvando...</span>';
            }

            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(res => {
                    if (btnSaveRedeAgua) {
                        btnSaveRedeAgua.disabled = false;
                        btnSaveRedeAgua.innerHTML = `
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            <span>Salvar Alterações</span>
                        `;
                    }

                    if (res.success) {
                        showCrudAlert(res.message || 'Dados gravados com sucesso!', 'success');
                        if (typeof loadLayerData === 'function') loadLayerData(camada);
                        setTimeout(() => closeEditModal(), 900);
                    } else {
                        showCrudAlert(res.message || 'Erro ao salvar dados.', 'error');
                    }
                })
                .catch(err => {
                    if (btnSaveRedeAgua) {
                        btnSaveRedeAgua.disabled = false;
                        btnSaveRedeAgua.innerHTML = `<span>Salvar Alterações</span>`;
                    }
                    console.error('Erro no salvamento:', err);
                    showCrudAlert('Erro de comunicação com o servidor.', 'error');
                });
        });
    }

    if (btnDeleteRedeAgua) {
        btnDeleteRedeAgua.addEventListener('click', function () {
            const idGeo = document.getElementById('edit-id-geo').value;
            if (!idGeo) return;

            const editCamadaInput = document.getElementById('edit-camada');
            const camada = editCamadaInput ? editCamadaInput.value : 'vw_rede_agua';
            const isEsgoto = (camada === 'vw_rede_esgoto');
            const endpoint = isEsgoto ? 'api/crud_rede_esgoto.php' : 'api/crud_rede_agua.php';
            const redeNome = isEsgoto ? 'rede de esgoto' : 'rede de água';

            if (!confirm(`Deseja realmente excluir todos os dados alfanuméricos deste trecho da ${redeNome}? A geometria será preservada.`)) {
                return;
            }

            fetch(endpoint, {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_geo: parseInt(idGeo) })
            })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showCrudAlert(res.message || 'Atributos removidos com sucesso.', 'success');
                        if (typeof loadLayerData === 'function') loadLayerData(camada);
                        setTimeout(() => closeEditModal(), 800);
                    } else {
                        showCrudAlert(res.message || 'Erro ao excluir atributos.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Erro na exclusão:', err);
                    showCrudAlert('Erro ao processar exclusão.', 'error');
                });
        });
    }

    // Handlers para Ramal de Água
    if (btnCloseEditRamalModal) btnCloseEditRamalModal.addEventListener('click', closeEditRamalModal);
    if (btnCancelEditRamalModal) btnCancelEditRamalModal.addEventListener('click', closeEditRamalModal);
    if (editRamalAguaModal) {
        editRamalAguaModal.addEventListener('click', function (e) {
            if (e.target === editRamalAguaModal) closeEditRamalModal();
        });
    }

    if (formEditRamalAgua) {
        formEditRamalAgua.addEventListener('submit', function (e) {
            e.preventDefault();

            const idGeo = document.getElementById('edit-ramal-id-geo').value;
            if (!idGeo) {
                showCrudRamalAlert('ID do imóvel não encontrado.', 'error');
                return;
            }

            const payload = {
                id_geo: parseInt(idGeo),
                numero_postal: document.getElementById('edit-ramal-numero-postal').value.trim(),
                tipo_edificacao: document.getElementById('edit-ramal-tipo-edificacao').value,
                hidrometro: document.getElementById('edit-ramal-hidrometro').value.trim(),
                material: document.getElementById('edit-ramal-material').value,
                diametro: document.getElementById('edit-ramal-diametro').value !== '' ? parseFloat(document.getElementById('edit-ramal-diametro').value) : null,
                profundidade: document.getElementById('edit-ramal-profundidade').value !== '' ? parseFloat(document.getElementById('edit-ramal-profundidade').value) : null,
                tipo_pavimento: document.getElementById('edit-ramal-pavimento').value,
                extensao: document.getElementById('edit-ramal-extensao').value !== '' ? parseFloat(document.getElementById('edit-ramal-extensao').value) : null,
                a_direita: document.getElementById('edit-ramal-a-direita').value !== '' ? parseFloat(document.getElementById('edit-ramal-a-direita').value) : null,
                a_esquerda: document.getElementById('edit-ramal-a-esquerda').value !== '' ? parseFloat(document.getElementById('edit-ramal-a-esquerda').value) : null,
                data_implantacao: document.getElementById('edit-ramal-data-implantacao').value || null,
                observacao: document.getElementById('edit-ramal-observacao').value.trim()
            };

            if (btnSaveRamalAgua) {
                btnSaveRamalAgua.disabled = true;
                btnSaveRamalAgua.innerHTML = '<span>Salvando...</span>';
            }

            fetch('api/crud_ramal_agua.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(res => {
                    if (btnSaveRamalAgua) {
                        btnSaveRamalAgua.disabled = false;
                        btnSaveRamalAgua.innerHTML = `
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            <span>Salvar Alterações</span>
                        `;
                    }

                    if (res.success) {
                        showCrudRamalAlert(res.message || 'Dados do ramal gravados com sucesso!', 'success');
                        if (typeof loadLayerData === 'function') {
                            loadLayerData('vw_edificacao_ramal_agua');
                            loadLayerData('vw_edificacoes');
                        }
                        setTimeout(() => closeEditRamalModal(), 900);
                    } else {
                        showCrudRamalAlert(res.message || 'Erro ao salvar dados do ramal.', 'error');
                    }
                })
                .catch(err => {
                    if (btnSaveRamalAgua) {
                        btnSaveRamalAgua.disabled = false;
                        btnSaveRamalAgua.innerHTML = `<span>Salvar Alterações</span>`;
                    }
                    console.error('Erro no salvamento do ramal:', err);
                    showCrudRamalAlert('Erro de comunicação com o servidor.', 'error');
                });
        });
    }

    if (btnDeleteRamalAgua) {
        btnDeleteRamalAgua.addEventListener('click', function () {
            const idGeo = document.getElementById('edit-ramal-id-geo').value;
            if (!idGeo) return;

            if (!confirm('Deseja realmente excluir todos os dados alfanuméricos deste ramal de água? A geometria do imóvel será preservada.')) {
                return;
            }

            fetch('api/crud_ramal_agua.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_geo: parseInt(idGeo) })
            })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showCrudRamalAlert(res.message || 'Atributos do ramal removidos com sucesso.', 'success');
                        if (typeof loadLayerData === 'function') {
                            loadLayerData('vw_edificacao_ramal_agua');
                            loadLayerData('vw_edificacoes');
                        }
                        setTimeout(() => closeEditRamalModal(), 800);
                    } else {
                        showCrudRamalAlert(res.message || 'Erro ao excluir atributos do ramal.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Erro na exclusão do ramal:', err);
                    showCrudRamalAlert('Erro ao processar exclusão.', 'error');
                });
        });
    }

    // Handlers para Ramal de Esgoto
    if (btnCloseEditRamalEsgotoModal) btnCloseEditRamalEsgotoModal.addEventListener('click', closeEditRamalEsgotoModal);
    if (btnCancelEditRamalEsgotoModal) btnCancelEditRamalEsgotoModal.addEventListener('click', closeEditRamalEsgotoModal);
    if (editRamalEsgotoModal) {
        editRamalEsgotoModal.addEventListener('click', function (e) {
            if (e.target === editRamalEsgotoModal) closeEditRamalEsgotoModal();
        });
    }

    if (formEditRamalEsgoto) {
        formEditRamalEsgoto.addEventListener('submit', function (e) {
            e.preventDefault();

            const idGeo = document.getElementById('edit-ramal-esgoto-id-geo').value;
            if (!idGeo) {
                showCrudRamalEsgotoAlert('ID do imóvel não encontrado.', 'error');
                return;
            }

            const payload = {
                id_geo: parseInt(idGeo),
                numero_postal: document.getElementById('edit-ramal-esgoto-numero-postal').value.trim(),
                tipo_edificacao: document.getElementById('edit-ramal-esgoto-tipo-edificacao').value,
                tipo_ramal: document.getElementById('edit-ramal-esgoto-tipo').value || null,
                material: document.getElementById('edit-ramal-esgoto-material').value,
                diametro: document.getElementById('edit-ramal-esgoto-diametro').value !== '' ? parseFloat(document.getElementById('edit-ramal-esgoto-diametro').value) : null,
                profundidade: document.getElementById('edit-ramal-esgoto-profundidade').value !== '' ? parseFloat(document.getElementById('edit-ramal-esgoto-profundidade').value) : null,
                tipo_pavimento: document.getElementById('edit-ramal-esgoto-pavimento').value,
                extensao: document.getElementById('edit-ramal-esgoto-extensao').value !== '' ? parseFloat(document.getElementById('edit-ramal-esgoto-extensao').value) : null,
                a_direita: document.getElementById('edit-ramal-esgoto-a-direita').value !== '' ? parseFloat(document.getElementById('edit-ramal-esgoto-a-direita').value) : null,
                a_esquerda: document.getElementById('edit-ramal-esgoto-a-esquerda').value !== '' ? parseFloat(document.getElementById('edit-ramal-esgoto-a-esquerda').value) : null,
                data_implantacao: document.getElementById('edit-ramal-esgoto-data-implantacao').value || null,
                observacao: document.getElementById('edit-ramal-esgoto-observacao').value.trim()
            };

            if (btnSaveRamalEsgoto) {
                btnSaveRamalEsgoto.disabled = true;
                btnSaveRamalEsgoto.innerHTML = '<span>Salvando...</span>';
            }

            fetch('api/crud_ramal_esgoto.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
                .then(res => res.json())
                .then(res => {
                    if (btnSaveRamalEsgoto) {
                        btnSaveRamalEsgoto.disabled = false;
                        btnSaveRamalEsgoto.innerHTML = `
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                <polyline points="7 3 7 8 15 8"></polyline>
                            </svg>
                            <span>Salvar Alterações</span>
                        `;
                    }

                    if (res.success) {
                        showCrudRamalEsgotoAlert(res.message || 'Dados do ramal de esgoto gravados com sucesso!', 'success');
                        if (typeof loadLayerData === 'function') {
                            loadLayerData('vw_edificacao_ramal_esgoto');
                            loadLayerData('vw_edificacoes');
                        }
                        setTimeout(() => closeEditRamalEsgotoModal(), 900);
                    } else {
                        showCrudRamalEsgotoAlert(res.message || 'Erro ao salvar dados do ramal de esgoto.', 'error');
                    }
                })
                .catch(err => {
                    if (btnSaveRamalEsgoto) {
                        btnSaveRamalEsgoto.disabled = false;
                        btnSaveRamalEsgoto.innerHTML = `<span>Salvar Alterações</span>`;
                    }
                    console.error('Erro no salvamento do ramal de esgoto:', err);
                    showCrudRamalEsgotoAlert('Erro de comunicação com o servidor.', 'error');
                });
        });
    }

    if (btnDeleteRamalEsgoto) {
        btnDeleteRamalEsgoto.addEventListener('click', function () {
            const idGeo = document.getElementById('edit-ramal-esgoto-id-geo').value;
            if (!idGeo) return;

            if (!confirm('Deseja realmente excluir todos os dados alfanuméricos deste ramal de esgoto? A geometria do imóvel será preservada.')) {
                return;
            }

            fetch('api/crud_ramal_esgoto.php', {
                method: 'DELETE',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id_geo: parseInt(idGeo) })
            })
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        showCrudRamalEsgotoAlert(res.message || 'Atributos do ramal de esgoto removidos com sucesso.', 'success');
                        if (typeof loadLayerData === 'function') {
                            loadLayerData('vw_edificacao_ramal_esgoto');
                            loadLayerData('vw_edificacoes');
                        }
                        setTimeout(() => closeEditRamalEsgotoModal(), 800);
                    } else {
                        showCrudRamalEsgotoAlert(res.message || 'Erro ao excluir atributos do ramal de esgoto.', 'error');
                    }
                })
                .catch(err => {
                    console.error('Erro na exclusão do ramal de esgoto:', err);
                    showCrudRamalEsgotoAlert('Erro ao processar exclusão.', 'error');
                });
        });
    }
});

// Vincula funções ao window para compatibilidade e chamadas inline
window.editModeActive = editModeActive;
window.toggleEditTool = toggleEditTool;
window.openEditRede = openEditRede;
window.openEditRedeAgua = openEditRedeAgua;
window.populateAndShowEditModal = populateAndShowEditModal;
window.closeEditModal = closeEditModal;
window.showCrudAlert = showCrudAlert;
window.hideCrudAlert = hideCrudAlert;
window.openEditRamalAgua = openEditRamalAgua;
window.populateAndShowEditRamalModal = populateAndShowEditRamalModal;
window.closeEditRamalModal = closeEditRamalModal;
window.showCrudRamalAlert = showCrudRamalAlert;
window.hideCrudRamalAlert = hideCrudRamalAlert;
window.openEditRamalEsgoto = openEditRamalEsgoto;
window.populateAndShowEditRamalEsgotoModal = populateAndShowEditRamalEsgotoModal;
window.closeEditRamalEsgotoModal = closeEditRamalEsgotoModal;
window.showCrudRamalEsgotoAlert = showCrudRamalEsgotoAlert;
window.hideCrudRamalEsgotoAlert = hideCrudRamalEsgotoAlert;
