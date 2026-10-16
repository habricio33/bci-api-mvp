<?php
session_start();
require_once __DIR__ . '/config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$username = isset($_POST['username']) ? trim($_POST['username']) : '';
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($username) || empty($password)) {
    header("Location: login.php?error=invalid");
    exit;
}

try {
    $db = getDBConnection();
    
    // Busca o usuário pelo login
    $stmt = $db->prepare("SELECT id, nome, login, senha, perfil, status FROM usuario WHERE login = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        header("Location: login.php?error=invalid");
        exit;
    }
    
    // Verifica se o usuário está ativo
    if ($user['status'] !== 'ativo') {
        header("Location: login.php?error=blocked");
        exit;
    }
    
    // Valida a senha criptografada
    if (!password_verify($password, $user['senha'])) {
        header("Location: login.php?error=invalid");
        exit;
    }
    
    // Define a sessão do usuário
    $_SESSION['usuario'] = [
        'id' => $user['id'],
        'nome' => $user['nome'],
        'login' => $user['login'],
        'perfil' => $user['perfil']
    ];
    
    // Atualiza a data do último login
    $update = $db->prepare("UPDATE usuario SET ultimo_login = NOW() WHERE id = ?");
    $update->execute([$user['id']]);
    
    header("Location: index.php");
    exit;
    
} catch (Exception $e) {
    // Em produção, registre o erro em log. Para desenvolvimento:
    header("Location: login.php?error=system");
    exit;
}
