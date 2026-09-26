# 02 — Atendimento

## Inbox observada

A tela usa três zonas: lista de conversas, conversa ativa e contexto do contato. A lista possui busca, filtros e estados **Entrada**, **Esperando** e **Finalizados**. Na conta auditada todos os contadores estavam zerados.

## Fluxo funcional consolidado

```text
mensagem recebida
→ contato e conversa identificados
→ setor/canal/bot aplicável
→ conversa aguarda ou entra na caixa do atendente
→ atendente assume/recebe atribuição
→ responde e consulta contexto
→ transfere, devolve à espera ou finaliza
→ histórico, avaliação, métricas e log são preservados
→ conversa pode ser reaberta
```

## Capacidades confirmadas por interface/API

- Criar/obter conversa por contato e canal; evitar duplicação quando já existe conversa aberta.
- Atribuir conversa a membro e/ou setor, alterar privacidade e fechar.
- Mensagens de texto e mídia; editar, excluir quando permitido pelo canal, reagir, encaminhar, reenviar e transcrever.
- Marcar conversa como lida/não lida; fixar; favoritar mensagens.
- Tags na conversa, campos personalizados e notas no contato.
- Respostas rápidas pessoais ou da organização com variáveis renderizadas a partir do contato/conversa.
- Mensagens agendadas em calendário e lista.
- Conversas internas entre membros e grupos.

## Ações não executadas

Assumir, transferir, finalizar, reabrir, enviar anexos e mensagens estavam sem dados elegíveis ou causariam mutação. A existência está confirmada pela interface/configurações/API, mas o comportamento ponta a ponta não foi exercitado.

## Padrões úteis para o Moves

- Manter lista, thread e contexto simultaneamente.
- Separar estado da conversa do estado do atendimento/ticket.
- Tornar transferência e finalização ações auditadas, com motivo e consequência explícita.
- Não esconder notas, SLA, vínculo condominial e histórico em telas secundárias.

## Estado atual do Moves

O Moves implementa assumir, enviar texto/anexo, nota, prioridade, tags, transferência, retorno à fila, finalizar/reabrir e histórico em [TalkController.php](../../../app/Controllers/TalkController.php) e [TalkService.php](../../../app/Services/Talk/TalkService.php). A inbox já adota o layout de três painéis em [talk-inbox.php](../../../resources/themes/admin/pages/talk-inbox.php).
