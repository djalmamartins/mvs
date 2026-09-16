<?php

declare(strict_types=1);

$this->layout('layouts/default', [
    'title' => $title ?? 'Visão geral',
    'productName' => $productName ?? 'Moves',
    'activeProduct' => $activeProduct ?? '',
    'currentPage' => $currentPage ?? 'dashboard',
]);
?>

<section class="studio-page">
    <header class="studio-page-header">
        <div>
            <h1><?= htmlspecialchars($productName ?? 'Moves', ENT_QUOTES, 'UTF-8') ?></h1>
            <p>Este produto está sendo preparado na Moves Platform.</p>
        </div>
    </header>
</section>
