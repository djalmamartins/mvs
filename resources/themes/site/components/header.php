<?php

/**
 * Moves | Site Header Component
 *
 * Exibe o cabeçalho principal do tema público.
 *
 * @author Djalma Martins
 */
?>

<header class="site-header">
    <a class="brand" href="/" aria-label="Moves — início">
        <img class="brand-logo" src="<?= $this->e($this->asset('images/brand/moves-logo.svg')) ?>" alt="MOVES" width="194" height="28">
    </a>
    <button class="menu-toggle" aria-expanded="false" aria-controls="main-nav" type="button">
        Menu <span aria-hidden="true">☰</span>
    </button>
    <nav id="main-nav" class="main-nav" aria-label="Navegação principal">
        <a href="/#main" class="active" aria-current="page">Início</a>
        <a href="/#services">Serviços</a>
        <a href="/#projects">Projetos</a>
        <a href="/#about">Sobre</a>
        <a href="/#content">Conteúdo</a>
        <a href="/#contact">Contato</a>
        <a class="mobile-budget" href="/login">Área do cliente ↗</a>
    </nav>
    <a class="button button-primary small" href="/login">Área do cliente ↗</a>
</header>
