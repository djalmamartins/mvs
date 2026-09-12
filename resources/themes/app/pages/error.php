<?php

declare(strict_types=1);

$messages = [
    403 => [
        'title' => 'Acesso negado',
        'message' => 'Você não tem permissão para acessar esta página.',
    ],

    404 => [
        'title' => 'Página não encontrada',
        'message' => 'A página solicitada não foi encontrada.',
    ],

    405 => [
        'title' => 'Método não permitido',
        'message' => 'O método utilizado não é permitido para esta página.',
    ],

    500 => [
        'title' => 'Erro interno',
        'message' => 'Ocorreu um erro inesperado durante o processamento da solicitação.',
    ],
];

$error = $messages[$code] ?? $messages[500];
?>

<section class="error-page">
    <div class="error-code">
        <?= (int) $code ?>
    </div>

    <h1>
        <?= htmlspecialchars($error['title'], ENT_QUOTES, 'UTF-8') ?>
    </h1>

    <p>
        <?= htmlspecialchars($error['message'], ENT_QUOTES, 'UTF-8') ?>
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
