<?php

declare(strict_types=1);

$this->layout('layouts/default', ['title' => $title, 'currentPage' => 'logs']);
$queryFor = static function (int $targetPage) use ($search, $level, $status): string {
    return '?' . http_build_query(array_filter([
        'q' => $search,
        'level' => $level,
        'status' => $status,
        'page' => $targetPage,
    ], static fn (string|int $value): bool => $value !== ''));
};
?>
<section class="studio-page">
    <header class="studio-page-heading"><div><p class="studio-eyebrow">GESTÃO TÉCNICA</p><h2>Log</h2><p>Leitura limitada e sanitizada dos eventos registrados pela aplicação.</p></div><span class="studio-badge"><?= $this->e((string) $total) ?> evento(s)</span></header>
    <form class="studio-filter-bar" method="get" action="/admin/logs">
        <label><span>Buscar</span><input type="search" name="q" value="<?= $this->e($search) ?>" maxlength="120" placeholder="Mensagem ou contexto"></label>
        <label><span>Nível</span><select name="level"><option value="">Todos</option><?php foreach (['info' => 'Informação', 'warning' => 'Alerta', 'error' => 'Erro'] as $value => $label): ?><option value="<?= $this->e($value) ?>"<?= $level === $value ? ' selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
        <label><span>Estado</span><select name="status"><option value="">Todos</option><?php foreach (['open' => 'Aberto', 'resolved' => 'Resolvido', 'ignored' => 'Ignorado'] as $value => $label): ?><option value="<?= $this->e($value) ?>"<?= $status === $value ? ' selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
        <button type="submit">Filtrar</button><a href="/admin/logs">Limpar</a>
    </form>
    <?php if ($entries === []): ?>
        <div class="studio-panel studio-empty">Nenhum evento corresponde aos filtros.</div>
    <?php else: ?>
        <div class="studio-table-wrap"><table class="studio-log-table"><thead><tr><th>Data</th><th>Nível</th><th>Estado</th><th>Mensagem</th><th>Contexto sanitizado</th><th>Ações</th></tr></thead><tbody>
        <?php foreach ($entries as $entry): ?><tr><td><time datetime="<?= $this->e($entry['timestamp']) ?>"><?= $this->e($entry['timestamp']) ?></time></td><td><span class="studio-status studio-status-<?= $this->e($entry['level']) ?>"><?= $this->e($entry['level']) ?></span></td><td><span class="studio-status"><?= $this->e(['open'=>'Aberto','resolved'=>'Resolvido','ignored'=>'Ignorado'][$entry['status']] ?? 'Aberto') ?></span></td><td class="studio-log-message"><?= $this->e($entry['message']) ?></td><td><code><?= $this->e($entry['context'] === [] ? '—' : (json_encode($entry['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—')) ?></code></td><td><form class="studio-log-actions" method="post" action="/admin/logs"><?= $this->csrf() ?><input type="hidden" name="fingerprint" value="<?= $this->e($entry['fingerprint']) ?>"><?php foreach (['resolved'=>'Resolver','open'=>'Reabrir','ignored'=>'Ignorar'] as $action=>$label): ?><?php if ($entry['status'] !== $action): ?><button type="submit" name="action" value="<?= $action ?>"><?= $label ?></button><?php endif; ?><?php endforeach; ?></form></td></tr><?php endforeach; ?>
        </tbody></table></div>
    <?php endif; ?>
    <?php if ($pages > 1): ?><nav class="studio-pagination" aria-label="Paginação do log"><?php if ($page > 1): ?><a href="<?= $this->e($queryFor($page - 1)) ?>">← Anterior</a><?php endif; ?><span>Página <?= $this->e((string) $page) ?> de <?= $this->e((string) $pages) ?></span><?php if ($page < $pages): ?><a href="<?= $this->e($queryFor($page + 1)) ?>">Próxima →</a><?php endif; ?></nav><?php endif; ?>
</section>
