<?php
declare(strict_types=1);
$this->layout('layouts/default', [
    'title' => $title,
    'productName' => 'Talk',
    'activeProduct' => 'talk',
    'currentPage' => $currentPage,
]);
$queue = $queue ?? [];
$counts = $counts ?? [];
$conversations = $conversations ?? [];
$contacts = $contacts ?? [];
?>
<section class="studio-page talk-page">
    <header class="knowledge-header">
        <div>
            <p class="studio-eyebrow">MOVES TALK</p>
            <h1><?= $this->e($title) ?></h1>
            <p><?= $this->e($description) ?></p>
        </div>
    </header>

    <?php if ($currentPage === 'dashboard'): ?>
        <div class="studio-dashboard-kpis">
            <article><span>Na fila</span><strong><?= (int)($counts['queued'] ?? 0) ?></strong></article>
            <article><span>Em atendimento</span><strong><?= (int)($counts['active'] ?? 0) ?></strong></article>
            <article><span>Finalizados hoje</span><strong><?= (int)($counts['closed_today'] ?? 0) ?></strong></article>
            <article><span>Contatos</span><strong><?= (int)($counts['contacts'] ?? 0) ?></strong></article>
        </div>
    <?php endif; ?>

    <?php if ($currentPage === 'queue'): ?>
        <div class="knowledge-header-actions" style="margin-bottom:16px">
            <form method="post" action="/talk/simulate">
                <?= $this->csrf() ?>
                <button class="studio-btn studio-btn-primary" type="submit"><i class="icon-add-outline"></i> Criar atendimento de simulação</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($currentPage === 'queue' || $currentPage === 'dashboard'): ?>
        <section class="studio-panel">
            <div class="studio-panel-body">
                <h2>Fila de atendimento</h2>
                <?php if ($queue === []): ?>
                    <div class="knowledge-empty"><i class="icon-time-outline"></i><strong>Fila vazia</strong><span>Nenhum atendimento está aguardando neste momento.</span></div>
                <?php else: ?>
                    <div class="studio-table-wrap"><table class="studio-table">
                        <thead><tr><th>Protocolo</th><th>Contato</th><th>Fila</th><th>Canal</th><th>Prioridade</th><th>Entrada</th><th>Ação</th></tr></thead>
                        <tbody>
                        <?php foreach ($queue as $ticket): ?>
                            <tr>
                                <td><a href="/talk/tickets/<?= (int)$ticket['id'] ?>"><strong><?= $this->e((string)$ticket['protocol']) ?></strong></a></td>
                                <td><?= $this->e((string)($ticket['contact_name'] ?: $ticket['contact_phone'] ?: 'Sem identificação')) ?></td>
                                <td><?= $this->e((string)($ticket['queue_name'] ?? 'Geral')) ?></td>
                                <td><?= $this->e((string)$ticket['channel']) ?></td>
                                <td><span class="studio-status"><?= $this->e((string)$ticket['priority']) ?></span></td>
                                <td><?= $this->e((string)($ticket['queued_at'] ?: $ticket['created_at'])) ?></td>
                                <td>
                                    <form method="post" action="/talk/queue">
                                        <?= $this->csrf() ?>
                                        <input type="hidden" name="ticket_id" value="<?= (int)$ticket['id'] ?>">
                                        <button class="studio-btn studio-btn-primary" type="submit">Assumir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table></div>
                <?php endif; ?>
            </div>
        </section>
    <?php elseif ($currentPage === 'conversations'): ?>
        <section class="studio-panel"><div class="studio-panel-body">
            <?php if ($conversations === []): ?><div class="knowledge-empty"><i class="icon-chatbubbles-outline"></i><strong>Nenhuma conversa</strong><span>As conversas dos canais conectados aparecerão aqui.</span></div>
            <?php else: ?><div class="studio-table-wrap"><table class="studio-table"><thead><tr><th>Contato</th><th>Canal</th><th>Protocolo</th><th>Status</th><th>Fila</th><th>Atendente</th></tr></thead><tbody>
            <?php foreach($conversations as $row): ?><tr><td><?= $this->e((string)($row['contact_name'] ?: $row['phone'] ?: 'Sem identificação')) ?></td><td><?= $this->e((string)$row['channel']) ?></td><td><?= $this->e((string)($row['protocol'] ?? '—')) ?></td><td><?= $this->e((string)($row['ticket_status'] ?? $row['status'])) ?></td><td><?= $this->e((string)($row['queue_name'] ?? '—')) ?></td><td><?= $this->e((string)($row['assigned_name'] ?? '—')) ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div></section>
    <?php elseif ($currentPage === 'contacts'): ?>
        <section class="studio-panel"><div class="studio-panel-body">
            <?php if ($contacts === []): ?><div class="knowledge-empty"><i class="icon-people-outline"></i><strong>Nenhum contato</strong><span>Novos contatos serão criados a partir das conversas recebidas.</span></div>
            <?php else: ?><div class="studio-table-wrap"><table class="studio-table"><thead><tr><th>Nome</th><th>Telefone</th><th>E-mail</th><th>Canal</th><th>Atualizado</th></tr></thead><tbody>
            <?php foreach($contacts as $row): ?><tr><td><?= $this->e((string)($row['name'] ?: 'Sem nome')) ?></td><td><?= $this->e((string)($row['phone'] ?? '—')) ?></td><td><?= $this->e((string)($row['email'] ?? '—')) ?></td><td><?= $this->e((string)$row['channel']) ?></td><td><?= $this->e((string)$row['updated_at']) ?></td></tr><?php endforeach; ?>
            </tbody></table></div><?php endif; ?>
        </div></section>
    <?php else: ?>
        <section class="studio-panel"><div class="studio-panel-body"><div class="knowledge-empty"><i class="icon-talk"></i><strong>Talk Foundation</strong><span>Esta área será conectada ao fluxo funcional do atendimento nos próximos blocos.</span></div></div></section>
    <?php endif; ?>
</section>
