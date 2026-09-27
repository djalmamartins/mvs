# Moves Talk — WhatsApp bridge local

Bridge multissessão para integrar Baileys ao domínio do Moves Talk. O frontend, usuários, filas, contatos, tickets e histórico continuam no Moves; este serviço mantém uma sessão isolada por canal cadastrado.

## Instalação local

```bash
cd services/talk-whatsapp
npm install
cp .env.example .env
```

Defina o mesmo token forte em `TALK_BAILEYS_BRIDGE_TOKEN` no ambiente do bridge e do PHP/Moves. No ambiente do Moves, configure:

```
TALK_WHATSAPP_DRIVER=baileys
TALK_BAILEYS_URL=http://127.0.0.1:3011
TALK_BAILEYS_BRIDGE_TOKEN=<mesmo-token-do-bridge>
```

No `.env` do bridge, ajuste `TALK_MOVES_INBOUND_URL` para a URL local da plataforma, por exemplo `http://mvs.lab/talk/bridge/inbound`.

Inicie com `npm start` e abra **Talk → Conexão**. Cadastre o canal e use **Conectar** para gerar seu QR individual. Cada canal usa `baileys_auth/<session_key>/`, restaura sua própria sessão após reinício e pode reconectar ou fazer logout sem afetar os demais.

O PHP chama exclusivamente as rotas autenticadas por canal:

```
GET  /channels/:session_key/status
POST /channels/:session_key/connect
POST /channels/:session_key/logout
POST /channels/:session_key/send/text
```

`external_id` identifica o canal no inbound; `session_key` é gerado pelo servidor, validado e usado somente como chave segura de sessão. Nenhum deles vem de um `.env` global por número.

Não versione `.env` nem `baileys_auth/`. O uso de Baileys é adequado ao desenvolvimento/teste local; para operação oficial, mantenha disponível o driver `meta_cloud`.

## Outbox durável

O request do atendente apenas confirma a mensagem e a outbox na mesma transação. O envio é feito fora do request pelo worker:

```bash
php scripts/talk-outbox-worker.php
```

Use `--once` para processar no máximo um item, útil para cron, testes e diagnóstico. Em modo contínuo o worker aguarda `TALK_OUTBOX_IDLE_MILLISECONDS` quando a fila está vazia, aceita `SIGTERM`/`SIGINT` e recupera locks abandonados após `TALK_OUTBOX_LOCK_TIMEOUT` segundos.

Autoatribuição e Jack usam outro processo supervisionado: `php scripts/talk-operations-worker.php`. Ele também aceita `--once`; veja [`docs/talk-realtime-v1.md`](../../docs/talk-realtime-v1.md) para comportamento e smoke operacional.

Falhas temporárias recebem backoff exponencial até `TALK_OUTBOX_MAX_ATTEMPTS`. O canal e a `session_key` gravados no ticket são sempre preservados: um canal desconectado nunca provoca fallback para outro número. A chave de idempotência segue até o bridge, cujo recibo local impede um segundo envio após timeout ou reinício.
