<?php

declare(strict_types=1);

/**
 * Moves | Settings Page
 *
 * Exibe o formulário administrativo de configurações.
 *
 * @author Djalma Martins
 */

$this->layout('layouts/default', ['title' => $title]);
?>

<main>
    <h1><?= $this->e($title) ?></h1>

    <form method="post" action="/admin/settings">
        <?= $this->csrf() ?>

        <div>
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
</main>
