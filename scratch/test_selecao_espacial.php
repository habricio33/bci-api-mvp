<?php
require_once __DIR__ . '/../config/db.php';

$db = getDBConnection();

$comunidadeId = 1;

$t0 = microtime(true);
$s1 = $db->prepare("SELECT COUNT(*) FROM geo.geo_lotes_dm l WHERE l.geom && (SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id) AND ST_Intersects(l.geom, (SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id))");
$s1->execute([':id' => $comunidadeId]);
$c1 = $s1->fetchColumn();
$t1 = microtime(true) - $t0;

$t0 = microtime(true);
$s2 = $db->prepare("SELECT COUNT(*) FROM geo.geo_edificacoes_dm e WHERE e.geom && (SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id) AND ST_Intersects(e.geom, (SELECT geom FROM geo.geo_comunidades_dm WHERE id2 = :id))");
$s2->execute([':id' => $comunidadeId]);
$c2 = $s2->fetchColumn();
$t2 = microtime(true) - $t0;

echo "Lotes: $c1 in " . round($t1, 4) . "s\n";
echo "Edificações: $c2 in " . round($t2, 4) . "s\n";
