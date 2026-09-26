# 15 — Recomendações para V1

## Princípio de produto

O Moves Talk deve ser o console operacional do ecossistema Moves, não um clone de inbox. Cada atendimento deve chegar enriquecido com identidade, condomínio, unidade, papel do contato, débitos/chamados/documentos permitidos e histórico relevante.

## Arquitetura alvo

```text
WhatsApp/Outros canais
→ Gateway de canal + validação de webhook
→ fila de eventos idempotente
→ Conversation/Ticket Service com tenant obrigatório
→ Identity Resolution
→ Policy/Permission Service
→ Moves ERP Adapter
→ Jack Orchestrator com ferramentas autorizadas
→ Inbox/Supervisão
→ Event Store + métricas + auditoria
```

## Decisões prioritárias

1. Adicionar tenant a todo agregado Talk e índices compostos; negar consultas sem tenant.
2. Separar conversa, atendimento e mensagem; preservar eventos imutáveis.
3. Executar tarefas assíncronas fora do polling da interface.
4. Padronizar estados e transições com máquina de estados testada.
5. Consolidar as duas UIs de atendimento atuais.
6. Criar adapters para WhatsApp e ERP com contratos, circuit breaker e telemetria.
7. Transformar Jack em orquestrador de ferramentas; respostas devem carregar confiança, fontes e ações.

## Oportunidades exclusivas

- Identificação automática de múltiplas unidades e desambiguação conversacional.
- Segunda via de boleto com autenticação e registro, sem expor dados de outra unidade.
- Abertura/consulta de chamado com anexos recebidos no WhatsApp.
- Resumo de assembleia, comunicado ou documento autorizado.
- Detecção de síndico/conselheiro e roteamento especializado.
- Alerta proativo de inadimplência ou ocorrência somente com base legal e opt-out.
- Handoff humano já contendo intenção, dados verificados, consultas executadas e pendências.

## Critério de “pronta para produção”

V1 não é apenas tela funcional: exige isolamento comprovado, autorização negativa testada, idempotência, recuperação de falha, backups, métricas, runbooks, LGPD, testes de carga e auditoria ponta a ponta.
