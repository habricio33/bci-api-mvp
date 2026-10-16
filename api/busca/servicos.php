<?php
/**
 * Endpoint reservado para futura integração com API de serviços de terceiros.
 * Retorna uma resposta vazia temporariamente.
 */
header('Content-Type: application/json; charset=utf-8');
echo json_encode([]);
exit;
