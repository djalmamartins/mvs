<?php
$this->layout('layouts/default', ['title'=>$title,'currentPage'=>'talk']);
?>
<section class="talk-workspace" data-talk-workspace data-sync-url="/talk/sync" data-revision="0">
    <aside class="talk-list" aria-label="Conversas">
        <header><div><p class="customer-eyebrow">TALK <span class="talk-sync-state" data-talk-sync-state role="status">Conectando…</span></p><h2>Conversas</h2></div><span class="talk-count" data-talk-total-count aria-label="<?= count($conversations) ?> conversas"><?= count($conversations) ?></span></header>
        <label class="talk-search"><span class="sr-only">Buscar conversas</span><input type="search" placeholder="Buscar nome, telefone ou unidade" data-talk-search></label>
        <div class="talk-filters" aria-label="Filtros"><button class="active" type="button" data-talk-filter="all" aria-pressed="true">Todas</button><button type="button" data-talk-filter="queue" aria-pressed="false">Fila <span data-talk-queue-count><?= (int)($queueCount ?? 0) ?></span></button><button type="button" data-talk-filter="mine" aria-pressed="false">Minhas <span data-talk-mine-count><?= (int)($mineCount ?? 0) ?></span></button></div>
        <div class="talk-list-body" aria-live="polite">
            <?php if (!$conversations): ?><div class="talk-empty"><strong>Nenhuma conversa disponível.</strong><p>Novas conversas aparecerão aqui quando o canal Talk estiver conectado.</p></div><?php else: ?>
                <?php foreach ($conversations as $conversation):
                    $status = (string)($conversation['status'] ?? '');
                    $isQueue = $status === 'queued';
                    $search = mb_strtolower(trim(implode(' ', [(string)($conversation['contact_name'] ?? ''), (string)($conversation['contact_phone'] ?? ''), (string)($conversation['protocol'] ?? ''), (string)($conversation['subject'] ?? '')])));
                ?>
                    <a class="talk-conversation" href="/talk/tickets/<?= (int)$conversation['id'] ?>" data-talk-conversation data-scope="<?= $isQueue ? 'queue' : 'mine' ?>" data-search="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                        <span class="talk-conversation-main"><strong><?= htmlspecialchars((string)($conversation['contact_name'] ?? 'Contato'), ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string)($conversation['subject'] ?? $conversation['protocol'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></span>
                        <span class="talk-conversation-meta"><small><?= $isQueue ? 'Fila' : 'Meu atendimento' ?></small><small><?= htmlspecialchars((string)($conversation['priority'] ?? 'normal'), ENT_QUOTES, 'UTF-8') ?></small></span>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </aside>
    <main class="talk-thread" aria-label="Conversa ativa">
        <?php if (!$selectedConversation): ?>
            <div class="talk-thread-empty"><span aria-hidden="true">◌</span><strong>Selecione uma conversa</strong><p>Escolha uma conversa na lista para visualizar mensagens e responder.</p></div>
        <?php else: ?>
            <header class="talk-thread-header"><div><strong><?= htmlspecialchars((string)$selectedConversation['contact_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string)$selectedConversation['protocol'], ENT_QUOTES, 'UTF-8') ?></small></div><div class="talk-ticket-actions">
                <?php if (($selectedConversation['status'] ?? '') === 'queued'): ?><form method="post"><input type="hidden" name="_token" value="<?= htmlspecialchars(\Moves\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="claim"><button type="submit">Assumir</button></form><?php endif; ?>
                <?php if (!empty($canOperateTicket) && in_array(($selectedConversation['status'] ?? ''), ['assigned','open'], true)): ?><form method="post"><input type="hidden" name="_token" value="<?= htmlspecialchars(\Moves\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="return"><button type="submit">Retornar à fila</button></form><form method="post" data-confirm="Finalizar este atendimento?"><input type="hidden" name="_token" value="<?= htmlspecialchars(\Moves\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="close"><button type="submit">Finalizar</button></form><?php endif; ?>
                <?php if (!empty($canManageTalk) && ($selectedConversation['status'] ?? '') === 'closed'): ?><form method="post"><input type="hidden" name="_token" value="<?= htmlspecialchars(\Moves\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="reopen"><button type="submit">Reabrir</button></form><?php endif; ?>
            </div></header>
            <div class="talk-messages" aria-live="polite">
                <?php if (empty($selectedConversation['messages'])): ?><div class="talk-empty"><strong>Ainda não há mensagens.</strong><p>As mensagens desta conversa aparecerão aqui.</p></div><?php else: foreach ($selectedConversation['messages'] as $message): ?><article class="talk-message <?= ($message['direction'] ?? '') === 'outbound' ? 'is-outbound' : 'is-inbound' ?>"><p><?= nl2br(htmlspecialchars((string)($message['body'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p><small><?= htmlspecialchars((string)($message['sender_name'] ?? (($message['direction'] ?? '') === 'outbound' ? 'Atendente' : $selectedConversation['contact_name'])), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)($message['sent_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= ($message['direction'] ?? '') === 'outbound' ? ' · '.htmlspecialchars((string)((json_decode((string)($message['metadata'] ?? '{}'), true)['delivery_status'] ?? 'enviado')), ENT_QUOTES, 'UTF-8') : '' ?></small></article><?php endforeach; endif; ?>
            </div>
            <?php if (!empty($selectedConversation['action_status'])): ?><p class="talk-composer-feedback" role="status"><?= htmlspecialchars((string)$selectedConversation['action_status'], ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
            <?php if (!empty($selectedConversation['outbound_error'])): ?><p class="talk-composer-feedback is-error" role="alert"><?= htmlspecialchars((string)$selectedConversation['outbound_error'], ENT_QUOTES, 'UTF-8') ?></p><?php elseif (!empty($selectedConversation['outbound_status'])): ?><p class="talk-composer-feedback" role="status">Mensagem enviada.</p><?php endif; ?>
            <?php if (!empty($canOperateTicket)): ?>
            <form class="talk-composer" method="post" data-talk-composer>
                <input type="hidden" name="_token" value="<?= htmlspecialchars(\Moves\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="send">
                <label class="sr-only" for="talk-message-body">Mensagem</label>
                <textarea id="talk-message-body" name="body" rows="1" maxlength="4000" placeholder="Digite uma mensagem" required data-talk-composer-body></textarea>
                <button type="submit" data-talk-send>Enviar</button>
            </form>
            <form class="talk-attachment-form" method="post" enctype="multipart/form-data" data-talk-attachment-form>
                <input type="hidden" name="_token" value="<?= htmlspecialchars(\Moves\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="attachment">
                <label><span class="sr-only">Selecionar anexo</span><input type="file" name="attachment" required accept=".jpg,.jpeg,.png,.webp,.pdf,.txt,.mp3,.ogg,.m4a,.mp4" data-talk-attachment></label>
                <button type="submit">Anexar</button>
                <small>Até 10 MB. Imagem, PDF, texto, áudio ou vídeo.</small>
            </form>
            <?php else: ?><p class="talk-composer-feedback" role="status">Assuma este atendimento para responder.</p><?php endif; ?>
        <?php endif; ?>
    </main>
    <aside class="talk-context" aria-label="Contexto do contato">
        <header><p class="customer-eyebrow">CONTEXTO</p><h2>Contato</h2></header>
        <?php if (!$selectedConversation): ?><div class="talk-empty"><strong>Nenhum contato selecionado.</strong><p>Dados do contato, condomínio, unidade, tags, chamados e notas aparecerão aqui.</p></div><?php else: ?><dl class="talk-contact-details"><div><dt>Contato</dt><dd><?= htmlspecialchars((string)$selectedConversation['contact_name'], ENT_QUOTES, 'UTF-8') ?></dd></div><div><dt>Telefone</dt><dd><?= htmlspecialchars((string)($selectedConversation['contact_phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd></div><div><dt>Fila</dt><dd><?= htmlspecialchars((string)($selectedConversation['queue_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd></div><div><dt>Prioridade</dt><dd><?= htmlspecialchars((string)($selectedConversation['priority'] ?? 'normal'), ENT_QUOTES, 'UTF-8') ?></dd></div></dl>
        <?php if (!empty($canOperateTicket)): ?>
        <details class="talk-context-section"><summary>Transferir atendimento</summary><form method="post"><input type="hidden" name="_token" value="<?= htmlspecialchars(\Moves\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="transfer"><label>Fila<select name="to_queue_id"><option value="">Manter fila atual</option><?php foreach (($selectedConversation['queues'] ?? []) as $queue): if (($queue['status'] ?? '') !== 'active') continue; ?><option value="<?= (int)$queue['id'] ?>"><?= htmlspecialchars((string)$queue['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label>Atendente<select name="to_user_id"><option value="">Somente para a fila</option><?php foreach (($selectedConversation['eligible_users'] ?? []) as $agent): ?><option value="<?= (int)$agent['id'] ?>"><?= htmlspecialchars((string)$agent['name'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select></label><label>Motivo<textarea name="reason" maxlength="500"></textarea></label><button type="submit">Transferir</button></form></details>
        <details class="talk-context-section"><summary>Nota interna</summary><form method="post"><input type="hidden" name="_token" value="<?= htmlspecialchars(\Moves\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="note"><label><span class="sr-only">Nota interna</span><textarea name="note" maxlength="2000" required placeholder="Adicionar nota para a equipe"></textarea></label><button type="submit">Adicionar nota</button></form></details>
        <?php endif; ?>
        <section class="talk-context-section"><h3>Notas</h3><?php if (empty($selectedConversation['notes'])): ?><p>Nenhuma nota interna.</p><?php else: foreach ($selectedConversation['notes'] as $note): ?><article><p><?= nl2br(htmlspecialchars((string)$note['body'], ENT_QUOTES, 'UTF-8')) ?></p><small><?= htmlspecialchars((string)($note['user_name'] ?? 'Equipe'), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)($note['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></small></article><?php endforeach; endif; ?></section><?php endif; ?>
    </aside>
</section>
