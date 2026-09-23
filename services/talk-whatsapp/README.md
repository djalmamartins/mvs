# Moves Talk — WhatsApp bridge local

Bridge recuperado da arquitetura do ConnectTalk para integrar Baileys ao domínio do Moves Talk. O frontend, usuários, filas, contatos, tickets e histórico continuam no Moves; este serviço cuida somente da sessão WhatsApp.

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

Inicie com `npm start` e abra **Talk → Conexão**. O QR é atualizado automaticamente. Depois de parear o aparelho, mensagens recebidas entram no banco do Moves, geram contato/conversa/ticket na fila e podem ser assumidas e respondidas na Central Talk.

Não versione `.env` nem `baileys_auth/`. O uso de Baileys é adequado ao desenvolvimento/teste local; para operação oficial, mantenha disponível o driver `meta_cloud`.
