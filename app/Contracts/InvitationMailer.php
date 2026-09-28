<?php

declare(strict_types=1);

namespace Moves\Contracts;

interface InvitationMailer
{
    public function sendInvitation(
        string $email,
        string $name,
        string $tenantName,
        string $acceptUrl,
        int $expiresInHours
    ): void;
}
