<?php
$this->layout('layouts/default', ['title'=>$title,'currentPage'=>'talk']);
?>
<section class="talk-workspace" data-talk-workspace>
    <aside class="talk-list" aria-label="Conversas">
        <header><div><p class="customer-eyebrow">TALK</p><h2>Conversas</h2></div><span class="talk-count"><?= count($conversations) ?></span></header>
        <label class="talk-search"><span class="sr-only">Buscar conversas</span><input type="search" placeholder="Buscar nome, telefone ou unidade" data-talk-search></label>
        <div class="talk-filters" aria-label="Filtros"><button class="active" type="button" data-talk-filter="all">Todas</button><button type="button" data-talk-filter="queue">Fila</button><button type="button" data-talk-filter="mine">Minhas</button><button type="button" data-talk-filter="unread">Não lidas</button></div>
        <div class="talk-list-body" aria-live="polite">
            <?php if (!$conversations): ?><div class="talk-empty"><strong>Nenhuma conversa disponível.</strong><p>Novas conversas aparecerão aqui quando o canal Talk estiver conectado.</p></div><?php endif; ?>
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
