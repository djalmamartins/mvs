# 16 — Roadmap até V1

Prioridades: **P0** bloqueia produção; **P1** necessária à operação; **P2** melhoria.

## FASE 1 — Fundação

| Item | Problema | Pri. | Dependências | Backend / frontend / banco | Testes | Critério de aceite |
|---|---|---:|---|---|---|---|
| Tenant obrigatório | risco de vazamento | P0 | modelo de administradora | policy context; seletor seguro; `tenant_id` + índices/FKs | isolamento e acesso negativo | nenhum dado cruza tenants, inclusive arquivo/log |
| Máquina de estados | transições inconsistentes | P0 | eventos | serviço de domínio; feedback; tabela/eventos | unitário/concorrência | somente transições válidas e auditadas |
| Jobs duráveis | polling executa trabalho crítico | P0 | worker/queue | outbox, retries; painel saúde; jobs/outbox | falha/replay | evento não perde nem duplica efeito |

## FASE 2 — Atendimento

| Item | Problema | Pri. | Dependências | Backend / frontend / banco | Testes | Critério de aceite |
|---|---|---:|---|---|---|---|
| Workspace única | duas UIs divergentes | P1 | estados | endpoints coerentes; 3 painéis; projeções | E2E/a11y | fila→assumir→responder→finalizar sem sair da tela |
| Contexto/linha do tempo | visão fragmentada | P1 | ERP adapter | agregador; painel; vínculos/eventos | autorização | mostra apenas contexto permitido e atual |
| Respostas rápidas | produtividade | P1 | variáveis | render seguro; picker; templates | escaping/permissão | prévia e resultado são auditáveis |

## FASE 3 — WhatsApp

| Item | Problema | Pri. | Dependências | Backend / frontend / banco | Testes | Critério de aceite |
|---|---|---:|---|---|---|---|
| Cloud API oficial | canal produtivo | P0 | Meta | webhooks/send/status; onboarding; canais/números | contrato/sandbox | inbound/outbound/status/retry idempotentes |
| Janela e templates | bloqueio regulatório | P0 | canal oficial | policy; aviso no composer; templates | janela/timezone | envio correto dentro/fora da janela |
| Observabilidade | falhas invisíveis | P1 | jobs | métricas/DLQ; saúde; delivery events | caos | falha é diagnosticável e recuperável |

## FASE 4 — Filas

| Item | Problema | Pri. | Dependências | Backend / frontend / banco | Testes | Critério de aceite |
|---|---|---:|---|---|---|---|
| Estratégias/fallback | distribuição incompleta | P1 | presença/jobs | least-load/round-robin; editor; regras | carga/concorrência | sem dupla atribuição e com fallback |
| Horários/SLA | métrica incorreta | P1 | calendário | cálculo útil; configuração; calendários | timezone/feriados | SLA reproduzível e alertável |

## FASE 5 — Supervisão

| Item | Problema | Pri. | Dependências | Backend / frontend / banco | Testes | Critério de aceite |
|---|---|---:|---|---|---|---|
| Painel Agora | pouca visibilidade | P1 | eventos | projeções; KPIs/filtros; agregados | precisão/carga | contagens batem com tickets |
| Intervenção auditada | suporte operacional | P1 | RBAC | comandos; controles; eventos | autorização | supervisor atua somente no escopo |

## FASE 6 — Jack

| Item | Problema | Pri. | Dependências | Backend / frontend / banco | Testes | Critério de aceite |
|---|---|---:|---|---|---|---|
| Orquestrador e handoff | bot básico | P1 | ERP/policy | tools + RAG; resumo/fontes; execuções | eval/red-team | handoff preserva contexto e nenhuma tool excede escopo |
| Base/versionamento | mudança sem controle | P2 | ingestão | versões; editor; documentos/chunks | regressão | rollback e auditoria disponíveis |

## FASE 7 — ERP

| Item | Problema | Pri. | Dependências | Backend / frontend / banco | Testes | Critério de aceite |
|---|---|---:|---|---|---|---|
| Identity Resolution | telefone ambíguo | P0 | tenant | matcher; confirmação; identidades/vínculos | casos ambíguos | vínculo incorreto nunca expõe dados |
| Ferramentas condomínio | atendimento manual | P1 | APIs ERP | boletos/chamados/docs; cards; logs | contrato/autorização | ação idempotente e rastreável |

## FASE 8 — Relatórios

| Item | Problema | Pri. | Dependências | Backend / frontend / banco | Testes | Critério de aceite |
|---|---|---:|---|---|---|---|
| Eventos e percentis | relatório pobre | P1 | event store | agregação; dashboards; fatos/projeções | reconciliação | filtros e KPIs reconciliam com eventos |

## FASE 9 — Administração

| Item | Problema | Pri. | Dependências | Backend / frontend / banco | Testes | Critério de aceite |
|---|---|---:|---|---|---|---|
| RBAC por escopo | privilégio excessivo | P0 | tenant | policy engine; matriz UI; grants | matriz negativa | toda rota declara ação/escopo |
| LGPD/auditoria | risco legal | P0 | identidade/eventos | retenção/exportação; console; consentimentos | segurança | solicitações e acesso têm trilha completa |

## FASE 10 — Hardening para V1

| Item | Problema | Pri. | Dependências | Backend / frontend / banco | Testes | Critério de aceite |
|---|---|---:|---|---|---|---|
| Segurança e resiliência | risco produtivo | P0 | todas | rate limit, secrets, backups; estados de erro; restore | pentest/carga/DR | SLO e RTO/RPO demonstrados |
| Qualidade operacional | regressão | P0 | CI | contratos/runbooks; UX de falha; fixtures | unit/integration/E2E | pipeline bloqueia regressão crítica |
| Go-live controlado | impacto amplo | P1 | observabilidade | feature flags; rollout; config | canário | piloto opera com rollback comprovado |
