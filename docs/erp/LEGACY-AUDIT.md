# ERP Condominial — Auditoria do legado

Issue: #37  
Estratégia: #36

## Objetivo
Registrar o estado verificável do repositório antes de criar o novo domínio ERP e impedir uma terceira implementação paralela.

## Evidências verificadas no `main`
- Aplicação PHP própria em `app/Boot`, `app/Controllers`, `app/Core`, `app/Middleware`, `app/Models` e `app/Support`.
- `app/Models` contém somente `Setting.php` e `User.php`; o domínio condominial ainda não existe nessa camada.
- `database/migrations` começa com Core (`users/settings`), papel do usuário, tentativas de login e depois migrations do Studio. Não há baseline ERP de condomínio/unidade/pessoa/financeiro no conjunto auditado.
- PHPUnit, PHPStan e `tests` já existem e devem ser reaproveitados.
- O Core oferece autenticação/acesso, CSRF, request/response, sessão, settings, logging e bootstrap/rotas.

## Inventário de rotas
`app/Boot/Routes.php` concentra hoje quatro grupos:

| Grupo | Exemplos | Decisão ERP |
|---|---|---|
| Site público | `/`, `/servicos`, `/projetos`, `/conteudo`, `/contato` | ISOLAR |
| App/Core | `/app`, `/app/status`, `/app/profile` | REAPROVEITAR infraestrutura; não misturar domínio |
| Studio | `/studio/*` usuários, conteúdo, mídia, propostas, notificações, relatórios, settings, diagnóstico | ISOLAR; reaproveitar DS/padrões transversais |
| Legado | `/admin/*` redirecionando para Studio | MANTER COMPATIBILIDADE até plano de remoção |
| Auth | `/login`, `/logout` | REAPROVEITAR + REFATORAR para MFA/escopo |

Não existe rota `/api/v1` nem rota de domínio condominial no arquivo auditado. `Modules::boot($router)` fornece ponto de extensão adequado: `Modules` registra closures de rotas e permissões sem exigir um plugin framework complexo. Isso é um candidato forte para hospedar o ERP modular sem contaminar `Routes.php`.

## ACL/Auth
`Access` usa hoje uma lista estática de permissões por papel (`admin` e `user`) e agrega permissões declaradas por `Modules`. `admin` herda as permissões de `user`. É uma boa base para permissões funcionais, mas **não implementa escopo de dados** Administradora → Condomínio → Unidade.

`Auth` autentica por e-mail/senha, exige `status=active`, grava somente o ID do usuário na sessão e regenera o ID de sessão após login. Isso deve ser preservado como base. Para o ERP faltam, no mínimo:
- vínculo usuário ↔ organização/condomínio;
- autorização contextual por recurso/escopo;
- MFA/2FA para perfis sensíveis;
- política de sessão/reauth para operações críticas;
- trilha de decisão de autorização para ações financeiras críticas.

Decisão: **não substituir Auth agora**. Evoluir aditivamente na #40 para reduzir risco de regressão de login.

## Migrations e banco
Arquivos confirmados no início da sequência:
- `20260910_001_create_core_tables.sql`;
- `20260911_001_add_role_to_users.sql`;
- `20260912_001_create_login_attempts.sql`;
- `20260913_001_create_studio_modules.sql`;
- `20260913_002_create_studio_management.sql`;
- `20260913_003_expand_studio_content.sql`;
- `20260913_004_complete_studio_operations.sql`;
- `20260913_005_create_content_revisions.sql`.

Classificação: Core/login = REAPROVEITAR; Studio = ISOLAR; migrations aplicadas = IMUTÁVEIS. A #38 deve definir baseline/runner/CI antes da primeira migration ERP.

## Testes
O diretório `tests` contém atualmente `CoreTest.php`. Portanto existe infraestrutura de teste, mas a cobertura está concentrada no Core e ainda não constitui uma suíte ERP nem prova fluxos autenticados/multi-escopo. A #38 deve transformar testes + análise estática em gate de CI; cada vertical ERP deve adicionar unit/integration tests próprios.

## Matriz de reaproveitamento
| Área | Decisão | Motivo / próximo passo |
|---|---|---|
| `app/Core` | REAPROVEITAR + REFATORAR | Serviços transversais úteis; não receber regra condominial. |
| `app/Boot/Routes.php` | REAPROVEITAR | Manter rotas globais e delegar módulos via `Modules::boot`. |
| `app/Boot/Modules.php` | REAPROVEITAR + EVOLUIR | Ponto de extensão limpo para ERP e permissões funcionais. |
| `app/Core/Auth.php` | REAPROVEITAR + EVOLUIR | Login/sessão válidos; adicionar MFA e contexto sem reescrever fluxo existente. |
| `app/Core/Access.php` | REFATORAR | ACL funcional existe, mas falta RBAC/ABAC por escopo. |
| `app/Models` | REAPROVEITAR estrutura, NOVO DOMÍNIO | Não há entidades condominiais; regras complexas devem viver em services/domain. |
| migrations Core/login | REAPROVEITAR | Base instalada; nunca reescrever migration aplicada. |
| migrations Studio | ISOLAR | ERP não deve depender de tabelas Studio. |
| Controllers Studio/Legacy | ISOLAR | Não usar como base de negócio ERP. |
| PHPUnit/PHPStan | REAPROVEITAR | Gate obrigatório das novas verticais. |
| UI/Design System | REAPROVEITAR | Sem tema ERP paralelo. |

## Gaps contra #36
### P0 — bloqueantes
1. Fronteira explícita do domínio ERP ainda não implementada.
2. RBAC atual não possui escopo Administradora → Condomínio.
3. Não há baseline ERP de migrations e entidades.
4. API `/api/v1` e contratos de erro/paginação/idempotência não existem.
5. Auditoria imutável para ações críticas não está demonstrada.
6. CI não executou checks no commit atual do PR de auditoria; a #38 deve criar/validar o gate.

### P1 — fundação ausente
Organization/Administradora; Condominium; Block/Tower; Unit/fração ideal; Person/vínculos temporais; Supplier; plano de contas; centros de custo; contas bancárias; ledger financeiro.

## Decisões para #38, #39 e #40
- **#38:** baseline de banco e CI antes de migrations ERP; nenhuma alteração destrutiva.
- **#39:** ERP em módulo/namespace próprio, registrado pelo mecanismo de módulos; API versionada e contratos próprios; Core permanece transversal.
- **#40:** manter autenticação existente e evoluir autorização para escopo contextual + MFA, sem quebrar usuários atuais.

## Próximos passos para encerrar #37
1. Inventariar o restante das migrations/tabelas e dependências, inclusive migrations posteriores às listadas acima.
2. Inspecionar `CoreTest.php` em detalhe e mapear o que já é coberto.
3. Identificar telas/recursos fora do Studio que possam representar Operação/ERP e classificá-los.
4. Confirmar runner de migrations e processo de bootstrap/seed.
5. Fechar matriz final e alimentar critérios executáveis de #38/#39/#40.

## Regra de segurança de migração
Nenhuma tabela, usuário ou dado existente será removido durante a fundação. Mudanças de schema serão aditivas até existir plano de migração, reconciliação e rollback aprovado na fase V1.
