# 10 — Configurações

## Inventário

| Área | Itens |
|---|---|
| Conta | perfil, preferências pessoais |
| Atendimento | canais, atendentes, setores, horários, etiquetas, grupos, campos personalizados, biblioteca de mídias |
| Automação | agentes IA, bases, chatbots, respostas rápidas, templates, WhatsApp Flows, agendamentos, variáveis |
| Organização | dados cadastrais, comportamento de conversas, reatribuição, inatividade, espera, atalhos de bot, transferência, privacidade, listagem global |
| Sistema | webhooks, API, atividades |

## Comportamentos detalhados observados

- Ao interagir com chatbot em execução: confirmar, parar silenciosamente ou manter bot.
- Assinatura do atendente: escolha individual, sempre ou nunca.
- Finalização: atendente escolhe; apenas finalizar; enviar mensagem/template; ou iniciar bot e finalizar.
- Mensagem, template e chatbot padrão podem ser sugeridos na finalização.
- Administradores podem ser ocultados da lista de visualizadores.
- Abrir conversa pode ou não marcar mensagens como lidas.
- Reatribuição global: disponibilizar ou iniciar bot.
- Transferência: todos os destinos ou apenas destinos acessíveis.

## Diretriz para o Moves

Configurações devem ser tipadas, versionadas, por tenant e com histórico antes/depois. Opções que mudam comportamento operacional devem apresentar impacto, validação e modo de teste. Segredos nunca devem voltar ao navegador após salvos.

O Moves hoje armazena configurações globais em `talk_settings` por chave e valor, sem tenant, esquema ou versionamento. Isso é adequado para protótipo, não para produção multiempresa.
