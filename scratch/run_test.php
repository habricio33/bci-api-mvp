<?php
$_SESSION['usuario'] = 'admin';
$_REQUEST['action'] = $argv[1] ?? 'listar';
$_REQUEST['id'] = $argv[2] ?? 1;
$_REQUEST['page'] = 1;
$_REQUEST['limit'] = 5;

require __DIR__ . '/../api/selecao_comunidade.php';
