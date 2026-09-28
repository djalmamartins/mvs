<?php
declare(strict_types=1);

use Moves\Core\Flash;

$messages = Flash::all();
?>
<?php if ($messages !== []): ?>
<div class="auth-alerts" aria-live="polite" aria-atomic="true">
    <?php foreach ($messages as $flash): ?>
        <p class="auth-alert auth-alert--<?= $this->e((string) $flash['type']) ?>" role="alert">
            <?= $this->e((string) $flash['message']) ?>
        </p>
    <?php endforeach; ?>
</div>
<?php endif; ?>
