<?php

declare(strict_types=1);

/**
 * Moves | Settings Page
 *
 * Exibe o formulário administrativo de configurações.
 *
 * @author Djalma Martins
 */

$this->layout('layouts/default', ['title' => $title, 'currentPage' => 'settings']);
?>
<section class="studio-page">
    <header class="studio-page-heading"><div><p class="studio-eyebrow">GESTÃO</p><h2><?= $this->e($title) ?></h2><p>Ajustes persistidos da aplicação.</p></div><a class="studio-button secondary" href="/admin/diagnostics">Sistema / Diagnóstico</a></header>
    <form class="studio-form-card" method="post" action="/admin/settings">
        <?= $this->csrf() ?>

        <div class="studio-field">
            <label for="app_name">Nome da aplicação</label>
            <input
                type="text"
                id="app_name"
                name="app_name"
                value="<?= $this->e((string) $appName) ?>"
                minlength="2"
                maxlength="100"
                required
            >
        </div>

        <button type="submit">Salvar configurações</button>
    </form>
</section>
