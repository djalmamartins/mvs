<?php

declare(strict_types=1);

namespace Moves\Modules\Erp\Security;

use Moves\Core\Config;

/**
 * Loads MFA cryptographic material exclusively from runtime configuration.
 * Invalid or missing configuration fails closed before any secret is read.
 */
final readonly class MfaRuntimeConfig
{
    public function __construct(
        public string $keyId,
        public string $key
    ) {
        if ($this->keyId === '' || strlen($this->key) !== 32) {
            throw new \RuntimeException('MFA runtime encryption configuration is unavailable.');
        }
    }

    public static function fromEnvironment(): self
    {
        $keyId = trim((string) Config::get('ERP_MFA_KEY_ID', ''));
        $encodedKey = trim((string) Config::get('ERP_MFA_KEY', ''));
        $key = base64_decode($encodedKey, true);

        if ($key === false) {
            throw new \RuntimeException('MFA runtime encryption configuration is unavailable.');
        }

        return new self($keyId, $key);
    }

    public function cipher(): MfaSecretCipher
    {
        return new MfaSecretCipher($this->keyId, $this->key);
    }
}
