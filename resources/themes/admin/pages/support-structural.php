<?php
$this->layout('layouts/default', compact('title', 'productName', 'activeProduct', 'currentPage'));
$isInbox = $currentPage === 'inbox';
$isTickets = in_array($currentPage, ['my-tickets', 'tickets'], true);
?>
<link rel="stylesheet" href="/themes/admin/css/support.css?v=20260919d">
<section class="support-workspace-page">
    <?php $this->insert('components/support-page-header', [
        'heading' => $title,
        'description' => $isInbox
            ? 'Organize conversas, acompanhe o contexto e prepare respostas em um único workspace.'
            : ($isTickets
                ? 'Acompanhe solicitações com filtros e uma estrutura pronta para a operação real.'
                : 'Defina tempos de atendimento quando o fluxo de chamados estiver disponível.'),
    ]); ?>
    <?php if ($isInbox): ?>
        <div class="support-inbox-shell" aria-label="Workspace da caixa de entrada">
            <aside class="support-inbox-list">
                <div class="support-inbox-search"><i class="icon-search-outline"></i><span>Buscar conversas</span></div>
                <div class="support-inbox-filters"><span>Todas</span><span>Não atribuídas</span></div>
                <?php $this->insert('components/support-empty-state', ['icon'=>$icon,'heading'=>'Nenhuma conversa recebida','description'=>$description,'note'=>'As conversas aparecerão aqui somente após a conexão de um canal real.']); ?>
            </aside>
            <section class="support-inbox-conversation">
                <?php $this->insert('components/support-empty-state', ['icon'=>'icon-chatbox-ellipses-outline','heading'=>'Selecione uma conversa','description'=>'O histórico e o campo de resposta serão exibidos neste espaço.']); ?>
            </section>
            <aside class="support-inbox-details">
                <header><h2>Detalhes</h2><p>Contexto do atendimento</p></header>
                <div class="support-detail-placeholder"><i class="icon-person-circle-outline"></i><span>Nenhum contato selecionado</span></div>
            </aside>
        </div>
    <?php elseif ($isTickets): ?>
        <div class="support-filterbar" aria-label="Filtros de chamados">
            <label><i class="icon-search-outline"></i><input type="search" placeholder="Buscar por assunto ou solicitante" disabled></label>
            <select aria-label="Status" disabled><option>Todos os status</option></select>
            <select aria-label="Prioridade" disabled><option>Todas as prioridades</option></select>
        </div>
        <section class="support-table support-ticket-table">
            <table><thead><tr><th>Chamado</th><th>Solicitante</th><th>Status</th><th>Prioridade</th><th>Responsável</th><th>Atualização</th></tr></thead></table>
            <?php $this->insert('components/support-empty-state', ['icon'=>$icon,'heading'=>$currentPage === 'my-tickets' ? 'Nenhum chamado atribuído' : 'Nenhum chamado registrado','description'=>$description,'note'=>'A listagem será habilitada quando existir uma fonte real de chamados.']); ?>
        </section>
    <?php else: ?>
        <div class="support-dependency-banner"><i class="icon-link-outline"></i><div><strong>Dependência: módulo de chamados</strong><span>As políticas só podem ser ativadas depois que estados, prioridades e responsáveis estiverem persistidos.</span></div></div>
        <div class="support-sla-grid">
            <section class="support-panel">
                <header><div><h2>Políticas de atendimento</h2><p>Tempos de primeira resposta e resolução.</p></div><span class="support-state">Planejado</span></header>
                <?php $this->insert('components/support-empty-state', ['icon'=>$icon,'heading'=>'Nenhuma política configurada','description'=>$description]); ?>
            </section>
            <section class="support-panel support-sla-readiness">
                <header><div><h2>Pré-requisitos</h2><p>Base necessária para métricas confiáveis.</p></div><i class="icon-checkbox-outline"></i></header>
                <ul><li>Chamados e eventos persistidos</li><li>Calendário e horário de atendimento</li><li>Prioridades e responsáveis definidos</li><li>Pausas e encerramentos auditáveis</li></ul>
            </section>
        </div>
    <?php endif; ?>
</section>
