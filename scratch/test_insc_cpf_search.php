<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    // Test 1: Search by Inscricao
    $termInsc = '0101006';
    $cleanInsc = preg_replace('/[^0-9a-zA-Z]/', '', $termInsc);

    $sqlInsc = '
        SELECT 
            i."ID" as id,
            i."INSC_MUNIC" as insc_munic,
            i."NOMEDOCONTRIBUINTE" as contribuinte,
            i."CPF_CNPJ" as cpf_cnpj,
            TRIM(CONCAT_WS(\' \', i."NOMETIPOLOGRA", i."NOMELOGRADOURO")) as logradouro,
            i."NUMEROLOGRADOURO" as numero,
            i."NOMEBAIRRO" as bairro,
            l.id as lote_id,
            COALESCE(
                ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))),
                ST_X(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326)))
            ) as lng,
            COALESCE(
                ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))),
                ST_Y(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326)))
            ) as lat,
            COALESCE(
                ST_AsGeoJSON(ST_Transform(ST_Force2D(l.geom), 4326)),
                ST_AsGeoJSON(ST_Transform(ST_Force2D(e.geom), 4326))
            ) as geojson
        FROM public."GP_IMOVEL" i
        LEFT JOIN geo.geo_lotes_dm l 
            ON (l.inscimob = SUBSTRING(i."INSC_MUNIC", 1, 11) 
                OR l.inscimob2 = i."INSC_MUNIC" 
                OR l.inscimob2 LIKE SUBSTRING(i."INSC_MUNIC", 1, 11) || \'%\' 
                OR l.inscimob = i."INSC_MUNIC")
        LEFT JOIN geo.geo_edificacoes_dm e 
            ON (e.inscimob = i."INSC_MUNIC" 
                OR e.inscimob = SUBSTRING(i."INSC_MUNIC", 1, 11))
        WHERE i."INSC_MUNIC" ILIKE :term
        ORDER BY i."INSC_MUNIC" ASC
        LIMIT 20
    ';

    $stmt1 = $db->prepare($sqlInsc);
    $stmt1->execute([':term' => '%' . $cleanInsc . '%']);
    $res1 = $stmt1->fetchAll();
    echo "=== SEARCH INSC RESULTS: " . count($res1) . " ===\n";
    foreach ($res1 as $r) {
        echo "{$r['insc_munic']} | {$r['contribuinte']} | {$r['logradouro']} - {$r['bairro']} | Lat: {$r['lat']}, Lng: {$r['lng']}\n";
    }

    // Test 2: Search by CPF / CNPJ (clean digits or formatted)
    $termCpf = '981.207';
    $cleanCpf = preg_replace('/[^0-9]/', '', $termCpf);
    echo "\n=== SEARCH CPF '$termCpf' (clean: '$cleanCpf') ===\n";

    $sqlCpf = '
        SELECT 
            i."ID" as id,
            i."INSC_MUNIC" as insc_munic,
            i."NOMEDOCONTRIBUINTE" as contribuinte,
            i."CPF_CNPJ" as cpf_cnpj,
            TRIM(CONCAT_WS(\' \', i."NOMETIPOLOGRA", i."NOMELOGRADOURO")) as logradouro,
            i."NUMEROLOGRADOURO" as numero,
            i."NOMEBAIRRO" as bairro,
            l.id as lote_id,
            COALESCE(
                ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))),
                ST_X(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326)))
            ) as lng,
            COALESCE(
                ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))),
                ST_Y(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326)))
            ) as lat,
            COALESCE(
                ST_AsGeoJSON(ST_Transform(ST_Force2D(l.geom), 4326)),
                ST_AsGeoJSON(ST_Transform(ST_Force2D(e.geom), 4326))
            ) as geojson
        FROM public."GP_IMOVEL" i
        LEFT JOIN geo.geo_lotes_dm l 
            ON (l.inscimob = SUBSTRING(i."INSC_MUNIC", 1, 11) 
                OR l.inscimob2 = i."INSC_MUNIC" 
                OR l.inscimob2 LIKE SUBSTRING(i."INSC_MUNIC", 1, 11) || \'%\' 
                OR l.inscimob = i."INSC_MUNIC")
        LEFT JOIN geo.geo_edificacoes_dm e 
            ON (e.inscimob = i."INSC_MUNIC" 
                OR e.inscimob = SUBSTRING(i."INSC_MUNIC", 1, 11))
        WHERE i."CPF_CNPJ" ILIKE :term 
           OR REGEXP_REPLACE(COALESCE(i."CPF_CNPJ", \'\'), \'[^0-9]\', \'\', \'g\') ILIKE :clean_term
        ORDER BY i."NOMEDOCONTRIBUINTE" ASC, i."INSC_MUNIC" ASC
        LIMIT 20
    ';

    $stmt2 = $db->prepare($sqlCpf);
    $stmt2->execute([':term' => '%' . $termCpf . '%', ':clean_term' => '%' . $cleanCpf . '%']);
    $res2 = $stmt2->fetchAll();
    echo "=== SEARCH CPF RESULTS: " . count($res2) . " ===\n";
    foreach ($res2 as $r) {
        echo "{$r['insc_munic']} | {$r['cpf_cnpj']} | {$r['contribuinte']} | {$r['logradouro']} - {$r['bairro']} | Lat: {$r['lat']}, Lng: {$r['lng']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
