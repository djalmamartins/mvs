<?php

declare(strict_types=1);

use Moves\Services\Auth\PasswordPolicy;
use PHPUnit\Framework\TestCase;

final class PasswordPolicyTest extends TestCase
{
    public function testAcceptsPasswordWithEveryRequiredClass(): void
    {
        self::assertTrue(PasswordPolicy::accepts('SenhaForte#2026'));
        self::assertSame([], PasswordPolicy::errors('SenhaForte#2026'));
    }

    public function testRejectsShortOrIncompletePasswordsWithActionableErrors(): void
    {
        $errors = PasswordPolicy::errors('somenteletras');

        self::assertContains('Inclua ao menos uma letra maiúscula.', $errors);
        self::assertContains('Inclua ao menos um número.', $errors);
        self::assertContains('Inclua ao menos um símbolo.', $errors);
        self::assertFalse(PasswordPolicy::accepts('Aa#1'));
    }

    public function testRejectsPasswordLongerThanMaximum(): void
    {
        self::assertFalse(PasswordPolicy::accepts('Aa#1' . str_repeat('x', 125)));
    }
}
