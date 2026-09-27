<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

use InvalidArgumentException;

/**
 * Explicit ERP authorization boundary.
 *
 * A scope never grants permission by itself; it only identifies the
 * administrator or condominium boundary where a permission may be evaluated.
 */
final readonly class AccessScope
{
    public const ADMINISTRATOR = 'administrator';
    public const CONDOMINIUM = 'condominium';

    public function __construct(
        public string $type,
        public int $id,
    ) {
        if (!in_array($type, [self::ADMINISTRATOR, self::CONDOMINIUM], true)) {
            throw new InvalidArgumentException('Unsupported ERP access scope.');
        }

        if ($id <= 0) {
            throw new InvalidArgumentException('ERP access scope id must be positive.');
        }
    }

    public static function administrator(int $id): self
    {
        return new self(self::ADMINISTRATOR, $id);
    }

    public static function condominium(int $id): self
    {
        return new self(self::CONDOMINIUM, $id);
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
