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
</section>
