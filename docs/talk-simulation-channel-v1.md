# Talk V1: canal de simulação após a migration de canais

`seedSimulation()` cria um canal de simulação com `external_id` e `session_key` estáveis e exclusivos por tenant. Ambos incluem o ID do tenant, pois as duas colunas têm índices únicos globais; a chave de sessão é obrigatória desde a migration `20260926_012_expand_talk_channels.sql`.

Uma chamada posterior no mesmo tenant reutiliza o canal. O `INSERT IGNORE` resolve duas criações simultâneas do mesmo canal e uma consulta retorna seu ID, sem alterar canais WhatsApp. Teste de integração em banco descartável cobre duas simulações no primeiro tenant e uma no segundo.

O smoke HTTP autenticado em instalação migrada sem canal de simulação confirmou que `POST /talk/simulate` cria o ticket e abre a inbox.
