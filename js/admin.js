/**
 * Controlador Frontend do Painel Administrativo do GeoPortal BCI
 * Gerencia Sidebar Colapsável, Indicadores, Gráficos Chart.js, Tabela de Usuários, Modais e Ações CRUD.
 */

// Estado Global do Módulo Administrativo
let adminState = {
    currentView: 'visao-geral',
    currentPage: 1,
    limit: 15,
    totalPages: 1,
    totalRecords: 0,
    search: '',
    perfil: 'todos',
    status: 'todos',
    usersCache: [],
    chartStatus: null,
    chartPerfil: null,
    sidebarCollapsed: false
};

// =========================================================================
// INICIALIZAÇÃO
// =========================================================================
document.addEventListener('DOMContentLoaded', () => {
    initAdminSidebar();
    carregarAdminDashboard(false);
    carregarListaUsuarios();
    initSincronizacaoDatas();

    // Auto-refresh suave de métricas a cada 60s
    setInterval(() => {
        if (adminState.currentView === 'visao-geral') {
            carregarAdminDashboard(false);
        } else if (adminState.currentView === 'sincronizacao') {
            carregarSincronizacaoDashboard(false);
        }
    }, 60000);
});

// =========================================================================
// SIDEBAR COLAPSÁVEL
// =========================================================================
function initAdminSidebar() {
    const savedCollapsed = localStorage.getItem('admin_sidebar_collapsed');
    const sidebar = document.getElementById('admin-sidebar');
    
    if (savedCollapsed === 'true' && sidebar) {
        sidebar.classList.add('collapsed');
        adminState.sidebarCollapsed = true;
    }
}

function toggleAdminSidebar(forceState = null) {
    const sidebar = document.getElementById('admin-sidebar');
    if (!sidebar) return;

    if (forceState !== null) {
        if (forceState) {
            sidebar.classList.add('collapsed');
            adminState.sidebarCollapsed = true;
        } else {
            sidebar.classList.remove('collapsed');
            adminState.sidebarCollapsed = false;
        }
    } else {
        sidebar.classList.toggle('collapsed');
        adminState.sidebarCollapsed = sidebar.classList.contains('collapsed');
    }

    localStorage.setItem('admin_sidebar_collapsed', adminState.sidebarCollapsed ? 'true' : 'false');

    // Redimensiona gráficos com suavidade após transição da sidebar
    setTimeout(() => {
        if (adminState.chartStatus) adminState.chartStatus.resize();
        if (adminState.chartPerfil) adminState.chartPerfil.resize();
    }, 300);
}

// =========================================================================
// ALTERNÂNCIA DE ABAS / VIEWS DA SIDEBAR
// =========================================================================
function switchAdminView(viewName) {
    adminState.currentView = viewName;

    // Atualiza botões da sidebar
    document.querySelectorAll('.admin-nav-item').forEach(btn => {
        if (btn.dataset.view === viewName) {
            btn.classList.add('active');
        } else if (btn.dataset.view) {
            btn.classList.remove('active');
        }
    });

    // Atualiza indicador / breadcrumb no header
    const breadcrumb = document.getElementById('breadcrumb-section-title');
    if (breadcrumb) {
        if (viewName === 'visao-geral') {
            breadcrumb.textContent = 'Visão Geral';
        } else if (viewName === 'usuarios') {
            breadcrumb.textContent = 'Gestão de Usuários';
        } else if (viewName === 'sincronizacao') {
            breadcrumb.textContent = 'Sincronização da API Tributária';
        }
    }

    // Alterna seções de conteúdo
    document.querySelectorAll('.admin-view-section').forEach(sec => {
        sec.classList.remove('active');
    });

    const targetSection = document.getElementById(`view-${viewName}`);
    if (targetSection) {
        targetSection.classList.add('active');
    }

    if (viewName === 'visao-geral') {
        carregarAdminDashboard(false);
    } else if (viewName === 'usuarios') {
        carregarListaUsuarios();
    } else if (viewName === 'sincronizacao') {
        carregarSincronizacaoDashboard(false);
        carregarListaSincronizados();
    }
}

// =========================================================================
// 1. CARREGAMENTO DO DASHBOARD & INDICADORES (KPIs & GRÁFICOS)
// =========================================================================
async function carregarAdminDashboard(showToastFeedback = false) {
    try {
        const response = await fetch('api/admin_usuarios.php?action=stats');
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar estatísticas.');
        }

        const kpis = data.kpis;
        const total = kpis.total_usuarios || 0;
        const ativos = kpis.usuarios_ativos || 0;
        const inativos = kpis.usuarios_inativos || 0;
        const bloqueados = kpis.usuarios_bloqueados || 0;

        // Atualiza Cards de KPIs
        document.getElementById('kpi-total-usuarios').textContent = total;
        document.getElementById('kpi-usuarios-ativos').textContent = ativos;
        document.getElementById('kpi-usuarios-inativos').textContent = inativos + bloqueados;
        document.getElementById('kpi-total-perfis').textContent = kpis.total_perfis || 3;

        // Badge de porcentagem de ativos
        const percentAtivos = total > 0 ? Math.round((ativos / total) * 100) : 0;
        const elemPercent = document.getElementById('kpi-percent-ativos');
        if (elemPercent) elemPercent.textContent = `${percentAtivos}% do total`;

        // Badge de bloqueados
        const elemBadgeBloq = document.getElementById('kpi-badge-bloqueados');
        if (elemBadgeBloq) elemBadgeBloq.textContent = `${bloqueados} bloqueado(s)`;

        // Badge de contagem na sidebar
        const navCountBadge = document.getElementById('nav-user-count-badge');
        if (navCountBadge) navCountBadge.textContent = total;

        // Atualiza Horário do Servidor
        const elemServerTime = document.getElementById('kpi-server-time');
        if (elemServerTime) elemServerTime.textContent = data.server_time || new Date().toLocaleTimeString();

        // Atualiza Métricas da Base Cadastral
        const elemLotes = document.getElementById('kpi-total-lotes-gis');
        if (elemLotes) elemLotes.textContent = (kpis.total_lotes || 0).toLocaleString('pt-BR');

        const elemImoveis = document.getElementById('kpi-total-imoveis-cad');
        if (elemImoveis) elemImoveis.textContent = (kpis.total_imoveis || 0).toLocaleString('pt-BR');

        // Atualiza Imóveis Sincronizados
        const elemSync = document.getElementById('kpi-total-sincronizados');
        if (elemSync) elemSync.textContent = (kpis.total_sincronizados || 0).toLocaleString('pt-BR');

        const navSyncBadge = document.getElementById('nav-sync-count-badge');
        if (navSyncBadge) navSyncBadge.textContent = (kpis.total_sincronizados || 0).toLocaleString('pt-BR');

        // Renderiza / Atualiza Gráficos
        renderizarGraficoStatus(kpis.status_breakdown || {});
        renderizarGraficoPerfil(kpis.perfis_breakdown || {});

        // Renderiza Usuários Recentes
        renderizarUsuariosRecentes(data.recentes || []);

        if (showToastFeedback) {
            mostrarAdminToast('Indicadores e métricas atualizados com sucesso!', 'success');
        }

    } catch (err) {
        console.error('Erro ao carregar dashboard admin:', err);
        if (showToastFeedback) {
            mostrarAdminToast('Não foi possível carregar as métricas.', 'error');
        }
    }
}

// =========================================================================
// RENDERIZAÇÃO DOS GRÁFICOS (CHART.JS)
// =========================================================================
function renderizarGraficoStatus(statusData) {
    const canvas = document.getElementById('chartStatusUsuarios');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const ativos = statusData.ativo || 0;
    const inativos = statusData.inativo || 0;
    const bloqueados = statusData.bloqueado || 0;
    const total = ativos + inativos + bloqueados;

    const chartConfig = {
        type: 'doughnut',
        data: {
            labels: ['Ativos', 'Inativos', 'Bloqueados'],
            datasets: [{
                data: [ativos, inativos, bloqueados],
                backgroundColor: [
                    '#10b981', // Verde Esmeralda
                    '#64748b', // Slate Gray
                    '#f43f5e'  // Rose Red
                ],
                borderColor: '#252f36',
                borderWidth: 3,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    borderColor: '#334155',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 6,
                    callbacks: {
                        label: function(context) {
                            const val = context.raw || 0;
                            const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                            return ` ${context.label}: ${val} (${pct}%)`;
                        }
                    }
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true
            }
        }
    };

    if (adminState.chartStatus) {
        adminState.chartStatus.data.datasets[0].data = [ativos, inativos, bloqueados];
        adminState.chartStatus.update();
    } else {
        adminState.chartStatus = new Chart(ctx, chartConfig);
    }

    // Renderiza Legenda Customizada
    const legendContainer = document.getElementById('legend-status-container');
    if (legendContainer) {
        legendContainer.innerHTML = `
            <div class="custom-legend-item">
                <span class="status-legend-dot dot-active"></span>
                <span>Ativos: <strong>${ativos}</strong></span>
            </div>
            <div class="custom-legend-item">
                <span class="status-legend-dot dot-inactive"></span>
                <span>Inativos: <strong>${inativos}</strong></span>
            </div>
            <div class="custom-legend-item">
                <span class="status-legend-dot dot-blocked"></span>
                <span>Bloqueados: <strong>${bloqueados}</strong></span>
            </div>
        `;
    }
}

function renderizarGraficoPerfil(perfilData) {
    const canvas = document.getElementById('chartPerfilUsuarios');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    const adminCount = perfilData.administrador || 0;
    const editorCount = perfilData.editor || 0;
    const viewerCount = perfilData.visualizador || 0;
    const total = adminCount + editorCount + viewerCount;

    const chartConfig = {
        type: 'doughnut',
        data: {
            labels: ['Administrador', 'Editor', 'Visualizador'],
            datasets: [{
                data: [adminCount, editorCount, viewerCount],
                backgroundColor: [
                    '#3b82f6', // Azul Primário
                    '#06b6d4', // Cyan
                    '#a855f7'  // Roxo / Purple
                ],
                borderColor: '#252f36',
                borderWidth: 3,
                hoverOffset: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    borderColor: '#334155',
                    borderWidth: 1,
                    padding: 10,
                    boxPadding: 6,
                    callbacks: {
                        label: function(context) {
                            const val = context.raw || 0;
                            const pct = total > 0 ? ((val / total) * 100).toFixed(1) : 0;
                            return ` ${context.label}: ${val} (${pct}%)`;
                        }
                    }
                }
            },
            animation: {
                animateScale: true,
                animateRotate: true
            }
        }
    };

    if (adminState.chartPerfil) {
        adminState.chartPerfil.data.datasets[0].data = [adminCount, editorCount, viewerCount];
        adminState.chartPerfil.update();
    } else {
        adminState.chartPerfil = new Chart(ctx, chartConfig);
    }

    // Renderiza Legenda Customizada
    const legendContainer = document.getElementById('legend-perfil-container');
    if (legendContainer) {
        legendContainer.innerHTML = `
            <div class="custom-legend-item">
                <span class="status-legend-dot" style="background:#3b82f6;"></span>
                <span>Admin: <strong>${adminCount}</strong></span>
            </div>
            <div class="custom-legend-item">
                <span class="status-legend-dot" style="background:#06b6d4;"></span>
                <span>Editor: <strong>${editorCount}</strong></span>
            </div>
            <div class="custom-legend-item">
                <span class="status-legend-dot" style="background:#a855f7;"></span>
                <span>Visualizador: <strong>${viewerCount}</strong></span>
            </div>
        `;
    }
}

function renderizarUsuariosRecentes(recentes) {
    const container = document.getElementById('recent-users-list');
    if (!container) return;

    if (!recentes || recentes.length === 0) {
        container.innerHTML = '<div class="loading-state-sm">Nenhum acesso registrado recentemente.</div>';
        return;
    }

    let html = '';
    recentes.forEach(u => {
        const initial = (u.nome || 'U').charAt(0).toUpperCase();
        const perfilClass = `badge-perfil-${(u.perfil || 'visualizador').toLowerCase()}`;
        const statusDotClass = `status-dot-${(u.status || 'ativo').toLowerCase()}`;
        
        let formattedDate = 'Nunca acessou';
        if (u.ultimo_login) {
            const d = new Date(u.ultimo_login);
            formattedDate = d.toLocaleDateString('pt-BR') + ' às ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
        }

        html += `
            <div class="recent-user-item">
                <div class="recent-user-left">
                    <div class="recent-user-avatar">${initial}</div>
                    <div class="recent-user-info">
                        <span class="recent-user-name">${escapeHtml(u.nome)}</span>
                        <span class="recent-user-login">@${escapeHtml(u.login)}</span>
                    </div>
                </div>
                <div class="recent-user-right">
                    <span class="badge-perfil ${perfilClass}">${capitalize(u.perfil)}</span>
                    <span class="badge-status badge-status-${u.status}">
                        <span class="status-dot-sm ${statusDotClass}"></span>
                        ${capitalize(u.status)}
                    </span>
                    <span class="recent-user-time" title="Último login">${formattedDate}</span>
                </div>
            </div>
        `;
    });

    container.innerHTML = html;
}

// =========================================================================
// 2. GESTÃO DE USUÁRIOS (TABELA & FILTROS)
// =========================================================================
let searchDebounceTimeout = null;

function handleUserSearchInput() {
    const input = document.getElementById('user-search-input');
    const clearBtn = document.getElementById('btn-clear-user-search');
    
    if (input.value.trim() !== '') {
        if (clearBtn) clearBtn.style.display = 'block';
    } else {
        if (clearBtn) clearBtn.style.display = 'none';
    }

    clearTimeout(searchDebounceTimeout);
    searchDebounceTimeout = setTimeout(() => {
        adminState.search = input.value.trim();
        adminState.currentPage = 1;
        carregarListaUsuarios();
    }, 300);
}

function clearUserSearch() {
    const input = document.getElementById('user-search-input');
    const clearBtn = document.getElementById('btn-clear-user-search');
    if (input) input.value = '';
    if (clearBtn) clearBtn.style.display = 'none';
    adminState.search = '';
    adminState.currentPage = 1;
    carregarListaUsuarios();
}

function aplicarFiltrosUsuarios() {
    const selPerfil = document.getElementById('filter-user-perfil');
    const selStatus = document.getElementById('filter-user-status');
    
    adminState.perfil = selPerfil ? selPerfil.value : 'todos';
    adminState.status = selStatus ? selStatus.value : 'todos';
    adminState.currentPage = 1;

    carregarListaUsuarios();
}

async function carregarListaUsuarios() {
    const tbody = document.getElementById('admin-users-tbody');
    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="8" class="table-loading-cell">
                <div class="spinner-sm"></div>
                <span>Carregando usuários do sistema...</span>
            </td>
        </tr>
    `;

    try {
        const params = new URLSearchParams({
            action: 'listar',
            page: adminState.currentPage,
            limit: adminState.limit,
            search: adminState.search,
            perfil: adminState.perfil,
            status: adminState.status
        });

        const res = await fetch(`api/admin_usuarios.php?${params.toString()}`);
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Falha ao buscar usuários.');
        }

        adminState.usersCache = data.usuarios || [];
        adminState.totalRecords = data.total_records || 0;
        adminState.totalPages = data.total_pages || 1;
        adminState.currentPage = data.page || 1;

        renderizarTabelaUsuarios(adminState.usersCache);
        atualizarPaginacaoUsuarios();

    } catch (err) {
        console.error('Erro ao carregar usuários:', err);
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="table-loading-cell" style="color: #f43f5e;">
                    Ocorreu um erro ao carregar os dados de usuários.
                </td>
            </tr>
        `;
    }
}

function renderizarTabelaUsuarios(usuarios) {
    const tbody = document.getElementById('admin-users-tbody');
    if (!tbody) return;

    if (!usuarios || usuarios.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" class="table-loading-cell">
                    Nenhum usuário encontrado com os filtros selecionados.
                </td>
            </tr>
        `;
        return;
    }

    let html = '';
    usuarios.forEach(u => {
        const initial = (u.nome || 'U').charAt(0).toUpperCase();
        const perfilClass = `badge-perfil-${(u.perfil || 'visualizador').toLowerCase()}`;
        const statusClass = `badge-status-${(u.status || 'ativo').toLowerCase()}`;
        const dotClass = `status-dot-${(u.status || 'ativo').toLowerCase()}`;

        const ultimoLogin = u.ultimo_login 
            ? new Date(u.ultimo_login).toLocaleDateString('pt-BR') + ' ' + new Date(u.ultimo_login).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
            : '<span style="color: var(--admin-text-muted); font-style: italic;">Nunca</span>';

        const criadoEm = u.criado_em
            ? new Date(u.criado_em).toLocaleDateString('pt-BR')
            : '-';

        // Determina botão de toggle status rápido
        let toggleIcon = '⏸️';
        let toggleTitle = 'Inativar Usuário';
        let nextStatus = 'inativo';

        if (u.status === 'inativo') {
            toggleIcon = '▶️';
            toggleTitle = 'Ativar Usuário';
            nextStatus = 'ativo';
        } else if (u.status === 'bloqueado') {
            toggleIcon = '🔓';
            toggleTitle = 'Desbloquear Usuário';
            nextStatus = 'ativo';
        }

        html += `
            <tr id="user-row-${u.id}">
                <td style="color: var(--admin-text-muted); font-family: monospace;">#${u.id}</td>
                <td>
                    <div class="user-col-wrapper">
                        <div class="table-user-avatar">${initial}</div>
                        <div class="table-user-info-group">
                            <span class="table-user-name">${escapeHtml(u.nome)}</span>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="table-user-login" style="font-family: monospace; font-size: 0.82rem; color: #60a5fa;">@${escapeHtml(u.login)}</span>
                </td>
                <td>
                    <span class="badge-perfil ${perfilClass}">${capitalize(u.perfil)}</span>
                </td>
                <td>
                    <span class="badge-status ${statusClass}">
                        <span class="status-dot-sm ${dotClass}"></span>
                        ${capitalize(u.status)}
                    </span>
                </td>
                <td style="font-size: 0.78rem;">${ultimoLogin}</td>
                <td style="font-size: 0.78rem; color: var(--admin-text-muted);">${criadoEm}</td>
                <td>
                    <div class="table-actions-group">
                        <button type="button" class="btn-table-action btn-table-edit" title="Editar Usuário" onclick="abrirModalEditarUsuario(${u.id})">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <button type="button" class="btn-table-action btn-table-pwd" title="Redefinir Senha" onclick="abrirModalRedefinirSenha(${u.id}, '${escapeJs(u.nome)}')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </button>
                        <button type="button" class="btn-table-action btn-table-toggle" title="${toggleTitle}" onclick="alternarStatusRapido(${u.id}, '${nextStatus}')">
                            <span style="font-size: 11px;">${toggleIcon}</span>
                        </button>
                        <button type="button" class="btn-table-action btn-table-delete" title="Excluir Usuário" onclick="excluirUsuario(${u.id}, '${escapeJs(u.nome)}')">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });

    tbody.innerHTML = html;
}

function atualizarPaginacaoUsuarios() {
    const info = document.getElementById('table-user-info');
    const indicator = document.getElementById('user-page-indicator');
    const btnPrev = document.getElementById('btn-user-page-prev');
    const btnNext = document.getElementById('btn-user-page-next');

    const start = adminState.totalRecords === 0 ? 0 : (adminState.currentPage - 1) * adminState.limit + 1;
    const end = Math.min(adminState.currentPage * adminState.limit, adminState.totalRecords);

    if (info) {
        info.textContent = `Mostrando ${start} a ${end} de ${adminState.totalRecords} usuário(s)`;
    }

    if (indicator) {
        indicator.textContent = `Página ${adminState.currentPage} de ${adminState.totalPages}`;
    }

    if (btnPrev) btnPrev.disabled = (adminState.currentPage <= 1);
    if (btnNext) btnNext.disabled = (adminState.currentPage >= adminState.totalPages);
}

function changeUserPage(direction) {
    const newPage = adminState.currentPage + direction;
    if (newPage >= 1 && newPage <= adminState.totalPages) {
        adminState.currentPage = newPage;
        carregarListaUsuarios();
    }
}

// =========================================================================
// 3. MODAIS & OPERAÇÕES CRUD DE USUÁRIOS
// =========================================================================
function abrirModalUsuarioNovo() {
    const modal = document.getElementById('modal-usuario');
    const title = document.getElementById('modal-usuario-title');
    const subtitle = document.getElementById('modal-usuario-subtitle');
    const form = document.getElementById('form-usuario');

    form.reset();
    document.getElementById('form-user-id').value = '';
    
    // Configura campos de senha para novo cadastro
    const reqStar = document.getElementById('req-senha-star');
    const hint = document.getElementById('label-senha-hint');
    const pwdInput = document.getElementById('form-user-senha');
    
    if (reqStar) reqStar.style.display = 'inline';
    if (hint) hint.style.display = 'none';
    if (pwdInput) {
        pwdInput.required = true;
        pwdInput.placeholder = 'Digite uma senha segura...';
    }

    title.textContent = 'Cadastrar Novo Usuário';
    subtitle.textContent = 'Preencha as informações para registrar o acesso ao GeoPortal';

    modal.style.display = 'flex';
    document.getElementById('form-user-nome').focus();
}

function abrirModalEditarUsuario(id) {
    const user = adminState.usersCache.find(u => Number(u.id) === Number(id));
    if (!user) return;

    const modal = document.getElementById('modal-usuario');
    const title = document.getElementById('modal-usuario-title');
    const subtitle = document.getElementById('modal-usuario-subtitle');
    
    document.getElementById('form-user-id').value = user.id;
    document.getElementById('form-user-nome').value = user.nome;
    document.getElementById('form-user-login').value = user.login;
    document.getElementById('form-user-perfil').value = user.perfil.toLowerCase();
    document.getElementById('form-user-status').value = user.status.toLowerCase();

    // Configura campos de senha para edição (opcional)
    const reqStar = document.getElementById('req-senha-star');
    const hint = document.getElementById('label-senha-hint');
    const pwdInput = document.getElementById('form-user-senha');
    
    if (reqStar) reqStar.style.display = 'none';
    if (hint) hint.style.display = 'inline';
    if (pwdInput) {
        pwdInput.value = '';
        pwdInput.required = false;
        pwdInput.placeholder = 'Deixe em branco para não alterar';
    }

    title.textContent = `Editar Usuário #${user.id}`;
    subtitle.textContent = `Alterando dados de ${user.nome}`;

    modal.style.display = 'flex';
}

function fecharModalUsuario() {
    const modal = document.getElementById('modal-usuario');
    if (modal) modal.style.display = 'none';
}

async function handleSalvarUsuario(e) {
    e.preventDefault();

    const id = document.getElementById('form-user-id').value;
    const nome = document.getElementById('form-user-nome').value.trim();
    const login = document.getElementById('form-user-login').value.trim();
    const perfil = document.getElementById('form-user-perfil').value;
    const status = document.getElementById('form-user-status').value;
    const senha = document.getElementById('form-user-senha').value;
    const btnSubmit = document.getElementById('btn-submit-usuario');

    if (!nome || !login) {
        mostrarAdminToast('Por favor, preencha o nome e login.', 'error');
        return;
    }

    if (!id && !senha) {
        mostrarAdminToast('A senha inicial é obrigatória.', 'error');
        return;
    }

    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span>Salvando...</span>';

    try {
        const formData = new FormData();
        formData.append('action', 'salvar');
        if (id) formData.append('id', id);
        formData.append('nome', nome);
        formData.append('login', login);
        formData.append('perfil', perfil);
        formData.append('status', status);
        if (senha) formData.append('senha', senha);

        const res = await fetch('api/admin_usuarios.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Erro ao salvar usuário.');
        }

        mostrarAdminToast(data.message || 'Usuário salvo com sucesso!', 'success');
        fecharModalUsuario();
        carregarListaUsuarios();
        carregarAdminDashboard(false);

    } catch (err) {
        console.error('Erro ao salvar:', err);
        mostrarAdminToast(err.message || 'Erro ao salvar usuário.', 'error');
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<span>Salvar Usuário</span>';
    }
}

// =========================================================================
// 4. REDEFINIR SENHA
// =========================================================================
function abrirModalRedefinirSenha(id, nome) {
    const modal = document.getElementById('modal-redefinir-senha');
    document.getElementById('form-pwd-user-id').value = id;
    document.getElementById('modal-pwd-user-name').textContent = `Usuário: ${nome} (#${id})`;
    document.getElementById('form-pwd-nova').value = '';
    document.getElementById('generated-pwd-preview').textContent = '';

    modal.style.display = 'flex';
    document.getElementById('form-pwd-nova').focus();
}

function fecharModalSenha() {
    const modal = document.getElementById('modal-redefinir-senha');
    if (modal) modal.style.display = 'none';
}

function gerarSenhaAleatoria() {
    const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789!@#%';
    let pwd = '';
    for (let i = 0; i < 8; i++) {
        pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    const input = document.getElementById('form-pwd-nova');
    const preview = document.getElementById('generated-pwd-preview');
    if (input) input.value = pwd;
    if (preview) preview.textContent = `Senha: ${pwd}`;
}

async function handleRedefinirSenha(e) {
    e.preventDefault();

    const id = document.getElementById('form-pwd-user-id').value;
    const senha = document.getElementById('form-pwd-nova').value.trim();
    const btnSubmit = document.getElementById('btn-submit-senha');

    if (!id || !senha || senha.length < 4) {
        mostrarAdminToast('A senha deve ter no mínimo 4 caracteres.', 'error');
        return;
    }

    btnSubmit.disabled = true;
    btnSubmit.innerHTML = '<span>Atualizando...</span>';

    try {
        const formData = new FormData();
        formData.append('action', 'redefinir_senha');
        formData.append('id', id);
        formData.append('senha', senha);

        const res = await fetch('api/admin_usuarios.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Erro ao redefinir senha.');
        }

        mostrarAdminToast('Senha atualizada com sucesso!', 'success');
        fecharModalSenha();

    } catch (err) {
        console.error('Erro ao redefinir senha:', err);
        mostrarAdminToast(err.message || 'Erro ao atualizar senha.', 'error');
    } finally {
        btnSubmit.disabled = false;
        btnSubmit.innerHTML = '<span>Atualizar Senha</span>';
    }
}

// =========================================================================
// 5. ALTERNAR STATUS RÁPIDO & EXCLUSÃO
// =========================================================================
async function alternarStatusRapido(id, novoStatus) {
    try {
        const formData = new FormData();
        formData.append('action', 'alterar_status');
        formData.append('id', id);
        formData.append('status', novoStatus);

        const res = await fetch('api/admin_usuarios.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Falha ao alterar status.');
        }

        mostrarAdminToast(data.message, 'success');
        carregarListaUsuarios();
        carregarAdminDashboard(false);

    } catch (err) {
        mostrarAdminToast(err.message || 'Erro ao alterar status.', 'error');
    }
}

async function excluirUsuario(id, nome) {
    const confirmDelete = confirm(`Deseja realmente excluir o usuário "${nome}" (#${id})?\nEsta ação não poderá ser desfeita.`);
    if (!confirmDelete) return;

    try {
        const formData = new FormData();
        formData.append('action', 'excluir');
        formData.append('id', id);

        const res = await fetch('api/admin_usuarios.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Falha ao excluir usuário.');
        }

        mostrarAdminToast('Usuário excluído com sucesso!', 'success');
        carregarListaUsuarios();
        carregarAdminDashboard(false);

    } catch (err) {
        mostrarAdminToast(err.message || 'Erro ao excluir usuário.', 'error');
    }
}

// =========================================================================
// 6. EXPORTAR CSV
// =========================================================================
function exportarUsuariosCSV() {
    const params = new URLSearchParams({
        action: 'export_csv',
        search: adminState.search,
        perfil: adminState.perfil,
        status: adminState.status
    });
    window.location.href = `api/admin_usuarios.php?${params.toString()}`;
}

// =========================================================================
// UTILITÁRIOS & TOASTS
// =========================================================================
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
}

function mostrarAdminToast(msg, type = 'info') {
    const container = document.getElementById('admin-toast-container');
    if (!container) return;

    const toast = document.createElement('div');
    toast.className = `admin-toast admin-toast-${type}`;

    let icon = 'ℹ️';
    if (type === 'success') icon = '✅';
    if (type === 'error') icon = '⚠️';

    toast.innerHTML = `<span>${icon}</span> <span>${escapeHtml(msg)}</span>`;
    container.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeJs(str) {
    if (!str) return '';
    return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function capitalize(str) {
    if (!str) return '';
    return str.charAt(0).toUpperCase() + str.slice(1).toLowerCase();
}

// =========================================================================
// MÓDULO: SINCRONIZAÇÃO DA API TRIBUTÁRIA OFICIAL (PMDM)
// =========================================================================

let adminSyncState = {
    currentPage: 1,
    limit: 15,
    search: '',
    situacao: 'todos',
    totalPages: 1,
    totalRecords: 0,
    isSyncing: false
};

function initSincronizacaoDatas() {
    const inputInicio = document.getElementById('sync-data-inicial');
    const inputFim = document.getElementById('sync-data-final');
    if (!inputInicio || !inputFim) return;

    const hoje = new Date();
    const anoAtual = hoje.getFullYear();
    const mes = String(hoje.getMonth() + 1).padStart(2, '0');
    const dia = String(hoje.getDate()).padStart(2, '0');

    inputFim.value = `${anoAtual}-${mes}-${dia}`;
    inputInicio.value = '2020-01-01'; // Período amplo por padrão para cobrir os 14.077 registros da base
}

function definirPeriodoSync(tipo) {
    const inputInicio = document.getElementById('sync-data-inicial');
    const inputFim = document.getElementById('sync-data-final');
    if (!inputInicio || !inputFim) return;

    const hoje = new Date();
    const formatDate = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dia = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${dia}`;
    };

    inputFim.value = formatDate(hoje);

    if (tipo === '30d') {
        const past = new Date();
        past.setDate(hoje.getDate() - 30);
        inputInicio.value = formatDate(past);
    } else if (tipo === '90d') {
        const past = new Date();
        past.setDate(hoje.getDate() - 90);
        inputInicio.value = formatDate(past);
    } else if (tipo === '2026') {
        inputInicio.value = '2026-01-01';
        inputFim.value = formatDate(hoje);
    } else if (tipo === '2025') {
        inputInicio.value = '2025-01-01';
        inputFim.value = '2025-12-31';
    } else if (tipo === 'todos') {
        inputInicio.value = '2020-01-01';
        inputFim.value = formatDate(hoje);
    }
}

async function testarConexaoApi() {
    const btn = document.getElementById('btn-test-api');
    const statusText = document.getElementById('sync-api-status');
    if (btn) btn.disabled = true;
    if (statusText) statusText.innerHTML = `<span style="color: #38bdf8;">⏳ Conectando...</span>`;

    try {
        const res = await fetch('api/admin_sincronizacao.php?action=test_conexao');
        const data = await res.json();

        if (data.success) {
            if (statusText) {
                statusText.innerHTML = `<span class="text-emerald">● Online (${data.tempo_ms}ms)</span>`;
            }
            mostrarAdminToast(`API Oficial Online! Latência: ${data.tempo_ms}ms`, 'success');
        } else {
            throw new Error(data.message || 'Falha ao conectar com a API Tributária.');
        }
    } catch (err) {
        if (statusText) {
            statusText.innerHTML = `<span style="color: #f43f5e;">✕ Erro de Conexão</span>`;
        }
        mostrarAdminToast(err.message || 'Erro ao conectar à API Tributária.', 'error');
    } finally {
        if (btn) btn.disabled = false;
    }
}

async function carregarSincronizacaoDashboard(showToastFeedback = false) {
    try {
        const res = await fetch('api/admin_sincronizacao.php?action=stats');
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar estatísticas de sincronização.');
        }

        const stats = data.stats;
        adminSyncState.stats = stats;
        const total = stats.total_sincronizados || 0;
        const totalBase = stats.total_legados || 13992;
        const proxPag = stats.proxima_pagina_estimada || 1;

        document.getElementById('sync-kpi-total').textContent = total.toLocaleString('pt-BR');
        document.getElementById('sync-kpi-percentual').textContent = `${stats.percentual || 0}%`;
        document.getElementById('sync-kpi-ultima').textContent = stats.ultima_atualizacao || 'Nunca';

        const elemBase = document.getElementById('sync-kpi-total-base');
        if (elemBase) elemBase.textContent = totalBase.toLocaleString('pt-BR');

        // Referência de data para sincronização incremental
        const elemIncRef = document.getElementById('sync-incremental-data-ref');
        if (elemIncRef && stats.sugestao_data_incremental) {
            const dParts = stats.sugestao_data_incremental.split('-');
            if (dParts.length === 3) {
                elemIncRef.textContent = `${dParts[2]}/${dParts[1]}/${dParts[0]}`;
            } else {
                elemIncRef.textContent = stats.sugestao_data_incremental;
            }
        }

        // Dica dinâmica de continuidade baseada no tamanho de página selecionado
        atualizarDicaContinuidade();

        // Atualiza contadores de situação
        let ativos = 0;
        let baixados = 0;
        if (Array.isArray(stats.situacoes)) {
            stats.situacoes.forEach(s => {
                const sit = (s.situacao || '').toLowerCase();
                const cnt = parseInt(s.total, 10) || 0;
                if (sit.includes('ativo')) ativos += cnt;
                else if (sit.includes('baixado') || sit.includes('inativo')) baixados += cnt;
            });
        }
        document.getElementById('sync-kpi-ativos').textContent = ativos.toLocaleString('pt-BR');
        document.getElementById('sync-kpi-baixados').textContent = baixados.toLocaleString('pt-BR');

        // Atualiza badge da sidebar
        const navSyncBadge = document.getElementById('nav-sync-count-badge');
        if (navSyncBadge) navSyncBadge.textContent = total.toLocaleString('pt-BR');

        if (showToastFeedback) {
            mostrarAdminToast('Métricas de sincronização atualizadas!', 'success');
        }
    } catch (err) {
        console.error('Erro ao carregar stats da sincronização:', err);
        if (showToastFeedback) {
            mostrarAdminToast(err.message || 'Erro ao atualizar dados.', 'error');
        }
    }
}

// Sincronização Incremental: baixa apenas as alterações e novos cadastros recentes
async function executarSincronizacaoIncremental() {
    if (adminSyncState.isSyncing) return;

    const btn = document.getElementById('btn-sync-incremental');
    const btnText = document.getElementById('btn-sync-incremental-text');
    const progressBox = document.getElementById('sync-progress-box');
    const progressMsg = document.getElementById('sync-progress-msg');
    const progressBadge = document.getElementById('sync-progress-badge');
    const progressBarFill = document.getElementById('sync-progress-bar-fill');

    // Data de início: usa a data da última alteração no banco ou 30 dias atrás
    const dataInicial = adminSyncState.stats?.sugestao_data_incremental || '2026-09-01';
    const hoje = new Date();
    const dataFinal = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-${String(hoje.getDate()).padStart(2, '0')}`;

    adminSyncState.isSyncing = true;
    if (btn) btn.disabled = true;
    if (btnText) btnText.textContent = 'Verificando Mudanças na API...';
    if (progressBox) progressBox.style.display = 'flex';
    if (progressMsg) progressMsg.textContent = `Consultando recadastramentos e alterações a partir de ${dataInicial}...`;
    if (progressBadge) {
        progressBadge.className = 'sync-status-badge badge-sync-ativo';
        progressBadge.textContent = 'Incremental';
    }
    if (progressBarFill) {
        progressBarFill.className = 'sync-progress-bar-fill indeterminate';
    }

    try {
        const formData = new FormData();
        formData.append('data_inicial', dataInicial);
        formData.append('data_final', dataFinal);
        formData.append('start_page', '1');
        formData.append('max_pages', '0'); // busca todas as páginas do período recente (normalmente 1 a 5 páginas)
        formData.append('page_size', '100');

        const res = await fetch('api/admin_sincronizacao.php?action=sincronizar', {
            method: 'POST',
            body: formData
        });

        const data = await res.json();
        if (!data.success) {
            throw new Error(data.message || 'Falha ao sincronizar alterações.');
        }

        const totalSalvos = data.detalhes?.total_salvos || 0;
        const tempo = data.detalhes?.tempo_segundos || 0;
        const novoTotal = data.detalhes?.novo_total_banco || 0;

        if (progressMsg) {
            progressMsg.innerHTML = `<strong>Sincronização incremental concluída!</strong> ${totalSalvos} imóvel(is) novo(s) ou atualizado(s) em ${tempo}s. Total na base: <strong>${novoTotal.toLocaleString('pt-BR')}</strong>.`;
        }
        if (progressBadge) {
            progressBadge.className = 'sync-status-badge badge-sync-ativo';
            progressBadge.textContent = 'Sucesso';
        }
        if (progressBarFill) {
            progressBarFill.className = 'sync-progress-bar-fill';
            progressBarFill.style.width = '100%';
        }

        mostrarAdminToast(`Sincronização incremental finalizada! ${totalSalvos} imóvel(is) atualizado(s).`, 'success');

        carregarSincronizacaoDashboard(false);
        carregarAdminDashboard(false);
        carregarListaSincronizados();

        setTimeout(() => {
            if (progressBox) progressBox.style.display = 'none';
        }, 8000);

    } catch (err) {
        console.error('Erro na sincronização incremental:', err);
        if (progressMsg) {
            progressMsg.innerHTML = `<span style="color: #f43f5e;">Falha incremental: ${escapeHtml(err.message)}</span>`;
        }
        if (progressBadge) {
            progressBadge.className = 'sync-status-badge badge-sync-baixado';
            progressBadge.textContent = 'Erro';
        }
        mostrarAdminToast(err.message || 'Erro ao sincronizar.', 'error');
    } finally {
        adminSyncState.isSyncing = false;
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = '⚡ Sincronizar Apenas Mudanças';
    }
}

// Carga em lote com execução encadeada (Chunks anti-timeout)
async function handleExecutarSync(e) {
    e.preventDefault();
    if (adminSyncState.isSyncing) return;

    const dataInicial = document.getElementById('sync-data-inicial').value;
    const dataFinal = document.getElementById('sync-data-final').value;
    let startPage = parseInt(document.getElementById('sync-start-page')?.value, 10) || 1;
    const maxPagesParam = parseInt(document.getElementById('sync-max-pages')?.value, 10);
    const pageSize = parseInt(document.getElementById('sync-page-size')?.value, 10) || 100;

    if (!dataInicial || !dataFinal) {
        mostrarAdminToast('Selecione as datas inicial e final para sincronizar.', 'error');
        return;
    }

    const btn = document.getElementById('btn-submit-sync');
    const btnText = document.getElementById('btn-submit-sync-text');
    const progressBox = document.getElementById('sync-progress-box');
    const progressMsg = document.getElementById('sync-progress-msg');
    const progressBadge = document.getElementById('sync-progress-badge');
    const progressBarFill = document.getElementById('sync-progress-bar-fill');

    adminSyncState.isSyncing = true;
    if (btn) btn.disabled = true;
    if (btnText) btnText.textContent = 'Sincronizando em Lotes...';
    if (progressBox) progressBox.style.display = 'flex';
    if (progressBadge) {
        progressBadge.className = 'sync-status-badge badge-sync-ativo';
        progressBadge.textContent = 'Processando Lotes';
    }
    if (progressBarFill) {
        progressBarFill.className = 'sync-progress-bar-fill indeterminate';
    }

    let totalSalvosAcumulado = 0;
    let paginasRestantes = (maxPagesParam === 0 || isNaN(maxPagesParam)) ? 999999 : maxPagesParam;
    let paginaAtual = startPage;
    let totalPagesApi = null;
    let chunkCount = 0;

    try {
        // Divide em chunks seguros de 5 páginas por requisição para NUNCA dar timeout no Apache e dar feedback rápido
        const CHUNK_SIZE = 5;

        while (paginasRestantes > 0) {
            chunkCount++;
            const pagesThisChunk = Math.min(CHUNK_SIZE, paginasRestantes);

            if (progressMsg) {
                const totalText = totalPagesApi ? `de ${totalPagesApi}` : '';
                progressMsg.innerHTML = `Processando Lote ${chunkCount} (Páginas ${paginaAtual} a ${paginaAtual + pagesThisChunk - 1} ${totalText})...`;
            }

            const formData = new FormData();
            formData.append('data_inicial', dataInicial);
            formData.append('data_final', dataFinal);
            formData.append('start_page', paginaAtual);
            formData.append('max_pages', pagesThisChunk);
            formData.append('page_size', pageSize);

            const res = await fetch('api/admin_sincronizacao.php?action=sincronizar', {
                method: 'POST',
                body: formData
            });

            const data = await res.json();

            if (!data.success) {
                throw new Error(data.message || 'Falha no lote de sincronização.');
            }

            const salvos = data.detalhes?.total_salvos || 0;
            totalSalvosAcumulado += salvos;
            totalPagesApi = data.detalhes?.total_pages_api || totalPagesApi;
            const lastPageProcessed = data.detalhes?.last_page || (paginaAtual + pagesThisChunk - 1);

            // Atualiza inputs e métricas intermediárias
            paginaAtual = lastPageProcessed + 1;
            const inputStartPage = document.getElementById('sync-start-page');
            if (inputStartPage) inputStartPage.value = paginaAtual;

            if (paginasRestantes !== 999999) {
                paginasRestantes -= pagesThisChunk;
            }

            // Atualiza barra de progresso visual em tempo real
            if (progressBarFill && totalPagesApi) {
                const pct = Math.min(100, Math.round((lastPageProcessed / totalPagesApi) * 100));
                progressBarFill.className = 'sync-progress-bar-fill';
                progressBarFill.style.width = `${pct}%`;
            }

            // Atualiza dados na tela após cada chunk
            carregarSincronizacaoDashboard(false);

            // Se atingiu o fim das páginas na API ou nenhum item retornou
            if (salvos === 0 || (totalPagesApi && lastPageProcessed >= totalPagesApi)) {
                break;
            }
        }

        if (progressMsg) {
            progressMsg.innerHTML = `<strong>Carga concluída!</strong> ${totalSalvosAcumulado.toLocaleString('pt-BR')} imóvel(is) processado(s) com sucesso.`;
        }
        if (progressBadge) {
            progressBadge.className = 'sync-status-badge badge-sync-ativo';
            progressBadge.textContent = 'Sucesso';
        }
        if (progressBarFill) {
            progressBarFill.className = 'sync-progress-bar-fill';
            progressBarFill.style.width = '100%';
        }

        mostrarAdminToast(`Sincronização finalizada! ${totalSalvosAcumulado} imóveis processados.`, 'success');

        carregarSincronizacaoDashboard(false);
        carregarAdminDashboard(false);
        carregarListaSincronizados();

        setTimeout(() => {
            if (progressBox) progressBox.style.display = 'none';
        }, 8000);

    } catch (err) {
        console.error('Erro na sincronização em lote:', err);
        if (progressMsg) {
            progressMsg.innerHTML = `<span style="color: #f43f5e;">Interrompido: ${escapeHtml(err.message)}</span>`;
        }
        if (progressBadge) {
            progressBadge.className = 'sync-status-badge badge-sync-baixado';
            progressBadge.textContent = 'Erro';
        }
        mostrarAdminToast(err.message || 'Erro durante a sincronização.', 'error');
    } finally {
        adminSyncState.isSyncing = false;
        if (btn) btn.disabled = false;
        if (btnText) btnText.textContent = 'Sincronizar Lote';
    }
}

async function carregarListaSincronizados() {
    const tbody = document.getElementById('admin-sync-tbody');
    if (!tbody) return;

    tbody.innerHTML = `
        <tr>
            <td colspan="9" class="table-loading-cell">
                <div class="spinner-sm"></div>
                <span>Carregando imóveis sincronizados...</span>
            </td>
        </tr>
    `;

    try {
        const params = new URLSearchParams({
            action: 'listar',
            page: adminSyncState.currentPage,
            limit: adminSyncState.limit,
            search: adminSyncState.search,
            situacao: adminSyncState.situacao
        });

        const res = await fetch(`api/admin_sincronizacao.php?${params.toString()}`);
        const result = await res.json();

        if (!result.success) {
            throw new Error(result.message || 'Falha ao carregar lista de registros.');
        }

        const data = result.data || [];
        const pag = result.pagination || {};

        adminSyncState.totalRecords = pag.total_records || 0;
        adminSyncState.totalPages = pag.total_pages || 1;
        adminSyncState.currentPage = pag.current_page || 1;

        // Atualiza controles de paginação
        const info = document.getElementById('table-sync-info');
        if (info) {
            const start = adminSyncState.totalRecords > 0 ? (adminSyncState.currentPage - 1) * adminSyncState.limit + 1 : 0;
            const end = Math.min(adminSyncState.currentPage * adminSyncState.limit, adminSyncState.totalRecords);
            info.textContent = `Mostrando ${start} a ${end} de ${adminSyncState.totalRecords} imóveis oficiais`;
        }

        const indicator = document.getElementById('sync-page-indicator');
        if (indicator) {
            indicator.textContent = `Página ${adminSyncState.currentPage} de ${adminSyncState.totalPages}`;
        }

        const prevBtn = document.getElementById('btn-sync-page-prev');
        const nextBtn = document.getElementById('btn-sync-page-next');
        if (prevBtn) prevBtn.disabled = adminSyncState.currentPage <= 1;
        if (nextBtn) nextBtn.disabled = adminSyncState.currentPage >= adminSyncState.totalPages;

        if (data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="table-empty-cell">
                        <span>Nenhum imóvel oficial encontrado na base para os filtros selecionados.</span>
                    </td>
                </tr>
            `;
            return;
        }

        let html = '';
        data.forEach(item => {
            const insc = escapeHtml(item.insc_munic || '-');
            const codigo = escapeHtml(item.codigo || '-');
            const contribuinte = escapeHtml(item.contribuinte || 'Não Informado');
            const logradouro = escapeHtml(item.logradouro ? `${item.logradouro}, ${item.numero || 'S/N'}` : '-');
            const bairro = escapeHtml(item.bairro || '-');
            const area = item.area_terreno ? `${escapeHtml(item.area_terreno)} m²` : '-';
            const situacao = item.situacao || 'Ativo';
            const badgeClass = (situacao.toLowerCase() === 'baixado') ? 'badge-sync-baixado' : 'badge-sync-ativo';
            const atualizado = item.atualizado_em_formatado || '-';

            html += `
                <tr>
                    <td>
                        <span class="field-badge-insc" style="cursor:pointer;" onclick="abrirModalDetalhesSync('${escapeJs(item.insc_munic)}')">
                            ${insc}
                        </span>
                    </td>
                    <td><span class="field-badge-id">#${codigo}</span></td>
                    <td><strong>${contribuinte}</strong></td>
                    <td>${logradouro}</td>
                    <td>${bairro}</td>
                    <td>${area}</td>
                    <td style="text-align: center;">
                        <span class="sync-status-badge ${badgeClass}">${escapeHtml(situacao)}</span>
                    </td>
                    <td style="font-size: 0.78rem; color: var(--admin-text-muted);">${atualizado}</td>
                    <td style="text-align: center;">
                        <button type="button" class="btn-table-action" onclick="abrirModalDetalhesSync('${escapeJs(item.insc_munic)}')" title="Ver Estrutura BCI e JSON Bruto da API">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="16" x2="12" y2="12"></line>
                                <line x1="12" y1="8" x2="12.01" y2="8"></line>
                            </svg>
                            <span>Ver BCI</span>
                        </button>
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

    } catch (err) {
        console.error('Erro ao listar imóveis sincronizados:', err);
        tbody.innerHTML = `
            <tr>
                <td colspan="9" class="table-empty-cell" style="color: #f43f5e;">
                    <span>Erro ao carregar dados: ${escapeHtml(err.message)}</span>
                </td>
            </tr>
        `;
    }
}

let syncSearchTimeout = null;
function handleSyncSearchInput() {
    const input = document.getElementById('sync-search-input');
    const clearBtn = document.getElementById('btn-clear-sync-search');
    const val = input.value.trim();

    if (clearBtn) clearBtn.style.display = val ? 'inline-block' : 'none';

    clearTimeout(syncSearchTimeout);
    syncSearchTimeout = setTimeout(() => {
        adminSyncState.search = val;
        adminSyncState.currentPage = 1;
        carregarListaSincronizados();
    }, 350);
}

function clearSyncSearch() {
    const input = document.getElementById('sync-search-input');
    const clearBtn = document.getElementById('btn-clear-sync-search');
    if (input) input.value = '';
    if (clearBtn) clearBtn.style.display = 'none';

    adminSyncState.search = '';
    adminSyncState.currentPage = 1;
    carregarListaSincronizados();
}

function aplicarFiltrosSync() {
    const situacaoSelect = document.getElementById('filter-sync-situacao');
    adminSyncState.situacao = situacaoSelect ? situacaoSelect.value : 'todos';
    adminSyncState.currentPage = 1;
    carregarListaSincronizados();
}

function changeSyncPage(delta) {
    const newPage = adminSyncState.currentPage + delta;
    if (newPage >= 1 && newPage <= adminSyncState.totalPages) {
        adminSyncState.currentPage = newPage;
        carregarListaSincronizados();
    }
}

async function abrirModalDetalhesSync(insc) {
    const modal = document.getElementById('modal-detalhes-sync');
    const title = document.getElementById('modal-sync-title');
    const subtitle = document.getElementById('modal-sync-subtitle');
    const bciBox = document.getElementById('modal-sync-bci-content');
    const rawBox = document.getElementById('modal-sync-raw-content');

    if (title) title.textContent = 'Imóvel Oficial da API';
    if (subtitle) subtitle.textContent = `Inscrição: ${insc}`;
    if (bciBox) bciBox.textContent = 'Carregando dados estruturados do BCI...';
    if (rawBox) rawBox.textContent = 'Carregando payload JSON bruto retornado da API...';
    if (modal) modal.style.display = 'flex';

    alternarTabModalSync('bci');

    try {
        const res = await fetch(`api/admin_sincronizacao.php?action=detalhes&insc=${encodeURIComponent(insc)}`);
        const data = await res.json();

        if (!data.success) {
            throw new Error(data.message || 'Erro ao carregar detalhes do imóvel.');
        }

        if (title) title.textContent = `Imóvel: ${data.dados_bci?.NOMEDOCONTRIBUINTE || insc}`;
        if (subtitle) subtitle.textContent = `Inscrição Municipal: ${data.insc_munic} • Código Reduzido: #${data.codigo || '-'}`;

        if (bciBox) {
            bciBox.textContent = JSON.stringify(data.dados_bci || {}, null, 2);
        }
        if (rawBox) {
            rawBox.textContent = JSON.stringify(data.dados_api_raw || {}, null, 2);
        }

    } catch (err) {
        if (bciBox) bciBox.textContent = `Erro: ${err.message}`;
        if (rawBox) rawBox.textContent = `Erro: ${err.message}`;
    }
}

function fecharModalDetalhesSync() {
    const modal = document.getElementById('modal-detalhes-sync');
    if (modal) modal.style.display = 'none';
}

function alternarTabModalSync(tab) {
    const btnBci = document.getElementById('tab-btn-sync-bci');
    const btnRaw = document.getElementById('tab-btn-sync-raw');
    const tabBci = document.getElementById('modal-sync-tab-bci');
    const tabRaw = document.getElementById('modal-sync-tab-raw');

    if (tab === 'bci') {
        if (btnBci) btnBci.classList.add('active');
        if (btnRaw) btnRaw.classList.remove('active');
        if (tabBci) tabBci.style.display = 'block';
        if (tabRaw) tabRaw.style.display = 'none';
    } else {
        if (btnBci) btnBci.classList.remove('active');
        if (btnRaw) btnRaw.classList.add('active');
        if (tabBci) tabBci.style.display = 'none';
        if (tabRaw) tabRaw.style.display = 'block';
    }
}

function calcularProximaPaginaEstimada() {
    const total = adminSyncState.stats?.total_sincronizados || 0;
    const sizeSelect = document.getElementById('sync-page-size');
    const pageSize = sizeSelect ? (parseInt(sizeSelect.value, 10) || 100) : 100;
    // Se temos 6.359 salvos e pageSize é 100: Math.floor(6359 / 100) + 1 = 64
    // Se pageSize é 50: Math.floor(6359 / 50) + 1 = 128
    return Math.max(1, Math.floor(total / pageSize) + 1);
}

function atualizarDicaContinuidade() {
    const stats = adminSyncState.stats;
    if (!stats) return;
    const total = stats.total_sincronizados || 0;
    const totalBase = stats.total_legados || 13992;
    const proxPag = calcularProximaPaginaEstimada();
    const pageSize = parseInt(document.getElementById('sync-page-size')?.value || '100', 10);

    const elemDica = document.getElementById('sync-dica-texto');
    if (elemDica) {
        elemDica.innerHTML = `Base atual: <strong>${total.toLocaleString('pt-BR')}</strong> de <strong>${totalBase.toLocaleString('pt-BR')}</strong> lotes sincronizados (${stats.percentual}%). Próxima página para continuar (${pageSize}/pág): <strong>Página ${proxPag}</strong>.`;
    }

    const btnCont = document.getElementById('btn-continuar-sync');
    if (btnCont) {
        btnCont.textContent = `Continuar de onde parou (Pág. ${proxPag}) →`;
    }
}

function configurarContinuacaoSync() {
    const proxPag = calcularProximaPaginaEstimada();
    const startInput = document.getElementById('sync-start-page');
    const maxSelect = document.getElementById('sync-max-pages');
    const sizeSelect = document.getElementById('sync-page-size');
    const pageSize = sizeSelect ? (parseInt(sizeSelect.value, 10) || 100) : 100;

    // Define o período amplo (2020 a hoje) para cobrir os 14.077 imóveis da base histórica
    const inputIni = document.getElementById('sync-data-inicial');
    const inputFim = document.getElementById('sync-data-final');
    if (inputIni) inputIni.value = '2020-01-01';
    if (inputFim) {
        const hoje = new Date();
        inputFim.value = `${hoje.getFullYear()}-${String(hoje.getMonth() + 1).padStart(2, '0')}-${String(hoje.getDate()).padStart(2, '0')}`;
    }

    if (startInput) startInput.value = proxPag;
    if (maxSelect) maxSelect.value = '0'; // Todas as páginas restantes

    atualizarDicaContinuidade();
    mostrarAdminToast(`Configurado para continuar a partir da Página ${proxPag} (${pageSize}/pág) até o fim!`, 'info');
}



