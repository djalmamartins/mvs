<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Core\Diagnostics;

/**
 * Moves | Diagnostics Test
 *
 * Valida os checks que independem do banco de dados.
 *
 * @author Djalma Martins
 */

$checks = Diagnostics::run(false);

if (
    !isset($checks['php'], $checks['extensions'], $checks['configuration'], $checks['storage'])
    || !$checks['php']['ok']
    || !$checks['extensions']['ok']
    || !$checks['storage']['ok']
) {
    throw new RuntimeException('FAIL: requisitos locais não atendidos.');
}

echo 'OK: diagnóstico básico.' . PHP_EOL;
