<?php
require_once __DIR__ . '/../config/db.php';

try {
    $db = getDBConnection();
    $sql = '
        CREATE TABLE IF NOT EXISTS public.tributario_api_imovel (
            insc_munic VARCHAR(50) PRIMARY KEY,
            codigo INT,
            dados_bci JSONB NOT NULL,
            dados_api_raw JSONB,
            data_inclusao DATE,
            data_alteracao DATE,
            data_exclusao DATE,
            situacao VARCHAR(100),
            atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        CREATE INDEX IF NOT EXISTS idx_trib_api_codigo ON public.tributario_api_imovel (codigo);
        CREATE INDEX IF NOT EXISTS idx_trib_api_situacao ON public.tributario_api_imovel (situacao);
    ';
    $db->exec($sql);
    echo "Tabela tributario_api_imovel criada com sucesso!\n";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
