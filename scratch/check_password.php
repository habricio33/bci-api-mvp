<?php
$dsn = "pgsql:host=localhost;port=5435;dbname=bci_cadastro";
$db = new PDO($dsn, 'postgres', 'postgres', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$users = $db->query('SELECT id, nome, login, senha, perfil, status FROM usuario')->fetchAll();
foreach ($users as $u) {
    echo "User: {$u['login']}\n";
    echo "Pass hash: {$u['senha']}\n";
    echo "password_verify('admin', hash): " . (password_verify('admin', $u['senha']) ? 'YES' : 'NO') . "\n";
    echo "password_verify('admin123', hash): " . (password_verify('admin123', $u['senha']) ? 'YES' : 'NO') . "\n";
    echo "password_verify('123456', hash): " . (password_verify('123456', $u['senha']) ? 'YES' : 'NO') . "\n";
    echo "password_verify('bci123', hash): " . (password_verify('bci123', $u['senha']) ? 'YES' : 'NO') . "\n";
}
