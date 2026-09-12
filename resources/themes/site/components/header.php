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
        <a href="/"<?= ($currentPage ?? null) === 'home' ? ' class="active" aria-current="page"' : '' ?>>Início</a>
        <a href="/servicos"<?= ($currentPage ?? null) === 'services' ? ' class="active" aria-current="page"' : '' ?>>Serviços</a>
        <a href="/projetos"<?= ($currentPage ?? null) === 'projects' ? ' class="active" aria-current="page"' : '' ?>>Projetos</a>
        <a href="/#content">Conteúdo</a>
        <a class="mobile-budget" href="/#contact">Solicitar orçamento ↗</a>
        <a class="mobile-client" href="/login">Área do cliente</a>
    </nav>
    <div class="header-actions">
        <a class="button button-primary small" href="/#contact">Solicitar orçamento ↗</a>
        <a class="header-client" href="/login">Área do cliente</a>
    </div>
</header>
