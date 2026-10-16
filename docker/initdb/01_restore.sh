#!/bin/bash
set -e

echo "========================================================="
echo "[initdb] Inicializando banco BCI / PostGIS..."
echo "========================================================="

# Garante a extensão PostGIS e Topology
psql -v ON_ERROR_STOP=0 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE EXTENSION IF NOT EXISTS postgis;
    CREATE EXTENSION IF NOT EXISTS postgis_topology;
EOSQL

echo "[initdb] Extensões PostGIS ativadas."

# Restaura arquivos SQL texto (.sql_dump) tolerando incompatibilidades legadas
for sql_file in /docker-entrypoint-initdb.d/*.sql_dump; do
    if [ -f "$sql_file" ]; then
        echo "[initdb] Restaurando dump SQL texto: $sql_file ..."
        psql -v ON_ERROR_STOP=0 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" -f "$sql_file" || true
        echo "[initdb] Concluída restauração de: $sql_file"
    fi
done

# Restaura arquivos binários do PostgreSQL (.dump ou .backup) se houverem
for bin_file in /docker-entrypoint-initdb.d/*.dump /docker-entrypoint-initdb.d/*.backup; do
    if [ -f "$bin_file" ]; then
        echo "[initdb] Restaurando dump binário: $bin_file ..."
        pg_restore -U "$POSTGRES_USER" -d "$POSTGRES_DB" --clean --if-exists --no-owner --no-privileges "$bin_file" || true
        echo "[initdb] Concluída restauração de: $bin_file"
    fi
done

echo "========================================================="
echo "[initdb] Processo de inicialização do BCI finalizado com sucesso!"
echo "========================================================="
