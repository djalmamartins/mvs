<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\MfaRequirementPolicy;
use PHPUnit\Framework\TestCase;

final class ErpMfaRequirementPolicyTest extends TestCase
{
    public function testSensitiveRolesRequireMfa(): void
    {
        $policy = new MfaRequirementPolicy();

        foreach (['admin', 'administrator', 'superadmin', 'supervisor', 'finance', 'financial', 'manager'] as $role) {
            self::assertTrue($policy->requiresMfa($role), $role);
        }
    }

    public function testRoleMatchingIsNormalized(): void
    {
        $policy = new MfaRequirementPolicy();

        self::assertTrue($policy->requiresMfa(' ADMIN '));
        self::assertTrue($policy->requiresMfa('Finance'));
    }

    public function testNonSensitiveOrMissingRoleDoesNotRequireMfa(): void
    {
        $policy = new MfaRequirementPolicy();

        foreach ([null, '', ' ', 'user', 'resident', 'attendant'] as $role) {
            self::assertFalse($policy->requiresMfa($role), (string) $role);
        }
    }
}
