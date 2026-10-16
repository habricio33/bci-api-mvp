<?php
/**
 * Endpoint de entrega e normalização das fotos de fachada dos imóveis.
 * Converte caminhos locais/absolutos em URLs web seguras e serve as imagens com cache.
 */

session_start();
if (!isset($_SESSION['usuario'])) {
    http_response_code(401);
    exit('Acesso não autorizado.');
}

require_once __DIR__ . '/../config/db.php';

$db = getDBConnection();
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$inscMunic = isset($_GET['insc_munic']) ? trim($_GET['insc_munic']) : null;

if (!$id && !$inscMunic) {
    servePlaceholder('Parâmetro não informado');
    exit;
}

try {
    if ($id) {
        $stmt = $db->prepare('SELECT * FROM public."GP_IMOVEL_FOTOS" WHERE "ID" = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
    } else {
        $stmt = $db->prepare('
            SELECT * FROM public."GP_IMOVEL_FOTOS" 
            WHERE "INSC_MUNIC" = :insc AND ("STATUS" = \'A\' OR "STATUS" IS NULL) 
            ORDER BY "ID" DESC 
            LIMIT 1
        ');
        $stmt->execute([':insc' => $inscMunic]);
    }

    $foto = $stmt->fetch();

    if (!$foto || empty($foto['NOME_FOTO'])) {
        servePlaceholder('Foto não cadastrada');
        exit;
    }

    $nomeFotoRaw = $foto['NOME_FOTO'];

    // Se for URL externa completa (http:// ou https://), redireciona
    if (preg_match('/^https?:\/\//i', $nomeFotoRaw)) {
        header('Location: ' . $nomeFotoRaw);
        exit;
    }

    // Normaliza o nome do arquivo independente do formato no banco (C:\..., /var/..., etc.)
    $fileName = basename(str_replace('\\', '/', $nomeFotoRaw));
    $diretoriosBusca = [
        __DIR__ . '/../img/fotos_fachada/',
        __DIR__ . '/../img/',
        __DIR__ . '/../uploads/fotos_fachada/',
        __DIR__ . '/../uploads/'
    ];

    $filePathFound = null;
    foreach ($diretoriosBusca as $dir) {
        $target = $dir . $fileName;
        if (file_exists($target) && is_file($target)) {
            $filePathFound = $target;
            break;
        }
    }

    if (!$filePathFound) {
        servePlaceholder('Arquivo não encontrado');
        exit;
    }

    // Identifica o MIME type
    $extension = strtolower(pathinfo($filePathFound, PATHINFO_EXTENSION));
    $mimeTypes = [
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'gif'  => 'image/gif'
    ];
    $contentType = $mimeTypes[$extension] ?? 'image/jpeg';

    $fileSize = filesize($filePathFound);
    $lastModified = filemtime($filePathFound);
    $etag = '"' . md5($filePathFound . $lastModified) . '"';

    // Suporte a cache HTTP
    if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }

    header('Content-Type: ' . $contentType);
    header('Content-Length: ' . $fileSize);
    header('ETag: ' . $etag);
    header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');

    readfile($filePathFound);
    exit;

} catch (Exception $e) {
    servePlaceholder('Erro ao carregar');
    exit;
}

/**
 * Emite um SVG elegante como placeholder caso o imóvel não possua foto ou arquivo físico.
 */
function servePlaceholder($mensagem = 'Sem foto cadastrada') {
    header('Content-Type: image/svg+xml; charset=utf-8');
    header('Cache-Control: public, max-age=3600');
    
    $msgSafe = htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8');
    echo <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="480" height="320" viewBox="0 0 480 320">
    <rect width="100%" height="100%" fill="#f8fafc" stroke="#e2e8f0" stroke-width="2" rx="8"/>
    <g transform="translate(240, 130)" text-anchor="middle">
        <!-- Ícone de Câmera / Imóvel -->
        <rect x="-38" y="-28" width="76" height="56" rx="6" fill="#cbd5e1"/>
        <path d="M-18 -28 L-10 -38 L10 -38 L18 -28 Z" fill="#94a3b8"/>
        <circle cx="0" cy="0" r="16" fill="#f8fafc" stroke="#94a3b8" stroke-width="4"/>
        <circle cx="22" cy="-16" r="4" fill="#ef4444"/>
        
        <!-- Texto explicativo -->
        <text y="58" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="14" font-weight="600" fill="#475569">{$msgSafe}</text>
        <text y="78" font-family="-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" font-size="11" font-weight="400" fill="#94a3b8">Cadastro de Fachada do Imóvel</text>
    </g>
</svg>
SVG;
    exit;
}
