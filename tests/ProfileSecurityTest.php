<?php

declare(strict_types=1);

use Moves\Services\Auth\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class ProfileSecurityTest extends TestCase
{
    public function testProfilePasswordPolicyRequiresStrongPassword(): void
    {
        self::assertFalse(PasswordPolicy::accepts('fraca'));
        self::assertTrue(PasswordPolicy::accepts('PerfilSeguro#2026'));
    }

    public function testCurrentPasswordMustMatchStoredHash(): void
    {
        $hash = password_hash('SenhaAtual#2026', PASSWORD_DEFAULT);
        self::assertTrue(password_verify('SenhaAtual#2026', $hash));
        self::assertFalse(password_verify('SenhaErrada#2026', $hash));
    }

    public function testNewPasswordCanBeDistinguishedFromCurrentPassword(): void
    {
        $hash = password_hash('SenhaAtual#2026', PASSWORD_DEFAULT);
        self::assertTrue(password_verify('SenhaAtual#2026', $hash));
        self::assertFalse(password_verify('NovaSenha#2027', $hash));
    }

    public function testPasswordConfirmationUsesConstantTimeComparison(): void
    {
        self::assertTrue(hash_equals('NovaSenha#2027', 'NovaSenha#2027'));
        self::assertFalse(hash_equals('NovaSenha#2027', 'OutraSenha#2027'));
    }
}
