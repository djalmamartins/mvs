<?php

declare(strict_types=1);

/**
 * Moves | Diagnostics Page
 *
 * Apresenta o estado dos requisitos essenciais da plataforma.
 *
 * @author Djalma Martins
 */

$this->layout('layouts/default', ['title' => $title, 'currentPage' => 'settings']);
?>
<section class="studio-page">
    <header class="studio-page-heading"><div><p class="studio-eyebrow">CONFIGURAÇÕES / SISTEMA</p><h2><?= $this->e($title) ?></h2><p>Verificações essenciais sem exposição de dados sensíveis.</p></div></header>
    <ul class="studio-diagnostics">
        <?php foreach ($checks as $check): ?>
            <li class="<?= $check['ok'] ? 'ok' : 'failure' ?>"><span aria-hidden="true"><?= $check['ok'] ? '✓' : '!' ?></span><div><strong><?= $check['ok'] ? 'Operacional' : 'Atenção necessária' ?></strong><small><?= $this->e($check['message']) ?></small></div></li>
        <?php endforeach; ?>
    </ul>
</section>
