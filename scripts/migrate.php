<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Moves\Boot\Connection;
use Moves\Boot\Environment;

/**
 * Moves | Migration Runner
 *
 * Executa migrations SQL pendentes
 * e registra o histórico no banco.
 *
 * @author Djalma Martins
 */

Environment::load(dirname(__DIR__));

$pdo = Connection::getInstance();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci'
);

$directory = dirname(__DIR__) . '/database/migrations';

$files = glob($directory . '/*.sql');

if ($files === false || $files === []) {
    echo 'Nenhuma migration encontrada.' . PHP_EOL;
    exit(0);
}

sort($files);

// The older Talk tenancy migrations and 20260926_011 describe different
// upgrade paths. Running 011 over the older schema partially changes MySQL
// tables before failing because DDL is not transactional.
$talkMultitenancyMigration = '20260926_011_create_talk_multitenancy.sql';
$pendingTalkMultitenancy = $pdo->prepare(
    'SELECT COUNT(*) FROM migrations WHERE migration = :migration'
);
$pendingTalkMultitenancy->execute(['migration' => $talkMultitenancyMigration]);
if ((int) $pendingTalkMultitenancy->fetchColumn() === 0) {
    $legacyTalkTenancy = $pdo->query(
        "SELECT COUNT(*) FROM migrations WHERE migration = '20260922_001_add_talk_tenant_context.sql'"
    );
    if ((int) $legacyTalkTenancy->fetchColumn() > 0) {
        throw new RuntimeException(
            'Schema Talk legado detectado. A migration 20260926_011 não é compatível ' .
            'com a trilha 20260922_001; interrompido antes de alterar dados. ' .
            'Use o plano de reconciliação da issue #170.'
        );
    }
}

foreach ($files as $file) {
    $migration = basename($file);

    $statement = $pdo->prepare(
        'SELECT COUNT(*) FROM migrations WHERE migration = :migration'
    );

    $statement->execute([
        'migration' => $migration,
    ]);

    if ((int) $statement->fetchColumn() > 0) {
        echo 'SKIP: ' . $migration . PHP_EOL;
        continue;
    }

    $sql = file_get_contents($file);

    if ($sql === false || trim($sql) === '') {
        throw new RuntimeException(
            'Migration vazia ou ilegível: ' . $migration
        );
    }

    try {
        $pdo->exec($sql);

        $statement = $pdo->prepare(
            'INSERT INTO migrations (migration) VALUES (:migration)'
        );

        $statement->execute([
            'migration' => $migration,
        ]);

        echo 'OK: ' . $migration . PHP_EOL;
    } catch (Throwable $exception) {
        throw $exception;
    }
}

echo 'Migrations concluídas.' . PHP_EOL;
