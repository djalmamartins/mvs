# MOVES V1 — Matriz de estado

Data: 2026-09-27  
Fonte: auditoria da `feat/mvs-dev`.

| Área | Item | Estado | Evidência atual | Falta para V1 |
|---|---|---|---|---|
| Fundação SaaS | Tenant canônico | Pronta | talk_tenants + TenantContext + testes A/B | Manter como única fonte de tenant |
| Fundação SaaS | Administradora | Pronta | CompanyService + migration 015 | Refinar lifecycle administrativo |
| Fundação SaaS | Produtos/entitlements | Parcial | ProductEntitlement + middleware | Aplicar gate uniformemente em todas as rotas |
| Fundação SaaS | Memberships | Parcial | MemberService + roles por tenant | Convite, desativação/remoção e gestão completa |
| Fundação SaaS | Condomínios | Pronta | CondominiumService escopado por tenant | Melhorar UX e lifecycle conforme ERP V1 |
| Fundação SaaS | Troca de tenant | Pronta | TenantController/TenantContext | QA visual e negativo |
| Fundação SaaS | Auditoria | Pronta | PlatformAudit | UI administrativa pode evoluir |
| Fundação SaaS | Configurações | Parcial | /settings real | Separar seções e aprofundar permissões |
| Fundação SaaS | Onboarding | Parcial | 4 etapas funcionais | Estrutura, importação, telefone, confirmação, checklist |
| Acesso/Segurança | Login/logout/sessão | Pronta | AuthController/Core/Auth | E2E browser final |
| Acesso/Segurança | CSRF/throttle/permissões | Pronta | Core + middleware | QA final |
| Acesso/Segurança | RBAC por tenant | Pronta | platform_roles/permissions | UI granular |
| Acesso/Segurança | MFA/TOTP | Parcial | implementação/testes em Erp/Security | Tornar política transversal da plataforma |
| Acesso/Segurança | Four-eyes/audit security | Parcial | services e testes ERP/security | Integrar a operações sensíveis da plataforma |
| Acesso/Segurança | Recuperação de senha | Não iniciada | apenas laboratório /layout | Fluxo real seguro |
| Acesso/Segurança | Convite/primeiro acesso | Não iniciada | apenas laboratório /layout | Fluxo real + membership |
| Meu Dia | Dashboard real | Não iniciada | /day renderiza platform-placeholder | Definir e implementar dados/jornada |
| Meu Dia | Agenda/tarefas | Não iniciada | sem domínio/service canônico | Definir escopo V1 |
| Talk | Isolamento multitenant | Pronta | TalkTenantContext + testes | Manter regressões |
| Talk | Fila/claim/atribuição | Pronta | TalkService/Metadata + testes | QA operacional |
| Talk | Tickets/conversas/contatos | Pronta | services/controllers/views | QA operacional |
| Talk | Transferir/finalizar/reabrir | Pronta | TalkService + controller | E2E operacional |
| Talk | Tags/notas/prioridade/anexos | Pronta | services + controller | QA visual |
| Talk | Notificações/realtime sync | Parcial | sync + NotificationService | validar browser/realtime sob uso real |
| Talk | Jack | Parcial | settings/interactions existem | validar comportamento real/escopo V1 |
| Talk | Canais WhatsApp | Parcial | ChannelService + modal QR + multissessão | E2E real e saúde/reconexão |
| Talk | WhatsApp inbound | Quebrada | falha manual anterior; código corrigido depois sem novo E2E externo comprovado | validar mensagem real ponta a ponta |
| Talk | WhatsApp outbound | Parcial | Outbound/Outbox/transport | validar resposta real ponta a ponta |
| Talk | Identidade JID/LID/telefone | Parcial | WhatsAppIdentity + migration 017 + testes | confirmar número real após nova conexão |
| Talk | UX final | Parcial | inbox/workspace reais | concluir #97/#105/#110/#114 |
| Talk | QA final | Parcial | muitos testes locais | concluir #98 + piloto |
| ERP | API/contracts | Pronta | ApiQuery/ApiResponse/Idempotency | Integrar à jornada escolhida |
| ERP | Administradoras/condomínios | Pronta | repositories/services/migrations | UI de produto |
| ERP | Scopes/autorização | Pronta | security stack + testes | integração transversal |
| ERP | Workspace ERP | Não iniciada | /erp = platform-placeholder | definir ERP V1 e implementar vertical |
| Support | Knowledge Base | Pronta | artigos/revisões/taxonomias/help público | QA tenancy/UX |
| Support | Dashboard | Parcial | métricas reais de knowledge base | alinhar com escopo do produto |
| Support | Usuários | Parcial | query real, mas global | escopar por tenant |
| Support | Relatórios | Parcial | relatórios de KB | definir relatórios do helpdesk |
| Support | Inbox | Não iniciada | support-structural | implementar tickets ou retirar da V1 |
| Support | Meus chamados | Não iniciada | support-structural | implementar tickets ou retirar da V1 |
| Support | Todos os chamados | Não iniciada | support-structural | implementar tickets ou retirar da V1 |
| Support | SLA | Não iniciada | support-structural | depende do domínio de chamados |
| Support | Settings atendimento | Parcial | estrutura visual | persistência/regras reais |
| CMS | Conteúdo editorial | Pronta | CmsService + StudioModulesController | QA tenant |
| CMS | Categorias/tags | Pronta | CRUD + testes | QA tenant |
| CMS | Mídia | Pronta | biblioteca + usage | política pública/privada e tenancy |
| CMS | Lixeira/restauração | Pronta | service + testes | QA |
| CMS | Menus | Pronta | persistência/ordenação | QA |
| CMS | SEO/preview | Pronta | campos + preview noindex | QA público |
| CMS | Isolamento SaaS | Parcial | domínio histórico anterior ao tenant | auditoria e testes A/B específicos |
| Studio | Dashboard/admin | Pronta | controllers/views reais | QA |
| Studio | Conteúdo/mídia | Pronta | rotas e serviços reais | tenancy + entitlement |
| Studio | Propostas | Parcial | operação existe | validar profundidade V1 |
| Studio | Notificações | Parcial | operação existe | preferências/escopo/tenant |
| Studio | Relatórios | Parcial | operação existe | definir métricas V1 |
| Studio | Logs/diagnóstico/versões | Pronta | controllers e rotas protegidas | QA operacional |
| Studio | Entitlement | Quebrada | $studioProduct criado, não aplicado | aplicar ProductAccessMiddleware |
| Legado/Lab | /layout/* | Fora da V1 | development-only | usar apenas como referência visual |
| Legado/Lab | /admin/* | Fora da V1 | redirects para Studio | manter só compatibilidade temporária |
| Legado/Lab | /profile | Fora da V1 | rota legacy | consolidar em /app/profile |
| Legado/Lab | Talk conexão antiga | Fora da V1 | bloco elseif(false) | remover após QA da nova conexão |

## Gates de conclusão

| Gate | Condição de saída |
|---|---|
| G0 — Escopo | Esta matriz aprovada como fonte de verdade |
| G1 — SaaS | Gates de produto, memberships, onboarding e tenant uniformes |
| G2 — Segurança | recuperação/convite reais, MFA transversal e testes negativos |
| G3 — Talk | inbound + outbound WhatsApp reais, UX/QA issues fechadas, piloto pronto |
| G4 — Meu Dia | placeholder substituído por jornada mínima com dados reais |
| G5 — Support | decidir KB-only ou implementar helpdesk vertical |
| G6 — ERP | escopo V1 aprovado e pelo menos uma jornada funcional sem placeholder |
| G7 — CMS/Studio | tenancy e entitlements auditados; QA concluído |
| G8 — Homologação | fresh/upgrade, A/B tenant, browser, smoke, segurança e piloto verdes |

## Regra de execução

Nenhuma nova funcionalidade deve ser iniciada fora desta matriz até o G0 ser aprovado. Itens classificados como “Não iniciada” não devem permanecer visíveis como se fossem produtos concluídos; ou entram no escopo com implementação vertical, ou ficam ocultos/feature-gated até estarem prontos.
