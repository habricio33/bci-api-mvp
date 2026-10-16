<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

echo "Creating indexes...\n";
$db->exec('CREATE INDEX IF NOT EXISTS idx_geo_lotes_inscimob2 ON geo.geo_lotes_dm (inscimob2)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_geo_lotes_inscimob ON geo.geo_lotes_dm (inscimob)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_gp_imovel_insc_munic ON public."GP_IMOVEL" ("INSC_MUNIC")');
echo "Indexes created successfully!\n";
