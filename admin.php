<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php?error=auth");
    exit;
}
$currentUser = $_SESSION['usuario'];
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo - GeoPortal Módulo BCI</title>

    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Chart.js para gráficos interativos -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- Estilos da aplicação -->
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
</head>

<body class="admin-body">

    <!-- Container Principal -->
    <div class="admin-app-container">

        <!-- Header Superior do Painel Administrativo -->
        <header class="admin-header">
            <div class="admin-header-left">
                <!-- Botão de Toggle da Sidebar -->
                <button type="button" id="toggle-admin-sidebar" class="btn-toggle-admin-sidebar" title="Recolher / Expandir Menu Lateral" onclick="toggleAdminSidebar()">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="3" y1="12" x2="21" y2="12"></line>
                        <line x1="3" y1="6" x2="21" y2="6"></line>
                        <line x1="3" y1="18" x2="21" y2="18"></line>
                    </svg>
                </button>

                <!-- Identidade / Logo -->
                <div class="logo-area">
                    <div class="logo-icon admin-logo-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="7" height="7"></rect>
                            <rect x="14" y="3" width="7" height="7"></rect>
                            <rect x="14" y="14" width="7" height="7"></rect>
                            <rect x="3" y="14" width="7" height="7"></rect>
                        </svg>
                    </div>
                    <div>
                        <div class="logo-title">GeoPortal <span>Módulo BCI</span></div>
                        <div class="admin-header-subtitle">Painel de Controle Administrativo</div>
                    </div>
                </div>
            </div>

            <!-- Centro: Indicador da Seção Ativa / Breadcrumb -->
            <div class="admin-header-center">
                <div class="admin-breadcrumb-badge">
                    <span class="pulse-dot-blue"></span>
                    <span id="breadcrumb-section-title">Visão Geral</span>
                </div>
            </div>

            <!-- Ações Direita (Voltar ao Mapa, Usuário, Logout) -->
            <div class="admin-header-right">
                <a href="index.php" class="btn-return-map" title="Retornar ao Mapa Interativo do GeoPortal">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon>
                        <line x1="8" y1="2" x2="8" y2="18"></line>
                        <line x1="16" y1="6" x2="16" y2="22"></line>
                    </svg>
                    <span>Voltar ao Mapa</span>
                </a>

                <div class="user-status admin-user-pill">
                    <div class="user-avatar-sm">
                        <?php echo strtoupper(substr($currentUser['nome'] ?? 'A', 0, 1)); ?>
                    </div>
                    <div class="user-info-text">
                        <span class="user-name-label"><strong><?php echo htmlspecialchars($currentUser['nome']); ?></strong></span>
                        <span class="user-badge admin-badge-<?php echo strtolower($currentUser['perfil']); ?>">
                            <?php echo ucfirst(htmlspecialchars($currentUser['perfil'])); ?>
                        </span>
                    </div>
                    <a href="logout.php" class="btn-logout" title="Sair da Conta">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </a>
                </div>
            </div>
        </header>

        <!-- Workspace Principal (Sidebar Esquerda + Área de Conteúdo) -->
        <div class="admin-workspace">

            <!-- SIDEBAR COLAPSÁVEL À ESQUERDA -->
            <aside id="admin-sidebar" class="admin-sidebar">
                <div class="admin-sidebar-inner">
                    
                    <!-- Bloco de Navegação Principal -->
                    <div class="sidebar-nav-section">
                        <div class="sidebar-section-label">
                            <span>Administração</span>
                        </div>

                        <nav class="admin-sidebar-menu">
                            <!-- Menu 1: Visão Geral -->
                            <button type="button" class="admin-nav-item active" data-view="visao-geral" onclick="switchAdminView('visao-geral')" data-tooltip="Visão Geral">
                                <div class="nav-item-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
                                        <polyline points="9 22 9 12 15 12 15 22"></polyline>
                                    </svg>
                                </div>
                                <span class="nav-item-label">Visão Geral</span>
                            </button>

                            <!-- Menu 2: Gestão de Usuários -->
                            <button type="button" class="admin-nav-item" data-view="usuarios" onclick="switchAdminView('usuarios')" data-tooltip="Gestão de Usuários">
                                <div class="nav-item-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                                <span class="nav-item-label">Gestão de Usuários</span>
                                <span id="nav-user-count-badge" class="nav-sidebar-badge">0</span>
                            </button>

                            <!-- Menu 3: Sincronização API Tributária -->
                            <button type="button" class="admin-nav-item" data-view="sincronizacao" onclick="switchAdminView('sincronizacao')" data-tooltip="Sincronização API">
                                <div class="nav-item-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
                                    </svg>
                                </div>
                                <span class="nav-item-label">Sincronização API</span>
                                <span id="nav-sync-count-badge" class="nav-sidebar-badge badge-emerald">0</span>
                            </button>
                        </nav>
                    </div>

                    <!-- Bloco de Atalhos de Sistema -->
                    <div class="sidebar-nav-section">
                        <div class="sidebar-section-label">
                            <span>Navegação</span>
                        </div>

                        <nav class="admin-sidebar-menu">
                            <a href="index.php" class="admin-nav-item nav-link-map" data-tooltip="Voltar ao Mapa">
                                <div class="nav-item-icon">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polygon points="1 6 1 22 8 18 16 22 23 18 23 2 16 6 8 2 1 6"></polygon>
                                        <line x1="8" y1="2" x2="8" y2="18"></line>
                                        <line x1="16" y1="6" x2="16" y2="22"></line>
                                    </svg>
                                </div>
                                <span class="nav-item-label">Mapa Interativo</span>
                            </a>
                        </nav>
                    </div>

                </div>

                <!-- Rodapé da Sidebar com Botão de Recolhimento -->
                <div class="admin-sidebar-footer">
                    <button type="button" id="btn-sidebar-collapse-toggle" class="btn-sidebar-footer-collapse" title="Recolher / Expandir Menu Lateral" onclick="toggleAdminSidebar()">
                        <div class="nav-item-icon">
                            <svg class="icon-collapse-arrow" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="15 18 9 12 15 6"></polyline>
                            </svg>
                        </div>
                        <span class="nav-item-label">Recolher Menu</span>
                    </button>
                </div>
            </aside>

            <!-- CONTEÚDO PRINCIPAL -->
            <main class="admin-main-content">

                <!-- ============================================================= -->
                <!-- 1. ABA: VISÃO GERAL (DASHBOARD) -->
                <!-- ============================================================= -->
                <section id="view-visao-geral" class="admin-view-section active">
                    
                    <!-- Hero Banner do Sistema -->
                    <div class="admin-hero-banner">
                        <div class="hero-left">
                            <div class="hero-tag">
                                <span class="pulse-dot-green"></span>
                                <span>Sistema Ativo e Operacional</span>
                            </div>
                            <h1>Visão Geral da Administração</h1>
                            <p>Controle gerencial de contas, perfis de acesso, métricas de atividade e monitoramento da base de dados do GeoPortal BCI.</p>
                        </div>
                        <div class="hero-right">
                            <div class="hero-stat-box">
                                <span class="stat-label">Conexão PostGIS</span>
                                <span class="stat-value text-emerald">● Online</span>
                            </div>
                            <div class="hero-stat-box">
                                <span class="stat-label">Última Atualização</span>
                                <span class="stat-value" id="kpi-server-time">Carregando...</span>
                            </div>
                            <button type="button" class="btn-hero-refresh" onclick="carregarAdminDashboard(true)" title="Recarregar Indicadores">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 4v6h-6"></path>
                                    <path d="M1 20v-6h6"></path>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                </svg>
                                <span>Atualizar</span>
                            </button>
                        </div>
                    </div>

                    <!-- 4 CARDS DE KPIS PRINCIPAIS -->
                    <div class="admin-kpi-grid">
                        <!-- Card 1: Total de Usuários -->
                        <div class="admin-kpi-card kpi-card-blue">
                            <div class="kpi-card-header">
                                <span class="kpi-card-title">Total de Usuários</span>
                                <div class="kpi-icon-wrapper bg-blue-glow">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="9" cy="7" r="4"></circle>
                                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="kpi-card-body">
                                <div class="kpi-main-number" id="kpi-total-usuarios">-</div>
                                <div class="kpi-meta-text">
                                    <span class="kpi-badge kpi-badge-blue">Base Geral</span>
                                    <span>Cadastros em <code>public.usuario</code></span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Usuários Ativos -->
                        <div class="admin-kpi-card kpi-card-emerald">
                            <div class="kpi-card-header">
                                <span class="kpi-card-title">Usuários Ativos</span>
                                <div class="kpi-icon-wrapper bg-emerald-glow">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                        <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                    </svg>
                                </div>
                            </div>
                            <div class="kpi-card-body">
                                <div class="kpi-main-number text-emerald" id="kpi-usuarios-ativos">-</div>
                                <div class="kpi-meta-text">
                                    <span class="kpi-badge kpi-badge-emerald" id="kpi-percent-ativos">-% do total</span>
                                    <span>Acesso liberado ao GeoPortal</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Usuários Inativos / Bloqueados -->
                        <div class="admin-kpi-card kpi-card-amber">
                            <div class="kpi-card-header">
                                <span class="kpi-card-title">Inativos & Bloqueados</span>
                                <div class="kpi-icon-wrapper bg-amber-glow">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="4.93" y1="4.93" x2="19.07" y2="19.07"></line>
                                    </svg>
                                </div>
                            </div>
                            <div class="kpi-card-body">
                                <div class="kpi-main-number text-amber" id="kpi-usuarios-inativos">-</div>
                                <div class="kpi-meta-text">
                                    <span class="kpi-badge kpi-badge-amber" id="kpi-badge-bloqueados">0 bloqueados</span>
                                    <span>Contas sem acesso</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 4: Total de Perfis -->
                        <div class="admin-kpi-card kpi-card-purple">
                            <div class="kpi-card-header">
                                <span class="kpi-card-title">Perfis de Acesso</span>
                                <div class="kpi-icon-wrapper bg-purple-glow">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                                    </svg>
                                </div>
                            </div>
                            <div class="kpi-card-body">
                                <div class="kpi-main-number text-purple" id="kpi-total-perfis">3</div>
                                <div class="kpi-meta-text">
                                    <span class="kpi-badge kpi-badge-purple">Admin • Editor • Viewer</span>
                                    <span>Níveis de permissão</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- SEÇÃO DE GRÁFICOS E INDICADORES VISUAIS -->
                    <div class="admin-charts-grid">
                        
                        <!-- Gráfico 1: Usuários por Status (Donut Chart) -->
                        <div class="admin-chart-card">
                            <div class="chart-card-header">
                                <div>
                                    <h3 class="chart-title">Distribuição de Usuários por Status</h3>
                                    <p class="chart-subtitle">Proporção entre contas ativas, inativas e bloqueadas</p>
                                </div>
                                <div class="chart-header-tag">
                                    <span class="status-legend-dot dot-active"></span> Ativos
                                    <span class="status-legend-dot dot-inactive"></span> Inativos
                                    <span class="status-legend-dot dot-blocked"></span> Bloqueados
                                </div>
                            </div>
                            <div class="chart-card-body">
                                <div class="chart-canvas-container">
                                    <canvas id="chartStatusUsuarios"></canvas>
                                </div>
                                <div class="chart-custom-legend" id="legend-status-container">
                                    <!-- Preenchido dinamicamente via JS -->
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico 2: Usuários por Perfil de Acesso (Bar/Donut) -->
                        <div class="admin-chart-card">
                            <div class="chart-card-header">
                                <div>
                                    <h3 class="chart-title">Distribuição por Perfil de Acesso</h3>
                                    <p class="chart-subtitle">Permissões e papéis atribuídos aos usuários</p>
                                </div>
                                <div class="chart-header-tag">
                                    <span class="badge-role-admin">Admin</span>
                                    <span class="badge-role-editor">Editor</span>
                                    <span class="badge-role-viewer">Viewer</span>
                                </div>
                            </div>
                            <div class="chart-card-body">
                                <div class="chart-canvas-container">
                                    <canvas id="chartPerfilUsuarios"></canvas>
                                </div>
                                <div class="chart-custom-legend" id="legend-perfil-container">
                                    <!-- Preenchido dinamicamente via JS -->
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- SEÇÃO INFERIOR: ÚLTIMOS ACESSOS & RESUMO CADASTRAL -->
                    <div class="admin-bottom-grid">
                        
                        <!-- Card de Atividades Recentes / Usuários Recentes -->
                        <div class="admin-subcard">
                            <div class="subcard-header">
                                <div class="subcard-title-group">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                    <h3>Últimos Acessos ao Sistema</h3>
                                </div>
                                <button type="button" class="btn-text-action" onclick="switchAdminView('usuarios')">Ver Todos &rarr;</button>
                            </div>
                            <div class="subcard-body">
                                <div class="recent-users-list" id="recent-users-list">
                                    <div class="loading-state-sm">Carregando usuários recentes...</div>
                                </div>
                            </div>
                        </div>

                        <!-- Card de Resumo Técnico do BCI -->
                        <div class="admin-subcard">
                            <div class="subcard-header">
                                <div class="subcard-title-group">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                                        <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                                        <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                                    </svg>
                                    <h3>Ambiente e Base Geocadastral</h3>
                                </div>
                                <span class="badge-env-docker">Docker Container</span>
                            </div>
                            <div class="subcard-body">
                                <div class="system-metrics-list">
                                    <div class="metric-row">
                                        <span class="metric-name">Lotes Cartografados (PostGIS)</span>
                                        <span class="metric-val" id="kpi-total-lotes-gis">-</span>
                                    </div>
                                    <div class="metric-row">
                                        <span class="metric-name">Cadastros Tributários (GP_IMOVEL)</span>
                                        <span class="metric-val" id="kpi-total-imoveis-cad">-</span>
                                    </div>
                                    <div class="metric-row">
                                        <span class="metric-name">Oficial Sincronizado (API PMDM)</span>
                                        <span class="metric-val text-emerald" id="kpi-total-sincronizados" style="cursor:pointer;" onclick="switchAdminView('sincronizacao')" title="Clique para gerenciar a sincronização">-</span>
                                    </div>
                                    <div class="metric-row">
                                        <span class="metric-name">Servidor de Mapas GeoServer</span>
                                        <span class="metric-val text-emerald">Porta 8606 (Ativo)</span>
                                    </div>
                                    <div class="metric-row">
                                        <span class="metric-name">Ambiente PHP / Apache</span>
                                        <span class="metric-val">PHP 8.2 • Apache 2.4</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </section>


                <!-- ============================================================= -->
                <!-- 2. ABA: GESTÃO DE USUÁRIOS (TABELA & CRUD) -->
                <!-- ============================================================= -->
                <section id="view-usuarios" class="admin-view-section">
                    
                    <!-- Barra de Ações e Filtros de Usuários -->
                    <div class="admin-toolbar-card">
                        <div class="toolbar-left">
                            <div class="search-input-wrapper">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                                <input type="text" id="user-search-input" placeholder="Buscar por nome ou login..." oninput="handleUserSearchInput()">
                                <button type="button" id="btn-clear-user-search" class="btn-clear-search" onclick="clearUserSearch()" style="display:none;">✕</button>
                            </div>

                            <!-- Filtro de Perfil -->
                            <div class="filter-select-wrapper">
                                <label for="filter-user-perfil">Perfil:</label>
                                <select id="filter-user-perfil" onchange="aplicarFiltrosUsuarios()">
                                    <option value="todos">Todos os Perfis</option>
                                    <option value="administrador">Administrador</option>
                                    <option value="editor">Editor</option>
                                    <option value="visualizador">Visualizador</option>
                                </select>
                            </div>

                            <!-- Filtro de Status -->
                            <div class="filter-select-wrapper">
                                <label for="filter-user-status">Status:</label>
                                <select id="filter-user-status" onchange="aplicarFiltrosUsuarios()">
                                    <option value="todos">Todos os Status</option>
                                    <option value="ativo">Ativo</option>
                                    <option value="inativo">Inativo</option>
                                    <option value="bloqueado">Bloqueado</option>
                                </select>
                            </div>
                        </div>

                        <div class="toolbar-right">
                            <button type="button" class="btn-admin-action btn-admin-primary" onclick="abrirModalUsuarioNovo()">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                <span>Novo Usuário</span>
                            </button>
                            <button type="button" class="btn-admin-action btn-admin-secondary" onclick="exportarUsuariosCSV()" title="Exportar dados da tabela em CSV">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                                    <polyline points="7 10 12 15 17 10"></polyline>
                                    <line x1="12" y1="15" x2="12" y2="3"></line>
                                </svg>
                                <span>Exportar CSV</span>
                            </button>
                            <button type="button" class="btn-admin-icon" onclick="carregarListaUsuarios()" title="Recarregar Tabela">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 4v6h-6"></path>
                                    <path d="M1 20v-6h6"></path>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Tabela de Gestão de Usuários -->
                    <div class="admin-table-card">
                        <div class="admin-table-responsive">
                            <table class="admin-table" id="admin-users-table">
                                <thead>
                                    <tr>
                                        <th style="width: 60px;">ID</th>
                                        <th>Nome do Usuário</th>
                                        <th>Login de Acesso</th>
                                        <th>Perfil</th>
                                        <th>Status</th>
                                        <th>Último Login</th>
                                        <th>Cadastrado em</th>
                                        <th style="text-align: center; width: 150px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="admin-users-tbody">
                                    <tr>
                                        <td colspan="8" class="table-loading-cell">
                                            <div class="spinner-sm"></div>
                                            <span>Carregando usuários do sistema...</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginação da Tabela -->
                        <div class="admin-table-footer">
                            <div class="table-records-info" id="table-user-info">
                                Mostrando 0 de 0 registros
                            </div>
                            <div class="admin-pagination">
                                <button type="button" class="btn-page-ctrl" id="btn-user-page-prev" onclick="changeUserPage(-1)" disabled>
                                    &larr; Anterior
                                </button>
                                <span class="page-indicator" id="user-page-indicator">Página 1 de 1</span>
                                <button type="button" class="btn-page-ctrl" id="btn-user-page-next" onclick="changeUserPage(1)" disabled>
                                    Próxima &rarr;
                                </button>
                            </div>
                        </div>
                    </div>

                </section>


                <!-- ============================================================= -->
                <!-- 3. ABA: SINCRONIZAÇÃO DA BASE COM A API TRIBUTÁRIA OFICIAL -->
                <!-- ============================================================= -->
                <section id="view-sincronizacao" class="admin-view-section">
                    
                    <!-- Hero Banner da Sincronização -->
                    <div class="admin-hero-banner">
                        <div class="hero-left">
                            <div class="hero-tag">
                                <span class="pulse-dot-green"></span>
                                <span>Integração Oficial PMDM • API REST</span>
                            </div>
                            <h1>Sincronização da Base Tributária</h1>
                            <p>Baixe dados oficiais atualizados de recadastramentos imobiliários da Prefeitura de Domingos Martins para a base local do GeoPortal.</p>
                        </div>
                        <div class="hero-right">
                            <div class="hero-stat-box">
                                <span class="stat-label">Conexão API PMDM</span>
                                <span class="stat-value text-emerald" id="sync-api-status">● Não testada</span>
                            </div>
                            <button type="button" class="btn-hero-refresh" id="btn-test-api" onclick="testarConexaoApi()" title="Testar Autenticação e Conexão com API">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                                </svg>
                                <span>Testar Conexão</span>
                            </button>
                            <button type="button" class="btn-hero-refresh" onclick="carregarSincronizacaoDashboard(true)" title="Atualizar Métricas de Sincronização">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 4v6h-6"></path>
                                    <path d="M1 20v-6h6"></path>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                </svg>
                                <span>Atualizar</span>
                            </button>
                        </div>
                    </div>

                    <!-- 4 CARDS DE MÉTRICAS DA SINCRONIZAÇÃO -->
                    <div class="admin-kpi-grid">
                        <!-- Card 1: Total Sincronizados -->
                        <div class="admin-kpi-card kpi-card-emerald">
                            <div class="kpi-card-header">
                                <span class="kpi-card-title">Oficial Sincronizado</span>
                                <div class="kpi-icon-wrapper bg-emerald-glow">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"></polyline>
                                    </svg>
                                </div>
                            </div>
                            <div class="kpi-card-body">
                                <div class="kpi-main-number" id="sync-kpi-total">-</div>
                                <div class="kpi-meta-text">
                                    <span class="kpi-badge kpi-badge-emerald" id="sync-kpi-percentual">0%</span>
                                    <span>da base de <strong id="sync-kpi-total-base">13.992</strong> lotes</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Imóveis Ativos -->
                        <div class="admin-kpi-card kpi-card-blue">
                            <div class="kpi-card-header">
                                <span class="kpi-card-title">Imóveis Ativos</span>
                                <div class="kpi-icon-wrapper bg-blue-glow">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                    </svg>
                                </div>
                            </div>
                            <div class="kpi-card-body">
                                <div class="kpi-main-number" id="sync-kpi-ativos">-</div>
                                <div class="kpi-meta-text">
                                    <span class="kpi-badge kpi-badge-blue">API Oficial</span>
                                    <span>Situação "Ativo"</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Imóveis Baixados / Outros -->
                        <div class="admin-kpi-card kpi-card-rose">
                            <div class="kpi-card-header">
                                <span class="kpi-card-title">Baixados / Inativos</span>
                                <div class="kpi-icon-wrapper bg-rose-glow">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <line x1="15" y1="9" x2="9" y2="15"></line>
                                        <line x1="9" y1="9" x2="15" y2="15"></line>
                                    </svg>
                                </div>
                            </div>
                            <div class="kpi-card-body">
                                <div class="kpi-main-number" id="sync-kpi-baixados">-</div>
                                <div class="kpi-meta-text">
                                    <span class="kpi-badge kpi-badge-rose">Desativados</span>
                                    <span>Situação "Baixado"</span>
                                </div>
                            </div>
                        </div>

                        <!-- Card 4: Última Sincronização -->
                        <div class="admin-kpi-card kpi-card-purple">
                            <div class="kpi-card-header">
                                <span class="kpi-card-title">Última Execução</span>
                                <div class="kpi-icon-wrapper bg-purple-glow">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <circle cx="12" cy="12" r="10"></circle>
                                        <polyline points="12 6 12 12 16 14"></polyline>
                                    </svg>
                                </div>
                            </div>
                            <div class="kpi-card-body">
                                <div class="kpi-main-number" style="font-size: 1.15rem; line-height: 1.4;" id="sync-kpi-ultima">-</div>
                                <div class="kpi-meta-text">
                                    <span class="kpi-badge kpi-badge-purple">Automático</span>
                                    <span>Timestamp no banco</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 1: MODO INCREMENTAL (RECOMENDADO PARA O DIA A DIA) -->
                    <div class="sync-ctrl-card" style="border-left: 4px solid var(--admin-emerald); background: linear-gradient(135deg, rgba(16, 185, 129, 0.08), var(--admin-surface)); margin-bottom: 20px;">
                        <div class="subcard-header" style="flex-wrap: wrap; gap: 14px; margin-bottom: 0;">
                            <div class="subcard-title-group" style="flex: 1; min-width: 280px;">
                                <div style="background: var(--admin-emerald-bg); border: 1px solid rgba(16, 185, 129, 0.3); border-radius: 10px; width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--admin-emerald);">
                                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                                    </svg>
                                </div>
                                <div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <h3 style="color: #fff; font-size: 1.05rem; margin: 0;">Sincronização Incremental (Apenas Mudanças)</h3>
                                        <span class="sync-status-badge badge-sync-ativo">Recomendado</span>
                                    </div>
                                    <p style="font-size: 0.8rem; color: var(--admin-text-muted); margin-top: 4px; line-height: 1.4;">
                                        Ideal para o dia a dia em produção. Consulta exclusivamente imóveis novos ou alterados na API da Prefeitura desde a última sincronização (<strong id="sync-incremental-data-ref" class="text-emerald">detectando...</strong>). Conclui em poucos segundos sem reprocessar toda a base!
                                    </p>
                                </div>
                            </div>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <button type="button" id="btn-sync-incremental" class="btn-admin-action btn-admin-primary" style="background: linear-gradient(135deg, #10b981, #059669); border-color: #059669; padding: 10px 20px; font-weight: 700;" onclick="executarSincronizacaoIncremental()">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="23 4 23 10 17 10"></polyline>
                                        <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                                    </svg>
                                    <span id="btn-sync-incremental-text">⚡ Sincronizar Apenas Mudanças</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- CARD 2: CARGA EM LOTE / IMPLANTAÇÃO HISTÓRICA -->
                    <div class="sync-ctrl-card">
                        <div class="subcard-header" style="margin-bottom: 12px;">
                            <div class="subcard-title-group">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"></path>
                                </svg>
                                <div>
                                    <h3>Carga Histórica Inicial / Período Personalizado</h3>
                                    <p style="font-size: 0.76rem; color: var(--admin-text-muted);">Utilize durante a implantação inicial para baixar grandes volumes de dados ou períodos customizados.</p>
                                </div>
                            </div>
                            <span class="badge-env-docker">Carga em Lote</span>
                        </div>

                        <!-- Atalhos Rápidos de Período -->
                        <div class="sync-quick-pills">
                            <span style="font-size: 0.75rem; color: var(--admin-text-muted); align-self: center; margin-right: 4px;">Atalhos:</span>
                            <button type="button" class="btn-quick-pill" onclick="definirPeriodoSync('30d')">Últimos 30 Dias</button>
                            <button type="button" class="btn-quick-pill" onclick="definirPeriodoSync('90d')">Últimos 90 Dias</button>
                            <button type="button" class="btn-quick-pill" onclick="definirPeriodoSync('2026')">Ano 2026</button>
                            <button type="button" class="btn-quick-pill" onclick="definirPeriodoSync('2025')">Ano 2025</button>
                            <button type="button" class="btn-quick-pill" onclick="definirPeriodoSync('todos')">Histórico Amplo (2020 - Hoje)</button>
                        </div>

                        <!-- Formulário de Filtros de Sincronização -->
                        <form id="form-sincronizacao" onsubmit="handleExecutarSync(event)">
                            <div class="sync-form-grid" style="grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)) 180px;">
                                <div class="form-group-admin">
                                    <label for="sync-data-inicial">Data Inicial <span class="required">*</span></label>
                                    <input type="date" id="sync-data-inicial" class="admin-input" required>
                                </div>
                                <div class="form-group-admin">
                                    <label for="sync-data-final">Data Final <span class="required">*</span></label>
                                    <input type="date" id="sync-data-final" class="admin-input" required>
                                </div>
                                <div class="form-group-admin">
                                    <label for="sync-start-page">Página Inicial</label>
                                    <input type="number" id="sync-start-page" class="admin-input" min="1" value="1" title="Página para iniciar a busca">
                                </div>
                                <div class="form-group-admin">
                                    <label for="sync-max-pages">Qtd. de Páginas</label>
                                    <select id="sync-max-pages" class="admin-input">
                                        <option value="10">10 Páginas (~500 a 1.000)</option>
                                        <option value="25">25 Páginas (~1.250 a 2.500)</option>
                                        <option value="50">50 Páginas (~2.500 a 5.000)</option>
                                        <option value="100">100 Páginas (~5.000 a 10.000)</option>
                                        <option value="0" selected>⚡ Todas as Páginas Restantes</option>
                                    </select>
                                </div>
                                <div class="form-group-admin">
                                    <label for="sync-page-size">Itens / Página</label>
                                    <select id="sync-page-size" class="admin-input" onchange="atualizarDicaContinuidade()">
                                        <option value="50">50 por página (Padrão)</option>
                                        <option value="100" selected>100 por página (2x Mais Rápido)</option>
                                    </select>
                                </div>
                                <div>
                                    <button type="submit" id="btn-submit-sync" class="btn-admin-action btn-admin-primary" style="width: 100%; justify-content: center; height: 40px;">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="23 4 23 10 17 10"></polyline>
                                            <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                                        </svg>
                                        <span id="btn-submit-sync-text">Sincronizar Lote</span>
                                    </button>
                                </div>
                            </div>

                            <div id="sync-dica-continuidade-wrap" style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.78rem; color: var(--admin-text-muted); margin-top: 12px; padding-top: 10px; border-top: 1px solid rgba(255, 255, 255, 0.05);">
                                <span id="sync-dica-texto">Calculando estado atual da sincronização...</span>
                                <button type="button" id="btn-continuar-sync" class="btn-text-action" onclick="configurarContinuacaoSync()" style="color: #38bdf8; font-weight: 600; font-size: 0.78rem;">
                                    Continuar de onde parou &rarr;
                                </button>
                            </div>
                        </form>

                        <!-- Box de Progresso e Status -->
                        <div id="sync-progress-box" class="sync-progress-wrap" style="display: none;">
                            <div class="sync-status-text">
                                <span id="sync-progress-msg">Conectando à API da Prefeitura...</span>
                                <span id="sync-progress-badge" class="sync-status-badge badge-sync-ativo">Processando</span>
                            </div>
                            <div class="sync-progress-bar-bg">
                                <div id="sync-progress-bar-fill" class="sync-progress-bar-fill indeterminate"></div>
                            </div>
                        </div>
                    </div>

                    <!-- TABELA DE REGISTROS SINCRONIZADOS -->
                    <div class="admin-toolbar-card">
                        <div class="toolbar-left">
                            <div class="search-input-wrapper">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="11" cy="11" r="8"></circle>
                                    <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                </svg>
                                <input type="text" id="sync-search-input" placeholder="Buscar por inscrição, código ou contribuinte..." oninput="handleSyncSearchInput()">
                                <button type="button" id="btn-clear-sync-search" class="btn-clear-search" onclick="clearSyncSearch()" style="display:none;">✕</button>
                            </div>

                            <div class="filter-select-wrapper">
                                <label for="filter-sync-situacao">Situação:</label>
                                <select id="filter-sync-situacao" onchange="aplicarFiltrosSync()">
                                    <option value="todos">Todas as Situações</option>
                                    <option value="Ativo">Ativo</option>
                                    <option value="Baixado">Baixado</option>
                                </select>
                            </div>
                        </div>

                        <div class="toolbar-right">
                            <button type="button" class="btn-admin-icon" onclick="carregarListaSincronizados()" title="Recarregar Tabela">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M23 4v6h-6"></path>
                                    <path d="M1 20v-6h6"></path>
                                    <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="admin-table-card">
                        <div class="admin-table-responsive">
                            <table class="admin-table" id="admin-sync-table">
                                <thead>
                                    <tr>
                                        <th style="width: 140px;">Inscrição Municipal</th>
                                        <th style="width: 80px;">Código</th>
                                        <th>Contribuinte</th>
                                        <th>Endereço</th>
                                        <th>Bairro</th>
                                        <th style="width: 90px;">Área Terreno</th>
                                        <th style="width: 90px; text-align: center;">Situação</th>
                                        <th style="width: 140px;">Sincronizado em</th>
                                        <th style="text-align: center; width: 100px;">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="admin-sync-tbody">
                                    <tr>
                                        <td colspan="9" class="table-loading-cell">
                                            <div class="spinner-sm"></div>
                                            <span>Carregando imóveis sincronizados...</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="admin-table-footer">
                            <div class="table-records-info" id="table-sync-info">
                                Mostrando 0 de 0 registros
                            </div>
                            <div class="admin-pagination">
                                <button type="button" class="btn-page-ctrl" id="btn-sync-page-prev" onclick="changeSyncPage(-1)" disabled>
                                    &larr; Anterior
                                </button>
                                <span class="page-indicator" id="sync-page-indicator">Página 1 de 1</span>
                                <button type="button" class="btn-page-ctrl" id="btn-sync-page-next" onclick="changeSyncPage(1)" disabled>
                                    Próxima &rarr;
                                </button>
                            </div>
                        </div>
                    </div>

                </section>

            </main>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- MODAL: NOVO / EDITAR USUÁRIO -->
    <!-- ================================================================= -->
    <div id="modal-usuario" class="admin-modal-backdrop" style="display: none;">
        <div class="admin-modal-content">
            <div class="admin-modal-header">
                <div class="modal-title-group">
                    <div class="modal-icon bg-blue-glow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </div>
                    <div>
                        <h3 id="modal-usuario-title">Cadastrar Novo Usuário</h3>
                        <p id="modal-usuario-subtitle">Preencha as informações para registrar o acesso</p>
                    </div>
                </div>
                <button type="button" class="btn-modal-close" onclick="fecharModalUsuario()">✕</button>
            </div>

            <form id="form-usuario" onsubmit="handleSalvarUsuario(event)">
                <input type="hidden" id="form-user-id" value="">

                <div class="admin-modal-body">
                    <!-- Nome Completo -->
                    <div class="form-group-admin">
                        <label for="form-user-nome">Nome Completo <span class="required">*</span></label>
                        <input type="text" id="form-user-nome" class="admin-input" placeholder="Ex: Maria Silva" required>
                    </div>

                    <!-- Login -->
                    <div class="form-group-admin">
                        <label for="form-user-login">Login de Acesso <span class="required">*</span></label>
                        <input type="text" id="form-user-login" class="admin-input" placeholder="Ex: maria.silva" required autocomplete="off">
                    </div>

                    <div class="form-row-admin">
                        <!-- Perfil -->
                        <div class="form-group-admin form-col-6">
                            <label for="form-user-perfil">Perfil de Acesso <span class="required">*</span></label>
                            <select id="form-user-perfil" class="admin-input" required>
                                <option value="visualizador">Visualizador (Somente Leitura)</option>
                                <option value="editor">Editor (Consulta & Edição)</option>
                                <option value="administrador">Administrador (Controle Total)</option>
                            </select>
                        </div>

                        <!-- Status -->
                        <div class="form-group-admin form-col-6">
                            <label for="form-user-status">Status da Conta <span class="required">*</span></label>
                            <select id="form-user-status" class="admin-input" required>
                                <option value="ativo">Ativo (Acesso Liberado)</option>
                                <option value="inativo">Inativo (Acesso Suspenso)</option>
                                <option value="bloqueado">Bloqueado (Acesso Bloqueado)</option>
                            </select>
                        </div>
                    </div>

                    <!-- Senha -->
                    <div class="form-group-admin" id="form-group-senha">
                        <label for="form-user-senha">
                            <span id="label-senha-title">Senha de Acesso</span>
                            <span class="required" id="req-senha-star">*</span>
                            <small id="label-senha-hint" style="display:none; color: var(--admin-text-muted); font-weight: normal;">(Deixe em branco para manter a atual)</small>
                        </label>
                        <div class="password-input-wrap">
                            <input type="password" id="form-user-senha" class="admin-input" placeholder="Digite a senha..." autocomplete="new-password">
                            <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility('form-user-senha')">👁️</button>
                        </div>
                    </div>
                </div>

                <div class="admin-modal-footer">
                    <button type="button" class="btn-admin-cancel" onclick="fecharModalUsuario()">Cancelar</button>
                    <button type="submit" id="btn-submit-usuario" class="btn-admin-action btn-admin-primary">
                        <span>Salvar Usuário</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ================================================================= -->
    <!-- MODAL: REDEFINIR SENHA -->
    <!-- ================================================================= -->
    <div id="modal-redefinir-senha" class="admin-modal-backdrop" style="display: none;">
        <div class="admin-modal-content modal-sm">
            <div class="admin-modal-header">
                <div class="modal-title-group">
                    <div class="modal-icon bg-amber-glow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </div>
                    <div>
                        <h3>Redefinir Senha</h3>
                        <p id="modal-pwd-user-name">Usuário: -</p>
                    </div>
                </div>
                <button type="button" class="btn-modal-close" onclick="fecharModalSenha()">✕</button>
            </div>

            <form id="form-redefinir-senha" onsubmit="handleRedefinirSenha(event)">
                <input type="hidden" id="form-pwd-user-id" value="">

                <div class="admin-modal-body">
                    <div class="form-group-admin">
                        <label for="form-pwd-nova">Nova Senha <span class="required">*</span></label>
                        <div class="password-input-wrap">
                            <input type="password" id="form-pwd-nova" class="admin-input" placeholder="Mínimo 4 caracteres" required minlength="4">
                            <button type="button" class="btn-toggle-pwd" onclick="togglePasswordVisibility('form-pwd-nova')">👁️</button>
                        </div>
                    </div>

                    <div class="pwd-quick-actions">
                        <button type="button" class="btn-quick-pwd" onclick="gerarSenhaAleatoria()">
                            ⚡ Gerar Senha Segura
                        </button>
                        <span id="generated-pwd-preview" class="pwd-preview-text"></span>
                    </div>
                </div>

                <div class="admin-modal-footer">
                    <button type="button" class="btn-admin-cancel" onclick="fecharModalSenha()">Cancelar</button>
                    <button type="submit" id="btn-submit-senha" class="btn-admin-action btn-admin-primary">
                        <span>Atualizar Senha</span>
                    </button>
                </div>
            </form>
    <!-- ================================================================= -->
    <!-- MODAL: DETALHES DO IMÓVEL SINCRONIZADO (BCI & RAW API) -->
    <!-- ================================================================= -->
    <div id="modal-detalhes-sync" class="admin-modal-backdrop" style="display: none;">
        <div class="admin-modal-content" style="max-width: 780px;">
            <div class="admin-modal-header">
                <div class="modal-title-group">
                    <div class="modal-icon bg-emerald-glow">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                    <div>
                        <h3 id="modal-sync-title">Imóvel Oficial da API</h3>
                        <p id="modal-sync-subtitle">Inscrição: -</p>
                    </div>
                </div>
                <button type="button" class="btn-modal-close" onclick="fecharModalDetalhesSync()">✕</button>
            </div>

            <div class="admin-modal-body">
                <div style="display: flex; gap: 8px; margin-bottom: 14px;">
                    <button type="button" class="btn-quick-pill active" id="tab-btn-sync-bci" onclick="alternarTabModalSync('bci')">Estrutura BCI</button>
                    <button type="button" class="btn-quick-pill" id="tab-btn-sync-raw" onclick="alternarTabModalSync('raw')">JSON Bruto da API</button>
                </div>

                <div id="modal-sync-tab-bci">
                    <div class="json-inspector-box" id="modal-sync-bci-content">Carregando...</div>
                </div>

                <div id="modal-sync-tab-raw" style="display: none;">
                    <div class="json-inspector-box" id="modal-sync-raw-content">Carregando...</div>
                </div>
            </div>

            <div class="admin-modal-footer">
                <button type="button" class="btn-admin-cancel" onclick="fecharModalDetalhesSync()">Fechar</button>
            </div>
        </div>
    </div>

    <!-- Container de Toasts / Notificações -->
    <div id="admin-toast-container" class="admin-toast-container"></div>

    <!-- Script JavaScript do Painel Administrativo -->
    <script src="js/admin.js?v=<?= time() ?>"></script>
</body>

</html>
