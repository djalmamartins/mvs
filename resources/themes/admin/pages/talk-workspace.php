<?php
declare(strict_types=1);
$this->layout('layouts/default', [
    'title' => $title,
    'productName' => 'Talk',
    'activeProduct' => 'talk',
    'currentPage' => $currentPage,
]);
?>
<section class="studio-page talk-page">
    <header class="knowledge-header">
        <div>
            <p class="studio-eyebrow">MOVES TALK</p>
            <h1><?= $this->e($title) ?></h1>
            <p><?= $this->e($description) ?></p>
        </div>
    </header>

    <section class="studio-panel">
        <div class="studio-panel-body">
            <div class="knowledge-empty">
                <i class="icon-talk" aria-hidden="true"></i>
                <strong>Talk Foundation</strong>
                <span>Estrutura inicial pronta para receber o fluxo funcional desta área.</span>
            </div>
        </div>
    </section>
</section>
