# Moves v1 — Backup e Restore Runbook

## Objetivo
Definir um procedimento seguro, repetível e auditável para backup e restauração do banco e do storage da plataforma Moves.

## Princípios
- Nunca executar restore diretamente sobre produção.
- Nunca versionar dumps, credenciais, tokens ou arquivos privados.
- Todo restore drill deve usar destino descartável.
- Validar integridade antes de considerar um backup utilizável.

## Backup de banco
Variáveis esperadas: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME e DB_PASSWORD.

```bash
mkdir -p storage/backups/database
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
MYSQL_PWD="$DB_PASSWORD" mysqldump \
  --host="$DB_HOST" \
  --port="${DB_PORT:-3306}" \
  --user="$DB_USERNAME" \
  --single-transaction \
  --routines \
  --triggers \
  --set-gtid-purged=OFF \
  "$DB_DATABASE" | gzip > "storage/backups/database/${DB_DATABASE}-${stamp}.sql.gz"
```

## Backup de storage
Incluir apenas diretórios de dados persistentes necessários à restauração. Excluir cache, logs temporários e artefatos regeneráveis.

```bash
mkdir -p storage/backups/files
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
tar -czf "storage/backups/files/storage-${stamp}.tar.gz" storage/
```

## Retenção
A retenção deve ser configurável por ambiente. Recomendação inicial de operação:
- diários: 14 cópias;
- semanais: 8 cópias;
- mensais: 12 cópias.

A política definitiva deve considerar volume, RPO/RTO e requisitos contratuais.

## Restore drill
1. Criar banco descartável.
2. Restaurar dump no banco descartável.
3. Executar `php scripts/migrate.php` para comprovar compatibilidade de upgrade.
4. Executar testes críticos.
5. Registrar data, duração, tamanho do backup e resultado.
6. Destruir o destino descartável após a validação.

## Critérios de aceite para a issue #17
- backup de banco automatizável;
- backup de storage definido;
- retenção documentada;
- credenciais fora do repositório;
- restore executado apenas em ambiente descartável;
- evidência registrada de ao menos um restore drill bem-sucedido.
