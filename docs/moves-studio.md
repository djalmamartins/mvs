# Moves Studio

O Moves Studio é o CMS e painel administrativo oficial da plataforma Moves. A migração funcional aprovada foi concluída sobre a fundação atual de segurança e persistência. A auditoria funcional do antecessor está em `docs/moves-studio-audit.md`.

## Antigo x novo

| Antigo | Novo | Motivo |
| --- | --- | --- |
| Header técnico “Moves Admin” | Sidebar e topbar do Moves Studio | Dar identidade e navegação escalável ao CMS |
| `/studio` abria a listagem de usuários | `/studio` abre um dashboard com dados reais | Separar visão geral da gestão de usuários |
| Usuários, configurações e diagnóstico isolados | Telas preservadas dentro do mesmo shell | Manter funcionalidades e permissões existentes |

## Navegação

- A rota oficial é `/studio`.
- Bookmarks GET em `/admin` recebem redirect permanente para o equivalente em `/studio`, preservando a query string.
- Não existem formulários ou mutações oficiais em `/admin`.

- Visão geral: Dashboard, Relatórios, Notificações e Propostas são funcionais.
- Conteúdo: Páginas, Projetos, Artigos, Mídia, Destaques, Depoimentos e FAQ são funcionais.
- Gestão: Usuários, Configurações, Versões informativa e Log somente leitura são funcionais.
- Diagnóstico continua funcional em `/studio/diagnostics`, acessível pelo Dashboard e por Configurações.

Todos os itens exibidos na navegação apontam para rotas existentes e protegidas.

## Dashboard

Os indicadores usam somente fontes existentes: usuários, saúde de `Diagnostics::run()`, ambiente, versão, propostas, conteúdo persistido e eventos recentes do Logger. Todos os atalhos do CMS apontam para módulos funcionais. Não há métricas simuladas.

## Gestão técnica

- `/studio/users` permite pesquisar, filtrar, criar e editar contas, alterar estado e excluir contas que não tenham vínculos protegidos. O administrador primário e a sessão atual recebem proteções adicionais.
- `/studio/settings` mantém somente identidade pública, SEO básico, contato e redes sociais. Credenciais SMTP, pagamentos e outros segredos continuam fora da interface.
- `/studio/versions` informa o ambiente e registra um histórico semântico auditável por autor. O registro não executa deploy, atualização, tag ou rollback.
- `/studio/logs` lê no máximo os 2.000 registros mais recentes do JSONL sanitizado e permite classificar eventos existentes como aberto, resolvido ou ignorado. O conteúdo original permanece imutável e o caminho do arquivo não é revelado.
- `/studio/media` mantém a biblioteca de imagens, texto alternativo, recortes derivados e associações protegidas com conteúdos.
- `/studio/articles`, `/studio/pages`, `/studio/projects`, `/studio/highlights` e `/studio/testimonials` oferecem editores específicos com mídia, SEO, status e ordenação. Artigos alimentam `/conteudo`; projetos alimentam o componente de portfólio reutilizado na home e em `/projetos`.
- `/studio/faq` organiza perguntas e respostas por categoria; `/studio/proposals` mantém pipeline e histórico; `/studio/notifications` oferece contador e ações por destinatário; `/studio/reports` agrega dados reais e exporta CSV.
- `/studio/search` pesquisa conteúdo, usuários e propostas. Páginas publicadas usam `/pagina/{slug}`, FAQ usa `/faq`, e destaques/depoimentos publicados alimentam a página inicial respeitando ordem e período.

## Fundação visual

O Studio usa a identidade visual do tema de referência fornecido em `studio.zip`: sidebar e topbar brancas, realce roxo, marca MovesOS, busca global, cards compactos, perfil suspenso semântico, tema claro/escuro persistente, menu recolhível e rodapé de versão. O raio padrão é 6 px, controles têm 44 px e o texto funcional não fica abaixo de 14 px. O CSS autoritativo fica em `public/themes/admin/css/studio-reference.css`, com tokens em `design-system.css` e adaptação dos templates atuais isolada em `compat.css`.

Todos os ícones do shell usam o sprite SVG local. O Moves Editor é um componente nativo, sem CDN, fonte de ícones ou dependência de editor externo.

## Segurança e escopo

As rotas continuam protegidas por `AuthMiddleware` e `PermissionMiddleware`, com permissões separadas para dashboard, busca, conteúdo, mídia, propostas, notificações, relatórios, usuários, configurações, diagnóstico e log. CSRF cobre logout e toda mutação administrativa. O ZIP foi utilizado apenas como referência de apresentação; seu roteamento e código de negócio não foi executado nem incorporado.

## Roadmap

1. **Fundação — concluída:** shell, Dashboard, Usuários, Configurações e Diagnóstico.
2. **Gestão técnica — concluída:** Dashboard consolidado, Versões informativa e Log seguro.
3. **CMS base — concluída:** Mídia, Artigos, `/conteudo` e `/conteudo/{slug}` lendo do banco.
4. **CMS institucional — concluída:** Páginas, Destaques, Depoimentos e FAQ.
5. **Comercial — concluída:** propostas persistidas a partir de `/contato` e notificações.
6. **Relatórios — concluída:** contagens reais dos módulos persistidos, sem métricas simuladas.

## Homologação integrada

`php scripts/seed-studio-demo.php` cadastra ou atualiza, somente no ambiente local não produtivo, exemplos publicados de página, artigo, destaque, depoimento, FAQ e projeto, além de uma proposta e uma notificação. O comando é idempotente e serve para inspeção manual de todos os componentes.

`php scripts/test-admin-settings.php` executa o fluxo HTTP autenticado de cadastro e publicação dos módulos, busca global, mídia e recorte, proposta, notificação e relatório. Os registros aleatórios desse teste são removidos ao final.
