# Changelog

## ERP — Plano de contas persistente

### Added

- Plano único por condomínio, ligado por chave composta à administradora ativa do tenant.
- Contas hierárquicas com código, nome, natureza Moves, tipo sintético/analítico, pai, nível, ordenação e situação.
- Listagem, detalhe, criação/edição, busca por código/nome e filtros de natureza/situação no ERP.
- Auditoria transacional, CSRF e isolamento de administradora/plano.

### Limites desta etapa

- Sem template global, versionamento, centro de custo, lançamentos, ledger, pagamentos, recebimentos, fechamento ou integração com bancos.
- O código e a hierarquia são únicos e validados no plano; exclusão física não é disponibilizada.

## ERP — Fração ideal cadastral

### Added

- Percentual ideal opcional (0–100%, quatro casas decimais) em vínculo temporal de proprietário e unidade.
- Exibição no histórico da pessoa e na unidade, com valor persistido e auditado.
- Validações de faixa, papel e unidade tanto no serviço quanto na constraint MySQL.

### Limites desta etapa

- Não totaliza frações entre proprietários nem executa rateio ou cobrança.
- Alterações de titularidade/fração são registradas por encerramento e novo vínculo, preservando o histórico anterior.

## ERP — Competência mensal aberta

### Added

- Cadastro persistente de competências mensais abertas por condomínio, com filtros por condomínio, ano e situação, detalhe e trilha de auditoria.
- Unicidade no banco por administradora, condomínio, ano e mês, com validação de tenant, autorização ERP e CSRF.

### Limites desta etapa

- Competências permanecem abertas. Fechamento, reabertura e efeitos financeiros não foram implementados.

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
