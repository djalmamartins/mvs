# 11 — Integrações e API

## API observada

Base exibida: `/api`, especificação OpenAPI em `/api/docs/v1/docs.json`, com autorização central. Grupos de endpoints identificados:

- ActivityLogs, Channels, Chats, Messages, Contacts, Tags e CustomFieldDefinitions.
- Members, Permissions, Organizations, Invites, Sectors e Groups.
- Bots, AiAgents, AiAssistant e KnowledgeBases.
- QuickAnswers, ScheduledMessages, Templates, Flows e FlowMessages.
- BulkSendSession, ContactRatings, UserNotifications, Variables, Webhooks.
- OrganizationFiles, Stickers e atalhos de bot.

## Integrações confirmadas ou inferidas diretamente da API

- Meta/WhatsApp oficial e provedor Gupshup em publicação de Flow/template.
- WhatsApp Web/Starter com QR Code.
- Webhooks registráveis e lista de faixas IP de origem.
- Crawler de sites e importação de documentos/Q&A para IA.
- API permite operações completas de atendimento, contatos e administração.

Zapier, Make, n8n, CRM e ERP não apareceram nominalmente. Podem integrar via API/webhooks, mas não devem ser documentados como integração nativa.

## Requisitos para a API Moves

- OAuth2/client credentials ou chaves rotacionáveis por integração; nunca token compartilhado global.
- Escopos e tenant obrigatórios, rate limit, idempotency key e correlation ID.
- Webhooks assinados, versionados, com retries, replay e painel de entrega.
- OpenAPI gerada do código e testes de contrato.
- Eventos mínimos: mensagem recebida/enviada/falhou, ticket criado/atribuído/transferido/finalizado/reaberto, contato vinculado e ação do Jack.
- Adapter interno para Moves ERP em vez de acoplamento direto do controller ao banco do ERP.
