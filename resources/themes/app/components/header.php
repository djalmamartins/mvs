<?php

/**
 * Moves | App Header Component
 *
 * Exibe o cabeçalho principal do tema padrão.
 *
 * @author Djalma Martins
 */
?>

<header>
    <strong><a href="/app">Moves</a></strong>
    <nav aria-label="Navegação da aplicação">
        <a href="/app">Painel</a>
        <a href="/app/profile">Perfil</a>
        <form method="post" action="/logout">
            <?= $this->csrf() ?>
            <button type="submit">Sair</button>
        </form>
    </nav>
</header>
