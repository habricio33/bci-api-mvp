# Scripts e Dumps de Inicialização do Banco de Dados (PostGIS)

Coloque aqui seus arquivos de inicialização:
- **Scripts SQL texto:** `.sql` ou `.sql.gz`
- **Dumps binários do PostgreSQL:** `.dump` ou `.backup`

Quando o container `db` for criado pela primeira vez (volume vazio), o PostgreSQL executará os scripts e o script `01_restore.sh` fará a restauração dos dumps automaticamente.
