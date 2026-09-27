# Talk V1: presença e autoatribuição

`/talk/sync` envia um heartbeat para atualizar `last_seen_at`. O primeiro heartbeat cria a presença `online`; os seguintes preservam a escolha manual de `online`, `away` ou `offline` feita em `/talk/users`. O seletor de presença mostra o estado efetivo atual.

Um heartbeat com mais de cinco minutos é exibido como `offline` na equipe, mesmo que o valor armazenado ainda seja `online`. A autoatribuição usa o mesmo limite: somente membros ativos com presença `online` recente e capacidade disponível recebem tickets. O operador pode voltar a `online` pelo formulário; um novo heartbeat então renova a atividade sem alterar a escolha.

O teste em banco descartável cobre escolhas manuais, expiração, retorno, autoatribuição e isolamento entre tenants.
