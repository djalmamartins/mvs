# 03 — WhatsApp e canais

## Tipos observados

| Canal | Funcionamento apresentado | Observação |
|---|---|---|
| WhatsApp API Oficial | conexão direta à Meta, templates, botões, escala, independe do celular | recomendado pela plataforma; parceiro Meta |
| WhatsApp Web | espelhamento do celular, ativação imediata, QR Code | indicado para testes/cenários específicos |
| Webchat | widget no site, independente do WhatsApp | bloqueado no plano observado |

## Conceitos operacionais

- Vários canais por organização são suportados pela API.
- Canal Oficial exige template aprovado para conversa iniciada pela empresa.
- Templates podem ser publicados em canais oficiais adicionais.
- WhatsApp Flows são formulários preenchidos dentro da conversa; exigem canal Business API ativo.
- A API diferencia canal “Starter” (QR Code) e WABA/Business; a criação WABA requer ativação assistida.
- A interface expõe status, criação de canal e estados de conexão; reconectar/desconectar não foi acionado.
- Mensagens em massa e campanhas existem, mas dependem de canal/plano e não foram executadas.

## Recomendações ao Moves

1. Tratar `channel`, `provider`, `number/account` e `tenant` como entidades distintas.
2. Implementar por canal: credenciais cifradas, webhook, health check, reconexão, limites, janela, templates e idempotência.
3. Preferir Cloud API oficial para produção; manter Baileys como adaptador explicitamente não oficial/experimental.
4. Exibir ao atendente a janela de atendimento e o motivo de bloqueio do composer.
5. Registrar cada evento externo com `provider_event_id`, tentativas e erro normalizado.

## Estado atual do Moves

Há uma interface de transporte e adaptadores Meta Cloud, Baileys e Null em [Transport](../../../app/Services/Talk/Transport), recebimento idempotente em [TalkInboundService.php](../../../app/Services/Talk/TalkInboundService.php) e serviço Node/Baileys em [services/talk-whatsapp](../../../services/talk-whatsapp). Ainda faltam modelo explícito de número por empresa, templates/janela, retries duráveis, dead-letter queue e console de webhooks.
