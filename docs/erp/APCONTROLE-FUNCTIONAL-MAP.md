# Mapa funcional do APControle (observado e inventariado)

Legenda de evidência: **V** = página aberta; **M** = item identificado no menu/link, página ainda não auditada; **I** = inferência explícita. Não usar este mapa como especificação fechada antes de auditar os itens M.

## Navegação principal

| Módulo | Páginas e capacidades | Entrada → resultado / dependências | Evidência |
|---|---|---|---|
| Dashboards | Administradora, Condomínio, Unidade | Contexto de visão geral e indicadores → cards/gráficos e atalhos | V: administradora; M: demais |
| Cadastros | Condomínios; Inquilinos/Proprietários; Unidades; Áreas Vinculáveis; Áreas Comuns; Funcionários; Fornecedores; Veículos; Visitantes; Animais; Conselho; Contatos Úteis; Advogados; Votação; Assembleia Virtual; Patrimônio | Dados cadastrais → registros reutilizados por operação, cobrança, agenda e relatórios | V: condomínio, pessoas, unidades, fornecedores; demais M |
| Lançamentos | Ver Lançamentos; Lançamentos Fixos; Contas a Pagar; Contas a Receber; Lançar Despesa/Receita/Desconto; Multa Avulsa; Cobranças Extras; Outros Lançamentos; Fechamento Mensal; Cobrança Automatizada/Pagamentos (Bancos) | Competência, conta, fornecedor/pagador, documento e valor → títulos, parcelas ou movimentos contábeis | V: lançamentos, fixos, pagar/receber e fechamento; demais M |
| Cobranças | Faturas; Acordos; Parcelas de Acordo; Boletos; Arquivo de Retorno; Histórico de Cobrança Judicial; Recibos | Condomínio/unidade/competência → fatura, boleto, acordo, recebimento e histórico | V: faturas, acordos, boletos, recibos; demais M |
| Leituras | Relógios; Leituras; Tarifas; Importação de Leituras | Medidor, período, consumo, tarifa → consumo para cobrança | M; não processado |
| Relatórios | Consumo, Financeiros, Contábeis, Diversos, Sistema, Prestação de Contas | Filtros por período/condomínio/conta/unidade → resultado visual ou saída aparente de relatório | 57 identificados; aberta somente listagem de prestação |
| Gráficos | Gerais; Contas; Consumo por Unidade/Geral; Financeiro; Inadimplência; Financeiro por Conta; Áreas Comuns | Dimensão/período → visualização agregada | M |
| Agenda | Manutenções; Comunicados; Ocorrências; Áreas Comuns (reservas, pendências, espera, multas); Atendimento Cobrança; Seguros; Contratos; Mudanças (agendamentos/configuração) | Condomínio, pessoas e datas → agenda, solicitações ou histórico operacional | M; submenu expandido na segunda passada |
| Documentos | Disco Virtual; certidão/declaração/comprovante; avisos de recebimento; cópias de cheques; empenhos; malotes; carta de anuência | Documento e referência operacional → consulta/arquivo | M; sem baixar ou emitir |
| Portaria | Gerenciamento; previsão de visita; acessos; achados e perdidos; entregas e registros de entrega | Unidade, visitante/entrega e horário → registro de acesso/ocorrência | M |
| Mala Direta | Cobrança; Agendada; Endereços | Destinatários e contexto → comunicação/endereçamento | M; envio não executado |
| Importações | OFX; XML NF-e; inadimplentes; fração ideal; lançamentos; lançamentos por unidade; contas a pagar | Arquivo + mapeamento → importação/alteração em lote | M; páginas de importação não abertas |
| Configurações | Administrativas; Área de Moradores; Boletos; Financeiras; Diversos | parâmetros e cadastros de apoio → comportamento financeiro/portal | V: plano de contas, parâmetros, frações; links restantes M |

## Páginas abertas — entradas, resultados e regras visíveis

| Página | Entrada/filtros e informações | Relações/regras observadas | Ações identificadas (não executadas) |
|---|---|---|---|
| Dashboard Administradora | Cards e gráficos agregados; visão anual | Agrega condomínio/unidade e dados administrativos | Atalhos; relatório/geração não acionado |
| Contas a pagar | Condomínio, descrição, intervalo de valor, mês de lançamento, tamanho da página; tabela de condomínio, histórico, anexos, parcelas, data, total | Título pode abrir parcelas relacionadas | Filtrar e baixar (download não acionado); excluir e demais ações bloqueadas |
| Contas a receber | Condomínio, descrição, mês; colunas de parcelas, data e total | Título relacionado a parcelas | Filtrar disponível; mutações não executadas |
| Lançamentos | Condomínio, competências, tipo, conta, intervalo de valor; tipo/data/competência/histórico/documento/anexo/valor | Movimentos podem ter relações com cobranças e contas | Criar, ajustar, editar crédito, copiar e excluir não executados |
| Lançamentos fixos | Condomínio, competência, tipo; tipo/histórico/documento/valor | Lançamento periódico é lançado por competência; linhas já lançadas podem impedir repetição aparente | Lançar disponível em alguns estados; não clicado |
| Parcelas a pagar | Condomínio, texto, situação, competência, vencimento, conta, documento, valor, fornecedor, tipo e rateio | Parcela vincula fornecedor, título, competência, vencimento, pagamento e aprovação de síndico | Pagar lote, criar/desfazer rateio, cancelar não executados |
| Parcelas a receber | Condomínio, descrição, competência, vencimento, situação; datas/valores/situação | Parcela vincula título de recebimento | Receber lote não executado |
| Faturas | Unidade, pessoa responsável, bloco, identificador, situação, tipo, boleto, competência | Detalhe tem dados de unidade e lista de itens com total | Atualizar, fechar, reabrir, pagar, boletos e acordo não executados |
| Boletos | Condomínio, estado, datas, sequência, IDs de fatura/acordo/cobrança extra | Estados de sincronização, pagamento pendente e pago; indicador de visualização pelo portal | Gerar boleto/remessa, impressão, e-mail, anexar e cancelar não executados |
| Acordos | Condomínio, situação, unidade, bloco, número, descrição, advogado, classificação | Situações exibidas: simulação, aprovado, cancelado, pago, quebrado | Criar, relatório e termo não acionados |
| Fechamento | Condomínio e situação/competência | Fechamento bloqueia lançamentos daquele mês e acelera cálculos de relatórios, conforme texto da página | Fechar/reabrir não executados |
| Prestação de contas (listagem) | Condomínios múltiplos, período, situação; condomínio, nome, estado e datas | Registros abertos e fechados listados | Detalhar, publicar, exportar ou aprovar não executados |
| Condomínios | Busca por nome, cidade, síndico, endereço e estado; dados cadastrais | Relaciona-se a pessoa responsável e estrutura de unidades | Abrir edição observado, modal fechado sem salvar; salvar/excluir não acionados |
| Pessoas | Busca por condomínio, nome, email/documento, RG, bloco, unidade, boleto e tipo; tabela com contato/preferência/documento/tipo | Interface informa cadastro global reutilizável entre condomínios | Cadastro/edição não efetuados |
| Unidades | Condomínio, bloco, unidade e pessoa associada; proprietário e ocupante separados | Lista mostra papéis ligados à unidade, mas não prova cardinalidade/histórico | Links para abrir edição não acionados |
| Fornecedores | Busca global por nome, documento, tipo de serviço e exibição no site | Categorias de serviço; possível relação a finanças/contratos ainda não verificada | Alterações não executadas |
| Frações ideais | Condomínio, nome; tipo, padrão e status | Entidade de fração associável à cobrança é plausível, mas cálculo não observado | Novo registro/edição não acionados |
| Parâmetros do condomínio | Condomínio e paginação; colunas de vencimento, mês de encerramento, fundo de reserva, multa e juros | Parâmetros parecem ser por condomínio | Alterar/salvar não executado; valores reais omitidos |
| Plano de contas | Abas contas, configurações, transferências automáticas e centros de custo | Hierarquia de receita/despesa/ativo/passivo; mapeamentos de cobrança, fundos, juros e consumo; transferências entre contas | Adicionar/editar/excluir não executado |
| Pagamento automatizado | Condomínio, banco, tipo, situação, data e valor | Lista de pagamentos por banco | Filtrar/cancelar/processar não executados |

## Catálogo de relatórios — 57/57 páginas de filtro abertas

**Financeiros (30):** Acordos; Taxas de Acordos; Centros de Custo; Comparativo; Demonstrativo; Demonstrativo Contas; Demonstrativo Analítico Tabela; Demonstrativo Situação Mensal; Demonstrativo Contas Relacionadas; Extrato Conta; Inadimplentes (Geral); Inadimplentes (Unidade); Inadimplentes (Detalhado Unidade); Inadimplentes (Taxa); Inadimplentes (Morador); Rateio; Rateio Comparativo; Rateio Detalhado; Rateio Analítico; Rateio Analítico (Detalhado); Rateio por Conta; Lançamentos; Lançamentos (Detalhado); Boletos; Recebimentos; Recebimentos por Conta; Saldos de Contas; Registro de Pagamentos; Memória de Cálculo; Transferências.

**Contábeis (8):** Demonstrativo; Comparativo; Balancete Verificação; Balancete Anual; Balanço Patrimonial; Resultado Exercício; Livro Razão; Previsão Orçamentária.

**Diversos (12):** Reservas Áreas Comuns; Unidades; Listagem de Pessoas; Presenças em Assembleias; Condomínios; Ocorrências; Administração; Parecer; Pagamentos Pendentes; Listagem de prestação de contas; Mudanças; Endereços.

**Sistema (4):** Logs; Pessoas com Acesso; Boletos Enviados; Relatórios Enviados.

**Consumo (2):** Consumo Geral; Consumo por Unidade. **Direto (1):** Prestação de Contas (listagem).

Filtros e opções de apresentação estão documentados abaixo. Colunas finais, agrupamentos/totais calculados e formatos de saída permanecem sem evidência porque não foi acionado “Gerar” nem exportação.

## Configurações adicionais identificadas, ainda não auditadas página a página

- Administrativas: Administrativo; Usuários do Sistema; Permissões de Usuários; Notificações da Prestação de Contas.
- Área de Moradores: criação de usuários, permissões, layout, notificações e banners.
- Boletos: configuração Financeiro, Demonstrativo e Alertas.
- Financeiras: Plano de Contas; Saldos iniciais; Previsão Orçamentária; Imposto; Índices.
- Diversos: Parâmetros; Fração Ideal; Tipos de Faturas.

## Estados/aparências confirmados

Aberto/fechado para prestação; ativo/inativo em cadastros/configuração; para acordo: simulação/aprovado/cancelado/pago/quebrado; para boleto: aguardando sincronização/aguardando pagamento/pago; para fatura: pendente/paga. Estados apresentados por outras listagens não foram suficientemente percorridos e não devem ser tratados como vocabulário completo.

### Consulta aprofundada de cobrança e recibo

| Página/detalhe | Entrada e evidência adicional | Cardinalidade / limite |
|---|---|---|
| Prestação de contas | Listagem exibiu períodos abertos e fechados. Menu de registro fechado informa que não pode ser editado e oferece gerar, reabrir, copiar configurações, excluir e enviar e-mail. | Não existe na listagem ação identificada claramente como “visualizar conteúdo”; geração não foi acionada. Composição, aprovação, anexos e origem dos saldos não comprovados. |
| Contas a pagar / parcelas | Listas foram consultadas no condomínio corrente sem data de vencimento restritiva e estavam sem resultados. A listagem de parcelas inclui competência, vencimento, pagamento, valor, valor pago, estado, anexos e aprovação do síndico; filtros incluem tipo e existência de rateio. | Sem registro nesta sessão: aprovação, título de origem, pagamento, parcialidade e rateio não inspecionados em detalhe. Estados de filtro para parcela: Em aberto, Pagas, Cancelado; não inferir sequência transitória. |
| Contas a receber | Lista sem resultado para o contexto corrente; link próprio conduz a parcelas de contas a receber. | Não foi possível inspecionar registro nem confirmar baixa/recebimento parcial ou vínculo bancário. |
| Fatura — detalhe consultivo | “Ver fatura” abriu painel de consulta com condomínio, unidade, responsável, vencimento, valor/data de quitação, tipo, observação e tabela de itens datados/valor. Um exemplo tinha dois itens e total. | **Uma fatura→N itens confirmada para o exemplo; cardinalidade geral e regra de composição não comprovadas.** |
| Boleto filtrado pela fatura | Link da fatura abre a lista de boletos com filtro por ID de fatura. Um boleto correspondente apareceu com valor pago zero e “Aguardando pagamento”; listagem também expõe cobrança(s), unidade(s), valor, vencimento, nosso número e registro bancário. | **Fatura→boleto confirmada no exemplo**; 1:1 ou 1:N global e boleto→faturas não comprovados. |
| Recibos | Lista mostra tipo, origem clicável, valor e situação; menu tem “Comprovante de pagamento” e “Cancelar”. Situações vistas: Disponível e Cancelado. | Relação da origem, conteúdo do comprovante e cardinalidade entre recibo, pagamento/recebimento e parcela não comprovados; nenhum arquivo aberto/baixado. |

Os exemplos consultados eram dados reais; nomes, documentos, unidades específicas, identificadores e valores individuais não são reproduzidos neste mapa.

## Rodada de relatórios: filtros efetivamente vistos

**Limite comum:** apenas abrir a tela de filtro é consulta. O botão **Gerar** e opções de download/exportação não foram acionados. “Pergunta” abaixo é a leitura operacional do nome e dos filtros; colunas finais, totais e regras de cálculo não foram verificados sem resultado. Prioridade: P0 fecha operação mensal; P1 paridade de análise; P2 operacional/cadastral; P3 administração técnica.

### Financeiros — 30/30 páginas de filtro abertas

| Relatório | Classe | Pergunta operacional / entradas observadas | Saída e dependências observáveis | Equivalente Moves / prioridade |
|---|---|---|---|---|
| Acordos | Cobrança | Quais acordos existem no condomínio/período/número/situação? Pode incluir detalhe, faturas e omitir cancelados. | Depende de acordos, faturas e competência; resultado não gerado. | Relatório de acordos P1 |
| Taxas de Acordos | Contábil | Quanto/quantas taxas de acordo por unidade, número e intervalo de pagamento/situação? | Depende de acordo, parcela e pagamento. | Razão de cobranças acessórias P1 |
| Centros de Custo | Financeiro | Como movimento por centro se distribui em intervalo e modo analítico? | Filtros condomínio + centro + datas + forma analítica. | Visão realizado por centro P1 |
| Comparativo Financeiro | Gerencial | Como receitas/despesas mudam entre meses selecionados? | Opções: desconto em receita, ordem do plano, sintético, detalhe de acordo, somente receita/despesa. | Comparativo mês a mês P1 |
| Demonstrativo Financeiro | Prestação de Contas | Qual demonstrativo de período por condomínio/tipo deve ser apresentado? | Pode separar receitas/despesas/ativos, acordo e saldo de conta zerada; usa plano de contas. | Demonstrativo P0 |
| Demonstrativo de Contas | Prestação de Contas | Qual demonstrativo por conta e período de lançamento/crédito? | Intervalos de data e data de crédito; orientação paisagem. | Extrato/demonstrativo por conta P0 |
| Demonstrativo Analítico Tabela | Prestação de Contas | Quais movimentos e detalhes devem aparecer em formato de tabela? | Opções de desconto, acréscimos/descontos, somente receitas e resumo por conta. | Razão visual detalhado P1 |
| Demonstrativo Situação Mensal | Cobrança | Como situação de cobrança da unidade evolui por competência ou pagamento? | Condomínio, unidade, competência, pagamento, tipo de cobrança/fatura e forma de apresentação. | Histórico mensal de cobrança P0 |
| Demonstrativo Contas Relacionadas | Contábil | Como movimentos de contas relacionadas se apresentam por período de pagamento? | Condomínio, intervalo de pagamento e seleção de conta; saída não gerada. | Movimentos relacionados P1 |
| Extrato Conta | Bancário | Quais movimentos/entradas/saídas ocorreram nas contas no período? | Condomínios múltiplos, período e contas; opções de total diário, entradas/saídas, detalhe e agrupamentos automáticos, contas-mãe/sem movimento e resumo. Regra visível ajusta data inicial após primeiro fechamento. | Extrato bancário/razão P0 |
| Inadimplentes (Geral) | Inadimplência | Qual inadimplência no recorte de competência/vencimento/condomínio até uma data? | Opções para ocultar nomes/unidades; resultado não gerado. | Painel de exposição vencida P0 |
| Inadimplentes (Unidade) | Inadimplência | Quais débitos por unidade estão vencidos e qual o total até uma data? | Competência, vencimento, unidade, tipo de morador, boleto/fatura, ordenação; pode mostrar juros/multa/percentual e nosso número. | Aging por unidade P0 |
| Inadimplentes (Detalhado Unidade) | Inadimplência | Quais títulos/competências formam o saldo vencido de cada unidade? | Filtros similares por competência, vencimento, unidade, tipo; detalhe de apresentação. | Extrato do devedor por unidade P0 |
| Inadimplentes (Taxa) | Inadimplência | Qual inadimplência de condomínio no intervalo de competência/vencimento? | Filtros de condomínio e intervalos; taxa/formula de saída não observadas sem gerar. | Taxa de inadimplência P1 |
| Inadimplentes (Morador) | Inadimplência | Qual posição de dívida do morador/unidade, incluindo correções? | Condomínio/unidade/pessoa, competência, tipo cobrança/fatura, vencimento, método de correção; exibição de correção/juros/multa/taxa. | Conta corrente do devedor P0 |
| Rateio | Cobrança | Qual valor rateado por unidade no intervalo/tipo de fatura? | Condomínio, conta, competências, tipo fatura; opção de cobrança extra. | Memória de rateio por unidade P0 |
| Rateio Comparativo | Gerencial | Como o rateio compara até três competências? | Condomínio/conta, até três meses, tipo de fatura; por conta, agrupar, valores rateados, extras. | Comparativo de rateio P1 |
| Rateio Detalhado | Cobrança | Como se detalha rateio por unidade/agrupamento? | Condomínio, agrupamento, conta, competências e tipo de fatura; total, agrupar, resumo por conta e extra. | Composição individual P0 |
| Rateio Analítico | Contábil | Quais contas e detalhes formam o rateio no período? | Condomínio, conta, competência e tipo fatura; detalhe, paisagem e extras. | Razão de rateio P1 |
| Rateio Analítico (Detalhado) | Contábil | Quais itens e movimentos compõem cada parte do rateio? | Filtros de condomínio/conta/período/fatura; opção de detalhes e cobrança extra. | Trilha de cálculo do rateio P1 |
| Rateio por Conta | Contábil | Quanto cada conta contribuiu para o rateio? | Situação de fatura, tipo de agrupamento, conta, competência; pode exibir extra, multas e total geral das unidades. | Rateio agrupado por plano P1 |
| Lançamentos | Financeiro | Quais lançamentos financeiros ocorreram por período e competência? | Condomínio/datas, mês competência, tipos, rateio, conta e cobrador; opções para conta, sobras de rateio e fundo. | Diário financeiro P0 |
| Lançamentos (Detalhado) | Financeiro | Qual detalhe/documento/fornecedor explica cada lançamento? | Mesmos recortes + tipo fatura; mostra/oculta contas de receita/despesa/ativo/passivo, anexo e fornecedor. Mês competência ignora datas inicial/final conforme ajuda da tela. | Diário auditável P0 |
| Boletos | Cobrança | Quais boletos foram emitidos/pagos por referência e data? | Condomínio, referência, tipo/status, datas de pagamento/crédito, ordenação e conta; resultado não gerado. | Relatório de títulos emitidos P0 |
| Recebimentos | Bancário | Quais recebimentos foram creditados/pagos no intervalo? | Condomínio, data de crédito e pagamento e conta; campos de saída não gerados. | Diário de recebimentos P0 |
| Recebimentos por Conta | Bancário | Quanto entrou por conta no período? | Condomínio, data inicial/final e conta. | Entradas por conta P0 |
| Saldos de Contas | Bancário | Qual posição de saldo por condomínio/conta em uma data? | Data e seleções de condomínio/conta. | Saldos derivados do ledger P0 |
| Registro de Pagamentos | Bancário | Quais pagamentos ocorreram, de qual conta, para qual conta contábil e tipo? | Condomínio, intervalo, conta bancária, conta destino e tipo de pagamento. | Diário de pagamentos P0 |
| Memória de Cálculo | Cobrança | Como encargos e saldo de títulos foram calculados até datas? | Condomínio, unidade, vencimento, situação; saída não gerada. | Memória auditável de encargos P1 |
| Transferências | Bancário | Quais transferências ocorreram por competência/tipo? | Condomínio, período de competência, tipo de relatório e transferência. | Relatório de transferência entre contas P0 |

### Contábeis — 8/8 páginas de filtro abertas

| Relatório | Classe | Entradas e pergunta | Dependência observada | Moves / prioridade |
|---|---|---|---|---|
| Demonstrativo Contábil | Contábil | Condomínio e período; opção de imobilizado e detalhamento de acordo. Como se apresenta resultado por conta? | Plano/contas e período; não gerado. | Demonstrativo derivado do ledger P0 |
| Comparativo Contábil | Gerencial | Condomínio e competências de início/fim. Como os grupos contábeis variam no intervalo? | Competência e plano; sem saída gerada. | Comparativo P1 |
| Balancete Verificação | Contábil | Condomínio, intervalo, contas sem movimento e omissão de passivo. | Plano e movimentos do período. | Balancete P0 |
| Balancete Anual | Contábil | Condomínio e período de fechamento, opção de contas sem movimento. | **Período fechado**, comprovado pela seleção de fechamento. | Balancete anual P1 |
| Balanço Patrimonial | Contábil | Condomínio e período fechado, opção de contas sem movimento. | **Período fechado**, contas de ativo/passivo. | Balanço P1 |
| Resultado Exercício | Contábil | Condomínio e período de fechamento. Qual resultado do exercício encerrado? | **Período fechado** e contas de resultado. | Resultado anual P1 |
| Livro Razão | Contábil | Condomínio, datas, conta e total por dia. Quais movimentos detalham o saldo? | Plano de contas e lançamentos. | Razão P0 |
| Previsão Orçamentária | Gerencial | Condomínio, tipo e ano; ano de comparação, previsão dividida entre meses, comparar anos e previsto x realizado. | Previsto versus movimentos; vínculo explícito com conta/centro não provado. | Orçamento P1 |

### Prestação, outros relatórios e inventário restante

- **Prestação de Contas (listagem), classe Prestação de Contas:** condomínio, período e situação; mostra nome/período/status; estados aberto e fechado foram vistos. Conteúdo não aberto. P0.
- **Sistema — 4 páginas abertas:** Logs (data, usuário, ação, IP; filtro período/usuário/ação), Pessoas com Acesso (condomínio/nome; opção histórico/unidade/bloco), Boletos Enviados (condomínio/nome/e-mail/data) e Relatórios Enviados (condomínio/tipo/relatório/destinatário/e-mail/data). Classes Operacional/Outro; resultado não gerado. P2; acesso/histórico pode ser P0 para auditoria de segurança.
- **Consumo — 2 páginas abertas:** Geral (condomínio, competência, tipo de consumo/tabela, medidor/status e filtro de consumo) e Unidade (condomínio, unidade, período, tipo/tabela). Classe Financeiro/Cobrança por servir potencialmente aos itens de fatura; cálculo não demonstrado. P1.
- **Diversos — 12 relatórios, filtros documentados:** Reservas de áreas comuns (datas, situação, área comum e opção de link de anexos; Operacional, P2); Unidades (condomínio/tipo, histórico de ocupação/titularidade, moradores adicionais, fração ideal, veículos, animais, isenção de rateio, observações; Cadastral, P1); Listagem de Pessoas (condomínio, pessoa, situação e seleção de colunas/ordem/assinatura; Cadastral, P2); Presenças em Assembleias (condomínio, papel, situação de inadimplência e assunto; Operacional, P2); Condomínios (situação, tipo, ordenação e síndico; Cadastral, P2); Ocorrências (condomínio, intervalo, assunto, número, situação e classificação; Operacional, P2); Administração (competência; Gerencial, P2); Parecer (condomínio/nome; Outro, P2); Pagamentos Pendentes (vencimento, conta e descrição; Financeiro, P1); Listagem de prestação de contas (condomínio/período/situação; Prestação de Contas, P0); Mudanças (condomínio, unidade, período, situação/tipo; Operacional, P2); Endereços (condomínio, pessoa, tipo de endereço e formato; Cadastral, P2). Resultados não gerados.
- Assim, **57/57 relatórios tiveram a página de filtros/listagem aberta e catalogada; nenhum resultado de relatório foi gerado ou exportado**. Não houve relatório inacessível ou não seguro apenas para abertura da página.

## Continuação read-only — novas evidências e cobertura de fluxo

- **Contas a receber:** detalhe acessado por “Visualizar” confirmou um título com duas parcelas no exemplo. Título mostra contas, descrição, competência/data de lançamento, total, quantidade de parcelas, papel cobrado, reserva, rateio e tipo de fatura. Parcela mostra vencimento e competência próprios e colunas de recebido. Pagamento efetivo, status, banco e aprovação não comprovados.
- **Fatura/boleto:** para COMPETÊNCIA MÊS X (junho/2026) no CONDOMÍNIO B, a lista tinha oito faturas no estado “Paga”; um detalhe mostrou o item “Taxa condominial”. A lista consultiva de boletos relacionada ao recorte mostrou registros em estados pago/liquidado e cancelado/baixado, incluindo uma linha com várias referências de cobrança. A relação de boleto com cobranças é observada; cardinalidade universal, reemissão e liquidação financeira continuam inferidas.
- **Recibos:** no CONDOMÍNIO B havia 23 registros visíveis de tipo “Despesa”, situação “Disponível”; origem clicável não foi aberta. Isso não comprova vínculo de recibo a pagamento nem documento/comprovante.
- **Prestação:** sete registros no CONDOMÍNIO B estavam abertos, incluindo junho/2026. O menu da competência oferece editar/gerar/copiar configurações/excluir e não oferece visualização segura, portanto conteúdo não aberto.
- **Leituras:** página é formulário que combina leituras de água/gás com competência, unidade/bloco, medidor e cobrança/tipo de fatura; aplicação informou configuração de leitura desativada para esse condomínio. Fluxo histórico não comprovado.
- **Documentos/contratos/agenda/portaria/configurações:** Disco Virtual apresenta categorias e anexos ligados a lançamentos, leituras e contas a pagar, sem acesso a arquivos; contrato sem resultados no recorte e colunas para vigência/valor/anexos; agenda apresenta calendário de reserva por área comum; portaria separa previsão de visita, acesso, achados/perdidos e entregas; grupos de configuração identificados, sem alteração. Ver matriz final de cobertura em `ERP-PARITY-MATRIX.md`.

## Fechamento da auditoria — configuração financeira read-only

### Configurações consultadas

- **Plano de contas:** árvore por classes (ativos, passivos, patrimônio/resultado, receitas e despesas) e grupos operacionais; contas de sistema podem ser imutáveis. Aba Configurações mapeia contas especiais para cobrança, fundos, acordo, leituras/consumo, juros/multas, descontos, transferências, tarifas e receitas/despesas. Escopo selecionado por condomínio na interface; persistência/compartilhamento do plano não comprovados.
- **Centros de custo:** aba no plano, sem registros no contexto; há seletor de centro no detalhe de conta e filtro de relatório por condomínio/centro. Relações com conta, lançamento e orçamento não comprovadas.
- **Transferências automáticas:** relação conta origem→destino. Ajuda diz que a transferência é feita na entrada de boletos de fatura, unificados, cobrança extra e multas. Nenhuma configuração existente no contexto consultado.
- **Configuração do boleto:** campos incluem condomínio, banco, beneficiário/documento/endereço, tarifa contábil, carteira/convênio, baixa automática, sequência de boleto/remessa, local de pagamento, conta que recebe boletos pagos, instruções e desconto antecipado. Parte da tela está bloqueada; identificadores bancários e taxas foram omitidos. Baixa automática, lançamento de tarifas e desconto estavam desligados no contexto consultado; modalidade de carteira aparecia como registrada. Nenhum campo foi alterado.
- **Demonstrativo de boleto:** lista com configuração por condomínio e ações de edição. 36 entradas globais listadas; formulário individual não aberto por ser rota de edição. Conteúdo não comprovado.
- **Alertas de vencimento:** colunas para notificar antes/depois e quantidade de dias; consulta sem linhas. Regra e destinatários não comprovados.
- **Notificação da prestação:** busca por condomínio e colunas para alertas/destinatários; sem registros. Publicação/conteúdo não comprovados.
- **Orçamento:** formulário anual por condomínio, ano, previsão/realizado anterior e valor de reajuste, com botões de cópia/reajuste e salvamento. Nenhum botão foi acionado. Relação com centro de custo/lançamento não comprovada.
- **Impostos e índices:** lista de impostos expõe classificação de conta de despesa e passivo; catálogo de índices apresenta tipo de juros/cálculo/cadastro. Aplicação e prioridade não testadas.
- **Área de Moradores:** menu mostrou usuários, permissões, layout, notificações e banners; não identificada configuração explicitamente ligada a cobrança nesta navegação. Telas individuais não abertas.

**Competência e banco:** fechamento foi descrito pela ajuda como bloqueio de lançamento mensal e suporte a relatórios; relatórios anuais selecionam períodos fechados. OFX trata extrato e matching; retorno bancário fica em fluxo separado de arquivo de cobrança. Não presumir que OFX e retorno sejam equivalentes. A cadeia recebimento→banco→conciliação→prestação não foi comprovada.

Ver a tabela de parâmetros, a classificação A–D, limitações e decisão de primeiro slice na seção de encerramento de `APCONTROLE-AUDIT.md`.
