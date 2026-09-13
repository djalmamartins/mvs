# Roadmap de lançamento — Moves

Objetivo: transformar o estado auditado em um lançamento seguro sem recriar o ERP condominial. Estimativas: `S` até 2 dias, `M` 3–5 dias, `L` 1–2 semanas, `XL` várias semanas; devem ser recalibradas após desenho técnico.

## Fronteiras de lançamento

- **Marco 1 — Base operável:** P0 completo.
- **Marco 2 — Site + Studio:** P1 público/Studio completo; pode ser lançado sem portal comercializado.
- **Marco 3 — Área do Cliente:** P1 de cliente completo e autorização aprovada.
- **Marco 4 — Evolução:** P2/P3 guiados por uso real.

Portanto, faltam **3 etapas obrigatórias** para o lançamento completo (P0, P1 site/Studio e P1 cliente) e uma etapa contínua pós-lançamento.

## P0 — Bloqueadores de lançamento

| Tarefa | Objetivo | Dependências | Impacto | Risco | Esforço |
|---|---|---|---|---|---:|
| Runner de migrations | Ledger, lock, execução única, falha controlada e evidência. | Backup e banco de homologação. | Evita drift/corrupção. | Alto | M |
| Backup + restore drill | Recuperar banco e storage dentro de objetivo definido. | Credenciais/storage de produção. | Reduz perda irrecuperável. | Alto | M |
| Pipeline e runbook | Validate, audit, lint, PHPStan, testes, migration, smoke e rollback. | Ambiente de homologação. | Deploy repetível. | Alto | M/L |
| Gate de configuração | Bloquear debug/secrets/cookies/URLs/permissões inseguros. | Matriz por ambiente. | Evita exposição acidental. | Alto | S/M |
| Hardening do contato | Rate limit, honeypot, idempotência e telemetria. | Política de abuso. | Protege aquisição e banco. | Alto | S/M |
| Outbox mínima de proposta | Entrega rastreável, retries e alerta de falha. | SMTP/provedor e worker. | Evita lead perdido. | Alto | M |
| Auditoria mínima | Registrar mutações críticas em usuário, conteúdo, proposta e configuração. | Esquema/eventos. | Investigação e accountability. | Médio/alto | M |
| Política de mídia | Estados público/privado, acesso e limpeza segura. | Inventário de referências. | Evita exposição/orfandade. | Médio | M |
| Smoke e e2e críticos | Home→contato, login, CRUD editorial, proposta e logout. | Ambiente estável. | Gate de regressão. | Alto | M |

Critério de saída: restore executado, deploy reprodutível, migrations testadas em banco limpo e cópia, formulário protegido, alertas chegando e jornada crítica verde.

## P1 — Necessário para lançar

### Site e Studio

| Tarefa | Objetivo | Dependências | Impacto | Risco | Esforço |
|---|---|---|---|---|---:|
| Conteúdo/legal final | Revisar textos, dados de contato, privacidade e termos. | Decisão jurídica/comercial. | Confiança e conformidade. | Médio | S/M |
| Acessibilidade WCAG 2.2 AA | Teclado, foco, contraste, nomes e leitor de tela. | Componentes estáveis. | Inclusão e qualidade. | Médio | M |
| Matriz responsiva/browser | Validar breakpoints e navegadores suportados. | Testes browser. | Evita quebra visual. | Médio | M |
| Observabilidade mínima | Uptime, erros, latência, fila e falha de job com alertas. | Infra de produção. | Detecção rápida. | Alto | M |
| Empty/error states uniformes | Dar causa, recuperação e ação em todos os módulos. | Design system. | Reduz suporte. | Baixo | S/M |

### Área do Cliente

| Tarefa | Objetivo | Dependências | Impacto | Risco | Esforço |
|---|---|---|---|---|---:|
| Modelo cliente/membro | Organização, usuários, papéis e estado. | Decisões de tenancy. | Fundação do portal. | Alto | L |
| Ownership central | Autorizar cada objeto por cliente e papel. | Modelo cliente. | Previne IDOR. | Muito alto | L |
| Conversão de proposta | Criar cliente/projeto de forma idempotente e auditada. | Cliente + projeto. | Fecha funil comercial. | Alto | M |
| Serviços e projetos | Dados reais, status, marcos e timeline somente leitura. | Ownership + schema. | Valor principal do portal. | Alto | L |
| Chamados mínimos | Abrir, listar, responder, fechar e anexar com segurança. | Ownership, outbox e storage privado. | Canal de suporte. | Alto | L |
| Recuperação de acesso | Convite, troca e reset seguro de senha. | Outbox. | Autonomia do cliente. | Alto | M |
| Notificações do cliente | Eventos, leitura e preferências essenciais. | Outbox + ownership. | Comunicação confiável. | Médio | M |
| Testes de autorização | Casos positivos e negativos por cliente/objeto. | Todos os módulos do portal. | Segurança de lançamento. | Muito alto | M/L |

Critério de saída: um cliente convidado acompanha projeto/serviço, abre e responde chamado, recebe eventos e nunca acessa objetos de outro cliente.

## P2 — Melhorias importantes

| Tarefa | Objetivo | Dependências | Impacto | Risco | Esforço |
|---|---|---|---|---|---:|
| Autosave e histórico editorial | Evitar perda e permitir reversão. | Modelo de revisões. | Alto para equipe editorial. | Médio | M/L |
| Preview seguro | Revisar antes de publicar. | Tokens/ownership editorial. | Reduz erro público. | Médio | M |
| Filtros e ações em lote | Ganho operacional em listas grandes. | APIs/queries uniformes. | Médio. | Baixo | M |
| Exports CSV/PDF úteis | Portabilidade e operação comercial. | Métricas/campos definidos. | Médio. | Médio | M |
| Proposta em PDF | Artefato comercial versionado. | Templates e histórico. | Médio. | Médio | M |
| Documentos privados | Compartilhar entregáveis com autorização. | Storage privado + antivirus. | Alto para alguns clientes. | Alto | L |
| Performance budgets | Medir e bloquear regressões. | CI/browser. | Qualidade contínua. | Baixo | M |
| Dados estruturados SEO | Ampliar interpretação por buscadores. | Conteúdo final. | Médio. | Baixo | S/M |

## P3 — Pós-lançamento

| Tarefa | Objetivo | Dependências | Impacto | Risco | Esforço |
|---|---|---|---|---|---:|
| Faturas/pagamentos integrados | Expor financeiro sem construir um banco próprio. | Provedor/ERP financeiro. | Variável. | Alto | L/XL |
| Domínios/hospedagem | Status e ações com dados reais. | APIs de provisionamento. | Variável. | Alto | L/XL |
| Agenda/tarefas | Coordenação interna ou integração. | Processo validado. | Médio. | Médio | L |
| Automações/webhooks | Integrar CRM, suporte e operação. | Outbox e auditoria maduras. | Alto em escala. | Alto | L |
| BI/SLA avançado | Tendências, funil e eficiência. | Volume e qualidade dos dados. | Médio. | Médio | L |
| Colaboração editorial | Aprovação, comentários e papéis avançados. | Revisões e equipe maior. | Variável. | Médio | L |

## Ordem recomendada da próxima implementação

1. Runner de migrations + teste em banco limpo/cópia.
2. Backup/restore e gate de configuração.
3. Pipeline de deploy com smoke e rollback.
4. Hardening do contato + outbox observável.
5. Auditoria e política de mídia.
6. Fechar qualidade/legal do site + Studio.
7. Só então iniciar o domínio `cliente → membro → serviço/projeto → ownership`.
8. Construir o portal verticalmente: uma jornada real completa antes de adicionar todos os cards.

Essa ordem reduz o maior risco atual: criar mais módulos sobre uma base que ainda não possui operação de produção e fronteira de dados por cliente comprovadas.

## Decisões de escopo

- Não bloquear o lançamento de site + Studio por faturas, hospedagem, domínios ou agenda.
- Não mostrar módulos vazios como disponíveis ao cliente; usar feature flags.
- Não migrar tabelas/fluxos condominiais.
- Não incluir métricas, notificações ou relatórios sem fonte real.
- Não aceitar anexos privados antes de ownership, storage privado e varredura apropriada.
- Não iniciar UI do portal antes de estabilizar seu modelo de autorização.

## Definition of done do lançamento

- P0 concluído e evidenciado;
- testes automáticos verdes no CI e smoke verde após deploy;
- rollback e restore ensaiados;
- debug desligado, secrets externos ao repositório e headers/cookies validados;
- logs e alertas sem dados sensíveis;
- conteúdo/legal aprovado;
- se o portal estiver no escopo, jornada cliente completa e testes de IDOR aprovados;
- responsável operacional, canal de incidente e critérios de rollback definidos.
