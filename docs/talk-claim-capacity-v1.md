# Talk V1: capacidade ao assumir atendimento

`TalkService::claim()` bloqueia a linha de vínculo do atendente no tenant antes de ler a carga ativa. Claims simultâneos para tickets diferentes do mesmo atendente passam por essa linha em sequência; a verificação e a atribuição ocorrem na mesma transação. O limite efetivo é o menor valor entre `talk_user_settings.max_active_tickets` e a capacidade ativa do membro da fila. Um claim recusado deixa o ticket na fila e não grava evento.

O claim também exige usuário globalmente ativo e, quando o ticket tem fila, fila ativa e membro ativo. A fila e o membro são bloqueados até o commit para serializar uma pausa da fila com o claim. A autoatribuição usa o mesmo método, portanto uma fila pausada ou um usuário desativado não recebe ticket mesmo que uma seleção anterior os tenha escolhido. Tickets sem fila preservam o contrato de claim pelo vínculo ativo do tenant.

Smoke HTTP autenticado em banco descartável confirmou recusa com a fila inativa, sem alterar o ticket, e aceite após a reativação da fila.

O lock é por vínculo `(tenant_id,user_id)`, de modo que atendentes de outros tenants continuam operando. A autoatribuição usa o mesmo método de claim e, portanto, obedece à mesma garantia.

As transferências diretas seguem a política de capacidade e elegibilidade documentada em [talk-transfer-capacity-v1.md](talk-transfer-capacity-v1.md). Esta entrega cobre o comando de assumir ticket, manual ou disparado pela autoatribuição.
