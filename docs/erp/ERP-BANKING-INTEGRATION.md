# Integrações bancárias do Moves ERP

**Estado:** arquitetura e planejamento, não implementação. **Consulta das fontes oficiais:** 2026-10-06. Revalidar documentação, adesão, limites e contratos antes de cada adapter: bancos publicam versões e habilitações por produto/conta/cooperativa.

## 1. Objetivo e limites

Preparar uma base multibanco para contas condominiais Inter, Sicoob e Sicredi sem fazer o ERP depender de APIs proprietárias. Esta proposta não implementa chamadas bancárias, não cria migrations nem aprova pagamentos. Paridade do ERP permanece ~10% (faixa 5–15%), 16 gaps P0 e 7 P1: planejar integração não entrega capacidade operacional.

A auditoria APControle é evidência histórica somente de leitura. Ela observou configuração bancária, pagamentos, boletos, OFX, CNAB/retorno e relatórios, mas não rastreou pagamento/recebimento até uma fonte financeira canônica. Não usar o APControle para presumir ledger, relação universal fatura–boleto, política de rateio ou confirmação de liquidação.

## 2. Princípios

- Domínio Moves depende de contratos próprios; adapters traduzem os modelos de cada API.
- Capabilities são granulares e efetivas por conexão: produto habilitado/contratado, credenciais, escopos e capacidade do adapter. Não tornar toda operação obrigatória em uma interface universal nem implementar métodos vazios.
- Contas pertencem ao condomínio e à administradora/tenant; uma administradora pode ter vários condomínios e cada condomínio nenhuma, uma ou várias contas, em diferentes bancos.
- Diferenciar conta bancária, conexão técnica, comando financeiro Moves, evento/transação externa e ledger contábil. Movimento importado do banco não é lançamento contábil nem comprovação automática de pagamento/recebimento.
- Valores monetários decimais, datas/moeda explícitas, status enumerados e status externo preservado junto do status normalizado.
- Toda consulta/escrita e todo callback são isolados por tenant e conexão autorizada. Integrações e workers não confiam em identificadores vindos apenas da UI.
- API-first onde houver produto contratado; OFX e CNAB ficam como caminhos complementares, não são eliminados por haver API.
- Antes de operação mutável, o domínio financeiro Moves (contas a pagar/receber, aprovação, pagamento/recebimento e vínculo contábil) precisa ser explícito. Nenhum clique pode equivaler a “pago”.

## 3. Pesquisa oficial e matriz comparativa

Classificações: **CONFIRMADO** = listado/documentado por fonte oficial consultada; **PARCIAL** = existe para alguns produtos/ambientes ou só a capacidade equivalente está confirmada; **NÃO ENCONTRADO** = não se achou confirmação suficiente nas fontes oficiais consultáveis; **NÃO APLICÁVEL** = não se aplica àquela linha. “Não encontrado” não prova inexistência. No portal Sicoob, a referência técnica é uma aplicação web sem conteúdo textual acessível nesta consulta; detalhes de endpoint/limite/homologação devem ser confirmados no portal autenticado.

| Recurso | Inter | Sicoob | Sicredi |
|---|---|---|---|
| Autenticação | **CONFIRMADO** — OAuth/token e certificados para a integração; conferir fluxo e escopos por API | **PARCIAL** — OAuth2 Client Credentials + certificado mTLS confirmados no manual Pix oficial legado; não generalizar para outros produtos | **CONFIRMADO** — mecanismo varia por API; consultar detalhe em §4 |
| mTLS | **PARCIAL** — integração usa certificado PFX/chave, mas a fonte consultada não explicita um requisito uniforme por endpoint | **PARCIAL** — obrigatório segundo manual oficial da API Pix consultado; demais APIs não verificadas | **PARCIAL** — requerido para Pix, Multipag, extrato e saldo; não para Cobrança |
| OAuth2 | **CONFIRMADO** — token OAuth; token documentado com 60 min de validade no FAQ | **PARCIAL** — OAuth2 documentado para API Pix no manual oficial; demais produtos não confirmados | **CONFIRMADO** — fluxo e escopos diferem por API |
| Certificado | **CONFIRMADO** — certificado/chave ativados na integração; arquivos PFX mostrados pelo SDK oficial | **PARCIAL** — certificado X.509 mTLS da API Pix conforme manual; produção não aceita autoassinado naquele manual | **PARCIAL** — CSR/certificado Sicredi para produtos mTLS |
| Cobrança | **CONFIRMADO** — API Cobranças (Boleto com Pix), V3 atual segundo portal; V2 foi descontinuada em 04/05/2026 | **CONFIRMADO** — produto “Cobrança Bancária” listado pelo Sicoob; operações/contrato técnicos não detalhados aqui | **CONFIRMADO** — API Cobrança; produto e adesão dependem do associado/cooperativa |
| Boleto | **CONFIRMADO** — emissão/consulta/cancelamento na API de Cobranças | **CONFIRMADO** — produto de Cobrança Bancária; detalhamento por versão no portal | **CONFIRMADO** — Cobrança tradicional documentada |
| Boleto híbrido | **CONFIRMADO** — boleto com QR Code Pix | **NÃO ENCONTRADO** — fonte pública consultada não esclarece modalidade híbrida | **CONFIRMADO** — modalidade híbrida existe; requer habilitação conforme guia de acesso |
| Pix cobrança | **CONFIRMADO** — imediata e com vencimento; APIs Pix e Cobranças | **CONFIRMADO** — Pix Recebimentos listado; escopo do manual Pix requer autenticação específica | **CONFIRMADO** — API Pix imediata e com vencimento |
| Webhook cobrança | **CONFIRMADO** — API Cobranças requer configurar webhook para notificação de pagamento | **NÃO ENCONTRADO** — webhook de Pix é mencionado no manual Pix; webhook de boleto/cobrança não confirmado | **CONFIRMADO** — cobrança informa liquidação e estorno, com variações por canal |
| Consulta cobrança | **CONFIRMADO** — consulta e sumário de cobrança documentados | **PARCIAL** — produtos de cobrança listados; operações exatas requerem acesso ao portal técnico | **CONFIRMADO** — APIs de Cobrança/Pix incluem consultas |
| Cancelamento/baixa | **CONFIRMADO** — cancelar cobrança; verificar estado e semântica por endpoint | **PARCIAL** — não se mapeou operação/versionamento exato | **CONFIRMADO** — instruções de baixa documentadas na API Cobrança; confirmar transições válidas do título |
| Saldo | **CONFIRMADO** — API Banking | **CONFIRMADO** — API Conta Corrente listada com consulta de saldo | **CONFIRMADO** — API Saldo de Conta Corrente |
| Extrato | **CONFIRMADO** — API Banking | **CONFIRMADO** — API Conta Corrente listada com consulta de extrato | **CONFIRMADO** — API Extrato de Conta Corrente |
| Pagamento de boleto | **CONFIRMADO** — API Banking e pagamentos em lote | **CONFIRMADO** — produto Cobrança Bancária - Pagamentos listado | **CONFIRMADO** — Multipag |
| Pix pagamento | **CONFIRMADO** — API Banking | **CONFIRMADO** — Pix Pagamentos listado | **CONFIRMADO** — Multipag, incluindo pagamentos por chave/dados bancários |
| Lote de pagamentos | **CONFIRMADO** — API Banking lista pagamentos em lote | **NÃO ENCONTRADO** — sem contrato/lote verificado na documentação pública consultada | **NÃO ENCONTRADO** — endpoints Multipag não foram confirmados como lote nesta consulta |
| Retorno CNAB | **CONFIRMADO** — CNAB 240 pagamentos e CNAB 400 cobrança/retorno documentados; VAN para alguns segmentos | **NÃO ENCONTRADO** — confirmar layouts/remessa/retorno por produto e cooperativa | **PARCIAL** — documentação CNAB 240 de extrato existe; layout de retorno de cobrança/pagamento precisa ser confirmado por produto |
| OFX/API equivalente | **PARCIAL** — extrato API confirmado; OFX como endpoint não encontrado | **PARCIAL** — extrato API listado; OFX API não encontrado | **PARCIAL** — extrato API confirmado; OFX API não encontrado |
| Sandbox | **CONFIRMADO** — portal oferece ambiente Sandbox; validar produtos e cenários disponíveis | **NÃO ENCONTRADO** — documentação acessível nesta consulta não confirmou endpoints/escopo de sandbox | **PARCIAL** — depende de cada guia/API; webhook de Multipag não tem sandbox para cadastro de webhook |
| Idempotência | **PARCIAL** — há identificadores de negócio (ex.: txid/código de solicitação); garantia de replay por API não foi confirmada | **NÃO ENCONTRADO** — sem regra pública verificável na consulta | **PARCIAL** — identificadores como `idTransacao` são documentados para Pix Multipag; semântica de replay/duplicidade deve ser confirmada |
| Rate limit | **CONFIRMADO** — há 429 e limites por API; FAQ informa emissão limitada por minuto e criação de token até 5/min | **NÃO ENCONTRADO** — limite vigente não verificado | **PARCIAL** — limites explícitos para algumas operações de Cobrança, não universais |
| Webhook (outros eventos) | **CONFIRMADO** — Banking/Pix/Cobranças documentam callbacks por produto | **PARCIAL** — API Pix e Pix Pagamentos listados; segurança/eventos de callback por operação precisam revisão atual | **PARCIAL** — Pix, Cobrança e Multipag têm eventos; contrato de segurança varia por API |
| Consulta posterior ao webhook | **CONFIRMADO** — FAQ indica consultar cobrança/Pix por identificador e callback de pagamento tem identificador de consulta | **NÃO ENCONTRADO** — reconsulta após evento não confirmada em fonte acessível | **PARCIAL** — alguns callbacks são apenas gatilho e exigem consulta; comportamento é por produto |

### Fontes oficiais consultadas em 2026-10-06

**Inter**

- [Nossas APIs](https://developers.inter.co/docs/introducao/nossas-apis) — catálogo atual, depreciação Cobrança V2, recursos de Cobrança V3, Banking, Pix, callbacks e conta PJ.
- [FAQ oficial](https://developers.inter.co/duvidas-frequentes) — expiração/token, rate limit/429, escopo do callback, cobrança síncrona/assíncrona e CNAB/VAN.
- [SDK Java oficial](https://developers.inter.co/docs/sdks/sdk-java) — configuração de certificado PFX, ambientes, rate limit, avisos de expiração e operações disponíveis no SDK.
- [Portal oficial](https://developers.inter.co/) — criação de integração, ativação de chaves/certificado e Sandbox.

**Sicoob**

- [Portal oficial Developers](https://developers.sicoob.com.br/portal/documentacao) — referência técnica; carregou sem conteúdo legível nesta consulta (portanto, não foi base para afirmar limites/contratos específicos).
- [Portal oficial para desenvolvedores Sicoob](https://www.sicoob.com.br/web/sicoobleste/servicos-empresa/-/asset_publisher/lPNqdz0jZZX1/content/portal-para-desenvolvedores/20128) — lista Pix Recebimentos, Cobrança Bancária, Conta Corrente, Pix Pagamentos, SPB, pagamento de boletos, Open Finance e outros produtos para PJ cooperado.
- [Manual oficial Sicoob Pix](https://www.sicoob.com.br/documents/61112012/64877374/Sicoob%2BPix%2B%E2%80%93%2BManual%2Bpara%2Butiliza%C3%A7%C3%A3o%2Bda%2BAPI%2BPix.pdf/994711e9-bdf4-a479-f9f1-33266d83d58c?download=true&t=1627932447480) — publicação de 2021, confirma OAuth2 client credentials, mTLS, certificado X.509 e webhook mTLS para aquela API Pix. É antiga; validar versão atual antes de implementar.

**Sicredi**

- [Informações gerais e autenticação por API](https://developers.sicredi.com.br/public/docs/general-information) — catálogo, variações OAuth/mTLS, sandbox, credenciais, certificados e matriz de webhooks.
- [Acesso à API de Cobrança](https://developers.sicredi.com.br/public/docs/como-obter-acesso-%C3%A0-api-de-cobran%C3%A7a) — adesão, modalidade híbrida e habilitação comercial.
- [Referência: cadastrar boleto](https://developers.sicredi.com.br/public/reference/cadastrarboleto) — boleto híbrido, comandos/campos e limitações do contrato.
- [Webhook Multipag](https://developers.sicredi.com.br/public/docs/webhook) — notificação de pagamentos e configuração em produção; sem sandbox para manutenção do webhook.
- [DDA Multipag](https://developers.sicredi.com.br/public/docs/dda) — autenticação, endpoints e dependência de adesão/pagador eletrônico.
- [Códigos de erro e limites](https://developers.sicredi.com.br/public/docs/c%C3%B3digos-de-erro) — 429/TPS e paginação apenas para operações especificadas.
- [Referência: pagamento Pix por dados bancários](https://developers.sicredi.com.br/public/reference/post_v1-pagamentos-pix-dados-bancarios) — headers/campos e `idTransacao` obrigatório.
- [CNAB 240 — extrato eletrônico](https://developers.sicredi.com.br/public/docs/getting-started-cnabs-240-electronic-statement) — alternativa de extrato em arquivo.

## 4. Diferenças relevantes por banco

### Inter

Catálogo oficial consultado lista API Cobranças V3 (boleto pagável por boleto/Pix, consulta, cancelamento, PDF e sumário), Banking (saldo, extrato, pagamentos incluindo Pix, lote e consultas) e API Pix (cobranças imediatas/vencimento, consulta, devolução e webhook). A V2 da API Boleto foi descontinuada em 04/05/2026: adapter novo deve mirar a V3, não exemplos legados. Integração é criada no Internet Banking; chaves e certificados são baixados/ativados. FAQ descreve token de 60 minutos e limite de 5 solicitações de token/minuto; limites de recursos variam e podem responder 429. CNAB 240 pagamentos e CNAB 400 cobrança/retorno continuam documentados; VAN depende de segmento/contratação. A documentação consultada não estabelece semântica universal de idempotência.

### Sicoob

O portal público para PJ lista famílias de produto para receber Pix, cobrança, consultar conta, fazer pagamento Pix/boleto e transferir. Isso confirma disponibilidade de famílias, não direito/habilitação para toda cooperativa, conta, associado ou escopo. O manual oficial Pix consultado é de 2021: requer OAuth2 Client Credentials, mTLS e certificado X.509; afirma mTLS também nos webhooks Pix. Não estender essa configuração para Cobrança, Conta Corrente ou Pagamentos sem a referência atual do produto no portal autenticado. Rate limit, idempotência, lote, sandbox, CNAB, versões e operação de callback continuam pendentes de validação direta no portal e com cooperativas candidatas.

### Sicredi

Autenticação difere por API: Pix, Multipag, Extrato e Saldo usam mTLS + OAuth2 Client Credentials; Cobrança usa OAuth2 `password` sem mTLS, com Código de Acesso do Internet Banking e chave de aplicação. Cobrança híbrida exige habilitação. Adesão/credenciais são tratadas por API com a cooperativa; sandbox varia. Webhooks existem para Pix, liquidação/estorno de Cobrança e status Multipag, com segurança e disponibilidade de testes específicas; a manutenção do webhook Multipag é somente em produção. Referência atual de Cobrança documenta limites de algumas operações (ex.: cadastro de boleto 20 req/s; consulta por Nosso Número 15 req/s), não um limite global. `idTransacao` existe no pagamento Pix, mas não foi confirmado como chave idempotente de replay.

## 5. Autenticação, capabilities e arquitetura Moves

```text
ERP (domínio Moves: recebível/pagável/pagamento/recebimento)
  ├─ ChargeService / PaymentService / StatementSyncService
  ├─ BankingService + capability checks + provider registry
  └─ interfaces pequenas por capability
       ├─ InterAdapter
       ├─ SicoobAdapter
       └─ SicrediAdapter (produto e autenticação específicos)
```

Usar contratos compostos por capacidade, por exemplo `ChargeProvider`, `PixChargeProvider`, `BalanceProvider`, `StatementProvider`, `BillPaymentProvider`, `PixPaymentProvider`, `WebhookProvider` e, se necessário, `BankReturnProvider`. `BankingProvider` pode descrever identidade/catálogo e informar capabilities sem exigir que cada adapter implemente todas as operações. A registry escolhe adapter pelo provider; operações verificam capability efetiva e retornam “não habilitado/não suportado” antes de criar efeitos. Uma capability disponível no catálogo não quer dizer que foi contratada/habilitada para aquela conexão.

Credenciais/scopes variam por produto/API, não só por banco. Uma conexão pode ter uma ou várias credenciais técnicas de produto. A configuração deve definir ambiente, produto, conta/provedor e referência segura da credencial; separar Sandbox de Produção. Token cache é isolado pelo par ambiente+credencial+escopos, guarda expiração do servidor e nunca é reutilizado entre tenants/contas indevidamente. Não fixar auth em um método comum que pressupõe um único OAuth flow.

## 6. Modelo conceitual (sem migrations nesta tarefa)

| Entidade | Proposta | Decisão |
|---|---|---|
| `bank_accounts` | `tenant_id`, `administrator_id`, `condominium_id`, instituição/ISPB, agência/conta mascaradas, tipo/moeda, apelido e estado | **Necessária.** FK composta deve provar administradora/condomínio/tenant; múltiplas contas por condomínio. Só metadados mínimos, sem segredo nem saldo editável como fonte financeira. |
| `bank_connections` | FK da conta, provider, produto/API, ambiente, estado, identificador externo, scopes/capabilities concedidas com versão/revisão | **Necessária.** Conexão por conta e produto, pois auth e habilitações podem divergir dentro do mesmo banco. |
| `bank_provider_capabilities` | catálogo relacional de capabilities | **Não criar no primeiro slice.** Catálogo/versionamento do adapter em código; capacidade efetiva deriva do adapter + produto/escopos/adesão verificados. Persistir snapshot somente se auditoria operacional demonstrar necessidade. |
| `bank_credentials` | valores de client secret, tokens, senhas ou chaves privadas | **Não persistir material em tabela ERP.** Persistir apenas `secret_ref`, `key_id`, tipo/produto, validade/rotação e estado, se o provedor de segredos não guardar a associação. |
| `bank_webhook_events` | conexão/provedor, identificador/digest, horário, payload cifrado ou minimizado, autenticação validada, estado de dedupe/processamento e referência de consulta | **Necessária quando o primeiro webhook for implementado.** Retenção limitada e sem tratar evento não validado como liquidação. |
| `bank_transactions` | transação externa normalizada, identificador do banco, valores/datas/moeda, descrição sanitizada, status externo e normalizado, referência de sincronização | **Necessária para extrato/API e CNAB.** Unique por conexão + identificador externo quando fornecido; não é ledger Moves. Importação OFX alimenta mesma camada normalizada. |
| `bank_sync_runs` | janela/cursor, conexão, início/fim, resultado, erro sanitizado, contagens e correlation id | **Condicional, recomendada** para sincronização recorrente, replay e suporte auditável; só criar quando BANK-008 definir cursor/execução. |

Não criar `ledger`, tabela genérica de “movimentos financeiros” ou relacionamento polymorphic definitivo nesta etapa. Persistência de cobranças, pagamentos, liquidações e tentativas deve ser decidida junto de Recebíveis/Payables e de suas chaves/FKs reais. `BankTransaction` é observação externa; conciliado não significa contabilizado. Saldo mostrado deve dizer sua origem e instante, preferencialmente derivado da API/extrato reconciliado conforme política futura.

## 7. Segurança e ambientes

- Preferir secret manager/KMS do ambiente de execução; credenciais em memória somente durante chamada. Segredo privado não vai em Git, banco, log, HTML, JS, auditoria ou exceção. Banco armazena referência opaca e key ID, nunca token/certificado/chave. Moves ainda não tem secret manager genérico identificado; MFA dispõe de `MfaSecretCipher` com AES-256-GCM e chave externa versionada, mas isso **não** substitui cofre/KMS para certificados bancários de produção. Caso cifra em repouso no banco seja etapa transitória aprovada, reutilizar o padrão AEAD versionado com chave fora do DB e plano de rotação, sem reutilizar a chave de MFA.
- Preferir certificado privado no secret store/HSM/volume secreto montado, permissão mínima, rotação e auditoria de acesso; avaliar se cada ambiente/runtime permite conexão mTLS sem exportação de chave. PFX com senha não é armazenado em tabela/configuração pública.
- Token é cache efêmero cifrado/isolado ou cache in-memory curto; não registrar bearer headers. Segredos distintos por tenant/conta/produto/ambiente e RBAC para administrar/revogar conexão.
- Operação de pagamento exige autorização explícita, RBAC, segregação de função quando configurada, limite, aprovação, confirmação no banco, estado pendente/recusado/falhou/liquidado e trilha de tentativa/retorno. Reversão/estorno é novo evento se o banco suportar; não apagar histórico.
- CSRF para ações web, ProductAccess `erp`, `erp.access`, `ErpTenantContext` e ACL de condomínio em acesso de usuário. Webhook é fluxo servidor-servidor separado, autenticado pelo método de cada API, com HTTPS, limites de corpo, validação de assinatura/token/certificado quando documentado e sem sessão de usuário.
- Sandbox e produção são conexões distintas; produção exige habilitação, confirmação explícita, credenciais próprias, teste e operação privilegiada. Não usar conta ou dado real em testes destrutivos.

## 8. Webhooks, idempotência e estados

Usar endpoint conceitual `/api/banking/webhooks/{provider}/{product}` se ele se ajustar à convenção API versionada presente (`/api/v1/...`). Rota lógica e autenticador selecionados pelo provider/produto/ambiente; não aceitar um “segredo genérico” ou apenas IP como prova universal. Resolver conexão usando identificadores verificados no callback (ex.: header de conta Inter, campos/header documentados em cada API), evitando descobrir tenant por payload arbitrário.

Fluxo: validar transporte/autenticidade → limitar e parsear → deduplicar por chave externa + conexão (digest restrito é fallback de transporte, não identidade financeira) → gravar evento bruto com retenção/ACL → responder dentro do SLA do banco → enfileirar → consultar API quando o callback for incompleto/trigger → validar transição e produzir evento de domínio idempotente. Webhook duplicado/atrasado/fora de ordem não pode sobrescrever status mais novo sem consulta. Falha vai a retry exponencial com jitter e limite/dead-letter; replay manual exige permissão e auditoria.

Para emissão/pagamento, chave Moves estável por comando e escopo do objeto, restrição única local, chave externa/txid conforme regras do banco, hash do payload e guarda do resultado. Timeout após enviar é **resultado incerto**: marcar pendente de reconciliação e primeiro consultar pelo ID/chave no banco; nunca reenviar cegamente. Confirmar no adapter o comportamento de idempotência e colisão por API antes de ligar retry automático. Sync/importação deduplica por ID bancário, depois referência estável e janela de sobreposição segura; OFX sem ID estável requer fingerprint composto e fila de revisão, não dedupe apenas por data+valor.

Mapear `external_status` (verbatim/permitido e sanitizado) para enum `normalized_status` versionado. Preservar histórico da mudança/status e hora do provedor. Exemplo: `pending_authorization`, `scheduled`, `processing`, `settled`, `rejected`, `cancelled`, `reversed`, `unknown`; não considerar aceite HTTP como pagamento liquidado.

## 9. Cobrança, pagamento e extrato

**Cobrança:** contrato Moves futuro separa criar, consultar, cancelar/baixar quando suportado, PDF, linha digitável e Pix/QR. Resposta contém IDs do banco, artefatos e status externo; armazenar artefato com ACL/expiração segura. Uma fatura pode não ter cardinalidade universal conhecida para boleto. Cobrança híbrida é capability distinta de boleto simples/Pix. API não define política financeira de multa, juros, desconto, reemissão, alocação ou baixa.

**Pagamento:** `PaymentService` valida pagável aprovado, usuário/limites/segregação, cria comando de pagamento idempotente e invoca adapter. Provider executa; domínio Moves acompanha agendamento, autorização pendente, confirmação, rejeição e liquidação confirmada. Detalhes de favorecido, boleto, tributo, Pix, lote e cancelamento são capabilities separadas. Dupla aprovação e efeito contábil seguem modelagem Payables/ledger futura.

**Extrato:** API ou arquivo entra em staging/sync run; validar conta, período/cursor, moeda, campos e duplicatas; armazenar `BankTransaction` imutável com fonte, identificador externo e status externo; não publicar lançamento automaticamente. Saldo bancário consultado, saldo derivado do extrato importado e saldo contábil devem permanecer distinguíveis.

## 10. Conciliação e fallbacks

Matching assistido começa por identificador bancário/EndToEndId/txid/nosso número/código de solicitação, vínculo explícito de cobrança/pagamento, conta e direção. Documentos/referências e valor/moeda/data reduzem candidatos; descrição é apoio. Não reconciliar automaticamente apenas data+valor. Mostrar explicação e grau/candidatos; confirmação humana em conflito, duplicata ou parcial. Guardar regra, autor, versão e desfazimento auditável. “Conciliado” liga evento externo a movimento Moves, não gera baixa sem fluxo autorizado.

OFX permanece importação manual assistida em staging e passa pelo normalizador `BankTransaction`, com hash/identidade de arquivo, prévia, deduplicação, origem e desfazer. Não sobrescrever extratos API nem assumir o modelo de matching mencionado na UI de referência.

CNAB permanece alternativa por banco/produto/cliente (remessa/retorno). Confirmar 240/400, posições, homologação e contratação com manual oficial vigente. Parser precisa validar layout/cabeçalho/rodapé/checksum/total, conta e ambiente, processar atomicamente em staging, preservar original com ACL/hash e tornar retorno reimportável sem duplicar liquidação. Não implementar parser universal sem contratos e casos de homologação.

## 11. Capacidade, observabilidade e resiliência

HTTP client comum futuro: TLS atualizado, mTLS configurável por conexão/produto, CA validada, timeout connect/read total, limites de bytes, allowlist de hosts por ambiente, sem redirects para origem diferente, redaction central, correlation ID, relógio confiável e resposta tipada. Unit tests usam transport fake; contract tests usam fixtures oficiais anonimizadas; sandbox cobre fluxo real quando disponível.

Logs estruturados: correlation_id, tenant/conta/conexão interna, provider, produto/operação, tempo, resultado, retry e status HTTP. Não incluir CNPJ/conta completos, payload financeiro bruto, tokens, segredo, certificado, chave privada, dados de favorecido ou headers de autenticação. Erros visíveis são sanitizados; detalhes auditáveis têm ACL.

Retry por erro transitório/documentação e operação segura/idempotente, backoff exponencial com jitter e `Retry-After`; 429 respeita política específica. Circuit breaker apenas se houver necessidade operacional medida. Proteção contra rate-limit coordenada por credencial/produto/conta, não somente por processo PHP. Worker usa leases/lock recuperável, tentativas limitadas, dead-letter e reprocessamento auditado (padrão semelhante ao Talk Outbox, adaptado a pagamentos com tratamento de resultado incerto). Métricas por provider/ambiente: taxa de sucesso, latência, timeout/429, idade do último sync, expiração de certificado/token, fila atrasada e callback inválido, sem segredos.

## 12. Sandbox, rollout e escolha inicial

Manter Sandbox e Produção no modelo, com endpoints e credenciais separados. Sandbox do Inter é publicamente anunciado. Sicredi documenta disponibilidade por API, não uniforme; cadastro webhook Multipag é somente produção. Disponibilidade Sicoob deve ser confirmada no portal atual e com cooperativa. Nenhum sandbox foi chamado nesta tarefa.

**Recomendação provisória: Inter para primeiro adapter de capacidade somente leitura (saldo/extrato), seguido de Cobrança V3 somente após Recebíveis/competência e ciclo de cobrança definidos.** Evidência técnica favorável: catálogo oficial amplo cobrindo saldo, extrato, cobrança boleto+Pix, webhook, pagamentos; documentação anuncia Sandbox e SDKs, token/certificado e CNAB alternativo. Isso não prova que é o banco dos condomínios Moves nem homologa pagamentos. Antes de fixar a decisão, levantar distribuição real de bancos/cooperativas dos pilotos, acesso a contas PJ/condomínios, produto contratado, onboarding e disponibilidade Sandbox. Sicoob pode ser primeiro se os clientes piloto forem majoritariamente cooperados com os produtos aprovados; Sicredi pode vencer se sua cobertura e cooperativas parceiras forem prioridade. Pagamento continua bloqueado até modelo Payable/aprovação e sandbox adequados.

Rollout: desenvolvimento com fake transport → Sandbox por capability → homologação por conta/cooperativa e checklists segregados → piloto read-only de saldo/extrato → cobrança de teste com cliente autorizado → piloto financeiro limitado e feature-flagged após double-check e aprovação → produção progressiva, kill switch, alertas e reconciliação diária. Nunca testar com dinheiro real sem aprovação e plano de reversão operacional.

## 13. Tarefas implementáveis futuras

IDs BANK-001…015 são convenção proposta para documentação; **nenhuma issue foi criada**. Cada tarefa será refinada com aceitação bancária e domínio Moves antes do desenvolvimento.

### BANK-001 — Modelo de contas bancárias

- **Objetivo:** cadastrar zero–N contas por condomínio/admin/tenant e prover chave estável de escopo.
- **Dependências:** grants/tenant e cadastro de condomínio atuais; campos bancários mínimos confirmados com piloto.
- **Escopo:** UX de cadastro/consulta, migration InnoDB, mascaramento, status e FKs compostas.
- **Fora de escopo:** segredos, pagamentos, saldo editável, adapter.
- **Aceite:** tenant A não lista/edita conta B; suporta múltiplas contas/bancos; dados sensíveis mínimos mascarados; auditoria de mudanças.
- **Testes:** migration instalação/reexecução, unit/HTTP, ACL/IDOR/cross-tenant e UI responsiva.
- **Segurança:** CSRF, autorização por condomínio e auditoria sem número integral.
- **DoD:** PHPUnit/PHPStan/lint/CI verdes, migration testada em DB descartável, docs e revisão visual.

### BANK-002 — Contratos BankingProvider e capabilities

- **Objetivo:** definir interfaces granulares e registry sem operação falsa/universal.
- **Dependências:** BANK-001; mapa oficial revalidado por produto e contratos financeiros Moves.
- **Escopo:** DTOs sem float, enum/status normalizado com versão, interfaces por capacidade, erros, capability check.
- **Fora de escopo:** chamadas de produção, bancos concretos e ledger.
- **Aceite:** fake provider cobre cada capability e chamada sem suporte falha antes de efeito; product capabilities não são confundidas com entitlement.
- **Testes:** contract tests contra fakes, status/error mapping, currency/decimal e ausência de método fallback silencioso.
- **Segurança:** sem credencial em DTO/log; `tenant_id`/connection scoped.
- **DoD:** contrato documentado, testes no CI e revisão de compatibilidade.

### BANK-003 — Conexões e gestão segura de credenciais

- **Objetivo:** configurar provider + produto + ambiente por conta com rotação/revogação segura.
- **Dependências:** BANK-001 e BANK-002; secret manager/KMS selecionado por ambiente.
- **Escopo:** `bank_connections`, secret references, estado/verificação, token cache isolado, onboarding/revoke, capabilities habilitadas.
- **Fora de escopo:** valor de chave privada/token em DB, operação financeira.
- **Aceite:** troca Sandbox/Produção impossível acidentalmente; status do certificado/credencial; rotação sem indisponibilidade indevida; admin não recupera segredo plaintext.
- **Testes:** key rotation, secret provider failure, expiração/token scope isolation e cross-tenant.
- **Segurança:** secret manager, autorização reforçada/MFA para gestão e logs redigidos.
- **DoD:** threat model, operação de rotação/revogação ensaiada e runbook.

### BANK-004 — Adapter Inter (começar read-only)

- **Objetivo:** integrar saldo/extrato Inter com docs/versões atuais; avaliar Cobrança V3 separadamente.
- **Dependências:** BANK-001–003, conta Inter elegível, acesso Sandbox e escopos/certificados; domínio transacional para cobrança.
- **Escopo:** autenticação documentada, health/read-only, saldo/extrato paginados e observabilidade; branch posterior para cobrança assíncrona V3.
- **Fora de escopo:** pagamento real, V2 de Boleto, Pix Automático e ledger.
- **Aceite:** respostas sandbox suportadas e paginadas, refresh de token/certificado controlado, reconsulta segura e capability real por conta.
- **Testes:** unit/fixtures, contract + sandbox read-only, rate-limit/429, expirado, timeout e reconexão.
- **Segurança:** mTLS/cert conforme endpoint, OAuth scopes mínimos, redaction.
- **DoD:** evidência sandbox, runbook, métricas, review de fonte/versão.

### BANK-005 — Adapter Sicoob

- **Objetivo:** suportar capabilities contratadas e verificadas para conta/cooperativa selecionada.
- **Dependências:** BANK-001–003, documentação atual no Developers, conta e cooperativa piloto, homologação liberada.
- **Escopo:** começar pela capability read-only escolhida; auth e escopos do produto específico, sem herdar regra Pix a outros produtos.
- **Fora de escopo:** declarar suporte nacional uniforme, processamento CNAB sem manual validado e pagamentos sem aprovação Moves.
- **Aceite:** matriz de habilitação por conta, respostas/sandbox e diferenças cooperativa registradas.
- **Testes:** contratos por produto, certificados/token, erros/429/timeout e sandbox.
- **Segurança:** certificado/secret isolado, callbacks autenticados, tenant scope.
- **DoD:** homologação por cooperativa documentada e CI verde.

### BANK-006 — Adapter Sicredi

- **Objetivo:** suportar produto Sicredi selecionado com auth e credencial específicas por API.
- **Dependências:** BANK-001–003, contrato/adesão, conta/cooperativa piloto, sandbox do produto.
- **Escopo:** separar Cobrança OAuth Password (sem mTLS) de Pix/Multipag/Extrato/Saldo mTLS + Client Credentials; iniciar capability acordada.
- **Fora de escopo:** fluxo de login único, webhook Multipag Sandbox (não existe na doc consultada), lote presumido.
- **Aceite:** token URLs/escopos por ambiente provenientes do guia atual; capability real por produto e reconciliação de IDs.
- **Testes:** auth de cada produto, cert, expiração, rate limit específico, sandbox e erros de regra.
- **Segurança:** autenticação/segredo separados por produto e ambiente.
- **DoD:** checklists de contratação, certificado e homologação anexados.

### BANK-007 — Receiver de webhooks bancários

- **Objetivo:** receber callbacks sem convertê-los diretamente em verdade financeira.
- **Dependências:** BANK-002–006 e rota API versionada; endpoint externo HTTPS; worker confiável.
- **Escopo:** autenticadores plugáveis por provider/produto, evento durável, dedupe, ACK e fila/retry.
- **Fora de escopo:** publicar baixa antes de consulta/validação; confiar em assinatura comum não documentada.
- **Aceite:** duplicado/reordenado/inválido não duplica efeito; evento autenticado pode acionar fetch de confirmação.
- **Testes:** assinatura/token/cert, replay, payload grande/malformado, timeout e DLQ.
- **Segurança:** origem, secret/signature, limite de payload e retenção cifrada/minimizada.
- **DoD:** teste real de Sandbox por produto que oferece; runbook e auditoria.

### BANK-008 — Sincronização de extrato

- **Objetivo:** importar extratos API com paginação/cursor e janela segura.
- **Dependências:** BANK-001–006 e operação de consulta confirmada.
- **Escopo:** scheduler/worker, lock por conexão, sync runs, paginação, reexecução idempotente.
- **Fora de escopo:** criar contabilização automática ou matching data+valor.
- **Aceite:** sobreposição de janelas não duplica; cursor recuperável; falha retoma sem perda.
- **Testes:** paginação, cursor, corrida, janela tardia, 429, timeout, conta/tenant incorretos.
- **Segurança:** cada execução resolve tenant/conta pela conexão ativa e credencial mínima.
- **DoD:** sandbox com extrato sintético, métricas/alertas e runbook.

### BANK-009 — BankTransaction normalizada

- **Objetivo:** persistir observação externa com identidade e status original/normalizado.
- **Dependências:** BANK-002, BANK-008 e contratos de OFX/CNAB.
- **Escopo:** schema tenant/connection scoped, identidade única externa, amount decimal, datas, origem e sanitização.
- **Fora de escopo:** ledger, lançamento, saldo contábil, pagamento/recebimento presumidos.
- **Aceite:** API/CNAB/OFX podem normalizar sem apagar proveniência; duplicata da mesma conexão é segura.
- **Testes:** idempotência, integridade composta, decimals/currency/timezones/status mapping e vazamento cross tenant.
- **Segurança:** payload bruto restrito/cifrado ou não armazenado; logs com redação.
- **DoD:** modelo revisado com finanças, migração reversível segura e docs.

### BANK-010 — Motor de conciliação

- **Objetivo:** sugerir e registrar vínculo explicável entre transação externa e evento financeiro Moves autorizado.
- **Dependências:** BANK-009 e Payables/Receivables/Payment/Receipt com chaves/FKs aprovadas.
- **Escopo:** matching por IDs, conta, tipo, moeda, valor/data como evidência composta; revisão/confirmação/desfazer.
- **Fora de escopo:** matching automático só por data+valor, ledger definitivo.
- **Aceite:** ambiguidade vai para revisão; parciais/divisão registradas sem perda; desfazer deixa histórico.
- **Testes:** duplicatas, parciais, estorno, candidatos conflitantes, multi-tenant e concorrência.
- **Segurança:** ACL e aprovação conforme papel; auditoria append-only.
- **DoD:** casos sintéticos, UX de explicabilidade e reconciliação balanceada.

### BANK-011 — OFX fallback

- **Objetivo:** importar OFX assistidamente pela mesma normalização de transações bancárias.
- **Dependências:** BANK-009 e storage seguro de upload.
- **Escopo:** staging, validação de formato/conta/competência, prévia, hash, dedupe e confirmação.
- **Fora de escopo:** lançamento automático e agrupamento silencioso de descrição.
- **Aceite:** arquivo repetido não duplica; parser falha com erro por linha seguro; operador desfaz importação antes da conciliação.
- **Testes:** fixtures anonimizadas válidas/corrompidas, replay, encoding, upload e cross-tenant.
- **Segurança:** tamanho/tipo/storage/quarentena, ACL e retenção.
- **DoD:** compatibilidade de formatos acordada, UX e documentação.

### BANK-012 — CNAB/retorno condicional

- **Objetivo:** suportar layouts requeridos por banco/produto/piloto que não sejam cobertos por API.
- **Dependências:** análise de necessidade por piloto, manuais oficiais atuais, BANK-009 e storage de arquivo.
- **Escopo:** selecionar layout (ex. Inter CNAB 240/400; Sicredi CNAB 240 conforme produto); staging e validação versionados.
- **Fora de escopo:** parser universal e layouts sem contrato oficial/fixtures.
- **Aceite:** rejeita layout incompatível sem efeitos; lote/total/conta conferidos; reimportação idempotente.
- **Testes:** fixtures anonimizadas e casos de erro por registro/total/encoding.
- **Segurança:** arquivo privado, hash, malware scan e trilha de acesso.
- **DoD:** layout escolhido com homologação do banco/cooperativa e operação documentada.

### BANK-013 — Observabilidade e saúde bancária

- **Objetivo:** detectar falha de integração e atraso antes de afetar fechamento/usuários.
- **Dependências:** BANK-003–008.
- **Escopo:** correlation ID, métricas de sucesso/latência/429, fila/cursor, validade de credenciais/certificados e health sem segredos.
- **Fora de escopo:** expor saldo ou detalhes pessoais em dashboard/log.
- **Aceite:** alerta acionável e runbook por provider/produto; incidente rastreável até conta mascarada/operação.
- **Testes:** health redaction, status crítico, logs sem token/certificado e alert delivery.
- **Segurança:** RBAC e minimização.
- **DoD:** dashboards/alertas e drill documentado.

### BANK-014 — Homologação multibanco

- **Objetivo:** provar capabilities em sandbox/homologação por produto, banco e cooperativa.
- **Dependências:** adapters selecionados, contas de teste, aprovações/contatos e BANK-013.
- **Escopo:** matriz de casos/credenciais separadas, emissão/consulta/baixa somente em sandbox, callbacks e extratos sintéticos.
- **Fora de escopo:** transferências de dinheiro real e marcar paridade por documentação.
- **Aceite:** evidência reproduzível e bloqueios/variações por conta registrados; reconciliação de callbacks e consulta posterior.
- **Testes:** smoke E2E banco, retries/timeout, duplicata, retorno e isolamento.
- **Segurança:** sem dados reais nos fixtures/logs; segredos de teste rotacionáveis.
- **DoD:** aceite de Financeiro/Operação/Segurança e roteiro de produção aprovado.

### BANK-015 — Hardening de produção

- **Objetivo:** habilitar operações bancárias controladas com segurança e reversão operacional.
- **Dependências:** BANK-001–014, domínio financeiro P0/ledger aprovado, homologação, aprovação interna e piloto designado.
- **Escopo:** feature flags, quotas, segregation of duties, limites, kill switch, rotação, backup/restore, runbooks e monitoramento.
- **Fora de escopo:** ativação ampla sem aceites, pagamento sem confirmação, liberar provider sem homologação.
- **Aceite:** fail-closed, dúvida de resultado bloqueia reenvio, approval/auditoria íntegros, rollback operacional ensaiado.
- **Testes:** caos/rede/cert expirada/429, recuperação de worker, duplicidade, restore e teste canário autorizado.
- **Segurança:** threat model e revisão independente; menor privilégio e segregação por tenant/conta.
- **DoD:** aprovação formal de Segurança/Financeiro, piloto limitado monitorado e runbook assinado.

## 14. Riscos e decisões pendentes

1. **Banco/cooperativas do piloto:** levantar quais contas condominiais existem e quem contrata/autoriza acesso; sem isso o banco inicial é apenas recomendação técnica.
2. **Fonte financeira:** fechar domínio de plano de contas, payable/receivable, pagamento/recebimento, partial/estorno e ledger antes de criar efeito automático de API.
3. **Escopo de propriedade:** confirmar contas pertencem ao condomínio ou administradora por produto e quem é o correntista legal; UI legada não prova relação técnica.
4. **Segredos:** escolher Secret Manager/KMS, HA e operação/rotação; cipher MFA existente é somente uma primitiva, não cofre operacional.
5. **Contratação/homologação:** cada entidade/condomínio pode precisar de adesão, escopo, certificado e habilitação por produto/cooperativa.
6. **Sicoob:** revalidar autenticador, sandbox, APIs, quotas, idempotência e callback no portal autenticado; manual Pix de 2021 pode estar desatualizado.
7. **CNAB/OFX:** determinar demanda e layouts por carteira/segmento; API não substitui necessariamente VAN/CNAB nem o processo de todos os clientes.
8. **Política operacional:** aprovação, quatro olhos, limites, horário de execução, cancelamento, reprocessamento e SLA para resultado incerto.
9. **Retenção/privacidade:** minimizar eventos brutos e extratos; definir retenção fiscal/legal e direitos de acesso/expurgo compatíveis.
10. **Idempotência externa:** confirmar garantias/regras de unicidade de cada endpoint em homologação antes de retry mutável.

## 15. Inspeção do Moves e decisões de sequência

Na branch auditada `feat/mvs-v1-integration` (HEAD ao início `98f00e3794904aa0caf3a0f08feefd33f28e9a54`):

- `ErpModule` monta `/erp/payables`, `/erp/receivables`, `/erp/billing`, `/erp/bank-accounts` e `/erp/reconciliation` por `PlatformController:erpPage`, páginas de demonstração atrás de autenticação + `erp.access`. Cadastros/competências reais têm controllers/services próprios. Nenhuma rota de API de banking/provider/webhook foi localizada.
- Migrations financeiras atuais incluem estrutura física, pessoas/fornecedores e `erp_accounting_periods`; não foram encontradas tabelas de conta/conexão/credencial/transação/sync/ledger bancário. Nenhum `BankingService`, adapter bancário, client HTTP compartilhado ou scheduler bancário foi localizado.
- Padrões reutilizáveis: `ErpTenantContext` resolve tenant/administradora; middleware ERP usa Auth + `erp.access`, além do gate de produto ERP na aplicação; `PlatformAudit`; `IdempotencyKey` normaliza/faz fingerprint, mas domínio deve persistir uniqueness/replay; `TalkOutboxWorker` implementa lock, retry e auditoria de worker a adaptar cuidadosamente. `MfaSecretCipher` demonstra AES-GCM com key ID e chave externa por configuração, não é solução de cofre bancário.
- .env/runtime recebe configuração; não há Secret Manager genérico nem política de certificados bancários encontrada. O adapter não deve se basear em segredos de desenvolvimento ou em `.env` compartilhado pelo banco inteiro.
- A competência mensal aberta está implementada em migration `20261006_030`; a fração ideal foi publicada em `98f00e3`. O roadmap P0 segue: cadastros operacionais → plano de contas/dimensões → pagar/receber e cobrança → contas bancárias/fluxos → conciliação/fechamento/prestação. Portanto, este planejamento não substitui competência nem autoriza começar API antes do domínio financeiro.

### Sequência bancária alinhada ao roadmap existente

Depois das dependências atuais de cadastro, plano e domínio de títulos/pagamentos serem resolvidas: (1) BANK-001 contas, (2) BANK-002 contrato/capabilities, (3) BANK-003 conexões/segredos, (4) escolher banco/capability por piloto, (5) adapter read-only primeiro, (6) normalização/sync, (7) cobrança quando Receivables aprovar ciclo, (8) webhooks e confirmação, (9) pagamentos somente após approval/Payables, (10) conciliação, OFX/CNAB conforme necessidade, (11) fechamento/prestação e hardening. `bank_transactions` não antecipa nem define ledger.

## 16. Revisão das fontes antes de implementação

Repetir a consulta oficial e registrar data/versão dentro da issue do adapter. Obter aprovação de conta/cooperativa e confirmação escrita de habilitação e sandbox. Se uma capability não estiver confirmada no contrato atual, documentar como indisponível e manter o feature gate fechado. Esta matriz é fotografia consultada em 2026-10-06, não garantia de serviço.
