# Talk V1: criação e aplicação de tags

A ação **Criar e aplicar** exige permissão de operação no ticket. `TalkMetadataService::createAndAttachTag()` bloqueia o vínculo do atendente e o ticket, verifica a permissão e grava a tag, a associação e o evento na mesma transação. Se qualquer etapa falhar, a transação é revertida. Atendentes que somente visualizam um ticket na fila não podem deixar tags sem uso.

O controlador devolve o erro ao formulário do ticket, sem expor detalhes internos. O teste de integração cobre a recusa sem efeitos colaterais e o sucesso após a atribuição em MySQL descartável.
