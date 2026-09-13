<?php

declare(strict_types=1);

$this->layout('layouts/default', ['title' => $title, 'currentPage' => 'versions']);
?>
<section class="studio-page">
    <header class="studio-page-heading"><div><p class="studio-eyebrow">GESTÃO TÉCNICA</p><h2>Versões</h2><p>Estado real da instalação. Esta tela não instala, baixa nem reverte versões.</p></div></header>
    <dl class="studio-facts">
        <div><dt>Moves</dt><dd><?= $this->e($version) ?></dd></div>
        <div><dt>PHP</dt><dd><?= $this->e($phpVersion) ?></dd></div>
        <div><dt>Ambiente</dt><dd><?= $this->e($environment) ?></dd></div>
        <div><dt>Banco</dt><dd><?= $this->e($database) ?></dd></div>
        <div><dt>Tema ativo</dt><dd><?= $this->e($themeName) ?></dd></div>
        <div><dt>Composer</dt><dd><?= $this->e($composer) ?></dd></div>
    </dl>
    <article class="studio-panel studio-version-panel">
        <div class="studio-panel-heading"><div><p class="studio-eyebrow">BANCO</p><h2>Migrations disponíveis</h2></div><span class="studio-badge"><?= $this->e((string) count($migrations)) ?> arquivo(s)</span></div>
        <?php if ($migrations === []): ?><p class="studio-empty">Nenhuma migration encontrada.</p><?php else: ?><ul class="studio-file-list"><?php foreach ($migrations as $migration): ?><li><code><?= $this->e($migration) ?></code></li><?php endforeach; ?></ul><?php endif; ?>
    </article>
    <section class="studio-version-layout"><form class="studio-form-card" method="post" action="/admin/versions"><?= $this->csrf() ?><p class="studio-eyebrow">NOVO REGISTRO</p><h2>Registrar versão</h2><p class="studio-help">Registra histórico e notas. Não altera arquivos, tags, dependências ou deploy.</p><div class="studio-fields-two"><div class="studio-field"><label for="version">Versão semântica</label><input id="version" name="version" placeholder="1.1.0" pattern="[0-9]+\.[0-9]+\.[0-9]+(?:-[0-9A-Za-z.-]+)?" required></div><div class="studio-field"><label for="version_name">Nome</label><input id="version_name" name="name" maxlength="120" required></div></div><div class="studio-field"><label for="notes">Notas da versão</label><textarea id="notes" name="notes" minlength="10" maxlength="4000" required></textarea></div><button type="submit">Registrar no histórico</button></form><article class="studio-panel"><header><div><p class="studio-eyebrow">HISTÓRICO</p><h2>Versões registradas</h2></div><span class="studio-badge"><?= $this->e((string) count($versions)) ?></span></header><?php if ($versions === []): ?><p class="studio-empty">Nenhuma versão registrada ainda.</p><?php else: ?><div class="studio-table-wrap"><table><thead><tr><th>Versão</th><th>Nome</th><th>Status</th><th>Responsável</th><th>Data</th></tr></thead><tbody><?php foreach ($versions as $release): ?><tr><td><strong><?= $this->e($release['version']) ?></strong></td><td><?= $this->e($release['name']) ?><small><?= $this->e($release['notes']) ?></small></td><td><span class="studio-status studio-status-<?= $release['status'] === 'current' ? 'info' : 'warning' ?>"><?= $this->e($release['status'] === 'current' ? 'Atual' : 'Arquivada') ?></span></td><td><?= $this->e($release['author_name'] ?? 'Sistema') ?></td><td><?= $this->e($release['published_at']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></article></section>
</section>
