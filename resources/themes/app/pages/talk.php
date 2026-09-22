<?php
$this->layout('layouts/default', ['title'=>$title,'currentPage'=>'talk']);
?>
<section class="talk-workspace" data-talk-workspace data-sync-url="/talk/sync" data-revision="0">
    <aside class="talk-list" aria-label="Conversas">
        <header><div><p class="customer-eyebrow">TALK <span class="talk-sync-state" data-talk-sync-state role="status">Conectando…</span></p><h2>Conversas</h2></div><span class="talk-count" aria-label="<?= count($conversations) ?> conversas"><?= count($conversations) ?></span></header>
        <label class="talk-search"><span class="sr-only">Buscar conversas</span><input type="search" placeholder="Buscar nome, telefone ou unidade" data-talk-search></label>
        <div class="talk-filters" aria-label="Filtros"><button class="active" type="button" data-talk-filter="all" aria-pressed="true">Todas</button><button type="button" data-talk-filter="queue" aria-pressed="false">Fila</button><button type="button" data-talk-filter="mine" aria-pressed="false">Minhas</button></div>
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
            <header class="talk-thread-header"><div><strong><?= htmlspecialchars((string)$selectedConversation['contact_name'], ENT_QUOTES, 'UTF-8') ?></strong><small><?= htmlspecialchars((string)$selectedConversation['protocol'], ENT_QUOTES, 'UTF-8') ?></small></div></header>
            <div class="talk-messages" aria-live="polite">
                <?php if (empty($selectedConversation['messages'])): ?><div class="talk-empty"><strong>Ainda não há mensagens.</strong><p>As mensagens desta conversa aparecerão aqui.</p></div><?php else: foreach ($selectedConversation['messages'] as $message): ?><article class="talk-message <?= ($message['direction'] ?? '') === 'outbound' ? 'is-outbound' : 'is-inbound' ?>"><p><?= nl2br(htmlspecialchars((string)($message['body'] ?? ''), ENT_QUOTES, 'UTF-8')) ?></p><small><?= htmlspecialchars((string)($message['sender_name'] ?? (($message['direction'] ?? '') === 'outbound' ? 'Atendente' : $selectedConversation['contact_name'])), ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars((string)($message['sent_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?><?= ($message['direction'] ?? '') === 'outbound' ? ' · '.htmlspecialchars((string)((json_decode((string)($message['metadata'] ?? '{}'), true)['delivery_status'] ?? 'enviado')), ENT_QUOTES, 'UTF-8') : '' ?></small></article><?php endforeach; endif; ?>
            </div>
            <?php if (!empty($selectedConversation['outbound_error'])): ?><p class="talk-composer-feedback is-error" role="alert"><?= htmlspecialchars((string)$selectedConversation['outbound_error'], ENT_QUOTES, 'UTF-8') ?></p><?php elseif (!empty($selectedConversation['outbound_status'])): ?><p class="talk-composer-feedback" role="status">Mensagem enviada.</p><?php endif; ?>
            <?php if (!empty($canOperateTicket)): ?>
            <form class="talk-composer" method="post" data-talk-composer>
                <input type="hidden" name="_token" value="<?= htmlspecialchars(\Moves\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="send">
                <label class="sr-only" for="talk-message-body">Mensagem</label>
                <textarea id="talk-message-body" name="body" rows="1" maxlength="4000" placeholder="Digite uma mensagem" required data-talk-composer-body></textarea>
                <button type="submit" data-talk-send>Enviar</button>
            </form>
            <?php else: ?><p class="talk-composer-feedback" role="status">Assuma este atendimento para responder.</p><?php endif; ?>
        <?php endif; ?>
    </main>
    <aside class="talk-context" aria-label="Contexto do contato">
        <header><p class="customer-eyebrow">CONTEXTO</p><h2>Contato</h2></header>
        <?php if (!$selectedConversation): ?><div class="talk-empty"><strong>Nenhum contato selecionado.</strong><p>Dados do contato, condomínio, unidade, tags, chamados e notas aparecerão aqui.</p></div><?php else: ?><dl class="talk-contact-details"><div><dt>Contato</dt><dd><?= htmlspecialchars((string)$selectedConversation['contact_name'], ENT_QUOTES, 'UTF-8') ?></dd></div><div><dt>Telefone</dt><dd><?= htmlspecialchars((string)($selectedConversation['contact_phone'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd></div><div><dt>Fila</dt><dd><?= htmlspecialchars((string)($selectedConversation['queue_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></dd></div><div><dt>Prioridade</dt><dd><?= htmlspecialchars((string)($selectedConversation['priority'] ?? 'normal'), ENT_QUOTES, 'UTF-8') ?></dd></div></dl><?php endif; ?>
    </aside>
</section>
