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

## Concluído na sprint final do CMS v1.0.0

- Paginação server-side de 20 registros com total, páginas, anterior/próxima e query string preservada.
- Filtros combináveis por busca, status, autor e categoria.
- Tags próprias do domínio CMS, relações, contagem e exclusão protegida.
- Autosave local por usuário/documento sem alteração do core do Moves Editor.
- Lixeira com restauração do status anterior seguro e exclusão permanente explícita.
- Preview autenticado de rascunhos com `noindex,nofollow`.
- SEO avançado com palavra-chave, canonical e Robots persistidos.
- Proteção de mídia por vínculos diretos e referências exatas no HTML do CMS e Support.
- Menus e itens com tipos Página interna/URL externa, ordem, hierarquia e status.

## Parcial conhecido e aceito

- Categorias são criadas/associadas pelo formulário, sem tela própria para editar, contar ou excluir.
- Legendas pertencem à figura no editor; `studio_media` mantém ALT, mas não possui legenda global.

## Bugs e riscos registrados

1. A exclusão permanente da lixeira é irreversível e permanece protegida por confirmação e autorização.
2. A hierarquia de menus é persistida; o tema público atual apresenta a navegação principal conforme sua capacidade visual.

Auditoria atualizada após implementação, suíte automatizada e homologação browser da sprint final.
