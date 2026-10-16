// Definição dos Basemaps disponíveis no GeoPortal SAAE Guanhães
const basemaps = {
    "Open Street Map": L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 24,
        maxNativeZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }),
    "Google Hibrído": L.tileLayer("https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}", {
        maxZoom: 24,
        maxNativeZoom: 20,
        attribution: '<a href="https://www.google.at/permissions/geoguidelines/attr-guide.html">Map data Google</a>',
    }),
    "Google Maps": L.tileLayer("http://{s}.google.com/vt/lyrs=m&x={x}&y={y}&z={z}", {
        maxZoom: 24,
        maxNativeZoom: 20,
        subdomains: ["mt0", "mt1", "mt2", "mt3"],
        attribution: '&copy; <a href="https://www.google.com/permissions/geoguidelines/">Google Maps</a>'
    }),
    "Google Satélite": L.tileLayer("http://{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}", {
        maxZoom: 24,
        maxNativeZoom: 20,
        subdomains: ["mt0", "mt1", "mt2", "mt3"],
        attribution: '&copy; <a href="https://www.google.com/permissions/geoguidelines/">Google Maps</a>'
    }),
    "OSM Light": L.tileLayer("https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}@2x.png", {
        maxZoom: 24,
        maxNativeZoom: 19,
        attribution: '&copy; <a href="http://osm.org/copyright">OpenStreetMap</a> contributors',
    }),
    "Esri World Imagery": L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 24,
        maxNativeZoom: 19,
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community'
    }),
    "Sem Mapa Base (Fundo Escuro)": L.layerGroup(),
    "Sem Mapa Base (Fundo Claro)": L.layerGroup(),
    "Sem Mapa Base (Grade CAD)": L.layerGroup()
};
