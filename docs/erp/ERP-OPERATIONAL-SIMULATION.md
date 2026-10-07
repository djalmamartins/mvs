# Homologação operacional do ERP

## Fase 1 — cadastros, CNPJ e pendências no Meu Dia (2026-10-07)

Homologação executada com bancos MySQL descartáveis e identidades sintéticas. Nenhum seed ou fixture operacional foi carregado em produção. Os fluxos operacionais listados nesta fase foram conferidos em 1920×1080, 1024×768 e 390×844; não houve overflow horizontal. Texto auxiliar e metadados visíveis respeitam a escala mínima Moves de 12px/16px.

| Cenário | Resultado | Evidência |
| --- | --- | --- |
| 5 condomínios sintéticos, incluindo CNPJ numérico, CNPJ alfanumérico, sem CNPJ e CNPJ regularizado durante a operação | FUNCIONA | E2E HTTP em MySQL descartável |
| Estrutura de 5 blocos e 35 unidades; PF/PJ, vínculos, multiunidade e copropriedade | FUNCIONA | E2E HTTP; registros persistidos e páginas consultadas |
| Competências abertas e fornecedores nos cenários suportados | FUNCIONA | E2E HTTP de cadastro e consulta |
| CNPJ ausente, pendência, Meu Dia, atribuição, status, deep link e resolução ao informar documento válido | FUNCIONA | E2E operacional; uma condição acompanha o mesmo condomínio |
| Isolamento entre tenant A e B | FUNCIONA | Acesso cruzado a unidade, pessoa, fornecedor, competência, pendência e condomínio negado |
| Editar condomínio 4 depois de receber CNPJ | FUNCIONA | ID e relações existentes permanecem; tarefa pendente é resolvida |
| CNPJ alfanumérico em pessoa e fornecedor | CORRIGIDO NESTA FASE | Validação, persistência, busca e apresentação verificados por teste unitário e HTTP |
| Fluxo financeiro de centro de custo, contas a pagar e cobrança | FORA DESTA FASE | Depende das fases seguintes e de decisões de domínio documentadas |

### Correção encontrada

O suporte alfanumérico já existia no serviço compartilhado de CNPJ, mas três caminhos ainda perdiam ou ignoravam letras: a listagem de condomínios removia caracteres não numéricos antes da formatação; a busca de fornecedores removia letras e podia transformar a busca de documento em `LIKE '%%'`; e o cadastro/edição da administradora removia letras antes da validação. A apresentação de condomínios e fornecedores agora usa o formatador comum, a busca preserva o CNPJ alfanumérico e `CompanyService` normaliza/valida sem converter o documento em número. Os formulários de onboarding e configurações aceitam texto. CPF continua em caminho independente.

### Matriz de auditoria CNPJ/CPF

| Local | Regra auditada | Aceita CNPJ numérico? | Aceita CNPJ alfanumérico? | Risco observado | Correção/evidência |
| --- | --- | --- | --- | --- | --- |
| `Services/Platform/Cnpj` | Normaliza texto, valida 12 caracteres alfanuméricos + 2 verificadores numéricos e formata | Sim | Sim | Centraliza cálculo; sem coerção numérica | Algoritmo comparado com a especificação oficial da Receita; testes de serviço |
| `CondominiumService` / `erp_condominiums.tax_id` | Campo textual opcional; valida antes de gravar; estrutura MySQL `VARCHAR` | Sim | Sim | Listagem antiga removia letras na formatação | Listagem usa `Cnpj::format`; fluxo de criação/edição e busca HTTP cobertos |
| `PeopleService` / `erp_people.document_number` | PF usa CPF; PJ/CNPJ usa validador comum; armazenamento textual | Sim | Sim | Regra CPF poderia ser afetada por uma normalização compartilhada | CPF permaneceu independente; E2E pessoa PF/PJ e regressão CPF passaram |
| `SupplierService`, `SupplierRepository` e detalhe/listagem | Documento PJ textual, busca e máscara | Sim | Sim | Busca só numérica produzia `LIKE '%%'` para CNPJ alfanumérico; máscara não apresentava letras | Normalização/máscara comum e E2E restritivo: resultado correspondente sem fornecedor alheio |
| `CompanyService` / `talk_tenants.tax_id` e `erp_administrators.tax_id` | Identificação fiscal textual da administradora | Sim | Sim | Remoção de não dígitos apagava letras e descartava CNPJ válido | Normaliza/valida por `Cnpj`; teste cria e edita preservando valor canônico |
| Onboarding e Configurações da plataforma | Entrada HTML de CNPJ | Sim | Sim | `inputmode=numeric` impedia entrada natural de letras em teclados móveis | Entrada textual, capitalização e limite de formato |
| CPF em Pessoas/Fornecedores | 11 dígitos e validação de CPF própria | N/A | N/A | Regressão de regra entre tipos documentais | Sem alteração de algoritmo/caminho de CPF; testes PF existentes e suíte completa |
| CEP, telefone, WhatsApp e códigos numéricos | Normalização numérica de outro domínio | N/A | N/A | Busca textual superficial por `preg_replace` pode confundir domínios | Não alterados: não operam sobre CNPJ |

Todos os CNPJs de teste foram sintéticos. Para CNPJ alfanumérico, a documentação oficial define letras/dígitos no corpo e dois dígitos verificadores; os caracteres são tratados como valores ASCII e o DV usa módulo 11. Referências: [Manual do DV do CNPJ Alfanumérico — Receita Federal](https://www.gov.br/receitafederal/pt-br/centrais-de-conteudo/publicacoes/documentos-tecnicos/cnpj/manual-dv-cnpj.pdf) e [CNPJ Alfanumérico — Receita Federal](https://www.gov.br/receitafederal/pt-br/acesso-a-informacao/acoes-e-programas/programas-e-atividades/cnpj-alfanumerico).

### Regressão adicional e gates

- Busca de fornecedor com CNPJ alfanumérico era ampla demais dentro da administradora porque a normalização vazia gerava `LIKE '%%'`. Corrigido e coberto por teste HTTP de resultado exclusivo.
- Consultas sem caracteres documentais (somente pontuação) também não podem produzir `LIKE '%%'`; a condição de documento agora só participa quando há caracteres normalizados. Há asserção HTTP negativa.
- Cadastro/edição da administradora agora preserva CNPJ alfanumérico; edição sincroniza `talk_tenants` e `erp_administrators` em uma transação. Teste de serviço troca para outro CNPJ alfanumérico sintético e confere ambos.
- Listagem de condomínios agora mostra o CNPJ alfanumérico formatado; regressão HTTP incluída no cenário de cinco condomínios.
- A revisão visual encontrou metadados de diretório abaixo da escala Moves em tabelas responsivas e controles do Application Shell. Os metadados do ERP afetados e controles visíveis do Shell foram alinhados ao mínimo de 12px/16px; títulos de página usam 28px/34px e títulos de seção 16px/22px.
- Baseline antes desta correção no HEAD `6604c26`: PHPUnit **269 testes / 1.194 assertions**, E2E de pessoas/fornecedores **78** e E2E operacional **40**. Após as correções finais: PHPUnit **270 testes / 1.198 assertions**; E2E de pessoas/fornecedores **82 verificações**; E2E operacional/pendências **43 verificações**. PHPStan, lint PHP, Composer validate/audit, Node 10/10, npm audit e `git diff --check` aprovados. `services/talk-whatsapp` não foi alterado; Node é executado por seu package dedicado.
- QA visual real em Chrome headless com overrides de 1920×1080, 1024×768 e 390×844: 15 rotas — Meu Dia, condomínios (lista/criação/edição e busca por CNPJ alfanumérico), unidades (lista/cadastro), pessoas (lista/cadastro), fornecedores (lista/cadastro), competências (lista/cadastro) e pendências (lista/detalhe). 45 capturas/respostas HTTP 200, sem overflow horizontal. Títulos 28px/34px Gotham Medium; corpo Gotham Book; varredura de metadados sem texto abaixo de 12px. E2E continua sendo a evidência funcional primária; viewport é evidência visual/layout.

### Matriz de acompanhamento da issue #253

| Requisito #253 | Implementação | Teste | Evidência | Status |
| --- | --- | --- | --- | --- |
| Condomínio e tenant | Condomínios ligados à administradora/tenant | E2E HTTP com tenant A/B | Banco MySQL descartável e tentativas cruzadas negadas | Atendido no fluxo testado |
| Bloco e unidade | Blocos persistidos e unidades vinculadas; código/complemento editáveis | E2E HTTP de estrutura e edição | Cinco blocos/35 unidades no cenário operacional; PHPUnit e navegador | Atendido no fluxo cadastral coberto |
| Identificador/complemento | Edição transacional com validação, unicidade e auditoria | Testes de serviço/HTTP, CSRF e isolamento A/B | `223363a` e comentário/evidências da issue #253 | Atendido neste subescopo |
| PF/PJ, multiunidade e copropriedade | Pessoas e vínculos temporais associados a unidades | E2E HTTP e constraints MySQL | Cenário sintético da fase 1 | Atendido no cenário testado |
| Fração ideal cadastral | Percentual opcional no vínculo de proprietário | PHPUnit, HTTP e constraint MySQL | Commit de fundação cadastral e E2E | Atendido no escopo cadastral |
| UX desktop/tablet/mobile | Telas de estrutura revisadas em três viewports | Navegação visual autenticada | Chrome real, 3 tamanhos e relatório temporário em `/tmp` | Validado sem overflow nos fluxos capturados |
| Atributos físicos adicionais e fechamento da issue | Ainda sem requisito de domínio especificado (por exemplo, área/vaga/andar/tipo) | Não se inventou schema nem regra | Body atual da #253 pede “estrutura física/cadastral” sem enumerar campos | **Gap aberto; issue permanece aberta** |

O resultado visual cobre páginas e layouts, mas não constitui uma sessão exploratória manual completa para cada ação. A #253 não foi fechada nem marcada concluída: o escopo de “estrutura física/cadastral” ainda não define quais atributos físicos adicionais são exigidos, e a própria issue requer validação no navegador além dos testes HTTP.

### Gates e limites

- PHPUnit completo: 269 testes, 1.194 asserções, aprovado em ambiente descartável.
- E2E de pessoas/fornecedores: 78 verificações HTTP aprovadas.
- E2E operacional/pendências: 40 verificações HTTP aprovadas.
- PHPStan, PHP lint dos arquivos alterados, `composer validate --strict`, `composer audit` e `git diff --check`: aprovados.
- O repositório não contém `package.json`; portanto `npm test`/`npm audit` não se aplicam. Não houve alteração de JavaScript de produto.
- Uma execução inicial de PHPUnit contra o banco indicado pelo `.env` encontrou divergência de schema local; nenhuma migration foi executada nesse banco. A validação completa foi repetida no MySQL descartável.
- #253 permanece aberta: esta homologação cobre os fluxos P0 que conseguiu comprovar, mas não comprova todo o escopo de estrutura física/homologação da issue. #255 já estava fechada e não foi reaberta. #75 segue aberta; este registro não fecha a Central de Obrigações.

Os testes foram executados sem importar dados reais. Fluxos de centro de custo, contas a pagar e cobrança ainda não foram homologados nesta fase.

## Ciclo financeiro — fase 1, fatura (2026-10-07)

**Status: BLOQUEADA POR DECISÃO DE DOMÍNIO.** A homologação anterior validou cadastro e competências, não uma composição financeira. A revisão do HEAD confirma título/parcelas manuais em `erp_receivables`, mas nenhuma fatura/composição persistida. A referência APControle mostra faturas com itens e vínculo a boleto em amostras, sem provar fórmula ou cardinalidade geral. Assim, não há E2E de fatura a executar sem inventar a origem ou os valores.

Regras pendentes para liberar a fase:

- fontes e cálculo/entrada explícita dos itens da composição por unidade e competência;
- escolha do responsável financeiro quando a unidade tem coproprietários;
- consolidação multiunidade somente dentro do mesmo condomínio, preservando cada alocação original;
- contrato de geração fatura → itens/snapshot → recebível/parcelas e proteção contra duplicidade.

As issues existentes são #257 (cobrança/boletos) e #258 (consolidação); não foi criada issue duplicada. Nenhuma migration, dado financeiro ou comportamento de produto foi alterado neste registro.

## Ciclo ERP — fundação de contas a pagar (#300, 2026-10-07)

### Escopo verificado

- Criar/listar/consultar obrigação por fornecedor vigente e condomínio da administradora atual.
- Classificar pelas contas analíticas de passivo e despesa do plano ativo do mesmo condomínio.
- Informar total e uma ou mais parcelas com valor, vencimento e competência aberta; soma exata em centavos.
- Gravar título, parcelas e evento `erp.payable.created` em uma transação; negar CSRF inválido e referências de tenant/condomínio incorretas.
- Verificar listagem vazia, formulário e detalhe HTTP, shell/sidebar/assets, pesquisa, acesso 404 cruzado e FK composta no MySQL.

### Evidências e gates

- Migration fresh completa (`20261007_003_create_erp_payables.sql`) e migration safety test: aprovados em MySQL descartável `moves_codex_erp_payables_*`, removido ao final.
- PHPUnit completo em DB descartável: **273 testes / 1.221 assertions**, aprovados. Baseline em configuração local de desenvolvimento havia 6 erros de ambiente/schema em Day/SaaS; esse banco não foi migrado nem alterado. Em DB descartável recém-migrado a suíte inteira ficou verde.
- E2E HTTP com dois tenants e MySQL/InnoDB descartável: **15 verificações**, incluindo login, formulário, CSRF, validação de total, bloqueio de fornecedor B, persistência/detalhe/pesquisa/auditoria e bloqueio de leitura pelo tenant B; aprovado.
- PHPStan, lint PHP dos arquivos alterados, `node --check` do JS novo, `composer audit`, `composer validate --strict` e `git diff --check`: aprovados na validação final; `services/talk-whatsapp` passou 10/10 testes e `npm audit` reportou zero vulnerabilidades.
- QA no navegador real autenticado: lista, formulário, filtro de opções por condomínio e detalhe abertos sem erro. Capturas em desktop (1280×720), tablet (768×1024) e mobile (390×844); lista e detalhe usam linhas empilhadas em tablet, formulário passa a uma coluna e não há overflow horizontal (`document/body.scrollWidth` 375px em viewport 390px). Os estilos usam a tipografia Moves compartilhada: título 28/34, texto e campos 14/20, seções 16/22 e metadados 12/16.

### Decisões e limites

Um título com uma ou mais parcelas e entrada explícita de valores é decisão Moves deste slice; os exemplos vazios do APControle não definem cardinalidade universal. A obrigação não tem estado de aprovação/pagamento e não atualiza razão, saldo ou banco. Pagamento/estorno, anexos fiscais, retenções, recorrência, rateio e quatro-olhos seguem fora, conforme o escopo limitado da #300 e a issue ampla #48.
