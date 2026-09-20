# Changelog

## Moves Support — Knowledge Base v1.0.0 (Release Candidate)

### Added

- Ciclo editorial completo de artigos com SEO, autoria, classificação, capa e mídia.
- Produtos, Categorias, Tags, Rascunhos, Revisões e Lixeira.
- Busca, filtros e paginação no servidor.
- Moves Editor 1.0.0 com autosave isolado por documento.
- 20 artigos reais de documentação do Moves, 3 Produtos, 8 Categorias e 14 Tags.
- Relatórios reais da KB, incluindo contagem de revisões.
- Interfaces finais de Caixa de entrada, Meus chamados, Todos os chamados e SLA em estado vazio real.

### Changed

- Formulário de artigo reorganizado em workspace principal e painel lateral responsivo.
- Sidebar do produto com rolagem própria para viewports de baixa altura.
- Componentes do Support alinhados à tipografia, Moves Icons e Application Shell oficiais.

### Fixed

- Layout quebrado em `/support/articles/create`.
- Persistência segura de largura e alinhamento de imagens do Moves Editor.
- Conflitos de slug manual agora retornam mensagem amigável.
- Exclusão de Produto ou Categoria em uso agora é bloqueada.
- Conteúdo do editor é sanitizado antes de ser armazenado.
- Imagem de capa passa a participar da proteção contra exclusão de mídia em uso.

### Known limitations

- Ticket engine ainda não habilitado.
- Backend e políticas de SLA ainda não habilitados.
- Restauração automática de uma revisão ainda não habilitada; snapshots podem ser consultados.
- Help Center público não faz parte deste Release Candidate.

