<?php
session_start();
$_SESSION['usuario'] = 'admin';
$_GET['page'] = 1;
$_GET['limit'] = 5;
$_GET['criterion'] = 'utilizacao';
$_GET['class'] = 'RESIDENCIAL';

require __DIR__ . '/../api/tabela_lotes.php';
