# Auditoria de consolidação de branches — 2026-09-27

A base de trabalho foi `feat/mvs-base` em `2249262`; `feat/mvs-dev` já existia e foi preservada. O inventário inicial de `git fetch origin --prune` encontrou 61 branches remotas. Os nomes são registrados abaixo para permitir revisão após a limpeza.

## Inventário inicial

- `erp/37-legacy-audit`
- `erp/38-ci-baseline`
- `erp/39-api-contracts`
- `erp/39-api-foundation`
- `erp/39-domain-events`
- `erp/39-idempotency-contract`
- `erp/39-query-contracts`
- `erp/39-query-idempotency-contracts`
- `erp/40-access-gate`
- `erp/40-access-scope`
- `erp/40-auth-session-boundary`
- `erp/40-four-eyes-audit`
- `erp/40-four-eyes-policy`
- `erp/40-http-access-middleware`
- `erp/40-http-mfa-enforcement`
- `erp/40-mfa-audit-events`
- `erp/40-mfa-audit-instrumentation`
- `erp/40-mfa-challenge-service`
- `erp/40-mfa-enrollment-repository`
- `erp/40-mfa-enrollment-schema`
- `erp/40-mfa-enrollment-upsert`
- `erp/40-mfa-login-gate`
- `erp/40-mfa-runtime-config`
- `erp/40-mfa-secret-cipher`
- `erp/40-scope-context`
- `erp/40-scope-grants-resolver`
- `erp/40-scope-grants-schema`
- `erp/40-scoped-access-service`
- `erp/40-scoped-authorization`
- `erp/40-scoped-authorizer`
- `erp/40-sensitive-mfa-policy`
- `erp/40-totp-verifier`
- `erp/41-admin-condo-schema`
- `erp/41-admin-condo-schema-v2`
- `erp/41-api-condominiums-read`
- `erp/41-cadastro-access-tests`
- `erp/41-cadastro-repositories`
- `erp/41-cadastro-runtime-factory`
- `erp/41-cadastro-write-boundary`
- `erp/41-cadastro-write-tests`
- `erp/41-scoped-cadastro-service`
- `erp/41-scoped-cadastros-service`
- `feat/mvs-base`
- `feat/mvs-dev`
- `feat/mvs-layout`
- `feat/platform-saas-foundation-v1`
- `feat/talk-layout-shell-v1`
- `fix/talk-legacy-schema-bridge-v1`
- `fix/v1-production-hardening-19`
- `main`
- `test/erp-cadastro-write-audit`
- `test/erp-cadastro-write-audit-v2`
- `test/v1-16-migration-safety`
- `v1/16-migration-lock`
- `v1/17-backup-runbook`
- `v1/18-ci-audit`
- `v1/18-composer-audit`
- `v1/19-prod-gate`
- `v1/20-contact-hardening`
- `work/erp-40-mfa-challenge`
- `work/erp-40-mfa-login-enforcement`

## Critério aplicado

Cada branch foi comparada pelo histórico, arquivos novos e alterações de conteúdo. Arquivos idênticos foram preservados sem novo merge. Alterações divergentes foram revistas contra a implementação mais recente em `feat/mvs-dev`; a comparação não usou apenas ahead/behind. As famílias ERP/V1 acumulavam commits de `main` com variações intermediárias. Foram portados seletivamente os contratos API, eventos, grants, MFA/TOTP, auditoria/four-eyes, cadastros ERP, middleware, testes, migrações, gate de produção e runbooks. `feat/platform-saas-foundation-v1` teve todos os seus arquivos novos preservados. `feat/mvs-layout` forneceu o laboratório visual e a revisão de autenticação, isolados em rotas de desenvolvimento. O bridge de schema legado de `fix/talk-legacy-schema-bridge-v1` foi incorporado ao runner atual de migrations.

`feat/talk-layout-shell-v1` foi auditada e permanece protegida. Seu backend e migrations Talk precedem a arquitetura multitenant atual e conflitam com ela; suas telas e CSS específicos não foram aplicados cegamente ao Talk atual. O trabalho de mídia WhatsApp existente na base foi preservado.

## Implementações substituídas

- `erp/41-admin-condo-schema`: migration 003 substituída pela 004 corrigida; aplicá-la criaria conflito no schema.
- `erp/41-scoped-cadastros-service`: serviço paralelo substituído por `CadastroAccessService` e `CadastroServiceFactory` canônicos.
- `erp/39-query-idempotency-contracts`: teste antigo dependia da API `normalize()` substituída. A allowlist de ordenação e o fingerprint de idempotência úteis foram adaptados à API atual e testados.
- Variantes intermediárias de `Routes`, `ScopedAccess`, autenticação e runner de migrations: retida a implementação mais recente, com gate de tenant e produto, lock e testes.

## Convergência de schema e acesso

A migration 014 vincula `erp_administrators` a `talk_tenants` (nome histórico da tabela canônica) e cria `platform_tenant_products`. Administradoras existentes recebem tenant sem troca de IDs; grants existentes geram memberships. Novas administradoras criam tenant e habilitação ERP na mesma transação. O acesso ERP exige grant, vínculo ativo, usuário ativo, tenant ativo e produto ERP habilitado. Talk é habilitado separadamente. O banco XAMPP em uso não foi alterado.

## Validação local

Em banco descartável: instalação limpa até migration 014, reexecução idempotente, teste de falha controlada e upgrade da 013 para 014 com administradora e grant existentes. PHPUnit: 159 testes e 674 assertions. PHPStan, lint PHP, sintaxe JavaScript, 6 testes Node, Composer audit e npm audit passaram. O CI remoto deve ser consultado no commit final antes de excluir branches.

No smoke HTTP com servidor local e banco descartável, login redirecionou para `/app` (200 autenticado), ERP sem grant retornou 403, Talk sem produto retornou 403 e a revisão visual de 2FA retornou 200. Um symlink local ignorado `.env` apontava para a configuração XAMPP e foi removido antes da validação HTTP definitiva. O carregamento de timezone agora respeita variáveis de ambiente explícitas, inclusive quando `$_ENV` não é populado pelo PHP CLI.
