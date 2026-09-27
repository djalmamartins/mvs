# ERP Condominial — Auditoria do legado

Issue: #37  
Estratégia: #36

## Objetivo
Registrar o estado verificável do repositório antes de criar o novo domínio ERP e impedir uma terceira implementação paralela.

## Evidências verificadas
- Aplicação PHP própria em `app/Boot`, `app/Controllers`, `app/Core`, `app/Middleware`, `app/Models` e `app/Support`.
- `app/Models` contém somente `Setting.php` e `User.php`; o domínio condominial ainda não existe nessa camada.
- O Core oferece autenticação/acesso, CSRF, request/response, sessão, settings, logging e bootstrap/rotas.
- PHPUnit, PHPStan, Composer audit e scripts de integração já fazem parte do contrato de desenvolvimento.
- O repositório não possui CI versionado no estado auditado; isso é bloqueante da #38.

## Inventário de rotas
`app/Boot/Routes.php` concentra hoje os seguintes grupos:

| Grupo | Exemplos | Decisão ERP |
|---|---|---|
| Site público | `/`, `/servicos`, `/projetos`, `/conteudo`, `/contato` | ISOLAR |
| App/Core | `/app`, `/app/status`, `/app/profile` | REAPROVEITAR infraestrutura; não misturar domínio |
| Studio | `/studio/*` usuários, conteúdo, mídia, propostas, notificações, relatórios, settings, diagnóstico | ISOLAR; reaproveitar DS/padrões transversais |
| Legado | `/admin/*` redirecionando para Studio | MANTER COMPATIBILIDADE até plano de remoção |
| Auth | `/login`, `/logout` | REAPROVEITAR + REFATORAR para MFA/escopo |

Não existe rota `/api/v1` nem rota de domínio condominial no arquivo auditado. `Modules::boot($router)` fornece ponto de extensão adequado para hospedar o ERP modular sem contaminar `Routes.php`.

## ACL/Auth
`Access` usa lista estática de permissões por papel (`admin` e `user`) e agrega permissões declaradas por `Modules`. É uma base funcional útil, mas não implementa escopo de dados Administradora → Condomínio → Unidade.

`Auth` autentica por e-mail/senha, exige `status=active`, grava o ID do usuário na sessão e regenera o ID de sessão após login. Para o ERP faltam vínculo usuário↔organização/condomínio, autorização contextual, MFA/2FA, reautenticação para operações críticas e trilha da decisão de autorização.

**Decisão:** não substituir Auth. Evoluir aditivamente na #40 para reduzir risco de regressão.

## Migrations e banco — inventário fechado
O diretório auditado contém exatamente oito migrations SQL, em ordem:
1. `20260910_001_create_core_tables.sql`;
2. `20260911_001_add_role_to_users.sql`;
3. `20260912_001_create_login_attempts.sql`;
4. `20260913_001_create_studio_modules.sql`;
5. `20260913_002_create_studio_management.sql`;
6. `20260913_003_expand_studio_content.sql`;
7. `20260913_004_complete_studio_operations.sql`;
8. `20260913_005_create_content_revisions.sql`.

Não existem migrations ERP posteriores escondidas no diretório auditado. Core/login = REAPROVEITAR; Studio = ISOLAR; migrations aplicadas = IMUTÁVEIS.

### Runner confirmado
`scripts/migrate.php` é o runner oficial. Ele:
- cria `migrations` com nome único e timestamp;
- lista `database/migrations/*.sql`, ordena por nome e executa apenas pendentes;
- registra a migration somente após `PDO::exec()` retornar sem erro;
- interrompe ao primeiro `Throwable`;
- não possui mecanismo de rollback/down migration.

Implicações para #38: manter migrations aditivas, testar instalação limpa e upgrade; não depender de rollback automático; documentar recuperação/backup para falha parcial de DDL; CI deve provar ordem e idempotência do runner.

## Bootstrap/seed
O README define instalação por `composer install`, cópia do `.env`, `scripts/check.php`, `scripts/migrate.php` e `scripts/create-user.php`. Não existe diretório de seeds no `database` auditado: o único conteúdo é `migrations/`. Portanto o bootstrap de dados administrativos é hoje um comando explícito de criação de usuário, não um seed geral. #38 deve preservar esse comportamento e só introduzir fixtures/seeds específicos de teste se forem isolados do ambiente real.

## Testes — cobertura real
`tests/CoreTest.php` contém 13 testes unitários/infraestruturais cobrindo:
- Config/environment;
- resolução de Theme por contexto;
- Validator;
- deduplicação de permissões de Modules;
- erro 500 em produção/desenvolvimento;
- segurança de redirects (externo e header injection);
- escaping de template;
- redação de dados sensíveis em logs;
- SEO automático e overrides;
- sanitização de rich text e embeds.

A suíte atual **não cobre** banco/migrations, login autenticado ponta a ponta, autorização contextual, API, multiempresa/multicondomínio ou domínio ERP. O README também define PHPStan, `composer validate --strict`, `composer audit` e `scripts/test-*.php`; #38 deve transformar esses comandos em gate de CI e adicionar teste de migrations em banco limpo.

## Recursos fora do Studio
No estado auditado não há controller/model/rota de domínio condominial fora do Studio. `app` é contexto genérico de usuário/perfil; não deve ser interpretado como ERP existente. Portanto não há módulo operacional condominial oculto a reaproveitar: a nova fronteira ERP pode ser criada sem migrar regras de negócio existentes, preservando apenas Core, autenticação, módulos e Design System.

## Matriz final de reaproveitamento
| Área | Decisão | Motivo / próximo passo |
|---|---|---|
| `app/Core` | REAPROVEITAR + REFATORAR | Infraestrutura transversal; nunca receber regra condominial. |
| `app/Boot/Routes.php` | REAPROVEITAR | Rotas globais; ERP delegado por módulo. |
| `app/Boot/Modules.php` | REAPROVEITAR + EVOLUIR | Ponto de extensão para ERP e permissões funcionais. |
| `app/Core/Auth.php` | REAPROVEITAR + EVOLUIR | Login/sessão válidos; adicionar MFA/contexto. |
| `app/Core/Access.php` | REFATORAR | Falta autorização por escopo/recurso. |
| `app/Models` | REAPROVEITAR convenção; NOVO DOMÍNIO | Entidades ERP ainda inexistentes. |
| migrations Core/login | REAPROVEITAR | Baseline instalada; histórico imutável. |
| migrations Studio | ISOLAR | ERP não depende de tabelas Studio. |
| Controllers Studio/Legacy | ISOLAR | Não são base de negócio ERP. |
| `/app` | REAPROVEITAR shell quando adequado | Contexto genérico, sem domínio condominial atual. |
| PHPUnit/PHPStan/scripts | REAPROVEITAR + EVOLUIR | Viram gate de CI na #38. |
| UI/Design System | REAPROVEITAR | Proibido criar tema ERP paralelo. |
| seed genérico | NÃO EXISTE / NÃO INVENTAR | Fixtures de teste devem ser isoladas. |
| CI | NÃO EXISTE / CRIAR | Entrega da #38. |

## Gaps contra #36
### P0 — bloqueantes
1. Fronteira explícita do domínio ERP ainda não implementada.
2. RBAC atual não possui escopo Administradora → Condomínio.
3. Não há baseline ERP de migrations e entidades.
4. API `/api/v1` e contratos de erro/paginação/idempotência não existem.
5. Auditoria imutável para ações críticas não está demonstrada.
6. CI não existe no estado auditado.

### P1 — fundação ausente
Organization/Administradora; Condominium; Block/Tower; Unit/fração ideal; Person/vínculos temporais; Supplier; plano de contas; centros de custo; contas bancárias; ledger financeiro.

## Critérios executáveis para as próximas P0
### #38 — baseline, banco e CI
- preservar as oito migrations existentes sem edição;
- validar runner em banco limpo e reexecução idempotente;
- CI com `composer validate --strict`, instalação, PHPUnit, PHPStan e checks compatíveis com ambiente automatizado;
- separar testes que exigem MySQL/HTTP local dos testes puros;
- documentar estratégia de falha/backup, pois não há rollback automático.

### #39 — arquitetura/API
- ERP em namespace/módulo próprio registrado por `Modules`;
- Core permanece transversal;
- `/api/v1` com contratos explícitos de erro, paginação, filtros e idempotência;
- nenhuma dependência de tabelas/controllers Studio.

### #40 — segurança
- manter login atual e compatibilidade de usuários;
- adicionar vínculo e autorização por organização/condomínio/recurso;
- MFA/2FA para perfis sensíveis;
- reautenticação/quatro olhos onde aplicável;
- auditoria de ações críticas sem registrar segredos.

## Conclusão da auditoria
A #37 pode ser encerrada quando este documento estiver revisado/mergeado: o legado foi classificado, o inventário de migrations foi fechado, o runner/bootstrap e a cobertura real de testes foram confirmados, não foi encontrado ERP condominial oculto a migrar e os critérios de entrada de #38–#40 estão explícitos.

## Regra de segurança de migração
Nenhuma tabela, usuário ou dado existente será removido durante a fundação. Mudanças de schema serão aditivas até existir plano de migração, reconciliação e rollback aprovado na fase V1.
