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
    <?php if (($debug ?? false) && isset($exception)): ?>
        <div class="error-debug">
            <h2>Detalhes do erro</h2>

            <p>
                <strong>Mensagem:</strong>
                <?= $this->e($exception->getMessage()) ?>
            </p>

            <p>
                <strong>Arquivo:</strong>
                <?= $this->e($exception->getFile()) ?>
            </p>

            <p>
                <strong>Linha:</strong>
                <?= (int) $exception->getLine() ?>
            </p>
        </div>
    <?php endif; ?>
    <p>
        <a href="/">Voltar para o início</a>
    </p>
</section>
