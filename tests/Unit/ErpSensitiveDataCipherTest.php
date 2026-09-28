<?php
declare(strict_types=1);

use Moves\Modules\Erp\Security\SensitiveDataCipher;
use PHPUnit\Framework\TestCase;

final class ErpSensitiveDataCipherTest extends TestCase
{
    public function testEncryptsAndDecryptsWithVersionedKey(): void
    {
        $cipher=new SensitiveDataCipher('erp-key-v1',str_repeat('k',32));
        $encrypted=$cipher->encrypt('{"pix":"fixture-only"}');
        self::assertSame('erp-key-v1',$encrypted['key_id']);
        self::assertStringNotContainsString('fixture-only',$encrypted['ciphertext']);
        self::assertSame('{"pix":"fixture-only"}',$cipher->decrypt($encrypted['ciphertext'],$encrypted['key_id']));
    }

    public function testRejectsUnknownKeyVersion(): void
    {
        $cipher=new SensitiveDataCipher('erp-key-v1',str_repeat('k',32));
        $encrypted=$cipher->encrypt('fixture');
        $this->expectException(RuntimeException::class);
        $cipher->decrypt($encrypted['ciphertext'],'erp-key-v2');
    }
}