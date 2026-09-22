<?php
$this->layout('layouts/default', ['title'=>$title,'currentPage'=>'talk']);
?>
<section class="talk-workspace" data-talk-workspace>
    <aside class="talk-list" aria-label="Conversas">
        <header><div><p class="customer-eyebrow">TALK</p><h2>Conversas</h2></div><span class="talk-count" aria-label="<?= count($conversations) ?> conversas"><?= count($conversations) ?></span></header>
        <label class="talk-search"><span class="sr-only">Buscar conversas</span><input type="search" placeholder="Buscar nome, telefone ou unidade" data-talk-search></label>
        <div class="talk-filters" aria-label="Filtros"><button class="active" type="button" data-talk-filter="all" aria-pressed="true">Todas</button><button type="button" data-talk-filter="queue" aria-pressed="false">Fila</button><button type="button" data-talk-filter="mine" aria-pressed="false">Minhas</button><button type="button" data-talk-filter="unread" aria-pressed="false">Não lidas</button></div>
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
        <div class="talk-thread-empty"><span aria-hidden="true">◌</span><strong>Selecione uma conversa</strong><p>Escolha uma conversa na lista para visualizar mensagens e responder.</p></div>
    </main>
    <aside class="talk-context" aria-label="Contexto do contato">
        <header><p class="customer-eyebrow">CONTEXTO</p><h2>Contato</h2></header>
        <div class="talk-empty"><strong>Nenhum contato selecionado.</strong><p>Dados do contato, condomínio, unidade, tags, chamados e notas aparecerão aqui.</p></div>
    </aside>
</section>
