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
$tickets = $tickets ?? [];
$transfers = $transfers ?? [];
$queues = $queues ?? [];
$users = $users ?? [];
$reports = $reports ?? [];
$settings = $settings ?? [];
$channels = $channels ?? [];
$jack_interactions = $jack_interactions ?? [];
$departments = $departments ?? [];
$members = $members ?? [];
$canManage = $canManage ?? false;
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
    <?php elseif (in_array($currentPage, ['my-tickets','history'], true)): ?>
        <section class="studio-panel"><div class="studio-panel-body">
        <?php if($tickets===[]): ?><div class="knowledge-empty"><i class="icon-headset-outline"></i><strong>Nenhum atendimento</strong><span>Não há registros nesta área.</span></div>
        <?php else: ?><div class="studio-table-wrap"><table class="studio-table"><thead><tr><th>Protocolo</th><th>Contato</th><th>Fila</th><th>Prioridade</th><th>Status</th><th>Atualizado</th></tr></thead><tbody><?php foreach($tickets as $row): ?><tr><td><a href="/talk/tickets/<?= (int)$row['id'] ?>"><strong><?= $this->e((string)$row['protocol']) ?></strong></a></td><td><?= $this->e((string)($row['contact_name']??'—')) ?></td><td><?= $this->e((string)($row['queue_name']??'—')) ?></td><td><?= $this->e((string)$row['priority']) ?></td><td><?= $this->e((string)$row['status']) ?></td><td><?= $this->e((string)($row['closed_at']??$row['updated_at'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </div></section>
    <?php elseif ($currentPage === 'transfers'): ?>
        <section class="studio-panel"><div class="studio-panel-body"><?php if($transfers===[]): ?><div class="knowledge-empty"><strong>Nenhuma transferência</strong></div><?php else: ?><div class="studio-table-wrap"><table class="studio-table"><thead><tr><th>Protocolo</th><th>Origem</th><th>Destino</th><th>Motivo</th><th>Data</th></tr></thead><tbody><?php foreach($transfers as $row): ?><tr><td><?= $this->e((string)$row['protocol']) ?></td><td><?= $this->e((string)($row['from_user']??$row['from_queue']??'—')) ?></td><td><?= $this->e((string)($row['to_user']??$row['to_queue']??'—')) ?></td><td><?= $this->e((string)($row['reason']??'—')) ?></td><td><?= $this->e((string)$row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
    <?php elseif ($currentPage === 'queues'): ?>
        <?php if($canManage): ?>
        <section class="studio-panel"><div class="studio-panel-body"><h2>Novo departamento</h2><form method="post" action="/talk/queues"><?= $this->csrf() ?><input type="hidden" name="action" value="save_department"><div class="studio-field"><label>Nome</label><input name="name" maxlength="120" required></div><input type="hidden" name="status" value="active"><button class="studio-btn" type="submit"><i class="icon-add-outline"></i> Criar departamento</button></form></div></section>
        <section class="studio-panel"><div class="studio-panel-body"><h2>Nova fila</h2><form method="post" action="/talk/queues"><?= $this->csrf() ?><input type="hidden" name="action" value="save_queue"><div class="studio-field"><label>Nome</label><input name="name" maxlength="120" required></div><div class="studio-field"><label>Departamento</label><select name="department_id"><option value="">Sem departamento</option><?php foreach($departments as $d): ?><option value="<?= (int)$d['id'] ?>"><?= $this->e((string)$d['name']) ?></option><?php endforeach; ?></select></div><div class="studio-field"><label>Autoatribuição (segundos)</label><input type="number" min="5" name="auto_assign_after_seconds" value="30"></div><input type="hidden" name="status" value="active"><button class="studio-btn studio-btn-primary" type="submit">Criar fila</button></form></div></section>
        <?php endif; ?>
        <?php foreach($queues as $row): ?><section class="studio-panel"><div class="studio-panel-body"><h2><?= $this->e((string)$row['name']) ?></h2><p><?= $this->e((string)($row['department_name']??'Sem departamento')) ?> · <?= (int)$row['members'] ?> membros · autoatribuição <?= (int)$row['auto_assign_after_seconds'] ?>s · <?= $this->e((string)$row['status']) ?></p>
        <?php if($canManage): ?><form method="post" action="/talk/queues"><?= $this->csrf() ?><input type="hidden" name="action" value="save_member"><input type="hidden" name="queue_id" value="<?= (int)$row['id'] ?>"><div class="studio-field"><label>Adicionar/atualizar membro</label><select name="user_id" required><option value="">Selecione</option><?php foreach($users as $u): ?><option value="<?= (int)$u['id'] ?>"><?= $this->e((string)$u['name']) ?></option><?php endforeach; ?></select></div><div class="studio-field"><label>Função</label><select name="role"><option value="agent">Atendente</option><option value="supervisor">Supervisor</option></select></div><div class="studio-field"><label>Capacidade</label><input type="number" min="1" max="100" name="capacity" value="5"></div><input type="hidden" name="status" value="active"><button class="studio-btn" type="submit">Salvar membro</button></form><?php endif; ?>
        <div class="studio-table-wrap"><table class="studio-table"><thead><tr><th>Membro</th><th>Função</th><th>Capacidade</th><th>Status</th><?php if($canManage): ?><th>Ação</th><?php endif; ?></tr></thead><tbody><?php foreach(($members[(int)$row['id']]??[]) as $m): ?><tr><td><?= $this->e((string)$m['name']) ?><br><small><?= $this->e((string)$m['email']) ?></small></td><td><?= $this->e((string)$m['role']) ?></td><td><?= (int)$m['capacity'] ?></td><td><?= $this->e((string)$m['status']) ?></td><?php if($canManage): ?><td><form method="post" action="/talk/queues"><?= $this->csrf() ?><input type="hidden" name="action" value="remove_member"><input type="hidden" name="queue_id" value="<?= (int)$row['id'] ?>"><input type="hidden" name="user_id" value="<?= (int)$m['user_id'] ?>"><button class="studio-btn" type="submit">Remover</button></form></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div>
        </div></section><?php endforeach; ?>
    <?php elseif ($currentPage === 'users'): ?>
        <section class="studio-panel"><div class="studio-panel-body"><h2>Minha presença</h2><form method="post" action="/talk/users"><?= $this->csrf() ?><input type="hidden" name="action" value="presence"><div class="studio-field"><select name="presence"><option value="online">Online</option><option value="away">Ausente</option><option value="offline">Offline</option></select></div><button class="studio-btn studio-btn-primary" type="submit">Atualizar presença</button></form></div></section>
        <section class="studio-panel"><div class="studio-panel-body"><div class="studio-table-wrap"><table class="studio-table"><thead><tr><th>Usuário</th><th>Perfil Talk</th><th>Presença</th><th>Ativos</th><th>Capacidade</th><th>Última atividade</th></tr></thead><tbody><?php foreach($users as $row): ?><tr><td><strong><?= $this->e((string)$row['name']) ?></strong><br><small><?= $this->e((string)$row['email']) ?></small></td><td><?php if($canManage): ?><form method="post" action="/talk/users"><?= $this->csrf() ?><input type="hidden" name="action" value="user_settings"><input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>"><select name="talk_role"><option value="agent" <?= $row['talk_role']==='agent'?'selected':'' ?>>Atendente</option><option value="supervisor" <?= $row['talk_role']==='supervisor'?'selected':'' ?>>Supervisor</option><option value="admin" <?= $row['talk_role']==='admin'?'selected':'' ?>>Admin</option></select><?php else: ?><?= $this->e((string)$row['talk_role']) ?><?php endif; ?></td><td><?= $this->e((string)$row['presence']) ?></td><td><?= (int)$row['active_tickets'] ?></td><td><?php if($canManage): ?><input type="number" name="capacity" min="1" max="100" value="<?= (int)$row['max_active_tickets'] ?>" style="width:72px"> <button class="studio-btn" type="submit">Salvar</button></form><?php else: ?><?= (int)$row['max_active_tickets'] ?><?php endif; ?></td><td><?= $this->e((string)($row['last_seen_at']??'—')) ?></td></tr><?php endforeach; ?></tbody></table></div></div></section>
    <?php elseif ($currentPage === 'reports'): ?>
        <div class="studio-dashboard-kpis"><article><span>Total</span><strong><?= (int)($reports['total']??0) ?></strong></article><article><span>Na fila</span><strong><?= (int)($reports['queued']??0) ?></strong></article><article><span>Em atendimento</span><strong><?= (int)($reports['active']??0) ?></strong></article><article><span>Finalizados</span><strong><?= (int)($reports['closed']??0) ?></strong></article></div>
        <section class="studio-panel"><div class="studio-panel-body"><h2>Atendimentos por fila</h2><?php foreach(($reports['by_queue']??[]) as $row): ?><p><?= $this->e((string)$row['label']) ?>: <strong><?= (int)$row['total'] ?></strong></p><?php endforeach; ?></div></section>
    <?php elseif ($currentPage === 'jack'): ?>
        <section class="studio-panel"><div class="studio-panel-body"><?php if($jack_interactions===[]): ?><div class="knowledge-empty"><i class="icon-sparkles-outline"></i><strong>Sem interações do Jack</strong><span>As decisões do agente virtual serão auditadas aqui.</span></div><?php else: ?><div class="studio-table-wrap"><table class="studio-table"><thead><tr><th>Protocolo</th><th>Contato</th><th>Ação</th><th>Resumo</th><th>Data</th></tr></thead><tbody><?php foreach($jack_interactions as $row): ?><tr><td><?= $this->e((string)$row['protocol']) ?></td><td><?= $this->e((string)$row['contact_name']) ?></td><td><?= $this->e((string)$row['action']) ?></td><td><?= $this->e((string)($row['summary']??'—')) ?></td><td><?= $this->e((string)$row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div></section>
    <?php elseif ($currentPage === 'jack-settings'): ?>
        <section class="studio-panel"><div class="studio-panel-body"><h2>Agente virtual Jack</h2><form method="post" action="/talk/jack/settings"><?= $this->csrf() ?><div class="studio-field"><label><input type="checkbox" name="jack_enabled" value="1" <?= ($settings['jack.enabled']??'0')==='1'?'checked':'' ?>> Ativar Jack</label></div><div class="studio-field"><label for="jack-wait">Aguardar antes de assumir (segundos)</label><input id="jack-wait" type="number" min="0" name="jack_wait_seconds" value="<?= (int)($settings['jack.wait_seconds']??60) ?>"></div><div class="studio-field"><label><input type="checkbox" name="jack_transfer_summary" value="1" <?= ($settings['jack.transfer_summary']??'1')==='1'?'checked':'' ?>> Gerar resumo ao transferir para humano</label></div><button class="studio-btn studio-btn-primary" type="submit">Salvar</button></form></div></section>
    <?php elseif ($currentPage === 'settings'): ?>
        <section class="studio-panel"><div class="studio-panel-body"><h2>Distribuição automática</h2><form method="post" action="/talk/settings"><?= $this->csrf() ?><div class="studio-field"><label><input type="checkbox" name="auto_assign_enabled" value="1" <?= ($settings['auto_assign.enabled']??'1')==='1'?'checked':'' ?>> Ativar autoatribuição</label></div><div class="studio-field"><label for="auto-seconds">Tempo padrão (segundos)</label><input id="auto-seconds" type="number" min="5" name="auto_assign_default_seconds" value="<?= (int)($settings['auto_assign.default_seconds']??30) ?>"></div><button class="studio-btn studio-btn-primary" type="submit">Salvar</button></form></div></section>
        <section class="studio-panel"><div class="studio-panel-body"><h2>Canais</h2><div class="studio-table-wrap"><table class="studio-table"><thead><tr><th>Canal</th><th>Tipo</th><th>Status</th><th>Última conexão</th></tr></thead><tbody><?php foreach($channels as $row): ?><tr><td><?= $this->e((string)$row['name']) ?></td><td><?= $this->e((string)$row['type']) ?></td><td><?= $this->e((string)$row['status']) ?></td><td><?= $this->e((string)($row['last_connected_at']??'—')) ?></td></tr><?php endforeach; ?></tbody></table></div></div></section>
    <?php else: ?>
        <section class="studio-panel"><div class="studio-panel-body"><div class="knowledge-empty"><i class="icon-talk"></i><strong>Talk</strong><span>Área preparada para a próxima integração operacional.</span></div></div></section>
    <?php endif; ?>
</section>
