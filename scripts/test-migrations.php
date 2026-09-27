<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use RuntimeException;

Environment::load(dirname(__DIR__));

$root = dirname(__DIR__);
$migrations = $root . '/database/migrations';
$php = escapeshellarg(PHP_BINARY);
$runner = escapeshellarg($root . '/scripts/migrate.php');

$run = static function () use ($php, $runner): array {
    $output = [];
    $code = 0;
    exec($php . ' ' . $runner . ' 2>&1', $output, $code);
    return [$code, implode("\n", $output)];
};

$pdo = Connection::getInstance();

// Baseline: banco já migrado pelo gate anterior do CI. Reexecutar deve ser idempotente.
[$code, $output] = $run();
if ($code !== 0 || !str_contains($output, 'SKIP:')) {
    throw new RuntimeException('Reexecução idempotente falhou: ' . $output);
}

// Upgrade: uma migration nova deve ser aplicada uma única vez e registrada no ledger.
$upgradeName = '99999999_998_ci_upgrade_probe.sql';
$upgradePath = $migrations . '/' . $upgradeName;
file_put_contents($upgradePath, 'CREATE TABLE ci_migration_upgrade_probe (id INT PRIMARY KEY);');

try {
    [$code, $output] = $run();
    if ($code !== 0 || !str_contains($output, 'OK: ' . $upgradeName)) {
        throw new RuntimeException('Upgrade incremental falhou: ' . $output);
    }

    [$code, $output] = $run();
    if ($code !== 0 || !str_contains($output, 'SKIP: ' . $upgradeName)) {
        throw new RuntimeException('Migration de upgrade foi reaplicada: ' . $output);
    }
} finally {
    @unlink($upgradePath);
}

// Falha controlada: SQL inválido deve falhar e nunca pode ser gravado no ledger.
$failureName = '99999999_999_ci_failure_probe.sql';
$failurePath = $migrations . '/' . $failureName;
file_put_contents($failurePath, 'THIS IS NOT VALID SQL;');

try {
    [$code] = $run();
    if ($code === 0) {
        throw new RuntimeException('Migration inválida terminou com sucesso indevido.');
    }

    $statement = $pdo->prepare('SELECT COUNT(*) FROM migrations WHERE migration = :migration');
    $statement->execute(['migration' => $failureName]);
    if ((int) $statement->fetchColumn() !== 0) {
        throw new RuntimeException('Migration com falha foi registrada no ledger.');
    }
} finally {
    @unlink($failurePath);
}

echo "Migration safety tests passed.\n";
