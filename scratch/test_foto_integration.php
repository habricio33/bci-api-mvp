<?php
// Test BCI endpoint with photo
$_GET['action'] = 'bci';
$_GET['insc_munic'] = '01010040238001';
$_SESSION['usuario'] = 'test';

try {
    $dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
    $db = new PDO($dsn, 'postgres', 'postgres', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

    $inscMunic = $_GET['insc_munic'];

    // Query Imovel
    $queryBci = '
        SELECT "ID", "INSC_MUNIC", "NOMEDAEMPRESA", "NOMEMUNICIPIO", "NOMEDOCONTRIBUINTE", "CPF_CNPJ"
        FROM public."GP_IMOVEL"
        WHERE "INSC_MUNIC" = :insc_munic
        LIMIT 1
    ';
    $stmt = $db->prepare($queryBci);
    $stmt->execute([':insc_munic' => $inscMunic]);
    $imovel = $stmt->fetch();

    // Query Foto
    $stmtFoto = $db->prepare('
        SELECT "ID", "INSC_MUNIC", "NOME_FOTO", "STATUS"
        FROM public."GP_IMOVEL_FOTOS"
        WHERE "INSC_MUNIC" = :insc AND ("STATUS" = \'A\' OR "STATUS" IS NULL)
        ORDER BY "ID" DESC
        LIMIT 1
    ');
    $stmtFoto->execute([':insc' => $inscMunic]);
    $fotoRow = $stmtFoto->fetch();

    $fileName = basename(str_replace('\\', '/', $fotoRow['NOME_FOTO']));
    $fileExists = file_exists(__DIR__ . '/../img/fotos_fachada/' . $fileName);

    echo "=== Test Result ===\n";
    echo "Imovel found: " . ($imovel ? "YES ({$imovel['NOMEDOCONTRIBUINTE']})" : "NO") . "\n";
    echo "Foto row found: " . ($fotoRow ? "YES (ID: {$fotoRow['ID']})" : "NO") . "\n";
    echo "File name: {$fileName}\n";
    echo "File exists on disk: " . ($fileExists ? "YES" : "NO") . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
