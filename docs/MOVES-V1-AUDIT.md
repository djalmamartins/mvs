# MOVES V1 — Auditoria integral da feat/mvs-dev

Data: 2026-09-27  
Branch auditada: `feat/mvs-dev`  
HEAD observado no início: `6d8996928b8236815892a8fbb3aad73932d56626`

> **Snapshot histórico, superado.** Este relatório descreve a `feat/mvs-dev` em 2026-09-27; não representa o estado atual da `feat/mvs-v1-integration`. As avaliações antigas de Meu Dia, ERP, recuperação/convites, gates e código morto devem ser lidas somente como histórico. Para o estado vigente, evidências e limitações, consulte [MOVES-V1-MATRIX.md](MOVES-V1-MATRIX.md), atualizado em 2026-10-07.

## Escopo e método

Auditoria somente de leitura do código existente, rotas, controllers, services, migrations, testes, templates e documentação. Nenhuma funcionalidade foi implementada e nenhuma issue foi criada nesta etapa. Os únicos arquivos novos produzidos são este relatório e a matriz `MOVES-V1-MATRIX.md`.

Classificação:

- **Pronta** — fluxo principal implementado e coberto por evidência técnica suficiente.
- **Parcial** — existe implementação real, mas faltam partes necessárias para a V1.
- **Quebrada** — existe, porém há falha funcional conhecida/reproduzida ou contrato incoerente.
- **Não iniciada** — há no máximo shell/placeholder, sem domínio funcional.
- **Fora da V1** — superfície histórica/laboratório que não deve dirigir o escopo da V1.

> Observação: esta auditoria não declara E2E externo do WhatsApp aprovado. O ciclo real inbound/outbound ainda depende de validação com dispositivo/número reais.

## Diagnóstico executivo

A `feat/mvs-dev` não está vazia nem precisa ser reconstruída. A base SaaS e boa parte do núcleo técnico são reais. O principal problema é de **profundidade desigual entre produtos**: SaaS, CMS/Studio e grande parte do domínio Talk têm implementação concreta; Meu Dia e ERP visual são essencialmente entradas/placeholder; Support mistura uma base de conhecimento funcional com um helpdesk estrutural ainda não implementado.

Também há uma segunda camada de dívida: rotas antigas e superfícies de laboratório convivem com as rotas canônicas. Isso aumenta a sensação de que existem mais produtos/telas concluídos do que realmente existem.

## 1. Fundação SaaS

### Pronta

- Tenant canônico em `talk_tenants`, reutilizado pela fundação da plataforma.
- Criação de administradora via `CompanyService`, com papéis iniciais e produtos.
- Membership usuário ↔ tenant por `talk_tenant_users`.
- Papéis de sistema por tenant: owner, administrator, supervisor, agent e operator.
- Entitlements centralizados em `ProductEntitlement`.
- Contexto/troca de tenant por `TenantContext` e `TenantController`.
- Cadastro/listagem de condomínios com escopo da administradora.
- Auditoria de mutações da plataforma.
- Testes de isolamento entre administradoras em `PlatformTenantIntegrationTest`.
- Migrations 014–016 consolidam vínculo ERP/tenant, fundação SaaS e permissões Studio.

### Parcial

**Configurações da plataforma.** `PlatformSettingsController` reúne empresa, produtos, membros e condomínios em uma superfície real, mas ainda é um CRUD mínimo. `MemberService` lista/adiciona/reativa membership e altera papel por upsert, porém não há fluxo explícito de convite, remoção/desativação de membro, gestão visual granular de permissões ou lifecycle completo.

**Onboarding.** Existe onboarding funcional em 4 etapas: conta → administradora → produtos → revisão/criação. Ele cria usuário, tenant, papéis, membership, entitlements e sessão. Porém diverge do onboarding V1 acordado: faltam telefone/confirmar senha, etapa Estrutura, Importação, primeiro acesso/checklist e experiência progressiva mais completa. A tela `/layout/*` contém protótipos adicionais, mas não é o fluxo real.

**Entitlement nas rotas.** O middleware existe e é usado nas rotas específicas de canais, mas várias rotas Talk e Support continuam apenas com `AuthMiddleware`; `/erp` usa gate no controller; a variável `$studioProduct` é criada em `Routes.php` mas não aplicada. A política central existe, porém sua aplicação HTTP ainda não é uniforme.

### Falta para V1

Uniformizar product gates; concluir lifecycle de membros; consolidar permissões da plataforma; completar onboarding acordado; checklist inicial; revisar settings para evitar uma página monolítica.

## 2. Acesso e Segurança

### Pronta

- Login/logout reais.
- Sessão e autenticação.
- CSRF nas mutações relevantes.
- Permission middleware.
- RBAC e permissões por tenant na fundação SaaS.
- MFA/TOTP, enrollment, challenge, requirement policy e login gate presentes na camada ERP/security.
- Scope grants, scoped authorization, security audit e four-eyes presentes e testados.
- Login throttle e headers/hardening no core.
- Upgrade de schema legado reconciliado anteriormente.

### Parcial

**MFA como política de plataforma.** A implementação existe, mas está localizada sob `Modules/Erp/Security`. Precisa ser tratada como segurança transversal, sem duplicar código.

**Recuperação/primeiro acesso/convite.** `LayoutController` possui telas de laboratório para forgot, nova senha, primeiro acesso, convite, 2FA etc., porém essas rotas são habilitadas apenas em desenvolvimento e não constituem fluxos reais de autenticação. No fluxo canônico há login/logout, mas não foi encontrada implementação equivalente completa para recuperação segura de senha e convite.

**Gestão de permissões.** O backend tem papéis/permissões, mas a administração visual granular ainda não corresponde à profundidade do modelo.

### Duplicações/legado

- `/profile` é rota legacy e `/app/profile` é a canônica.
- `/layout/login`, `/layout/cadastro`, `/layout/recuperar-senha` e `/layout/auth/*` são laboratório visual de desenvolvimento, não devem ser contados como produto pronto.
- Há segurança avançada sob namespace ERP que deve ser reposicionada conceitualmente como plataforma antes de novas expansões.

## 3. Meu Dia

### Não iniciada funcionalmente

A rota canônica `/day` chama `PlatformController::day()`, que renderiza `platform-placeholder`. Não há service/domínio de agenda, tarefas, compromissos, pendências ou timeline do Meu Dia.

Existe um laboratório visual em `/layout/meu-dia/*`, servido por `LayoutController` apenas em desenvolvimento. Isso é referência de UX, não implementação funcional.

### Falta para V1

Definir o escopo mínimo do Meu Dia antes de construir: fontes de dados, cards realmente acionáveis, agenda/tarefas/atendimentos e integrações. Não transformar o laboratório em backend por acidente.

## 4. Talk

### Pronta no domínio local

- Tenant isolation e contexto Talk.
- Contatos, conversas, tickets, fila, claim, atribuição e presença.
- Transferência, retorno à fila, fechamento e reabertura.
- Notas, prioridade, tags e anexos.
- Histórico.
- Notificações e contador.
- Jack/settings e interações.
- Filas, departamentos, membros e capacidade.
- Outbox/outbound e idempotência.
- Inbound service.
- Canais WhatsApp multissessão.
- Identidade WhatsApp separada e migration 017 de hardening.
- Modal explícito de QR na tela de canais; QR não fica permanentemente exposto.
- Estado do bridge indisponível não é apresentado como conectado.
- Testes de tenant, inbound, realtime e experiência WhatsApp.

### Parcial

**WhatsApp real.** O código chegou a um estado mais maduro, mas o E2E externo ainda não pode ser classificado como pronto. Na validação manual anterior, QR conectou, o número foi identificado incorretamente e inbound real não apareceu. O HEAD atual inclui correções/testes de lifecycle, mas não há evidência nesta auditoria de um novo ciclo real celular → Baileys → bridge → PHP → banco → UI → resposta.

**UX operacional.** Há inbox real, seleção de ticket, unread, sync, fila, contatos, histórico e canal. Ainda existem issues abertas conhecidas de UX/composer/auditoria visual (#97, #105, #110, #114) e QA final (#98). Portanto o Talk não deve ser declarado V1 final.

**Product gate.** As rotas novas de canais usam `ProductAccessMiddleware('talk')`, porém grande parte das rotas `/talk/*` antigas/canônicas ainda usa somente autenticação. Isso precisa ser uniformizado.

### Código morto/duplicado

Em `talk-inbox.php` permanece um bloco antigo de conexão protegido por `elseif(false)`. É código morto e deve ser removido após confirmar que a nova parcial `talk-channels.php` cobre todos os casos.

Há rotas/visões antigas (`/talk/queue`, `/talk/my-tickets`, etc.) coexistindo com `/talk/view/{view}`. Algumas são funcionais e podem ser mantidas como rotas especializadas, mas a navegação canônica precisa ser definida para evitar duas UX do mesmo domínio.

## 5. ERP

### Pronta no backend de fundação

- API v1 e contratos ApiQuery/ApiResponse.
- Idempotency/domain events.
- Administradoras e condomínios.
- Cadastro access/write services.
- Scope grants e autorização.
- MFA/TOTP/security audit/four-eyes.
- Vínculo da administradora ERP ao tenant canônico.
- Testes unitários extensos da camada ERP/security.

### Não iniciada na experiência de produto

A rota `/erp` valida acesso e depois renderiza `platform-placeholder`. Não existe workspace ERP V1 comparável à profundidade do backend.

Portanto, **ERP não deve ser apresentado como produto V1 funcional apenas porque sua fundação existe**.

### Falta para V1

Definir primeiro qual é o ERP V1. Depois construir uma fatia vertical real sobre os cadastros já existentes. Não expandir financeiro, cobrança, unidades, moradores etc. sem escopo aprovado.

## 6. Support

### Pronta — Base de conhecimento

- Artigos, rascunhos, revisões e lixeira.
- Produtos, categorias e tags.
- Help Center público com home, busca, produto, categoria, artigo e feedback.
- Serviços e testes da knowledge base.

### Parcial — Workspace

Dashboard, usuários e relatórios possuem dados reais, mas são majoritariamente métricas da base de conhecimento/usuários globais.

### Não iniciada — Helpdesk

`SupportWorkspaceController` declara explicitamente Inbox, Meus chamados, Todos os chamados e SLA como telas estruturais. O próprio texto das views informa dependência do “módulo de chamados”. Não há domínio completo de tickets Support por trás dessas telas.

`settings` também é principalmente estrutura visual.

### Risco SaaS

`WorkspaceService::users()` consulta a tabela global `users` sem escopo de tenant. Isso é aceitável apenas como implementação histórica interna; não deve ser usado como gestão SaaS de equipe do Support.

## 7. CMS

### Pronta para núcleo editorial V1

- Conteúdo paginado e filtrado no servidor.
- Categorias.
- Tags e relações.
- Lixeira, restauração e exclusão.
- Biblioteca de mídia e detecção de uso.
- Menus e ordenação.
- SEO avançado e preview protegido/noindex.
- Conteúdo público integrado.
- Testes específicos de CMS.

### Parcial

Ainda merece QA de UX, acessibilidade, política de mídia privada/pública e consistência de permissões por tenant. O domínio CMS/Studio nasceu antes da fundação SaaS e precisa de auditoria específica de tenancy antes de ser exposto como produto multiadministradora.

## 8. Studio

### Pronta no núcleo administrativo/editorial

Há rotas reais para dashboard, busca, versões, logs, páginas, projetos, artigos, destaques, depoimentos, FAQ, categorias, tags, lixeira, menus, mídia, propostas, notificações, relatórios, configurações, diagnósticos e usuários.

Rotas `/admin/*` antigas redirecionam por `LegacyStudioController`, o que é uma estratégia de compatibilidade explícita.

### Parcial

- `ProductAccessMiddleware('studio')` é instanciado em `Routes.php`, mas não é aplicado às rotas Studio.
- A separação CMS vs Studio precisa ficar clara: CMS é capacidade editorial; Studio é workspace administrativo. Hoje há sobreposição conceitual e de navegação.
- Necessária auditoria de tenant em queries antigas de Studio/CMS antes de comercializar o produto como isolado por administradora.
- Propostas/notificações/relatórios existem, mas profundidade e escopo SaaS variam.

## Páginas duplicadas, rotas antigas e superfícies enganosas

1. `/profile` legacy vs `/app/profile` canônica.
2. `/admin/*` legacy redirecionando para `/studio/*`.
3. `/layout/*` é laboratório visual development-only; não é produto.
4. `/day` e `/erp` são entradas reais que ainda renderizam placeholder.
5. Support Inbox/My Tickets/Tickets/SLA são shells estruturais, não helpdesk pronto.
6. Talk mantém um bloco antigo de conexão morto em template (`elseif(false)`).
7. Talk possui navegação `/talk/view/*` e rotas especializadas históricas em paralelo.
8. CMS e Studio compartilham domínio/editorial e precisam de fronteira de produto explícita.

## Backend sem interface proporcional

- ERP security/RBAC/MFA/four-eyes é muito mais profundo que a interface ERP.
- Fundação SaaS possui roles/permissions mais ricos que a UI de gestão de permissões.
- Condomínios têm service real, mas a experiência está concentrada em Configurações.
- Auditoria da plataforma existe sem uma central administrativa dedicada.

## Interface sem implementação proporcional

- Meu Dia.
- ERP.
- Support Inbox/My Tickets/Tickets/SLA.
- Várias telas `/layout/*` de autenticação/produtos.
- Parte da experiência de settings do Support.

## Sequência proposta para chegar à V1

### Gate 0 — Congelar o mapa

Usar `MOVES-V1-MATRIX.md` como fonte de verdade. Nenhum novo módulo entra na V1 sem alteração explícita de escopo.

### Gate 1 — Fundação SaaS transversal

Uniformizar product gates; completar memberships/permissões; finalizar onboarding; remover ambiguidades de tenant; auditar CMS/Studio/Support por tenant.

### Gate 2 — Acesso e segurança

Transformar MFA/security em capacidade transversal da plataforma; implementar recuperação/convite/primeiro acesso reais; validar permissões e testes negativos de tenant/objeto.

### Gate 3 — Talk operacional

Concluir #97/#110, executar E2E real WhatsApp, corrigir qualquer falha, fechar auditoria visual #105/#114 e usar #98 como gate final. Só então declarar Talk V1.

### Gate 4 — Meu Dia

Definir e implementar uma única jornada mínima baseada em dados reais dos produtos já existentes. Remover placeholder.

### Gate 5 — Support

Decidir se a V1 inclui somente Knowledge Base ou também Helpdesk. Se incluir Helpdesk, implementar tickets verticalmente antes de manter telas estruturais como disponíveis.

### Gate 6 — ERP

Definir o escopo ERP V1 e construir uma jornada vertical real sobre administradora/condomínio e autorização já existentes. Remover placeholder somente quando houver tarefa real.

### Gate 7 — CMS + Studio

Auditar tenancy, consolidar fronteira CMS/Studio, aplicar entitlement e fazer QA visual/funcional.

### Gate 8 — Homologação V1

Executar matriz E2E por produto, testes A/B de tenant, fresh/upgrade migrations, segurança, acessibilidade/responsividade, smoke HTTP e piloto. Somente depois promover `feat/mvs-dev`.

## Conclusão

A prioridade não é criar mais páginas. É alinhar profundidade, tenancy e gates dos produtos já existentes. A fundação SaaS é utilizável; Talk está próximo de uma V1 operacional mas ainda depende de E2E real e QA; CMS/Studio têm bastante implementação; Support helpdesk, Meu Dia e ERP visual ainda não sustentam uma declaração de V1 funcional.
