# Homologação operacional do ERP

## Fase 1 — cadastros, CNPJ e pendências no Meu Dia (2026-10-07)

Homologação executada com bancos MySQL descartáveis e identidades sintéticas. Nenhum seed ou fixture operacional foi carregado em produção. As telas de cadastro de pessoas e fornecedores foram conferidas em 1920×1080, 1024×768 e 390×844; não houve overflow horizontal. O texto auxiliar usa a escala compartilhada Moves de 12px/16px em Gotham Book.

| Cenário | Resultado | Evidência |
| --- | --- | --- |
| 5 condomínios sintéticos, incluindo CNPJ numérico, CNPJ alfanumérico, sem CNPJ e CNPJ regularizado durante a operação | FUNCIONA | E2E HTTP em MySQL descartável |
| Estrutura de 5 blocos e 33 unidades; PF/PJ, vínculos, multiunidade e copropriedade | FUNCIONA | E2E HTTP; registros persistidos e páginas consultadas |
| Competências abertas e fornecedores nos cenários suportados | FUNCIONA | E2E HTTP de cadastro e consulta |
| CNPJ ausente, pendência, Meu Dia, atribuição, status, deep link e resolução ao informar documento válido | FUNCIONA | E2E operacional; uma condição acompanha o mesmo condomínio |
| Isolamento entre tenant A e B | FUNCIONA | Acesso cruzado a unidade, pessoa, fornecedor, competência, pendência e condomínio negado |
| Editar condomínio 4 depois de receber CNPJ | FUNCIONA | ID e relações existentes permanecem; tarefa pendente é resolvida |
| CNPJ alfanumérico em pessoa e fornecedor | CORRIGIDO NESTA FASE | Validação, persistência, busca e apresentação verificados por teste unitário e HTTP |
| Fluxo financeiro de centro de custo, contas a pagar e cobrança | FORA DESTA FASE | Depende das fases seguintes e de decisões de domínio documentadas |

### Correção encontrada

O suporte alfanumérico já existia no serviço compartilhado de CNPJ e no cadastro do condomínio, mas os serviços de pessoas e fornecedores removiam letras antes de validar; a busca de pessoas e os formulários também pressupunham apenas números. A normalização e validação agora reutilizam `Cnpj`, mantendo o CPF em caminho independente. Os campos de CNPJ aceitam caracteres alfanuméricos sem mudar o contrato de armazenamento textual.

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
