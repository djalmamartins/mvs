# Moves Support — Knowledge Base v1.0.0

Status: Release Candidate, aguardando homologação do usuário.

## Escopo

A Knowledge Base cobre artigos, produtos, categorias, tags, rascunhos, revisões de conteúdo, lixeira, busca, filtros, paginação, SEO, autoria, capa, mídia e o Moves Editor 1.0.0. Caixa de entrada, Meus chamados, Todos os chamados e SLA possuem interface final e estado vazio real; o motor de tickets e o backend de SLA não fazem parte desta versão.

## Fluxo e rotas

- `/support`: dashboard e indicadores reais.
- `/support/articles`, `/support/articles/create`, `/support/articles/{slug}/edit`: listagem e ciclo editorial.
- `/support/products`, `/support/categories`, `/support/tags`: taxonomia.
- `/support/drafts`, `/support/revisions`, `/support/trash`: estados e histórico.
- `/support/users`, `/support/reports`, `/support/settings`: consulta e administração do workspace.
- `/support/inbox`, `/support/my-tickets`, `/support/tickets`, `/support/sla`: preparação visual do atendimento futuro.

Todas as rotas administrativas usam `AuthMiddleware`. Escritas usam POST, validação CSRF no controller e regras de domínio nos serviços.

## Arquitetura

O roteamento está em `app/Boot/Routes.php`. `SupportController` atende artigos; `SupportKnowledgeController`, a taxonomia; e `SupportWorkspaceController`, dashboard e páginas estruturais. As regras ficam em `ArticleService`, `ProductService`, `CategoryService`, `TagService` e `WorkspaceService`. Models de Support representam as tabelas e as views usam o Application Shell e Moves Icons existentes.

## Dados

- `support_articles`: conteúdo, estado, SEO, autoria, capa, publicação e exclusão lógica.
- `support_products` e `support_categories`: classificação principal e hierárquica.
- `support_tags` e `support_article_tags`: classificação transversal N:N.
- `support_article_revisions`: snapshots de título, resumo e conteúdo.
- `studio_media`: biblioteca oficial compartilhada, inclusive para capa e imagens do editor.

As migrations `20260916_001`, `20260917_001` e `20260919_001` formam a estrutura atual.

## Editor, mídia e autosave

O formulário integra os arquivos oficiais de distribuição do Moves Editor 1.0.0. A biblioteca de capa e a biblioteca interna do editor consultam `studio_media`; não existe gerenciador paralelo. O draft local usa uma chave por documento, só oferece conteúdo relevante do mesmo documento e é removido após salvamento confirmado. O HTML é sanitizado novamente no servidor antes da persistência.

## Estados editoriais

- `draft`: conteúdo salvo para trabalho interno.
- `published`: conteúdo publicado e com data de publicação.
- `archived`: conteúdo preservado fora do estado publicado.
- lixeira: exclusão lógica; não participa de dashboard e relatórios normais.

Restaurar preserva o registro e suas relações. Excluir permanentemente remove o artigo e dependências. Revisões são histórico de conteúdo, não backup integral.

## Documentação inicial

Execute `php scripts/seed-support-knowledge.php`. O processo é idempotente, usa os serviços oficiais e mantém 3 Produtos, 8 Categorias, 14 Tags e os 20 artigos oficiais em português. Registros descartáveis conhecidos das rodadas anteriores são removidos de forma explícita pelo mesmo processo.

## Segurança e integridade

O backend valida relações, status, URLs canonical, autoria, mídia e unicidade do slug manual. Produtos ou Categorias relacionados não podem ser excluídos. Categorias bloqueiam auto-relacionamento e ciclos. O sanitizador remove scripts, handlers, protocolos perigosos e atributos não permitidos, preservando somente apresentação de figura produzida pelo editor.

## Limitações conhecidas

- Motor de tickets, filas, conversas e atribuição ainda não habilitado.
- Backend e políticas reais de SLA ainda não habilitados.
- Restauração automática de snapshots de revisão fica para uma versão posterior; nesta versão é possível visualizar o histórico.
- A publicação pública/Help Center não faz parte deste escopo.

## Homologação manual

### 01 — Novo artigo

Rota: `/support/articles/create`. Título: `Homologação manual da Knowledge Base`. Resumo: `Validação manual do ciclo editorial do Moves Support.` Conteúdo: `<h2>Objetivo</h2><p>Confirmar criação, edição, mídia, SEO e publicação sem regressões.</p><h2>Resultado</h2><p>O artigo deve persistir e reaparecer corretamente.</p>`. Produto: `Moves Support`. Categoria: `Artigos`. Tags: `artigos`, `editor`, `seo`. Meta title: `Homologação da Knowledge Base | Moves`. Meta description: `Validação manual do fluxo editorial e do editor do Moves Support.` Palavra-chave: `homologação knowledge base`. Status: Rascunho. Esperado: salvar sem erro, slug `homologacao-manual-da-knowledge-base` e aparecer em Rascunhos.

### 02 — Editar e publicar

Abra o artigo do teste 01. Acrescente ao fim: `<p>Publicação homologada por Djalma.</p>`, altere o status para Publicado e salve. Esperado: conteúdo atualizado, data de publicação e uma revisão anterior.

### 03 — Rascunho local

No mesmo artigo, digite sem salvar: `Texto temporário exclusivo do teste de autosave 2026.` Aguarde o indicador de autosave e recarregue. Esperado: oferecer recuperação uma vez; ao recusar, não perguntar novamente para o mesmo draft.

### 04 — Busca

Rota: `/support/articles`. Pesquise `Homologação manual`. Esperado: somente o artigo do teste e total coerente.

### 05 — Filtros

Combine Produto `Moves Support`, Categoria `Artigos`, Status `Publicado` e busca `Homologação`. Esperado: parâmetros preservados na paginação e resultado compatível.

### 06 — Lixeira e restauração

Crie `DESCARTÁVEL — Teste de restauração`, mova para a lixeira, abra `/support/trash` e restaure. Esperado: sumir das métricas normais enquanto estiver na lixeira e voltar com conteúdo e relações.

### 07 — Exclusão permanente

Crie `DESCARTÁVEL — Teste de exclusão permanente`, mova para a lixeira e exclua definitivamente. Esperado: não reaparecer em busca, lixeira ou métricas.

### 08 — Produto

Em `/support/products`, crie `DESCARTÁVEL — Produto homologação`, edite a descrição e exclua sem relacionamentos. Depois tente excluir `Moves Support`. Esperado: o descartável é removido; o produto em uso é protegido com mensagem amigável.

### 09 — Categoria

Em `/support/categories`, crie `DESCARTÁVEL — Categoria homologação` no Produto `Moves Support`, posição `90`. Tente defini-la como própria categoria pai. Esperado: bloqueio do ciclo. Remova o registro ao final.

### 10 — Tag

Em `/support/tags`, crie `homologacao-manual`, relacione-a ao artigo do teste 01, salve, remova a relação e salve novamente. Esperado: contagens e relação acompanharem cada alteração. Exclua a tag ao final, se a interface disponibilizar a ação segura.

### 11 — Imagem

No artigo do teste 01, envie ou escolha uma imagem real. Defina ALT `Captura da homologação do Moves Support`, legenda `Interface validada durante a homologação`, largura `65%` e alinhamento central. Repita com `420px`, salve e reabra. Esperado: imagem, ALT, legenda, unidade e alinhamento persistirem; capa deve ser selecionável pela biblioteca oficial.

### 12 — SEO

Use os valores do teste 01, marque index/follow e salve. Esperado: reabrir com os mesmos valores e sem permitir canonical inválida. Canonical válida opcional: `http://mvs.lab/suporte/homologacao-manual-da-knowledge-base`.

### 13 — Revisões

Troque o primeiro parágrafo para `Confirmar novamente o histórico de alterações do Moves Support.` e salve. Abra `/support/revisions`. Esperado: novo snapshot com título, data e autor; a tela não deve chamá-lo de backup completo.

### 14 — Responsividade

Teste `/support`, `/support/articles`, `/support/articles/create`, `/support/revisions` e `/support/inbox` em larguras 1920, 1440, 1280 e aproximadamente 768 px. Esperado: sem scroll horizontal global, editor utilizável, painel lateral reorganizado e tabelas/drawer legíveis.

### 15 — Sidebar

Reduza a altura da janela para aproximadamente 650 px. Role apenas o menu lateral com mouse/trackpad até Configurações. Esperado: identidade e Rail permanecem organizados, último item acessível e nenhum scroll horizontal.

