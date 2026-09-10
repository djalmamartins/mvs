<?php

declare(strict_types=1);

$this->layout('layouts/default', [
    'title' => $title ?? 'Erro',
]);
?>

<section class="error-page">
    <div class="error-code">
        <?= (int) $code ?>
    </div>

    <h1>
        <?php if ($code === 404): ?>
            Página não encontrada
        <?php elseif ($code === 405): ?>
            Método não permitido
        <?php else: ?>
            Erro interno
        <?php endif; ?>
    </h1>

    <p>
        <?php if ($code === 404): ?>
            A página que você tentou acessar não existe.
        <?php elseif ($code === 405): ?>
            Este método HTTP não é permitido para este endereço.
        <?php else: ?>
            Ocorreu um erro inesperado durante o processamento da solicitação.
        <?php endif; ?>
    </p>

    <p>
        <a href="/">Voltar para o início</a>
    </p>
</section>
