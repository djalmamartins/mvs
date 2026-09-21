# ERP Condominial — Auditoria inicial do legado

Issue: #37  
Estratégia: #36

## Objetivo

Registrar o estado verificável do repositório antes de criar o novo domínio ERP e impedir uma terceira implementação paralela.

## Evidências verificadas no `main`

- Aplicação PHP com estrutura própria em `app/Boot`, `app/Controllers`, `app/Core`, `app/Middleware`, `app/Models` e `app/Support`.
- `app/Models` contém atualmente apenas `Setting.php` e `User.php`; não há modelos de domínio condominial nessa camada.
- O banco versionado está em `database/migrations`.
- As migrations visíveis começam pelo Core (`users/settings`), login e depois concentram-se no Studio; não foi identificada migration de condomínio/unidade/pessoa/financeiro na sequência auditada.
- O repositório já possui PHPUnit (`phpunit.xml`), PHPStan (`phpstan.neon`) e diretório `tests`, portanto a nova fundação deve reaproveitar a infraestrutura de qualidade em vez de criar outra.
- O Core já oferece autenticação/acesso, CSRF, request/response, sessão, settings, logging e bootstrap/rotas. Esses serviços devem ser avaliados e reaproveitados onde forem compatíveis com RBAC por escopo e API v1.

## Classificação inicial

| Área | Decisão | Motivo / próximo passo |
|---|---|---|
| `app/Core` | REAPROVEITAR + REFATORAR | Há infraestrutura transversal útil. Auditar `Access`, `Auth`, `Request`, `Response`, `Session`, `Settings`, `Logger` antes de estender. |
| `app/Boot` | REAPROVEITAR + REFATORAR | Bootstrap e roteamento existentes devem hospedar o ERP sem acoplar regras condominiais ao Core. |
| `app/Models` | REAPROVEITAR estrutura, SUBSTITUIR abordagem para ERP | A camada existe, mas ainda não representa o domínio condominial. Criar módulos/domínio sem colocar regras complexas em modelos anêmicos. |
| migrations Core | REAPROVEITAR | São a base instalada; nunca reescrever migration já aplicada. Evoluir apenas por migrations incrementais. |
| migrations Studio | ISOLAR | Não pertencem ao ERP; ERP não deve depender de tabelas Studio. |
| Controllers Studio/Legacy | ISOLAR | Não usar como base de domínio do ERP. Reutilizar somente padrões transversais comprovados. |
| PHPUnit/PHPStan/tests | REAPROVEITAR | Devem virar gate para cada vertical ERP. |
| UI/Design System existente | REAPROVEITAR | ERP deve consumir componentes/tokens globais e não criar tema paralelo. |

## Gaps contra #36

### P0 — bloqueantes

1. Não existe ainda fronteira explícita do domínio ERP.
2. RBAC atual precisa ser medido contra escopo Administradora → Condomínio.
3. Não há baseline ERP de migrations e entidades.
4. API `/api/v1` e contratos de erro/paginação/idempotência precisam ser definidos.
5. Auditoria imutável para ações críticas ainda precisa de confirmação/implementação.

### P1 — fundação ausente

- Organization/Administradora.
- Condominium.
- Block/Tower.
- Unit e fração ideal.
- Person e vínculos temporais.
- Supplier/terceiros.
- Plano de contas, centros de custo e contas bancárias.
- Ledger financeiro.

## Decisão arquitetural provisória

Até a conclusão da #37, nenhuma nova regra de negócio condominial deve ser adicionada a controllers Studio/Legacy. A fundação ERP deverá entrar em namespace/pasta de domínio próprio, consumindo apenas serviços transversais do Core. A decisão final de estrutura será registrada na #39 depois de validar bootstrap, roteamento, ACL e testes existentes.

## Próximas verificações da #37

1. Inventariar todas as rotas e separar Core/Studio/Operação/legado.
2. Inventariar tabelas de todas as migrations e dependências entre elas.
3. Auditar `Access.php` e `Auth.php` contra RBAC por escopo.
4. Inventariar `tests` e cobertura real dos fluxos autenticados.
5. Identificar telas/recursos que já implementam partes de Operação/ERP e marcar REAPROVEITAR/REFATORAR/SUBSTITUIR/REMOVER.
6. Fechar com matriz completa de gaps e alimentar #38, #39 e #40.

## Regra de segurança de migração

Nenhuma tabela, usuário ou dado existente será removido durante a fundação. Mudanças de schema serão aditivas até existir plano de migração, reconciliação e rollback aprovado na fase V1.
