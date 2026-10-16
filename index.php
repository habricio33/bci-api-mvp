<?php
session_start();
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php?error=auth");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GeoPortal Módulo BCI</title>

    <!-- Google Fonts (Inter) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Leaflet.js CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />

    <!-- Estilos customizados da aplicação -->
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

    <div class="app-container">
        <!-- Top Header -->
        <?php include_once 'includes/header.php'; ?>

        <!-- Main Workspace -->
        <div class="workspace">
            <!-- Sidebar -->
            <?php include_once 'includes/sidebar.php'; ?>

            <!-- Loading Indicator -->
            <div id="loading" class="loading-indicator">
                <div class="spinner"></div>
                <span>Carregando dados...</span>
            </div>

            <!-- Map Container -->
            <div id="map">
                <!-- Botões Flutuantes do Mapa -->
                <?php include_once 'includes/map-controls.php'; ?>

                <!-- Painéis Flutuantes e Análises Espaciais -->
                <?php include_once 'includes/panels/panel-buffer.php'; ?>
                <?php include_once 'includes/panels/panel-divergencia.php'; ?>
                <?php include_once 'includes/panels/panel-comunidade.php'; ?>
                <?php include_once 'includes/panels/panel-filtro-terreno.php'; ?>
                <?php include_once 'includes/panels/panel-tabela-dados.php'; ?>
            </div>
        </div>
    </div>

    <!-- Modais do Sistema -->
    <?php include_once 'includes/modals/modal-identify.php'; ?>
    <?php include_once 'includes/modals/modal-bci.php'; ?>
    <?php include_once 'includes/modals/modal-crud-redes.php'; ?>

    <!-- Leaflet.js JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <!-- Proj4js para transformações cartográficas precisas (WGS84 <-> SIRGAS 2000 / UTM 23S) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/proj4js/2.9.2/proj4.js"></script>

    <!-- Scripts da aplicação e Núcleo do Mapa -->
    <script src="js/basemaps.js"></script>
    <script src="js/map.js"></script>

    <!-- Módulos Funcionais do Sistema -->
    <script src="js/modules/module-filtros-tematicos.js"></script>
    <script src="js/modules/module-identify-bci.js"></script>
    <script src="js/modules/module-crud-redes.js"></script>
    <script src="js/modules/module-search.js"></script>
    <script src="js/modules/module-tabela-dados.js"></script>

    <!-- Módulos de Ferramentas Espaciais -->
    <script src="js/tools/tool-buffer.js"></script>
    <script src="js/tools/tool-divergencia.js"></script>
    <script src="js/tools/tool-comunidade.js"></script>
    <script src="js/tools/tool-filtro-terrenos.js"></script>
</body>

</html>