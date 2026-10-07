# MOVES V1 — Estado e usabilidade

**Atualizado:** 2026-10-07

**Branch avaliada:** `feat/mvs-v1-integration`
**HEAD avaliado:** `7f41a10` (`test(erp): cover complex condominium ownership scenario`)
**Regra:** somente código, testes e homologações presentes neste HEAD contam. Branches e PRs abertos são listados à parte e não são tratados como integrados.

## Classificação de uso

| Módulo | Classificação | Uso comprovado neste HEAD | Homologação pendente | Pode usar? |
|---|---|---|---|---|
| Acesso/Segurança | USÁVEL COM LIMITAÇÕES | Login/logout, CSRF, throttling, MFA, recuperação e convite têm rotas e implementação. O smoke HTTP local confirmou `/login` e os redirecionamentos de visitante. | Revisão de segurança final, E2E negativo completo e validação de SMTP/ambiente de release (#286). | Desenvolvimento e teste interno; não libera produção. |
| Meu Dia | USÁVEL COM LIMITAÇÕES | Dashboard consulta tarefas, eventos, pendências e tickets Talk; tarefa pode ser atualizada com POST e CSRF. | Homologação final entre produtos e segurança/UX do release (#283, #287, #288). | Desenvolvimento e piloto interno controlado depois dos gates. |
| Talk | USÁVEL COM LIMITAÇÕES | Caixa de atendimento, tickets, fila, contatos, histórico, atribuição, anexos, notas e operações têm implementação e testes locais. | WhatsApp com dispositivo/número reais, inbound/outbound e UX final ainda não têm homologação E2E de piloto (#282, #283, #288). | Desenvolvimento e teste interno; não operar atendimento real sem homologar o canal. |
| Support | ESTRUTURAL | Base de conhecimento/help público existe. O núcleo de helpdesk nesta branch ainda mostra telas estruturais. | Criar, persistir, visualizar, mudar status, histórico e isolamento do chamado. PR #297 está em branch separada e não integra este HEAD. | Somente inspecionar a base de conhecimento; não usar como helpdesk. |
| ERP | USÁVEL COM LIMITAÇÕES | Cadastro de administradora/condomínio, blocos/unidades, pessoas/vínculos, fornecedores, competências, plano de contas, recebíveis manuais e pendências têm rotas e serviços reais. Cenários HTTP documentados em #253/#255 cobrem tenant A/B. | Billing/fatura/boletos, pagamento/baixa, banco/conciliação, fechamento e prestação de contas não estão concluídos ou dependem de decisões de domínio (#257–#260). A implementação de Payables no commit `7049841` está em `feat/erp-payables-foundation`, fora deste HEAD. | Cadastro e homologação interna com dados sintéticos; não usar para movimentação financeira de produção. |
| Studio | USÁVEL COM LIMITAÇÕES | Conteúdo, mídia, páginas, artigos, taxonomias, menus, revisões e operações administrativas têm persistência real. Artigos publicados alimentam `/conteudo` e `/conteudo/{slug}`. | Revisão final de tenant, acessibilidade e viewports (#268–#276, #287). | Publicação de teste em ambiente interno; produção aguarda release gates. |

**Estado global da V1:** desenvolvimento avançado, sem liberação para produção. Nenhum módulo foi classificado como `PRONTO PARA USO INTERNO` ou liberado para piloto formal neste ciclo.

## Capacidades, evidência e limites

| Capacidade | Estado | Evidência/teste | Bloqueio ou limite | Pode usar? |
|---|---|---|---|---|
| Login, sessão, CSRF, logout e MFA | FUNCIONAL | `AuthController`, `/login`, `/login/2fa`, `/logout`; 50 verificações HTTP registradas no ciclo REL-002; testes Auth/MFA. | Revisão final de segurança e ambiente alvo ainda pendentes. | Teste interno. |
| Recuperação de senha e primeiro acesso | FUNCIONAL | `PasswordRecoveryController`, `InvitationController`, rotas `/forgot-password`, `/password-recovery/*`, `/first-access`; testes de serviço. | Entrega real de e-mail/SMTP e E2E de release devem ser homologados. | Teste interno. |
| Painel Meu Dia e mudança de estado de tarefa | FUNCIONAL | `DayController`, `DayService`, POST `/day/tasks/{id}` protegido por CSRF; testes `DayServiceTest` e E2E registrado em #253/#255. | O conteúdo agregado depende das fontes já ativas por tenant; QA transversal final pendente. | Desenvolvimento e teste interno. |
| Atendimento Talk e isolamento de tenant | FUNCIONAL | `TalkController`, `TalkService`, rotas protegidas e testes `TalkTenantIsolationTest`. | Não prova operação real WhatsApp nem piloto completo. | Teste interno com dados/canais de teste. |
| Canal WhatsApp real | PARCIAL | `ChannelService`, bridge Baileys, outbox, identidade JID/LID e testes Node 10/10. | Sem evidência completa de dispositivo/número real atravessando inbound e resposta até o browser. | Não usar em operação real. |
| Base de conhecimento Support | FUNCIONAL | Artigos, revisões, taxonomias e help público têm controllers/services/testes. | Verificar isolamento/UX final. | Consulta interna. |
| Helpdesk Support | ESTRUTURAL | `SupportWorkspaceController` envia Inbox, Meus chamados, Todos os chamados e SLA para `support-structural`. | TicketService do PR #297 está em branch separada; não contar como integrado. | Não usar para operar chamados. |
| Cadastros e estrutura ERP | FUNCIONAL | Módulos ERP e cenários HTTP em #253/#255: cinco condomínios, cinco blocos, 35 unidades, pessoas, vínculos e isolamento A/B. | Atributos físicos adicionais não foram especificados; #253 continua aberta por esse escopo. | Teste interno com dados sintéticos. |
| Plano de contas, competência e recebível manual | FUNCIONAL | Serviços/controllers/repositories e testes ERP existentes; recebível é valor/parcelas informado pelo operador. | Não é fatura gerada nem integração de cobrança. Sem emissão/baixa bancária. | Teste interno; não usar para contabilização oficial. |
| Contas a pagar persistente | NÃO IMPLEMENTADO NESTE HEAD | Commit `7049841` e migrations estão publicados em `feat/erp-payables-foundation`, não em `feat/mvs-v1-integration`. | Aguardar integração controlada; não presumir dados/rotas disponíveis. | Não usar nesta branch. |
| Fatura, boleto, Pix, baixa e conciliação | NÃO IMPLEMENTADO | A auditoria de domínio em `docs/erp/ERP-DOMAIN-MODEL.md` registra fontes, composição, copropriedade e idempotência ainda sem contrato aprovado. | Issues #257–#260; não inventar regra financeira. | Não usar. |
| Conteúdo público `/conteudo/{slug}` | FUNCIONAL | Rota em `app/Boot/Routes.php`; `Home::content()` lista artigos publicados; `CmsService::publishedArticle()` limita a artigo publicado, não removido e com data de publicação já atingida; template `pages/article.php`. | Teste cobre futuro, rascunho e removido; E2E visual final faz parte de #287/#288. | Teste editorial interno. |
| Gate de produção neste Mac | PARCIAL | Antes: `.env` `djalmamartins:admin`, modo `0644`; o gate apontou a permissão insegura. Depois: modo `0600` com ACL `daemon:read`. Com variáveis de produção explícitas, `scripts/check-production.php` passa; usando o `.env` local atual, o gate corretamente falha por `APP_DEBUG`, `APP_URL` e opções de cookie de desenvolvimento. Apache atende `/login` em HTTP 200. | A configuração local não é de produção e a ACL é específica deste Mac. Validar configuração e permissões no ambiente alvo durante o release. | Não é evidência de produção. |

## Releases e branches não integradas

- PR #297 (`feat/mvs-support`) está aberto; o código de tickets não foi contado como parte da integração.
- PR #296 (`feat/mvs-access-security`) está aberto; só o código presente no HEAD desta branch foi avaliado.
- `feat/erp-payables-foundation` contém `7049841` após este HEAD; sua implementação não está disponível na integração V1 até ser integrada e novamente testada.
- O CI atual dispara em `main` e `feat/mvs-dev`, não em `feat/mvs-v1-integration`; os gates locais não substituem o CI remoto de integração.

## Estado de release

| Gate | Estado | Evidência/pendência |
|---|---|---|
| Instalação limpa e upgrade com preservação | PARCIALMENTE HOMOLOGADO | REL-002 fechada com MySQL descartável e upgrade da migration de Payables; essa migration está fora do HEAD de integração. |
| Gate de produção | PERMISSÃO LOCAL OK; CONFIGURAÇÃO PENDENTE | `.env` está em `0600` com leitura ACL para `daemon`; o gate passa com valores seguros de produção explícitos e rejeita corretamente a configuração de desenvolvimento do `.env` local. O ambiente de produção não foi inspecionado. |
| Documentação fiel ao código | EM ATUALIZAÇÃO | Esta matriz é a fonte vigente. `MOVES-V1-AUDIT.md` é mantido como snapshot histórico. |
| E2E cross-product | PENDENTE | #283/#288. |
| Segurança e isolamento | PENDENTE | #286. |
| UX, acessibilidade e viewports | PENDENTE | #287. |
| Piloto operacional | PENDENTE | #289. |
| Aprovação e tag de release | PENDENTE | #290–#293. |

### Diferença de ambiente

- **Desenvolvimento:** rotas e capacidades descritas acima existem nesta branch; o estado pode mudar antes de integrar outras branches.
- **Piloto interno:** nenhum produto está aprovado para piloto formal até concluir E2E, segurança, UX, isolamento e definição das operações financeiras.
- **Produção:** não liberada. A #284 permanece aberta e o ambiente de produção ainda não passou pelo gate.

## O que o usuário consegue fazer agora

### Pode testar agora

- Entrar, encerrar sessão, executar MFA e percorrer recuperação/primeiro acesso em ambiente de teste.
- Consultar Meu Dia e atualizar tarefas de teste com proteção CSRF.
- Testar filas, tickets e histórico do Talk com dados sintéticos.
- Cadastrar condomínios, blocos, unidades, pessoas, fornecedores, competências e estrutura contábil; registrar recebíveis manuais de homologação.
- Publicar artigo de teste no Studio e verificar `/conteudo` e `/conteudo/{slug}`.

### Pode usar em piloto interno

- Nenhum fluxo foi aprovado formalmente neste ciclo. É possível fazer homologações isoladas com dados sintéticos e canal WhatsApp de teste.

### Ainda não use em produção

- Atendimento WhatsApp real sem E2E do dispositivo/número e resposta.
- Helpdesk Support para abrir/atribuir/acompanhar chamados.
- Fatura, cobrança, boleto, Pix, pagamento, baixa, banco, conciliação, fechamento ou prestação de contas.
- Qualquer operação financeira desta branch como livro oficial.

## Próximos gates

1. Fechar #284 após a auditoria final contra o estado integrado e os bloqueios de release resolvidos.
2. Integrar e validar branches abertas uma por vez; nunca presumir conteúdo de PR como presente no HEAD.
3. Completar Support operacional, E2E cross-product, segurança, UX e isolamento multitenant.
4. Definir as regras de domínio financeiro antes de implementar faturas/cobranças/pagamentos.
5. Homologar piloto, obter aprovação de release, publicar RC/tag apenas depois de todos os gates verdes.
