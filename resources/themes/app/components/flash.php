<?php

use Moves\Core\Flash;

/**
 * Moves | Flash Component
 *
 * Exibe as mensagens temporárias da aplicação.
 *
 * @author Djalma Martins
 */

$messages = Flash::all();
?>

<?php if ($messages): ?>

    <div class="moves-flash">

        <?php foreach ($messages as $flash): ?>

            <div
                class="moves-flash__message moves-flash__message--<?= $this->e($flash['type']) ?>"
                role="<?= $flash['type'] === 'error' ? 'alert' : 'status' ?>"
            >
                <?= $this->e($flash['message']) ?>
            </div>

        <?php endforeach; ?>

    </div>

<?php endif; ?>
