# Roadmap de paridade operacional do Moves ERP

Este roadmap prioriza dependências do trabalho real em um condomínio. Ele não afirma que a auditoria de referência esteja completa. Escopo e filtros de vários relatórios ainda precisam de confirmação em `APCONTROLE-FUNCTIONAL-MAP.md`.

## P0 — operar um condomínio por um mês no Moves

1. **Fechar cadastros operacionais** — campos mínimos de condomínio, unidade/bloco, pessoa e fornecedor; fração ideal; filtros/busca; papéis e vínculos temporais; validações e trilha de alterações. Dependências: tenant, ACL e estrutura já existentes.
2. **Plano de contas e dimensões** — plano hierárquico, contas ativas, centros de custo e política para mudar códigos usados. Dependência: cadastro de condomínio.
3. **Contas a pagar** — título, fornecedor, documento/anexo, competência, parcelas, vencimento, descontos/encargos, rateio por unidade/conta, aprovação e pagamento. Idempotência e auditoria para cada transição.
4. **Contas a receber e cobrança** — regras versionadas por condomínio, itens ordinários/extras/consumo, emissão por competência, relação a pessoas e unidades, valor devido, recebimento, estorno e inadimplência derivada. Cobrança consolidada opcional agrega por titular no mesmo condomínio, mantém itens por unidade e aceita baixa parcial.
5. **Caixa e bancos** — conta bancária, saldo derivado, transferência interna, movimentos originados de pagamento/recebimento e referências externas idempotentes.
6. **Conciliação** — importar extrato em staging descartável, validar duplicatas, parear sugestão/manual, lidar com diferença e preservar desfazer/auditoria. Executar sandbox com arquivos anonimizados.
7. **Competência e fechamento** — estado de período, bloqueio de lançamentos, regra de correção pós-fechamento e relatório de diferenças na reabertura.
8. **Prestação de contas** — saldo inicial, receitas, despesas, fundos, movimentos, anexos, documentos, aprovação/publicação e saldo final calculados da mesma fonte contábil.
9. **Gate operacional ponta a ponta** — fixtures sintéticas isoladas para: pessoa com várias unidades; coproprietários; lançamentos e parcelas; faturamento consolidado e distribuição; recebimento parcial; pagamento; extrato, conciliação, fechamento e prestação. Testar isolamento entre tenants.

## P1 — paridade do catálogo observado

- Faturas/boletos, acordos, juros/multa, rateios por regra, arquivo de retorno, remessa/OFX e recibos após definir integrações bancárias e sandbox.
- Leituras, relógios/medidores, tarifas e consumo para produzir itens de cobrança com aprovação e reprocessamento controlado.
- Orçamento anual, comparação previsto/realizado, contratos de fornecedor, vigência/reajuste e alertas.
- Relatórios priorizados por dependência: razão/balancete/saldos, lançamentos, pagar/receber, recebimentos, inadimplência, rateio, prestação de contas; depois catálogo auxiliar. Todos rastreáveis a filtros e contas.
- Documentos e anexos com permissões, retenção, hash, versionamento e antivírus.
- Configurações de portal do morador, demonstrativo e notificação com fronteiras de acesso.

## P2 — melhoria de operação

- Agenda de manutenção, comunicados, ocorrências, reservas/espera/multas de áreas comuns, mudança, seguro, portaria e entregas integradas a unidades e pessoas.
- Importadores assistidos para cadastros/planilhas, análise de prévia e erros por linha; nunca publicar silenciosamente.
- Mala direta, avisos e relatórios de logs/acesso com política de privacidade e autorização.

## P3 — diferenciais Moves

- Coordenação de tarefa e alertas financeiros em Meu Dia, com permissões e links ao objeto fonte.
- Talk/Support contextual para dúvidas de prestação/cobrança sem expor registros de outras unidades.
- Automação auditável baseada em regras e eventos; IA futura somente com fontes autorizadas, justificativa e aprovação humana para ações financeiras.
- Visão consolidada de carteira multiadministradora e investidor multiunidade, preservando isolamento e detalhamento por unidade.

## Critérios de conclusão

Paridade operacional só é atingida quando cenários P0 executam uma competência completa sem planilha/APControle e reconciliações são demonstráveis por entidade, usuário e documento. Definir indicadores exatos de paridade após abrir os filtros e resultados dos relatórios pendentes. Cada marco exige migration reexecutável onde possível, testes de isolamento/permissão, PHPUnit/PHPStan/lint, revisão UX com o Design System, documentação e CI verde. Operações bancárias e dados reais exigem sandbox e homologação explícita.

## Dependências observadas nesta rodada

- Balancete anual, balanço patrimonial e resultado do exercício recebem o **fechamento/período encerrado** como entrada. O relatório contábil anual, portanto, depende do fechamento segundo a UI observada.
- Livro razão e demonstrativos recebem condomínio/período/conta ou opções de contas; plano de contas é uma dimensão visível de relatório e configuração. A relação de origem exata de cada total não foi verificada sem gerar o relatório.
- O relatório de centro de custo pede condomínio e centro, e há aba de centros de custo dentro da configuração do plano selecionado por condomínio. Escopo por condomínio é inferência forte; hierarquia e uso obrigatório no lançamento não foram comprovados.
- No OFX, a ajuda descreve pareamento automático por data/valor; item sem par pode virar lançamento segundo configuração salva; itens com descrição igual podem ser agrupados. Isso exige importação em staging e prévia antes de promover movimentos no Moves.
- Remessa e arquivo de retorno existem como fluxos em Cobranças; tela de processamento pede condomínio, banco e arquivo. A integração entre retorno, baixa, boleto e conta não foi processada e permanece hipótese até auditoria complementar.
- Demonstrativos da prestação não foram gerados. A ligação entre ledger, saldo anterior/final, comprovantes e publicação segue requisito alvo, não fato comprovado.

## Próximo bloco recomendado — continuação da auditoria

Não iniciar implementação ainda. Os filtros/listagens dos 57 relatórios foram documentados, mas as saídas não foram geradas; conteúdo da prestação, conciliação manual, relações de centro de custo, fluxos completos de contas a pagar/receber e vários módulos não financeiros seguem sem evidência suficiente. Na próxima execução, auditar em modo consulta os detalhes não mutáveis de contas a pagar/receber e conferir páginas bancárias contextuais remanescentes; não clicar em Gerar/exportar nem processar dados reais. A escolha de um bloco de implementação P0 deve aguardar essa evidência operacional.

### Continuação da trilha de cobrança

Um detalhe consultivo de fatura confirmou que a composição pode exibir múltiplos itens num mesmo registro; a consulta filtrada de boletos mostrou relação entre essa fatura e um boleto pendente. A listagem de recibos mostra origem e comprovante de pagamento, mas não foi aberto arquivo. Contas a pagar/receber não tinham itens no contexto consultado. Prestação fechada bloqueia edição, enquanto gerar/reabrir são operações potencialmente consequenciais e ficaram intocadas. Nenhuma relação permite ainda provar saldo canônico ou fechamento→prestação; manter as dependências financeiras P0 como requisitos a investigar e não iniciar implementação.

## Atualização de evidência e decisão após auditoria operacional

No CONDOMÍNIO B, a tela consultiva confirmou um título a receber com duas parcelas e atributos de competência/vencimento por parcela. Faturas históricas e boletos mostraram itens, cobranças agrupadas e estados pago/liquidado/cancelado; recibos estão listados como “Despesa”, mas sem origem rastreada. As telas de contas a pagar/parcela permaneceram sem resultados. Não há pagamento ou recebimento rastreável a conta bancária/ledger.

A competência de junho/2026 escolhida para a amostra estava **aberta**; as sete prestações visíveis nesse contexto também estavam abertas. O menu não ofereceu uma ação de visualização segura; “Gerar” não foi usado. Em outro contexto a interface já mostrara prestações fechadas, sem caminho seguro para abrir composição. Por isso conteúdo de prestação, saldos e fonte canônica financeira continuam sem comprovação.

Leituras têm formulário de água/gás e cobrança mas o contexto selecionado não está configurado para registrar consumo. Documentos mostram categorias/anexos sem abrir arquivos. Contratos não tinham registros na lista contextual. Agenda oferece calendário de áreas comuns; portaria separa visita/acesso/entregas; grupos de configurações foram identificados sem alterar parâmetros.

**Decisão desta auditoria: NO-GO PARA IMPLEMENTAÇÃO do núcleo P0.** Não há evidência suficiente para escolher com segurança a primeira dependência entre competência, plano de contas, ledger, títulos ou bancos. Próxima etapa deve localizar detalhe somente leitura de conta a pagar/parcela e pagamento, ou prestação com ação explícita “Visualizar/Detalhes”, e fechar uma trilha de recebimento até banco. Não extrapolar o exemplo de fatura/boleto para toda cobrança.

## Encerramento da auditoria exploratória — sequência revisada

### FUNDAÇÃO SEGURA PARA IMPLEMENTAR

**Primeiro vertical slice proposto: competência mensal aberta.** Modelo pequeno por administradora/tenant e condomínio, `YYYY-MM`, início/fim explícitos, status inicial aberto e unicidade por condomínio/período. Inclui migration, autorização tenant-scoped, integridade referencial, auditoria, testes de isolamento/idempotência e UI Moves real de lista/criação. Sem fechar/reabrir, sem lançamento, sem ledger, sem geração de relatório. Esta é uma recomendação, não implementação nem autorização para pular a revisão do próximo bloco.

Competência vem antes do plano de contas por ser dimensão observada em títulos/parcelas, faturas, lançamentos e relatórios, com período e status no produto de referência. Plano de contas possui boa evidência estrutural, mas escopo de propriedade, versionamento e comportamento de contas em uso ainda são incertos. Centro de custo tem apenas aba/filtro/campo, sem registro ou fluxo. Ele fica para depois.

### OPERAÇÃO QUE EXIGE MAIS VALIDAÇÃO

- Fechamento/reabertura e efeitos em registros contabilizados.
- Plano de contas completo, escopo por administradora/condomínio, versões, bloqueio por uso e associação transacional.
- Centro de custo em conta, lançamento, rateio e orçamento.
- Título/parcela de contas a pagar, aprovação, pagamentos, parcialidades, comprovantes e estorno.
- Recebimento, fatura/itens, boletos/segunda via, banco, baixa automática, encargos e descontos.
- OFX/extrato, retorno de cobrança, conciliação, lançamentos automáticos e estorno de matching.
- Composição/saldo/aprovação/anexos/publicação de prestação e fonte canônica financeira.

### Princípios de arquitetura obrigatórios

Manter modelo Moves original e explícito; isolamento por tenant e integridade referencial; histórico e auditoria append-only para transições; transações e idempotência onde houver comando; decimal/moeda sem float; datas e competência explícitas; status enumerados; sem hardcode de condomínio ou demo em produção. Preservar multiadministradora, multi-condomínio, pessoa global com relações temporais, multiunidade e copropriedade, cobrança consolidada opcional com distribuição por unidade e integrações Talk/Support/Meu Dia sem duplicar fonte financeira.

O ledger permanece não definido. A estimativa de paridade continua ~10% (5–15%), com 16 P0 e 7 P1; esta auditoria de configurações não fecha nenhuma trilha financeira completa.


## Ciclo ERP seguinte à competência — fração ideal cadastral

O gap da fração ideal foi fechado no Moves como dado percentual opcional do vínculo temporal proprietário–unidade, sem regras de totalização ou rateio. O modelo, validação, auditoria e UI foram homologados em banco descartável e HTTP E2E. Isso avança uma parte do item 1 (cadastros operacionais), mas não elimina o P0 de cadastros, não altera a estimativa de paridade de 10% (5–15%) e não altera as contagens 16 P0 / 7 P1. O próximo candidato P0 continua o fechamento de cadastros e vínculos; plano de contas permanece posterior, pois escopo/versionamento e ciclo de vida usados continuam não comprovados.

## Planejamento multibanco — Inter, Sicoob e Sicredi (2026-10-06)

O mapeamento técnico, as fontes oficiais consultadas e a arquitetura proposta estão em [`ERP-BANKING-INTEGRATION.md`](ERP-BANKING-INTEGRATION.md). Esta documentação não entrega integração nem altera paridade, gaps P0/P1 ou critérios de conclusão.

Preservar a sequência P0 vigente: fechar cadastros operacionais (incluindo a issue existente #253 para validar os campos físicos restantes), plano de contas/dimensões, contas a pagar/receber e cobrança, e só então evoluir contas bancárias e efeitos financeiros. Competência mensal aberta segue implementada como fundação limitada; fechamento e ledger permanecem indefinidos. Não iniciar adapters de pagamento antes do modelo Moves de pagável/aprovação/liquidação e da fonte canônica financeira.

Quando as dependências permitirem, BANK-001 a BANK-015 são a decomposição técnica proposta, não issues GitHub. Iniciar por contas/conexões/capabilities e um adapter read-only de sandbox; consultar composição real dos bancos/cooperativas do piloto antes de confirmar Inter como primeiro banco. API, webhooks, OFX e CNAB devem alimentar observações externas idempotentes sem confundi-las com ledger. As diferenças de autenticação, produtos, limites e sandbox são por banco/API/conta; revalidar as fontes oficiais no início de cada tarefa de adapter.

Estado geral permanece em 16 P0 / 7 P1 e paridade ~10% (5–15%). Planejamento documental sozinho não fecha gap operacional.

## Implementação Moves — plano de contas persistente (2026-10-06)

O slice seguinte à competência implementou plano de contas por condomínio, com vínculo composto à administradora, árvore de contas, contas sintéticas/analíticas, validações de ciclo/natureza/status, pesquisa, CRUD de contas, auditoria, CSRF e isolamento. A dependência funcional direta — condomínio persistente — existe. A issue #253 segue aberta para completar atributos físicos e validar cadastro operacional; esse trabalho permanece P0, mas seus campos não participam da classificação contábil e não bloqueiam este slice. Esta decisão delimita dependência técnica, não altera a prioridade geral de fechamento dos cadastros.

Nenhuma parte do ledger ou da cadeia de títulos/recebimentos foi definida aqui. Centros de custo, versionamento, modelos/templates, contas em uso, integração bancária e demais capacidades financeiras permanecem por implementar. Paridade geral segue ~10% (5–15%), com 16 P0 / 7 P1: o item de plano de contas avançou de rota visual para estrutura classificável, mas o gap P0 de operação financeira não foi fechado. A evidência do Moves não atualiza a auditoria APControle.

## Implementação Moves — fundação de contas a receber (2026-10-07)

Issue #298 implementa somente o registro e a consulta de título e parcelas, com total validado contra valores explícitos, vencimento e competência aberta por parcela, unidade e responsável, e contas analíticas de ativo/receita do plano ativo do condomínio. Um título com uma ou mais parcelas é decisão Moves; nenhuma baixa é executada. O slice não define cobrança, rateio, juros/encargos, banco, pagamento, recebimento, ledger ou saldo. Conta a Receber avança de visual para estrutura parcial; seu gap P0 continua aberto até haver recebimento e alocação validados. A estimativa global segue ~10% (5–15%), com 16 P0 / 7 P1, pois não se fechou uma trilha financeira operacional.

**Próximo candidato após este corte:** avaliar uma fundação de contas a pagar sobre fornecedores, competências e planos existentes, refinando uma etapa auditável sem assumir aprovação, anexos ou liquidação sem especificação. Contas bancárias seguem dependentes de títulos e dos contratos em `ERP-BANKING-INTEGRATION.md`; centros de custo ficam bloqueados pela evidência C e por relações ainda desconhecidas.

## Slice operacional de CNPJ ausente (2026-10-07)

O cadastro de condomínio aceita CNPJ ausente sem marcador falso e cria uma única tarefa idempotente em `day_tasks`. Esse fluxo avança parte de cadastro/Meu Dia dentro da issue guarda-chuva #75, mas não fecha a Central de Obrigações nem a própria #75. A tarefa sem responsável aparece na supervisão, pode receber atribuição, prioridade e prazo interno e fica ligada ao condomínio. Gravação válida de CNPJ numérico ou alfanumérico resolve a mesma tarefa; remoção posterior a reabre com histórico de auditoria. `day_tasks` é o estado canônico e Support não é duplicado. A avaliação é event-driven no salvamento; scheduler e notificações diárias não foram criados. Não alteramos estimativa de paridade ou contagens de lacunas financeiras.

## Issue #253 — subgap de edição de unidade (2026-10-07)

A edição da estrutura existente agora permite corrigir identificador e complemento da unidade, campos já presentes no schema e na criação atual. Condomínio, bloco, situação, frações ideais e vínculos temporais não mudam neste fluxo; a atualização é autorizada pela administradora atual e auditada na mesma transação. Isso conclui apenas um subgap de manutenção cadastral. Permanecem sem especificação comprovada outros atributos físicos (por exemplo andar, área, vaga e tipo) e critérios de homologação com dados de operação; por isso a issue #253 continua aberta. Sem mudança de nível de paridade ou de contagem P0/P1.

## Ciclo financeiro — fase 1, fatura (2026-10-07)

**BLOQUEADA ANTES DA IMPLEMENTAÇÃO por regra de domínio ausente.** O HEAD não tem composição de cobrança ou fatura persistida; a fundação de recebíveis requer valor/parcela manual e não é substituta. A auditoria APControle confirma exemplos de faturas e itens (confiança B), mas não cálculo, itens elegíveis, rateio ou geração (D). Não inferir cobrança por fração ideal, copropriedade, taxa, consumo ou encargos. O bloqueio e os critérios para desbloqueio estão em `ERP-DOMAIN-MODEL.md`; issues existentes #257 (cobranças/boletos) e #258 (consolidação multiunidade) são os registros de trabalho, sem duplicação.

Não avançar para instrumentos, recebimento/baixa, provider bancário ou conciliação até existir um contrato Moves para composição e rastreabilidade por unidade. Próxima ação é obter/decidir as regras Moves explicitadas no bloqueio; depois construir uma prévia de composição testável antes de emitir fatura. Sem mudança de paridade ou das contagens P0/P1.

## Implementação — obrigação a pagar e parcelas (#300, 2026-10-07)

Após confirmar fornecedores, competências abertas e planos por condomínio no código, implementamos um registro auditável de obrigação: fornecedor vigente, classificação analítica de passivo/despesa, total e parcelas explícitas. A migration é `20261007_003_create_erp_payables.sql`; lista e detalhe são tenant-scoped.

Esta fundação não conclui o item P0 Contas a pagar. Não cria aprovação/quatro-olhos, rateio, recorrência, retenções, anexos, pagamento ou ledger; essas regras ainda precisam de contratos Moves explícitos. A #48 permanece aberta. Estimativas não mudam: **16 P0 / 7 P1 e ~10% (5–15%)**. Avaliar uma próxima etapa de aprovação/liquidação somente após explicitar transições, parcialidades, comprovantes, reversão e fonte contábil.
