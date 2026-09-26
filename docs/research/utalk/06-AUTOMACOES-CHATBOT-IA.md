# 06 — Automações, chatbot e IA

## Capacidades observadas

### Chatbot de fluxo

- CRUD e ordenação de bots; snapshots/versionamento.
- Instâncias de execução e inícios manuais.
- Vínculos do bot com outros pontos da organização.
- Atalhos de chatbot (Enterprise).
- Uso do bot ao finalizar, reatribuir, esperar ou detectar inatividade.

Os componentes internos do editor não puderam ser abertos sem criar um bot; condições, variáveis e tratamento de erro permanecem não verificados.

### Agente de IA

- Criação, configuração, treino, foto, voz e versões.
- Bases de conhecimento compartilháveis.
- Fontes: perguntas/respostas, arquivo, documentos, crawler de site e Q&A gerada de chats encerrados.
- Ingestão explícita e remoção de referências inválidas em snapshots.
- Setores/funções pré-treinadas e clonagem de base entre agentes.
- Assistente ao atendente: melhorar texto e sugerir três direções de resposta.
- Créditos de IA separados e painel de consumo.

### Automações auxiliares

- Respostas rápidas com variáveis de contato/campos/organização.
- Templates oficiais e WhatsApp Flows.
- Mensagens agendadas.
- Fluxos de horário, espera e inatividade.

## Comparação com Jack

O Jack atual é uma automação inicial: aguarda um tempo, lê mensagens de texto, grava uma resposta `jack-v1` e audita a interação em [TalkJackService.php](../../../app/Services/Talk/TalkJackService.php). Não há, no código atual, RAG, ferramentas ERP, políticas por intenção, handoff estruturado, versionamento de prompt, avaliação, confiança ou proteção contra prompt injection.

## Direção recomendada para Jack

```text
mensagem → identificar pessoa e escopo → classificar intenção
→ recuperar dados autorizados do ERP
→ resolver via ferramenta segura OU coletar dados faltantes
→ registrar fontes/ações/confiança
→ transferir com resumo, intenção, condomínio/unidade e próximos passos
```

Cada ferramenta do Jack deve ter escopo de tenant, autorização do contato, idempotência, limite de impacto e trilha de auditoria. Operações financeiras ou alterações cadastrais exigem confirmação explícita.
