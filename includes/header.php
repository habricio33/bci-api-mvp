<!-- Top Header -->
<header>
    <div class="logo-area">
        <button id="toggle-sidebar" class="btn-toggle" title="Alternar Painel Lateral">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                stroke-linecap="round" stroke-linejoin="round">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
        <div class="logo-icon">G</div>
        <div class="logo-title">GeoPortal <span>Módulo BCI</span></div>
    </div>
    <div class="header-right-actions">
        <a href="admin.php" class="btn-header-admin" title="Painel Administrativo do Sistema">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <rect x="3" y="3" width="7" height="7"></rect>
                <rect x="14" y="3" width="7" height="7"></rect>
                <rect x="14" y="14" width="7" height="7"></rect>
                <rect x="3" y="14" width="7" height="7"></rect>
            </svg>
            <span>Dashboard Admin</span>
        </a>
        <div class="user-status">
            <span>Olá, <strong><?php echo htmlspecialchars($_SESSION['usuario']['nome']); ?></strong></span>
            <span class="user-badge"><?php echo ucfirst(htmlspecialchars($_SESSION['usuario']['perfil'])); ?></span>
            <a href="logout.php" class="btn-logout" title="Sair do GeoPortal">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                    <polyline points="16 17 21 12 16 7"></polyline>
                    <line x1="21" y1="12" x2="9" y2="12"></line>
                </svg>
            </a>
        </div>
    </div>
</header>
