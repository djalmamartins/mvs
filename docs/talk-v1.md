# Moves Talk V1 — instalação, operação e validação

## Instalação e upgrade

O Talk usa o mesmo PHP 8.2+, MySQL/MariaDB e autenticação do Moves. Depois de atualizar o código, execute `composer install` e `php scripts/migrate.php`. Nunca edite uma migration já aplicada. O document root deve continuar em `public/`; anexos do Talk ficam em `storage/talk/`, fora da área pública.

Para WhatsApp, configure o transporte por ambiente. Sem credenciais válidas o driver seguro permanece desconectado e o sistema não deve apresentar mensagem como enviada. O canal `simulation` é isolado para smoke local.

## Segurança e permissões

Todas as páginas e ações do Talk exigem autenticação e `talk.access`. Alterações usam POST + CSRF. Visualização/operação de tickets é validada no backend: atendentes veem seus tickets e filas das quais são membros; supervisor/admin pode gerenciar o escopo operacional. Downloads de anexos passam pelo controller, revalidam acesso ao ticket, usam caminho canônico dentro de `storage/talk` e enviam `nosniff`.

Nunca publique `storage/talk` diretamente no servidor web. O limite atual é 10 MB e o MIME é detectado no servidor.

## Operação

Fila → assumir → atender → transferir/devolver → finalizar. Reabertura é restrita a supervisor/admin. Pesquisa e filtros de fila/prioridade são combináveis; SLA vencido recebe estado visual. Tags, notas e histórico recente usam o mesmo escopo autorizado do ticket.

A sincronização de contadores/notificações usa um único ciclo com backoff, pausa em aba oculta e evita recarregar a tela quando há rascunho no composer.

## Jack

O Jack é desativado por padrão. `jack.enabled=1` habilita o processamento e `jack.wait_seconds` controla a espera. Ele só atua em ticket ainda na fila, sem atendente atribuído e sem resposta humana. A decisão é revalidada sob lock antes de persistir, impedindo resposta duplicada em execução concorrente. Cada resposta cria `talk_jack_interactions` e `talk_events`; quando um humano assume, o resumo contextual é preservado como `jack.handoff`.

## Smoke de V1

1. Entrar com atendente e confirmar que só enxerga seus tickets e filas autorizadas.
2. Assumir ticket, enviar texto, nota, tag e anexo permitido; validar erro de MIME/tamanho inválido.
3. Transferir para membro/fila válida e confirmar que destino inválido não altera o ticket.
4. Finalizar; confirmar que envio fica bloqueado. Supervisor deve conseguir reabrir.
5. Abrir anexo autenticado e confirmar 404 quando o usuário não pode visualizar o ticket.
6. Validar badge e popover de notificações, leitura individual/em lote, Escape, clique externo e retorno de foco.
7. Testar busca/filtros, SLA, vazio, desktop e tablet.
8. Com Jack desativado, confirmar zero respostas. Ativar em ambiente de teste, aguardar elegibilidade e confirmar uma única resposta + trilha de auditoria + handoff ao assumir.

## Gate antes de release

Execute `composer validate --strict`, `composer audit`, migrations em banco limpo, PHPUnit, PHPStan e os smoke tests acima. Não faça merge, tag ou release enquanto houver bloqueador crítico ou issue de gate aberta.
