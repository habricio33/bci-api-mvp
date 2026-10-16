<?php
require_once __DIR__ . '/../config/db.php';
$db = getDBConnection();
$stmt = $db->query('SELECT "INSC_MUNIC", "NOMEDOCONTRIBUINTE", "AREA_DO_TERRENO13065" FROM public."GP_IMOVEL" WHERE "INSC_MUNIC" LIKE \'01010230605%\'');
$rows = $stmt->fetchAll();
echo "Found in GP_IMOVEL:\n";
print_r($rows);
