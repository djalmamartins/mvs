# Auditoria documental do Moves CMS

Auditoria executada em 20/09/2026 antes da publicação dos guias oficiais na Base de Conhecimento. Código, rotas, banco, navegador e testes foram tratados como fonte da verdade.

## Funcional

- Dashboard com contagens reais e atalhos.
- Conteúdo protegido pelas permissões `content.manage` e `media.manage`.
- Páginas, Projetos, Artigos, Destaques, Depoimentos e FAQ em `studio_content`.
- Status `draft`, `published` e `archived`.
- Slug normalizado e rota pública automática para Páginas (`/pagina/{slug}`) e Artigos (`/conteudo/{slug}`).
- Categorias criadas e associadas no formulário de Artigos, Projetos e FAQ.
- Moves Editor 1.0.0 com modos Visual/HTML, headings, listas, links, imagens, tabelas, preview e tela cheia.
- Biblioteca `studio_media`, upload, busca, seleção, ALT, crop e proteção de exclusão para vínculos diretos.
- SEO do CMS com título e descrição, incluindo fallback para os campos editoriais.
- Revisões e restauração para Páginas e Artigos.

## Parcial

- Listagens pesquisam texto e status, limitadas a 200 registros e sem paginação.
- Categorias são criadas/associadas pelo formulário, sem tela própria para editar, contar ou excluir.
- Proteção de exclusão de mídia cobre vínculos diretos, mas não referências antigas inseridas no HTML.
- Legendas pertencem à figura no editor; `studio_media` não possui metadado próprio de legenda.

## Inexistente e não documentado como funcional

- Tags no CMS.
- Autosave local e recuperação automática no editor do CMS.
- Lixeira/soft delete do conteúdo do CMS.
- Preview de conteúdo não publicado por URL pública.
- Menus e navegação administráveis.
- Focus keyword, canonical e Robots no SEO do CMS.
- Paginação e filtros por autor/categoria nas listas do CMS.

## Bugs e riscos registrados

1. `StudioModulesController::media()` não detecta referências de mídia existentes dentro do HTML antes da exclusão. Esperado: impedir exclusão ou atualizar referências de maneira transacional.
2. A exclusão de conteúdo do CMS é definitiva. A interface deve manter esse caráter explícito até existir uma lixeira transacional.
3. Listas com mais de 200 itens deixam de oferecer acesso aos registros excedentes por não haver paginação.

Esses pontos não foram refatorados nesta entrega, cujo escopo é Help Center e documentação oficial.
