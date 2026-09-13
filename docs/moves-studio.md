# Moves Studio

O Moves Studio é o CMS e painel administrativo oficial da plataforma Moves. A fundação e a primeira consolidação técnica estão prontas, sem antecipar CRUDs editoriais nem alterar a fundação de segurança. A auditoria funcional do antecessor está em `docs/moves-studio-audit.md`.

## Antigo x novo

| Antigo | Novo | Motivo |
| --- | --- | --- |
| Header técnico “Moves Admin” | Sidebar e topbar do Moves Studio | Dar identidade e navegação escalável ao CMS |
| `/admin` abria a listagem de usuários | `/admin` abre um dashboard com dados reais | Separar visão geral da gestão de usuários |
| Usuários, configurações e diagnóstico isolados | Telas preservadas dentro do mesmo shell | Manter funcionalidades e permissões existentes |

## Navegação

- Visão geral: Dashboard consolidado é funcional; Relatórios, Notificações e Propostas estão visíveis e desabilitados.
- Conteúdo: Páginas, Artigos, Mídia, Destaques, Depoimentos e FAQ estão visíveis e desabilitados.
- Gestão: Usuários, Configurações, Versões informativa e Log somente leitura são funcionais.
- Diagnóstico continua funcional em `/admin/diagnostics`, acessível pelo Dashboard e por Configurações.

Itens planejados são elementos sem link, identificados como “Em breve”. Nenhuma rota vazia ou destino 404 foi criado.

## Dashboard

Os indicadores usam somente fontes existentes: quantidade real de usuários, resultado real de `Diagnostics::run()`, ambiente, versão declarada, nome persistido e eventos recentes do Logger. Os módulos de CMS declaram honestamente “Não iniciado”. Não há métricas simuladas.

## Gestão técnica

- `/admin/versions` informa Moves, PHP, ambiente, banco, migrations, tema e presença do lock do Composer. Não executa atualização, release ou rollback.
- `/admin/logs` lê no máximo os 2.000 registros mais recentes do JSONL atual, aceita busca, nível e paginação, e reaplica sanitização de contexto. Não revela caminho do arquivo nem oferece mutações.

## Segurança e escopo

As rotas continuam protegidas por `AuthMiddleware` e `PermissionMiddleware`, usando as permissões atuais `users.manage`, `settings.manage` e `diagnostics.view`. CSRF do logout e de Configurações foi preservado. Nenhum CRUD editorial, banco adicional, leitura de logs, proposta, relatório ou notificação foi implementado nesta rodada.

## Roadmap

1. **Fundação — concluída:** shell, Dashboard, Usuários, Configurações e Diagnóstico.
2. **Gestão técnica — concluída:** Dashboard consolidado, Versões informativa e Log seguro.
3. **CMS base:** Mídia; Artigos; `/conteudo` e `/conteudo/{slug}` lendo do banco.
4. **CMS institucional:** Páginas, Destaques, Depoimentos e FAQ.
5. **Comercial:** propostas persistidas a partir de `/contato`; pipeline; notificações.
6. **Relatórios:** métricas e estatísticas somente após existirem fontes confiáveis.
