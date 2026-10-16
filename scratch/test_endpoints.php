<?php
try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);

    echo "--- Testing Inscricao Search ---\n";
    $term = '0101006';
    $cleanTerm = preg_replace('/[^0-9a-zA-Z]/', '', $term);
    $sql = '
        WITH matched_imoveis AS (
            SELECT 
                i."ID" AS id,
                i."INSC_MUNIC" AS insc_munic,
                COALESCE(i."NOMEDOCONTRIBUINTE", \'Não informado\') AS contribuinte,
                COALESCE(i."CPF_CNPJ", \'Não informado\') AS cpf_cnpj,
                TRIM(CONCAT_WS(\' \', i."NOMETIPOLOGRA", i."NOMELOGRADOURO")) AS logradouro,
                COALESCE(i."NUMEROLOGRADOURO", \'\') AS numero,
                COALESCE(i."NOMEBAIRRO", \'\') AS bairro,
                COALESCE(i."NOMEMUNICIPIO", \'Domingos Martins\') AS municipio
            FROM public."GP_IMOVEL" i
            WHERE i."INSC_MUNIC" ILIKE :term 
               OR i."INSC_MUNIC" ILIKE :clean_term
            ORDER BY i."INSC_MUNIC" ASC
            LIMIT 5
        )
        SELECT 
            m.*,
            COALESCE(
                ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))),
                ST_X(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326)))
            ) AS lng,
            COALESCE(
                ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))),
                ST_Y(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326)))
            ) AS lat,
            COALESCE(
                ST_AsGeoJSON(ST_Transform(ST_Force2D(l.geom), 4326)),
                ST_AsGeoJSON(ST_Transform(ST_Force2D(e.geom), 4326))
            ) AS geojson
        FROM matched_imoveis m
        LEFT JOIN LATERAL (
            SELECT geom FROM geo.geo_lotes_dm 
            WHERE inscimob = SUBSTRING(m.insc_munic, 1, 11) 
               OR inscimob2 = m.insc_munic 
               OR inscimob2 LIKE SUBSTRING(m.insc_munic, 1, 11) || \'%\' 
               OR inscimob = m.insc_munic
            LIMIT 1
        ) l ON true
        LEFT JOIN LATERAL (
            SELECT geom FROM geo.geo_edificacoes_dm 
            WHERE inscimob = m.insc_munic 
               OR inscimob = SUBSTRING(m.insc_munic, 1, 11)
            LIMIT 1
        ) e ON true
    ';
    $stmt = $db->prepare($sql);
    $stmt->execute([':term' => '%' . $term . '%', ':clean_term' => '%' . $cleanTerm . '%']);
    print_r($stmt->fetchAll());

    echo "\n--- Testing CPF Search ---\n";
    $rawTerm = '981.207.007-91';
    $cleanDigits = preg_replace('/[^0-9]/', '', $rawTerm);
    $hasDigits = !empty($cleanDigits) ? 1 : 0;
    $sqlCpf = '
        WITH matched_imoveis AS (
            SELECT 
                i."ID" AS id,
                i."INSC_MUNIC" AS insc_munic,
                COALESCE(i."NOMEDOCONTRIBUINTE", \'Não informado\') AS contribuinte,
                COALESCE(i."CPF_CNPJ", \'Não informado\') AS cpf_cnpj,
                TRIM(CONCAT_WS(\' \', i."NOMETIPOLOGRA", i."NOMELOGRADOURO")) AS logradouro,
                COALESCE(i."NUMEROLOGRADOURO", \'\') AS numero,
                COALESCE(i."NOMEBAIRRO", \'\') AS bairro,
                COALESCE(i."NOMEMUNICIPIO", \'Domingos Martins\') AS municipio
            FROM public."GP_IMOVEL" i
            WHERE i."CPF_CNPJ" ILIKE :raw_term 
               OR (:has_digits = 1 AND REGEXP_REPLACE(COALESCE(i."CPF_CNPJ", \'\'), \'[^0-9]\', \'\', \'g\') ILIKE :clean_digits)
               OR i."NOMEDOCONTRIBUINTE" ILIKE :raw_term
            ORDER BY i."NOMEDOCONTRIBUINTE" ASC, i."INSC_MUNIC" ASC
            LIMIT 5
        )
        SELECT 
            m.*,
            COALESCE(
                ST_X(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))),
                ST_X(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326)))
            ) AS lng,
            COALESCE(
                ST_Y(ST_Centroid(ST_Transform(ST_Force2D(l.geom), 4326))),
                ST_Y(ST_Centroid(ST_Transform(ST_Force2D(e.geom), 4326)))
            ) AS lat,
            COALESCE(
                ST_AsGeoJSON(ST_Transform(ST_Force2D(l.geom), 4326)),
                ST_AsGeoJSON(ST_Transform(ST_Force2D(e.geom), 4326))
            ) AS geojson
        FROM matched_imoveis m
        LEFT JOIN LATERAL (
            SELECT geom FROM geo.geo_lotes_dm 
            WHERE inscimob = SUBSTRING(m.insc_munic, 1, 11) 
               OR inscimob2 = m.insc_munic 
               OR inscimob2 LIKE SUBSTRING(m.insc_munic, 1, 11) || \'%\' 
               OR inscimob = m.insc_munic
            LIMIT 1
        ) l ON true
        LEFT JOIN LATERAL (
            SELECT geom FROM geo.geo_edificacoes_dm 
            WHERE inscimob = m.insc_munic 
               OR inscimob = SUBSTRING(m.insc_munic, 1, 11)
            LIMIT 1
        ) e ON true
    ';
    $stmt2 = $db->prepare($sqlCpf);
    $stmt2->execute([':raw_term' => '%' . $rawTerm . '%', ':has_digits' => $hasDigits, ':clean_digits' => '%' . $cleanDigits . '%']);
    print_r($stmt2->fetchAll());

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
