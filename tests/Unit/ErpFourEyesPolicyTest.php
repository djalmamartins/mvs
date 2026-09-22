<?php

declare(strict_types=1);

use Moves\Modules\Erp\Security\FourEyesPolicy;
use PHPUnit\Framework\TestCase;

final class ErpFourEyesPolicyTest extends TestCase
{
    public function testDistinctUsersAreRequiredForCriticalApproval(): void
    {
        $policy = new FourEyesPolicy();

        self::assertTrue($policy->allows(10, 11));
        self::assertFalse($policy->allows(10, 10));
    }

    public function testInvalidIdentityFailsClosed(): void
    {
        $policy = new FourEyesPolicy();

        self::assertFalse($policy->allows(0, 11));
        self::assertFalse($policy->allows(10, 0));
        self::assertFalse($policy->allows(-1, 11));
    }

    public function testAssertionRejectsSelfApproval(): void
    {
        $policy = new FourEyesPolicy();

        $this->expectException(DomainException::class);
        $policy->assertAllowed(10, 10);
    }

    public function testAssertionAcceptsDistinctApprover(): void
    {
        $policy = new FourEyesPolicy();
        $policy->assertAllowed(10, 11);

        self::assertTrue(true);
    }
}
