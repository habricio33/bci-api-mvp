<?php
/**
 * Conexão segura com o Banco de Dados PostgreSQL/PostGIS usando PDO e variáveis de ambiente.
 */

require_once __DIR__ . '/env.php';

function getDBConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        // Carrega as variáveis do arquivo .env
        loadEnv();
        
        // Suporte a fallback para config/database.php legado se existir
        $legacyConfig = [];
        $legacyConfigFile = __DIR__ . '/database.php';
        if (file_exists($legacyConfigFile)) {
            $legacyConfig = require $legacyConfigFile;
        }

        $host = env('DB_HOST', $legacyConfig['host'] ?? 'localhost');
        $port = env('DB_PORT', $legacyConfig['port'] ?? '5432');
        $dbname = env('DB_DATABASE', env('DB_NAME', $legacyConfig['dbname'] ?? 'bci_cadastro'));
        $username = env('DB_USERNAME', env('DB_USER', $legacyConfig['username'] ?? 'postgres'));
        $password = env('DB_PASSWORD', $legacyConfig['password'] ?? 'postgres');
        
        $dsn = sprintf(
            "pgsql:host=%s;port=%s;dbname=%s",
            $host,
            $port,
            $dbname
        );
        
        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $pdo->exec("SET search_path TO geo, public;");
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'error' => true,
                'message' => 'Falha na conexão com o banco de dados: ' . $e->getMessage()
            ]);
            exit;
        }
    }
    
    return $pdo;
}
