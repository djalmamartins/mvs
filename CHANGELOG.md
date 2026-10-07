# Changelog

## ERP — fundação de contas a pagar (#300, 2026-10-07)

### Added

- Registro e consulta de obrigações por fornecedor vigente, condomínio, plano ativo e contas analíticas de passivo/despesa.
- Parcelas com valor, vencimento e competência aberta explícitos; total conferido em centavos.
- Auditoria transacional, autorização ERP, CSRF, isolamento tenant e FKs compostas.
- E2E HTTP de criação/consulta e regressões de isolamento em MySQL descartável.

### Limites

- Sem aprovação, quatro-olhos, rateio, recorrência, anexos, retenções, pagamento, banco, ledger ou saldo.
- Detalhes de domínio e gates em `docs/erp/ERP-DOMAIN-MODEL.md` e `docs/erp/ERP-OPERATIONAL-SIMULATION.md`.

## ERP — correções de ponta a ponta para CNPJ alfanumérico (2026-10-07)

- Busca de fornecedor preserva letras do CNPJ e não amplia a consulta para todos os documentos quando a consulta contém caracteres alfanuméricos.
- Lista de condomínios e lista de fornecedores formatam CNPJ pelo serviço compartilhado.
- Cadastro e edição da administradora preservam e validam o CNPJ alfanumérico, sincronizando tenant e administradora em uma transação; campos da plataforma aceitam entrada textual.
- Testes HTTP cobrem busca restrita de fornecedor e exibição formatada em condomínio; teste de serviço cobre criação/edição da administradora.
- Evidências, matriz CNPJ/CPF e estado da #253 estão em `docs/erp/ERP-OPERATIONAL-SIMULATION.md`.

## ERP — homologação cadastral com CNPJ alfanumérico (2026-10-07)

- Pessoas e fornecedores agora normalizam/validam CNPJ com o serviço compartilhado, aceitando o formato alfanumérico oficial; CPF conserva validação separada.
- Busca e detalhes exibem e encontram CNPJ alfanumérico; formulários aceitam letras e indicam o formato textual esperado.
- Homologação dos cenários operacionais sintéticos e evidências: `docs/erp/ERP-OPERATIONAL-SIMULATION.md`.

## ERP — histórico de atendimento de fornecedores

### Fixed

- Encerramento de vínculo temporal entre fornecedor e condomínio usa parâmetros PDO nomeados distintos, permitindo persistir a data final em MySQL sem erro HY093.
- Teste de serviço e E2E HTTP cobrem a preservação do histórico, auditoria e isolamento tenant dessa operação.

## ERP — edição de identificação da unidade

### Added

- Edição tenant-scoped do identificador e complemento de uma unidade existente, com auditoria transacional.
- Condomínio, bloco, situação e vínculos pessoais/temporais permanecem inalterados neste fluxo.

### Limites desta etapa

- Não adiciona atributos físicos sem evidência (andar, área, vaga ou tipo), nem altera bloco, situação, fração ideal ou vínculos.
- A issue #253 continua aberta para os demais campos estruturais comprovados e homologação operacional.

## ERP — Fundação de contas a receber

### Added

- Cadastro e consulta tenant-scoped de títulos a receber ligados a unidade e vínculo pessoal ativo.
- Parcelas com valores, vencimentos e competências abertas informados explicitamente; a soma deve coincidir com o total.
- Classificação por contas analíticas de ativo e receita do plano do condomínio, auditoria transacional e FKs compostas.

### Limites desta etapa

- Um título para uma ou mais parcelas é decisão explícita Moves, motivada por um exemplo auditado, sem generalizar cardinalidade do APControle.
- Sem recebimento/estorno, cobrança/fatura/boleto, juros, rateio, banco, conciliação, ledger ou saldo.

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
