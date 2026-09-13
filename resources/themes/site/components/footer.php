<?php

/**
 * Moves | Site Footer Component
 *
 * Exibe o rodapé principal do tema público.
 *
 * @author Djalma Martins
 */
?>

<footer class="footer">
    <div class="footer-top">
        <a href="/" aria-label="Moves — início">
            <img class="brand-logo" src="<?= $this->e($this->asset('images/brand/moves-logo.svg')) ?>" alt="MOVES" width="194" height="28">
        </a>
        <p>Tecnologia. Pessoas. Resultados.<br>Sempre em movimento.</p>
        <nav aria-label="Rodapé">
            <a href="/">Início</a>
            <a href="/servicos">Serviços</a>
            <a href="/projetos">Projetos</a>
            <a href="/sobre">Sobre</a>
            <a href="/conteudo">Conteúdo</a>
            <a href="/contato">Solicitar orçamento</a>
        </nav>
    </div>
    <div class="footer-client">
        <p>Já é cliente?</p>
        <a class="text-link" href="/login">Acessar minha conta →</a>
    </div>
    <div class="footer-bottom">
        <a href="mailto:contato@moves.com.br">contato@moves.com.br</a>
        <span>Conselheiro Lafaiete · MG</span>
        <span>© 2026 Moves. Todos os direitos reservados.</span>
    </div>
</footer>
