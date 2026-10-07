# Matriz funcional — APControle × Moves ERP

Escala Moves: 0 inexistente; 1 só visual; 2 estrutura parcial; 3 funcional básico; 4 paridade funcional; 5 superior. Pontua o estado observado desta branch em 2026-10-06, não mockups ou capacidade planejada. Evidências APControle referem-se ao [mapa funcional](APCONTROLE-FUNCTIONAL-MAP.md); páginas identificadas somente no menu estão marcadas como inventário.

| Domínio | Função de referência / evidência | Moves atual | Nível | Gap | Prioridade | Implementação proposta |
|---|---|---|---:|---|---|---|
| Tenant | Cadastros e financeiro por administradora/condomínio; isolamento visto em páginas | Tenant/admin scope e grants ERP presentes; condomínio tenant-scoped | 3 | Completar cobertura de todos os novos casos de uso | P0 | Exigir tenant + ACL de escopo em toda consulta/escrita e teste cruzado |
| Condomínio | Cadastro, endereço, responsável, parâmetros | Cadastro consultável/criável/editável com tenant e validação fiscal; cobertura de campos menor | 3 | Campos, histórico, documentos e configuração operacional | P0 | Fechar cadastro mínimo para operação de um condomínio |
| Blocos/unidades | Condomínios → blocos/unidades → papéis | Bloco/unidade tenant-scoped; fração ideal percentual opcional no vínculo temporal de proprietário/unidade | 3 | Atributos físicos adicionais e validação das regras de propriedade em operação | P0 | Evoluir estrutura sem duplicar registros; validar cadastros com dados reais de homologação |
| Pessoas e vínculos | Pessoas globais reutilizadas; proprietário/ocupante na unidade | Pessoa PF/PJ e vínculos temporais multiunidade/multipapel; fração ideal percentual opcional em vínculo de proprietário com unidade | 3 | Busca/histórico mais completo e integração financeira por vínculo | P0 | Consolidar período/papel, auditoria e visão por pessoa/unidade |
| Investidor multiunidade | Uma pessoa pode ter mais de uma unidade; cardinalidade legada inconclusiva | Modelo de vínculo permite vários links pessoa-unidade/condomínio | 3 | Relatórios/cobrança agregada ainda ausentes | P0 | Cobertura de ownership temporal e cenários cross-condomínio |
| Fornecedor | Lista global por tipo; pagamento liga fornecedor | Pessoa + perfil fornecedor + categoria + N:N de atendimento temporal ao condomínio | 3 | Contratos, contatos/documentos detalhados e uso financeiro | P0 | Referenciar fornecedor nos títulos a pagar |
| Plano de contas | Árvore de classificação, mapas por tipo, abas e transferência automática; relatórios usam conta e modo de apresentação | Rota visual; nenhuma persistência ERP financeira real localizada | 1 | Entidades/regras e validação contábil ausentes | P0 | Plano hierárquico versionável por administradora/condomínio e conta classificável |
| Conta a pagar | Títulos, parcelas, fornecedor, aprovação, rateio e pagamento | Página de demonstração sem persistência/operações | 1 | Fluxo financeiro inteiro | P0 | Título + parcelas + aprovações + pagamento + auditoria |
| Conta a receber | Títulos/parcelas/baixa | Página de demonstração | 1 | Origem, baixa e distribuição | P0 | Recebível ligado a unidade/competência, eventos de baixa e estorno auditável |
| Cobrança/fatura | Fatura por unidade, composição de itens, status e boleto | Página visual sem geração/persistência | 1 | Competência, itens, cálculo, cobrança, baixa | P0 | Competência → itens por unidade → cobrança e distribuição de recebimento |
| Cobrança consolidada multiunidade | Requisito Moves explícito, referência não comprovada | Não implementada | 0 | Agregado por proprietário sem perder alocação por unidade | P0 | Lote de apresentação que preserva itens/saldos por unidade; recebimento distribuído |
| Inadimplência/acordos | Relatórios por unidade/pessoa/taxa; acordos e parcelas | Página geral visual, sem títulos e cálculo | 1 | Saldo vencido, cálculo, acordo, histórico e cobrança | P1 | Derivar de parcelas e eventos; nunca manter saldo duplicado |
| Contas bancárias | Configuração de conta/cedente, agência, conta, convênio, tarifas, baixa e sequência de remessa; relatório de extrato/saldo | Página visual | 1 | Conta, saldo e operação real | P0 | Cadastro seguro de conta, lançamentos e saldos derivados |
| Pagamento bancário/CNAB | Pagamentos automatizados por banco/tipo/situação/data/valor; remessas e retornos no menu; processamento tem banco+arquivo | Página visual; sem processamento real | 1 | Importação idempotente, arquivo/remessa/retorno e segurança | P1 | Adaptadores testáveis em sandbox e trilha de lote |
| OFX/conciliação | Formulário declara matching automático por mesma data/valor; itens não encontrados podem ser lançados conforme configuração salva; itens de mesma descrição são agrupados. Estado “Não conciliado” existe na lista OFX | Página Moves visual, sem importador/matching | 1 | Ingestão, matching manual/automático, prévia, reversão e auditoria | P0 | Staging de extrato, regras explicáveis e confirmação de lote por usuário autorizado |
| Fechamento competência | Referência bloqueia lançamentos e acelera relatórios | Não existe operação financeira funcional | 0 | Bloqueio/reabertura, snapshot e integridade | P0 | Estado de competência e política de mudanças pós-fechamento |
| Prestação de contas | Lista período/status; demonstrativos; estado aberto/fechado; relatórios anuais selecionam período fechado | Não implementada operacionalmente | 0 | Conteúdo, composição, documentos, aprovação e publicação | P0 | Reconstituir por ledger, competência, documento e trilha de aprovação |
| Relatórios | Filtros/listagens observados em 57/57; resultados não gerados | Sem relatórios operacionais financeiros | 0 | Valores, colunas finais, agregações e fórmulas ainda sem evidência | P1 | Priorizar relatórios que fecham ciclo; fórmulas rastreáveis |
| Documentos | Disco, comprovantes, contratos, avisos e anexos identificados | Não implementado no ERP financeiro | 0 | Armazenamento e vínculo seguro a entidades | P1 | Documento com ACL, hash, histórico, retenção e antivírus |
| Contratos | Lista de seguros e contratos na Agenda; campos não auditados | Não implementado | 0 | Vigência, reajuste, alerta, anexos e fornecedor | P1 | Agregado contratual tenant-scoped após cadastros/financeiro |
| Orçamento | Previsão orçamentária em menu de configuração e relatórios | Sem persistência | 0 | Plano previsto × realizado | P1 | Valores por competência/conta e comparação à razão realizada |
| Leituras/consumo | Medidores, tarifas, leitura, importação e relatórios | Não implementado | 0 | Fonte de itens de cobrança variável | P1 | Medidor → leitura validada → tarifa → item de cobrança |
| Agenda/áreas comuns/portaria | Reservas, ocorrências, acessos, entregas e manutenção identificados | Fora do atual escopo ERP funcional | 0 | Capacidades operacionais sem fluxo de domínio | P2 | Integrar agenda e acesso quando cadeia financeira P0 estiver pronta |
| Segurança/auditoria | Menus de logs/acessos; isolamento legado não foi testado | Tenant scope, grants, MFA e sink de auditoria ERP existem | 3 | Trilha financeira e segregação operacional ainda não exercitadas | P0 | Auditoria append-only, autorização por ação e política quatro-olhos |
| Talk / Support / Meu Dia | Diferencial Moves, fora da navegação legada observada | Produtos Moves integrados na plataforma | 3 | Relacionar eventos e contexto ERP aos produtos | P3 | Notificações/ações contextuais com ACL e sem duplicar ledger |

## Requisitos diferenciais Moves

- Multiadministradora e isolamento rigoroso; toda relação financeira deve provar tenant de ambas as pontas.
- Uma pessoa mantém vários papéis e vínculos temporais em várias unidades/condomínios.
- Cobrança consolidada opcional por titular/condomínio preserva o valor de cada unidade e aloca baixa parcial/total de forma rastreável.
- Talk, Support, Meu Dia, automações e IA futura consomem eventos autorizados; não alteram diretamente os saldos financeiros.

## Estimativa

Paridade operacional observável estimada em **10% (faixa 5–15%)**: a fundação de acesso, cadastros básicos e relacionamentos existe, mas o caminho dinheiro→banco→conciliação→fechamento→prestação ainda é majoritariamente visual ou ausente. A faixa reflete o escopo de módulos inventariados, não uma auditoria exaustiva de cada página APControle.

## Atualização da evidência de relatórios e bancos

As telas de filtro/listagem foram abertas e catalogadas para todos os 57 nomes do catálogo. Nenhum resultado de relatório foi gerado; por isso a evidência mostra filtros, não valores/colunas finais ou cálculos. A referência documenta automação no OFX e arquivos de remessa/retorno, o que aumenta a precisão do gap do Moves, sem mudar sua nota: a página Moves correspondente continua somente visual.

Os relatórios contábeis selecionam fechamentos para balancete anual, balanço patrimonial e resultado de exercício. Isto comprova uma dependência operacional do período fechado para esses relatórios específicos; não comprova que todos os demonstrativos dependam do fechamento.

**Contagem preliminar de gaps mantida:** 16 P0 e 7 P1, com fronteiras ainda sujeitas à auditoria de contas, fechamento e conteúdo final da prestação. Não reduzir gaps com base apenas em formulário visual.

### Evidência nova de fatura/boletos/recibos e disponibilidade das listas

- Um detalhe de fatura acessado por ação rotulada “Ver fatura” mostrou vários itens (um padrão e uma cobrança extra) e totalização. A existência de fatura multi-item fica confirmada para o registro observado, não como cardinalidade global.
- A consulta à lista de boletos com filtro visível da fatura retornou um boleto relacionado, ainda aguardando pagamento; não comprova exclusividade nem emissão de múltiplos boletos.
- A lista de recibos separa origem, tipo e situação e oferece comprovante de pagamento. O comprovante não foi aberto/baixado; o vínculo e suas permissões continuam gap.
- As consultas de contas a pagar, parcelas a pagar e contas a receber não retornaram registros no condomínio e contexto atuais, impedindo validação detalhada de aprovação, rateio, liquidação e isolamento por registro.
- A lista de prestação expõe períodos/condomínio/estado. Para registro fechado, a interface informa que não permite editar; mostra ação de geração e reabertura. Nenhuma delas foi executada, e o conteúdo/saldo da prestação continua inacessível em leitura segura.

Estas evidências refinam apenas a descrição operacional: **paridade segue estimada em 10% (5–15%) e gaps permanecem 16 P0 / 7 P1**. Título/parcela/pagamento, fonte financeira canônica, cardinalidade geral de boleto e composição/origem da prestação continuam sem comprovação suficiente.

## Matriz final de cobertura da auditoria de referência (2026-10-06)

“Descoberto” mede identificação de UI/menu; “auditado” mede consulta estrutural. “Fluxo comprovado” exige ligação observada de ponta a ponta, não apenas formulário, coluna ou ação disponível. Todos os nomes de registros reais foram omitidos.

| Domínio | Descoberto | Auditado | Fluxo comprovado | Gap Moves |
|---|---|---|---|---|
| Cadastros | Sim | Parcial: condomínio, pessoas, unidades, fornecedores | Cadastro básico, sem ciclo temporal completo | P0: completar campos, vínculos e históricos |
| Pessoas | Sim | Parcial: vínculo global e filtros/listas | Reuso global observado; papéis simultâneos/histórico não | P0: vínculos temporais e multiunidade |
| Unidades | Sim | Parcial: condomínio/bloco/unidade/pessoas | Associação observada; cardinalidade completa não | P0: copropriedade, moradores, fração e história |
| Fornecedores | Sim | Parcial: lista/categorias | Fornecedor→despesa/pagamento não | P0: integração com título e fornecedor |
| Plano de contas | Sim | UI/árvore/configuração e opções de relatórios | Conta→movimento/saldo não | P0: modelo e razão contábil |
| Centros de custo | Sim | Aba de plano e filtros em relatório | Centro→lançamento/rateio não | P0/P1: escopo e aplicação precisam confirmação |
| Contas a pagar | Sim | Listas/filtros; sem linha em contextos pesquisados | Nenhum ciclo comprovado | P0: título→parcela→aprovação→pagamento |
| Contas a receber | Sim | Detalhe de título e tabela de parcelas | Um título→duas parcelas; recebimento não comprovado | P0: baixa, parcial, reversão e banco |
| Cobrança | Sim | Faturas, tipo, itens e estados | Fatura observada, sem geração auditada | P0: regra, execução e distribuição por unidade |
| Faturas | Sim | Lista e detalhe consultivo histórico | Fatura→item; estado de paga exibido | P0: origem canônica dos itens e baixa |
| Boletos | Sim | Lista relacionada, estados e referências | Boleto(s)→referências de cobrança observados | P0/P1: emissão, liquidação e vínculo ao recebimento |
| Recebimentos | Sim | Colunas de parcelas/faturas e relatórios | Nenhum evento recebido rastreado até banco | P0: recebimento idempotente e alocação |
| Bancos | Sim | Configuração/listas/relatórios | Saldo e vínculo de pagamento/recebimento não | P0: contas, movimentos e fonte de saldo |
| OFX | Sim | Formulário, ajuda e lista/estados em inventário | Matching descrito pela UI, não executado | P0: staging, match explicável e reversão |
| Retorno | Sim | Formulário e histórico em inventário | Arquivo→boleto→recebimento não | P1: processamento em sandbox |
| Fechamento | Sim | Listas/relatórios e regras de tela | Período fechado→relatório anual específico | P0: snapshot, bloqueio e reabertura auditável |
| Prestação | Sim | Lista de contextos; em B, sete abertas | Conteúdo e composição financeira não | P0: prestação ledger-backed, aprovada e publicável |
| Relatórios | Sim: 57/57 filtros | Filtros, sem resultados gerados | Cálculos/colunas finais não | P1: resultados rastreáveis ao ledger |
| Leituras | Sim | Formulário água/gás; desabilitado por configuração no contexto | Leitura→consumo→fatura não | P1: medidor, histórico, tarifa e cobrança |
| Documentos | Sim | Categorias/lista do Disco Virtual | ACL, versão e vínculo documental não | P1: documento seguro e referenciado |
| Contratos | Sim | Lista/filtros; nenhum resultado no contexto B | Fornecedor→contrato→pagamento/alerta não | P1: vigência, reajuste, anexos e alertas |
| Agenda | Sim | Calendário de reservas/áreas comuns | Reserva→cobrança/tarefa/contrato não | P2: operação integrada |
| Portaria | Sim | Visita e entrega; sem registros consultáveis no recorte | Previsão→acesso→unidade/entrega não | P2: fluxo com ACL e trilha |
| Configurações | Sim: grupos identificados | Parcial: nenhuma página de detalhe desta rodada | Regra salva→efeito em transação não | P0/P1: versionamento e auditoria de política |

**Resultado de cobertura:** há evidência de UI para todos os domínios da tabela, mas a espinha financeira não foi rastreada de origem a prestação. A contagem estimada anterior (~130 capacidades, 85+ páginas e 57 relatórios) mantém-se aproximada; gaps permanecem 16 P0 / 7 P1 e paridade ~10% (faixa 5–15%). **NO-GO** para implementar núcleo P0 até comprovar pagamento/recebimento, banco/ledger e conteúdo de prestação por ação de leitura explicitamente segura.

## Confiança final por domínio e limites

A = alta evidência de fluxo/estrutura; B = suficiente para fundação limitada; C = parcial; D = desconhecido. Avalia evidência exploratória, não paridade implementada.

| Domínio | Confiança | Evidência principal |
|---|---|---|
| Cadastros | B | Cadastros básicos e listas vistos; histórias e casos-limite incompletos. |
| Condomínios | B | Cadastro/contexto consultado; parâmetros de negócio incompletos. |
| Pessoas | B | Pessoa global e papéis listados; temporalidade parcial. |
| Unidades | B | Bloco/unidade e vínculos vistos; cardinalidade total desconhecida. |
| Fornecedores | B | Cadastro visível; pagamento/título não rastreado. |
| Plano de contas | B | Hierarquia e mapeamentos especiais vistos; aplicação no ledger desconhecida. |
| Centro de custo | C | Aba/filtro/campo vistos sem entidade/transação de exemplo. |
| Competência | B | Período/status, bloqueio descrito e relatórios fechados observados. |
| Contas a pagar | C | Listas e campos, sem registro consultável. |
| Pagamento | D | Sem trilha de pagamento até parcela e banco. |
| Contas a receber | B | Título e duas parcelas consultados; baixa incompleta. |
| Cobrança | B | Fatura/item/boleto e configurações parciais vistas. |
| Fatura | B | Lista/detalhe histórico consultados, sem cálculo de emissão. |
| Boleto | B | Estados e configuração consultados; ciclo bancário incompleto. |
| Recebimento | C | Campos/lista correlatos, sem trilha de liquidação. |
| Banco | C | Configuração/relatórios vistos; extrato não relacionado a evento. |
| OFX | C | Ajuda de matching e estado, sem processamento. |
| Retorno | C | Formulário/lista reconhecidos, sem processamento. |
| Conciliação | C | Matching descrito pela ajuda; sem pareamento observado. |
| Fechamento | C | Estados e efeitos descritos; ciclo seguro não acessível. |
| Prestação | D | Sem visualização segura de composição e saldo. |
| Relatórios | B | Filtros de 57/57 catalogados, saídas não geradas. |
| Leituras | C | Formulário consultado; configuração de água/gás inativa no contexto. |
| Documentos | C | Categorias/anexos listados; ACL/versões não verificados. |
| Contratos | C | Lista/filtros vistos, sem registros no recorte B. |

## Decisão arquitetural orientativa

**Fundação segura para implementar (fora desta auditoria):** catálogo de competências mensais abertas por condomínio e tenant/administradora, com período explícito, unicidade, autorização, auditoria e UI consultiva/criação. Não implementar nesta execução. O teste de criação repetida, isolamento por tenant e FK composta deve acompanhar o futuro slice.

**Operação que exige mais validação:** fechar/reabrir períodos; plano de contas completo e transições após uso; centro de custo; contas a pagar e pagamento; baixa de recebimento e banco; segunda via/boletos; juros, multa e desconto; OFX/retorno/conciliação; prestação e ledger.

**Comparação de primeiros candidatos:** competência precede plano de contas porque é usada transversalmente em título, fatura, lançamento e relatório e tem período/estado/mês observáveis; uma versão somente de períodos abertos evita inventar efeitos contábeis. Plano de contas vem depois de resolver escopo e ciclo de vida. Centro de custo tem apenas evidência de aba/filtro e fica para depois.

Não reduzir a paridade ~10% nem os 16 gaps P0/7 P1 por existir configuração visual; não há fonte canônica financeira confirmada.


## Implementação Moves — fração ideal cadastral

Na branch `feat/mvs-v1-integration`, a fração ideal agora pode ser registrada como percentual decimal opcional (maior que 0 e até 100, com quatro casas) em um vínculo temporal de proprietário com unidade. A relação, as datas e a autoria do vínculo preservam o histórico; mudança de titularidade permanece encerramento + novo vínculo. O banco e o serviço rejeitam percentual em papel não proprietário ou sem unidade. **Não** há regra de soma entre coproprietários, cálculo de rateio, nem inferência do comportamento do APControle; tais políticas continuam fora desta implementação. A validação HTTP/MySQL e os testes do Moves são evidência da implementação, não nova evidência do produto de referência.
