<?php
/**
 * Carregador de variáveis de ambiente (.env) para PHP puro.
 */

if (!function_exists('loadEnv')) {
    /**
     * Carrega as variáveis do arquivo .env especificado.
     *
     * @param string|null $path Caminho para o arquivo .env
     * @return void
     */
    function loadEnv(?string $path = null): void {
        static $loaded = false;
        
        // Se já carregou com caminho padrão e nenhum caminho específico foi passado, ignora
        if ($loaded && $path === null) {
            return;
        }

        if ($path === null) {
            $path = dirname(__DIR__) . '/.env';
        }

        if (!file_exists($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignorar comentários ou linhas vazias
            if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Remover aspas simples ou duplas ao redor do valor
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[-1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Define em putenv, $_ENV e $_SERVER
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        $loaded = true;
    }
}

if (!function_exists('env')) {
    /**
     * Recupera o valor de uma variável de ambiente com suporte a valor padrão.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    function env(string $key, mixed $default = null): mixed {
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
        return $default;
    }
}

// Carrega automaticamente o .env ao incluir este arquivo
loadEnv();
