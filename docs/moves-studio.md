# Moves Studio

O Moves Studio é o CMS e painel administrativo oficial da plataforma Moves. Esta primeira rodada substitui a apresentação do admin técnico por um shell próprio, sem antecipar CRUDs ou alterar a fundação de segurança.

## Antigo x novo

| Antigo | Novo | Motivo |
| --- | --- | --- |
| Header técnico “Moves Admin” | Sidebar e topbar do Moves Studio | Dar identidade e navegação escalável ao CMS |
| `/admin` abria a listagem de usuários | `/admin` abre um dashboard com dados reais | Separar visão geral da gestão de usuários |
| Usuários, configurações e diagnóstico isolados | Telas preservadas dentro do mesmo shell | Manter funcionalidades e permissões existentes |

## Navegação

- Visão geral: Dashboard é funcional; Relatórios, Notificações e Propostas estão visíveis e desabilitados.
- Conteúdo: Páginas, Artigos, Mídia, Destaques, Depoimentos e FAQ estão visíveis e desabilitados.
- Gestão: Usuários e Configurações são funcionais; Versões e Log estão visíveis e desabilitados.
- Diagnóstico continua funcional em `/admin/diagnostics`, acessível pelo Dashboard e por Configurações.

Itens planejados são elementos sem link, identificados como “Em breve”. Nenhuma rota vazia ou destino 404 foi criado.

## Dashboard

Os indicadores usam somente fontes existentes: quantidade real de usuários, resultado real de `Diagnostics::run()` e nome persistido da aplicação. Os módulos de CMS declaram honestamente “Não iniciado”. Não há métricas simuladas.

## Segurança e escopo

As rotas continuam protegidas por `AuthMiddleware` e `PermissionMiddleware`, usando as permissões atuais `users.manage`, `settings.manage` e `diagnostics.view`. CSRF do logout e de Configurações foi preservado. Nenhum CRUD editorial, banco adicional, leitura de logs, proposta, relatório ou notificação foi implementado nesta rodada.

## Próximas rodadas

1. consolidar Dashboard e gestão técnica existente;
2. criar biblioteca de Mídia;
3. implementar Artigos e fazer `/conteudo` ler do banco;
4. evoluir Páginas, Destaques, Depoimentos e FAQ;
5. implementar Propostas e integrar o envio de `/contato`;
6. adicionar Relatórios e Notificações.
