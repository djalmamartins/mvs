<?php

/**
 * Moves | Login Page
 *
 * Exibe o formulário de autenticação da aplicação.
 *
 * @author Djalma Martins
 */

$this->layout(
    'layouts/default',
    [
        'title' => $title,
    ]
);
?>

<main>

    <h1>
        <?= $this->e($title) ?>
    </h1>

    <form
        method="post"
        action="/login"
    >

        <?= $this->csrf() ?>

        <div>
            <label for="email">
                E-mail
            </label>

            <input
                type="email"
                id="email"
                name="email"
                autocomplete="email"
                required
            >
        </div>

        <div>
            <label for="password">
                Senha
            </label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="current-password"
                required
            >
        </div>

        <button type="submit">
            Entrar
        </button>

    </form>

</main>
