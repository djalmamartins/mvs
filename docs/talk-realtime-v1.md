# Talk V1: atualização operacional

O endpoint autenticado `/talk/sync` registra a presença do usuário e retorna a revisão opaca do conjunto de tickets que ele pode ver, mais a contagem de notificações. A revisão combina os tickets visíveis, o último evento, a última mensagem e o total de entradas não lidas. Eventos gravados no mesmo segundo geram revisões distintas. O endpoint e a renderização da workspace não executam autoatribuição nem Jack.

Para clientes que precisam de dados em JSON, `?include=snapshot` acrescenta conversas filtradas pela permissão do usuário e o ticket selecionado autorizado. O poll da interface usa apenas a revisão e renderiza os fragmentos pela view do servidor, preservando o Design System e a autorização existente.

Na workspace de conversas, o cliente consulta o sync de forma serial a cada dois segundos. Quando a revisão muda, obtém a página autenticada atual e substitui apenas a lista, a conversa e o contexto. O texto já digitado no composer permanece no DOM e a atualização da conversa aguarda o fim da edição. A aba oculta pausa as requisições; ao voltar, consulta imediatamente. Falhas de rede mostram estado de reconexão e repetem a consulta após cinco segundos. O badge de notificações é atualizado em cada resposta.

## Smoke em ambiente de piloto

1. Entrar como atendente, abrir `/talk/view/inbox?ticket=<id>` e confirmar “Atualizações conectadas”.
2. Receber duas mensagens em rápida sequência no mesmo ticket; ambas devem aparecer sem atualizar o navegador.
3. Digitar um rascunho, receber outra mensagem e confirmar que o texto permanece. Ao sair do composer e esvaziá-lo, a conversa deve alcançar a revisão atual.
4. Deixar a aba oculta e voltar; a lista deve alcançar o estado atual. Derrubar temporariamente a rede deve mostrar “Atualizações interrompidas” e a reconexão deve voltar ao estado conectado.
5. Verificar o badge, a aba “Não lidos” e um atendimento transferido que deixa de ser visível para o usuário.

## Worker operacional

Execute `php scripts/talk-operations-worker.php` como processo supervisionado junto com o worker da outbox. `--once` processa uma rodada, adequado para cron e verificação. O intervalo contínuo é configurável por `TALK_OPERATIONS_INTERVAL_MS` (padrão 5000, limites 1000–60000). O worker percorre os tenants ativos, obtém um lock por tenant para evitar duas rodadas simultâneas e executa autoatribuição e Jack. A atribuição continua condicionada à presença ativa do atendente. Se o worker estiver parado, essas tarefas ficam pendentes; a workspace permanece somente leitura quanto a elas.

O E2E com WhatsApp real requer sessão/canal conectado, um número de teste e credenciais de atendente em ambiente isolado.
