<?php
/**
 * Map Controls / Floating Buttons Component
 * Botões flutuantes de ferramentas sobre o mapa Leaflet.
 */
?>
<!-- Botões Flutuantes da Barra de Ferramentas do Mapa -->
<button id="btn-identify" class="map-floating-btn" title="Identificar Feições (Info)">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="16" x2="12" y2="12"></line>
        <line x1="12" y1="8" x2="12.01" y2="8"></line>
    </svg>
</button>
<button id="btn-measure-dist" class="map-floating-btn" title="Medir Distância (Régua)" style="top: 180px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 20h9"></path>
        <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"></path>
    </svg>
</button>
<button id="btn-measure-area" class="map-floating-btn" title="Medir Área (Polígono)" style="top: 230px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="12 2 22 8.5 22 15.5 12 22 2 15.5 2 8.5 12 2"></polygon>
    </svg>
</button>
<button id="btn-buffer-tool" class="map-floating-btn" title="Simular Intervenção / Área de Influência (Lotes / Edificações)" style="top: 280px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <circle cx="12" cy="12" r="6"></circle>
        <circle cx="12" cy="12" r="2"></circle>
    </svg>
</button>
<button id="btn-coords-tool" class="map-floating-btn" title="Exibir Coordenadas em Tempo Real" style="top: 330px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="22" y1="12" x2="18" y2="12"></line>
        <line x1="6" y1="12" x2="2" y2="12"></line>
        <line x1="12" y1="6" x2="12" y2="2"></line>
        <line x1="12" y1="22" x2="12" y2="18"></line>
    </svg>
</button>
<button id="btn-edit-tool" class="map-floating-btn" title="Editar / Cadastrar Atributos da Rede (Água / Esgoto)" style="top: 380px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
    </svg>
</button>
<button id="btn-table-tool" class="map-floating-btn" title="Ver Tabela de Dados (Lotes / Edificações)" style="top: 430px;" onclick="toggleBottomPanel()">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 3h18v18H3zM3 9h18M3 15h18M9 3v18M15 3v18"/>
    </svg>
</button>
<button id="btn-divergencia-tool" class="map-floating-btn" title="Análise de Divergência de Áreas (GIS vs Tributário > 10%)" style="top: 480px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"></path>
        <path d="M2 16l3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1z"></path>
        <path d="M7 21h10"></path>
        <path d="M12 3v18"></path>
        <path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"></path>
    </svg>
</button>
<button id="btn-spatial-select-tool" class="map-floating-btn" title="Seleção Espacial por Comunidade (Lotes e Edificações)" style="top: 530px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="12 2 2 7 12 12 22 7 12 2"></polygon>
        <polyline points="2 17 12 22 22 17"></polyline>
        <polyline points="2 12 12 17 22 12"></polyline>
        <circle cx="12" cy="12" r="2.5" fill="currentColor"></circle>
    </svg>
</button>
<button id="btn-filtro-terreno-tool" class="map-floating-btn" title="Filtro Avançado de Terrenos (Área & Ocupação)" style="top: 580px;">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
    </svg>
</button>
