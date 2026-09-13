# Moves Studio

O Moves Studio é o CMS e painel administrativo oficial da plataforma Moves. A fundação e a primeira consolidação técnica estão prontas, sem antecipar CRUDs editoriais nem alterar a fundação de segurança. A auditoria funcional do antecessor está em `docs/moves-studio-audit.md`.

## Antigo x novo

| Antigo | Novo | Motivo |
| --- | --- | --- |
| Header técnico “Moves Admin” | Sidebar e topbar do Moves Studio | Dar identidade e navegação escalável ao CMS |
| `/admin` abria a listagem de usuários | `/admin` abre um dashboard com dados reais | Separar visão geral da gestão de usuários |
| Usuários, configurações e diagnóstico isolados | Telas preservadas dentro do mesmo shell | Manter funcionalidades e permissões existentes |

## Navegação

- Visão geral: Dashboard, Relatórios, Notificações e Propostas são funcionais.
- Conteúdo: Páginas, Artigos, Mídia, Destaques, Depoimentos e FAQ são funcionais.
- Gestão: Usuários, Configurações, Versões informativa e Log somente leitura são funcionais.
- Diagnóstico continua funcional em `/admin/diagnostics`, acessível pelo Dashboard e por Configurações.

Todos os itens exibidos na navegação apontam para rotas existentes e protegidas.

## Dashboard

Os indicadores usam somente fontes existentes: quantidade real de usuários, resultado real de `Diagnostics::run()`, ambiente, versão declarada, nome persistido e eventos recentes do Logger. Os módulos de CMS declaram honestamente “Não iniciado”. Não há métricas simuladas.

## Gestão técnica

- `/admin/versions` informa Moves, PHP, ambiente, banco, migrations, tema e presença do lock do Composer. Não executa atualização, release ou rollback.
- `/admin/logs` lê no máximo os 2.000 registros mais recentes do JSONL atual, aceita busca, nível e paginação, e reaplica sanitização de contexto. Não revela caminho do arquivo nem oferece mutações.

## Fundação visual

O Studio usa a identidade visual do tema de referência fornecido em `studio.zip`: sidebar branca, realce roxo, marca MovesOS, busca no topo, cards compactos, perfil suspenso, tema claro/escuro persistente, menu recolhível e rodapé de versão. A camada foi adaptada às rotas `/admin` e aos dados atuais, sem importar controllers, consultas ou autenticação do sistema antigo. O CSS autoritativo da referência fica em `public/themes/admin/css/studio-reference.css`, com compatibilidade local isolada em `compat.css`.

## Segurança e escopo

As rotas continuam protegidas por `AuthMiddleware` e `PermissionMiddleware`, usando as permissões atuais `users.manage`, `settings.manage` e `diagnostics.view`. CSRF do logout, configurações e módulos mutáveis foi preservado. O ZIP foi utilizado apenas como referência de apresentação; seu roteamento e código de negócio não foram executados nem incorporados.

## Roadmap

1. **Fundação — concluída:** shell, Dashboard, Usuários, Configurações e Diagnóstico.
2. **Gestão técnica — concluída:** Dashboard consolidado, Versões informativa e Log seguro.
3. **CMS base — concluída:** Mídia, Artigos, `/conteudo` e `/conteudo/{slug}` lendo do banco.
4. **CMS institucional — concluída:** Páginas, Destaques, Depoimentos e FAQ.
5. **Comercial — concluída:** propostas persistidas a partir de `/contato` e notificações.
6. **Relatórios — concluída:** contagens reais dos módulos persistidos, sem métricas simuladas.
