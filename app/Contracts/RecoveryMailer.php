<?php
declare(strict_types=1);
namespace Moves\Contracts;
interface RecoveryMailer
{
    public function sendRecoveryCode(string $email, string $name, string $code, int $expiresInMinutes): void;
}
