# Gaps de produto e lançamento — Moves

Este documento deriva da auditoria comparativa de 13/09/2026. A prioridade considera valor para o produto Moves atual, não o volume de código existente no ERP legado.

## Recursos ausentes

### Crítico

| Recurso | Impacto | Recomendação |
|---|---|---|
| Pipeline/runbook de deploy com rollback | Mudanças não são promovidas de modo reprodutível. | Criar checklist automatizado de ambiente, migration, cache, permissões, smoke e rollback. |
| Migrations com ledger e execução segura | Reaplicação ou falha parcial pode quebrar produção. | Introduzir tabela de migrations, transação quando suportada, backup prévio e teste em banco limpo/cópia. |
| Backup e restore testado | Falha de banco/storage pode ser irreversível. | Automatizar backup, retenção, criptografia, restore periódico e evidência do ensaio. |
| Antispam/rate limit no contato | Formulário público pode gerar abuso e poluição de propostas. | Limite dedicado, honeypot, idempotência e alerta de volume. |
| Configuração/gate de produção | Debug, cookies, APP_URL, SMTP ou permissões incorretas podem expor dados. | Falhar deploy se invariantes de produção não forem atendidas. |

### Alto

| Recurso | Impacto | Recomendação |
|---|---|---|
| Cliente/organização e ownership | Sem ele não existe Área do Cliente segura. | Modelar cliente, membros, papéis e vínculo por objeto antes de criar telas. |
| Conversão proposta → cliente/projeto | O fluxo comercial termina manualmente. | Ação idempotente, auditada, com confirmação e prevenção de duplicidade. |
| Projetos e serviços do cliente | Portal não entrega acompanhamento real. | MVP somente leitura com status, marcos, datas e responsáveis. |
| Chamados e respostas | Suporte prometido dependeria de canal externo. | Recriar fluxo mínimo do helpdesk legado, com autorização por ticket. |
| Recuperação/troca de senha segura | Cliente bloqueado exige intervenção administrativa. | Token forte, hash no banco, expiração, uso único, rate limit e resposta neutra. |
| E-mail transacional e fila | Propostas, recuperação e alertas não têm entrega confiável. | Outbox/queue com tentativas, idempotência, status e worker monitorado. |
| Auditoria de mutações | Incidentes e alterações não são integralmente rastreáveis. | Log append-only de ator/ação/alvo, diffs seguros e correlação. |
| Política de mídia pública/privada | ID previsível pode revelar asset não publicado. | Estado de publicação e autorização; storage privado para documentos. |

### Médio

- preview autenticado e link temporário antes da publicação;
- autosave, rascunho recuperável e histórico editorial;
- filtros consistentes, filtros persistentes e ações em lote;
- exportações CSV/PDF selecionadas por necessidade real;
- documentos privados do cliente com download autorizado;
- notificações do cliente, preferências e marcação de leitura;
- dashboards baseados em métricas acionáveis;
- políticas de retenção de logs, mídia e dados pessoais;
- testes e2e, acessibilidade, responsividade e performance;
- sitemap/schema.org e validação final do SEO em produção;
- páginas legais e consentimentos compatíveis com o tratamento real de dados.

### Baixo

- atalhos de teclado no editor;
- ordenação por drag-and-drop onde trouxer ganho claro;
- preferências de visualização por usuário;
- importação/exportação de conteúdo para portabilidade;
- refinamento de ranking e sugestões da busca global.

### Futuro

- domínios e hospedagem com fonte real de provisionamento;
- faturas/pagamentos por integração com provedor financeiro;
- agenda e tarefas internas, preferencialmente integradas antes de serem recriadas;
- SLA avançado, automações comerciais e webhooks;
- relatórios analíticos avançados após existir volume confiável.

## Recursos existentes que precisam melhorar

| Recurso | Problema atual | Impacto | Solução recomendada | Esforço | Prioridade |
|---|---|---|---|---:|---:|
| Formulário de contato | Persiste, mas não tem defesa dedicada nem entrega externa confiável. | Spam e proposta sem resposta. | Throttle, honeypot, outbox e status de entrega. | M | P0 |
| Migrations | SQL sequencial sem histórico de execução. | Drift/falha em deploy. | Runner com ledger, locks e testes. | M | P0 |
| Logs | Cobertura por ação não é uniforme. | Baixa rastreabilidade. | Evento de auditoria central e política de retenção. | M | P0 |
| Mídia | Boa UX, governança incompleta. | Órfãos, quota e exposição indevida. | Visibilidade, referência, limpeza segura e quotas. | M | P1 |
| Usuários | Papéis amplos; sem lifecycle de convite/recuperação. | Administração manual e risco futuro. | Convite, redefinição e permissões por módulo/cliente. | M | P1 |
| Propostas | Não converte nem gera artefato comercial. | Retrabalho. | Conversão auditada primeiro; PDF depois. | M/L | P1/P2 |
| Notificações | Principalmente internas. | Usuário não recebe eventos relevantes. | Preferências + fila transacional observável. | L | P1 |
| Relatórios | Cobertura reduzida e sem exports úteis. | Gestão limitada. | Definir decisões suportadas e métricas de origem comprovada. | M | P2 |
| Editor | Fluxo principal pronto, sem autosave/revisão/preview uniforme. | Perda de trabalho e erro editorial. | Snapshot local/servidor, histórico e preview. | M | P2 |
| Responsividade | Implementação visual sem matriz de regressão. | Quebra silenciosa em dispositivos. | Playwright/screenshots em breakpoints-alvo. | M | P1 |
| Acessibilidade | Boas bases, sem conformidade demonstrada. | Exclusão e risco reputacional. | Auditoria WCAG 2.2 AA, teclado, foco e leitor de tela. | M | P1 |
| Performance | Sem budgets ou monitoramento. | Regressões passam despercebidas. | Lighthouse/medidas reais, budgets e cache consciente. | M | P2 |
| Empty/error states | Existem, mas variam por módulo. | Dificulta recuperação do usuário. | Padrão de estado com causa, ação e suporte. | S/M | P1 |

## O que impede o lançamento hoje?

### Bloqueador

1. Não há processo de deploy/rollback comprovado.
2. Migrations não têm ledger, repetibilidade e ensaio de falha.
3. Não há backup/restauração testados.
4. O contato público precisa de proteção contra abuso e entrega rastreável.
5. Configuração e secrets de produção não possuem gate automatizado.
6. Se a Área do Cliente fizer parte da oferta: não há modelo de cliente/ownership nem tarefas reais no portal.

### Importante

- recuperação de senha segura antes de cadastrar clientes externos;
- trilha de auditoria consistente;
- política pública/privada para mídia e futuros anexos;
- CI com testes, lint, análise estática, migrations e smoke HTTP;
- revisão legal/privacidade e acessibilidade;
- monitoramento de erros, disponibilidade e fila.

### Desejável

- autosave/preview/histórico editorial;
- relatórios e exportações adicionais;
- ações em lote e filtros persistentes;
- proposta em PDF;
- analytics e automações avançadas.

## MVP de lançamento

### MVP A — site + Studio interno

- site, conteúdo, portfólio, contato e SEO atuais;
- módulos Studio atuais, com um administrador responsável;
- P0 operacional completo: migrations, deploy, backup/restore, configuração e observabilidade mínima;
- antispam e fila/alerta de proposta;
- política de mídia e logs;
- smoke/e2e das jornadas públicas e administrativas críticas;
- páginas legais e conteúdo de produção revisados.

### MVP B — Área do Cliente

Além do MVP A:

- cliente/organização, membros e ownership;
- convite, login, perfil, troca e recuperação de senha;
- dashboard com dados reais;
- serviços e projetos do cliente, inicialmente somente leitura;
- chamados com mensagens e anexos autorizados;
- notificações transacionais e histórico essencial;
- matriz de autorização com testes de IDOR.

### Pós-lançamento

- documentos gerais, faturas e pagamentos;
- domínios/hospedagem e integrações de provisionamento;
- propostas PDF e assinatura;
- agenda/tarefas internas;
- automações avançadas, webhooks e BI;
- colaboração editorial avançada.

## Funções importantes do antigo a reconstruir

Reconstruir no novo domínio e padrões: helpdesk mínimo, fila de e-mail, jobs agendados, proposta PDF quando priorizada, exports úteis, trilha de auditoria, migrations/deploy check, backup/restore e conceitos de ownership. Reaproveitar requisitos e casos de uso; não copiar controllers, sessão, SQL ou markup legado.

## O que não deve ser migrado

- condomínios, blocos, unidades, proprietários e moradores;
- visitas de campo, geolocalização, checklists e saúde condominial;
- assembleias, comunicados condominiais e prestação de contas;
- carteiras, inadimplência, transações bancárias e conciliação do ERP;
- duplicações de CMS entre Studio, Operation e Helpdesk;
- logout mutável por GET;
- recuperação com `md5/uniqid/rand` e mensagens que enumeram contas;
- relatórios sem fonte real, dashboards decorativos e configurações sem consumidor;
- abstrações genéricas do legado que escondem autorização ou ownership.

## Sugestões novas

Separadas das funções herdadas:

1. **Centro de saúde operacional:** deploy, backup recente, fila, storage, erros e uptime em uma visão administrativa.
2. **Preview compartilhável com expiração:** revisão de conteúdo/projeto sem publicação prematura.
3. **Linha do tempo unificada do cliente:** proposta, projeto, chamado e comunicação, respeitando permissões.
4. **Outbox idempotente:** base única para e-mail, notificações e futuros webhooks.
5. **Feature flags por módulo:** ativar portal e integrações gradualmente sem navegação morta.
6. **Budgets de qualidade:** impedir regressões de performance, acessibilidade e tamanho de assets no CI.
7. **Retenção por classe de dado:** regras distintas para logs, propostas, mídias e documentos privados.
