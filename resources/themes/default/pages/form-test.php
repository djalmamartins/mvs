<?php

/**
 * Moves | Form Test Page
 *
 * Exibe o formulário utilizado para validar
 * o fluxo HTTP da aplicação.
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
        action="/form-test"
    >

        <?= $this->csrf() ?>

        <div>
            <label for="name">
                Nome
            </label>

            <input
                type="text"
                id="name"
                name="name"
            >
        </div>

        <div>
            <label for="email">
                E-mail
            </label>

            <input
                type="email"
                id="email"
                name="email"
            >
        </div>

        <button type="submit">
            Enviar
        </button>

    </form>

</main>