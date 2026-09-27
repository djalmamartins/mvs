# Talk V1: capacidade em transferências diretas

Transferências para um atendente bloqueiam primeiro o vínculo `(tenant_id,user_id)`, a mesma linha usada pelo claim. Assim, claim e transferência concorrentes para a mesma pessoa são serializados antes da leitura de carga.

Dentro da transação o serviço bloqueia e revalida o ticket, a permissão do ator, a fila ativa e a associação ativa do destinatário. O limite efetivo é o menor valor entre `talk_user_settings.max_active_tickets` e `talk_queue_members.capacity`. O próprio ticket é excluído da contagem, permitindo transferências para si sem consumir capacidade duas vezes.

Quando a elegibilidade ou capacidade falha, ticket, histórico, evento e notificações permanecem inalterados. Transferências apenas para fila continuam deixando o ticket em `queued`, sem destinatário direto.
