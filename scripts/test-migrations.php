<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;
use Moves\Core\Config;

Environment::load(dirname(__DIR__));

if (Config::environment() !== 'testing') {
    throw new RuntimeException('Testes de migration exigem APP_ENV=testing e banco descartável.');
}

$root = dirname(__DIR__);
$migrations = $root . '/database/migrations';
$php = escapeshellarg(PHP_BINARY) . ' -d variables_order=EGPCS';
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

// Upgrade real: simula uma base na versão imediatamente anterior à migration
// mais recente de produto. Remove somente os objetos que essa migration cria,
// mantém os dados preexistentes e volta a executar o runner.
$payablesMigration = '20261007_003_create_erp_payables.sql';
$userEmail = 'ci-release-upgrade-' . bin2hex(random_bytes(8)) . '@example.test';
$insertUser = $pdo->prepare(
    "INSERT INTO users (name, email, password, status, role)\n" .
    "VALUES (:name, :email, :password, 'active', 'user')"
);
$insertUser->execute([
    'name' => 'Release upgrade sentinel',
    'email' => $userEmail,
    'password' => 'test-only-not-a-login-credential',
]);
$sentinelUserId = (int) $pdo->lastInsertId();

try {
    $pdo->exec('DROP TABLE IF EXISTS erp_payable_installments');
    $pdo->exec('DROP TABLE IF EXISTS erp_payables');

    $removeMigration = $pdo->prepare('DELETE FROM migrations WHERE migration = :migration');
    $removeMigration->execute(['migration' => $payablesMigration]);

    [$code, $output] = $run();
    if ($code !== 0 || !str_contains($output, 'OK: ' . $payablesMigration)) {
        throw new RuntimeException('Upgrade da migration de produto falhou: ' . $output);
    }

    $findSentinel = $pdo->prepare(
        'SELECT name, email, password, status, role FROM users WHERE id = :id'
    );
    $findSentinel->execute(['id' => $sentinelUserId]);
    $sentinel = $findSentinel->fetch(PDO::FETCH_ASSOC);
    if ($sentinel === false
        || $sentinel['name'] !== 'Release upgrade sentinel'
        || $sentinel['email'] !== $userEmail
        || $sentinel['password'] !== 'test-only-not-a-login-credential'
        || $sentinel['status'] !== 'active'
        || $sentinel['role'] !== 'user') {
        throw new RuntimeException('Upgrade alterou ou removeu dados preexistentes de users.');
    }

    foreach (['erp_payables', 'erp_payable_installments'] as $table) {
        $checkTable = $pdo->prepare(
            'SELECT COUNT(*) FROM information_schema.tables ' .
            'WHERE table_schema = DATABASE() AND table_name = :table_name'
        );
        $checkTable->execute(['table_name' => $table]);
        if ((int) $checkTable->fetchColumn() !== 1) {
            throw new RuntimeException('Upgrade não criou a tabela ' . $table . '.');
        }
    }
} finally {
    $removeSentinel = $pdo->prepare('DELETE FROM users WHERE id = :id');
    $removeSentinel->execute(['id' => $sentinelUserId]);
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
