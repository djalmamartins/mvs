# 09 — Dashboard e métricas

## Abas observadas

### Geral

- Total de conversas iniciadas, abertas e finalizadas.
- Conversas com interação do atendente.
- Tempo médio de primeira resposta e de finalização.
- Conversas abertas por atendente e por canal.
- Conversas por região, estado e DDD.
- Conversas iniciadas por dia e por hora.
- Filtros de canal, atendente, comparação de atendentes e período.
- Visões por conversas, atendentes, setores, etiquetas e contatos.

### Agora

- Conversas em espera.
- Conversas em andamento/caixa de entrada.
- Atendentes online.
- Primeira resposta na última hora.
- Conversas novas ou com interação nos últimos minutos.

### Chatbots, créditos, mensagens e avaliações

- Execuções por bot e período.
- Créditos consumidos, média diária, evolução e fonte (IA, template, mensagem).
- Mensagens totais, por canal e por dia, filtradas por origem.
- Taxa de resposta das avaliações e distribuição positiva/neutra/negativa.

Exportação não foi confirmada na conta auditada. SLA nominal não apareceu como KPI específico, embora tempos e espera sejam medidos.

## Moves atual

`TalkService::reports()` retorna apenas total, aguardando, ativos, finalizados e volume por fila. A tela declara indicadores de transferência e SLA, mas o backend ainda não entrega a maior parte deles.

## Métricas V1 recomendadas

1. Volume recebido/finalizado/reaberto por tenant, canal e fila.
2. Fila atual, maior espera e percentis P50/P90/P95.
3. Primeira resposta e resolução por horário útil.
4. SLA cumprido/violado, backlog por idade e prioridade.
5. Carga, produtividade e transferências por atendente sem ranking punitivo.
6. Contenção do Jack, handoff, retrabalho, confiança e resoluções revertidas.
7. Saúde de canal: disponibilidade, latência, falhas e retries.

Todos os eventos precisam de timestamps imutáveis; relatórios não devem depender apenas do estado atual do ticket.
