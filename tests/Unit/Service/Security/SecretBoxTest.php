<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Security;

use App\Service\Security\SecretBox;
use App\Service\Security\SecretBoxException;
use PHPUnit\Framework\TestCase;

final class SecretBoxTest extends TestCase
{
    private const string KEY = 'unit-test-master-key-0123456789abcdef';

    public function testRoundTrip(): void
    {
        $box = new SecretBox(self::KEY);

        $encrypted = $box->encrypt('JBSWY3DPEHPK3PXP');

        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $encrypted);
        self::assertSame('JBSWY3DPEHPK3PXP', $box->decrypt($encrypted));
    }

    public function testSameValueEncryptsDifferentlyEachTime(): void
    {
        $box = new SecretBox(self::KEY);

        self::assertNotSame($box->encrypt('geheim'), $box->encrypt('geheim'));
    }

    public function testEncryptedTotpSecretFitsItsColumn(): void
    {
        // app_user.totp_secret_enc is VARBINARY(255); a 32-character base32 secret needs 1 + 24 + 32 + 16 bytes
        self::assertSame(73, \strlen((new SecretBox(self::KEY))->encrypt(str_repeat('A', 32))));
    }

    public function testAlteredValueIsRejected(): void
    {
        $box = new SecretBox(self::KEY);
        $encrypted = $box->encrypt('geheim');
        $encrypted[-1] = \chr(\ord($encrypted[-1]) ^ 1);

        $this->expectException(SecretBoxException::class);
        $box->decrypt($encrypted);
    }

    public function testOtherMasterKeyCannotDecrypt(): void
    {
        $encrypted = (new SecretBox(self::KEY))->encrypt('geheim');

        $this->expectException(SecretBoxException::class);
        (new SecretBox('another-master-key-0123456789abcdefgh'))->decrypt($encrypted);
    }

    public function testUnknownFormatIsRejected(): void
    {
        $this->expectException(SecretBoxException::class);
        (new SecretBox(self::KEY))->decrypt('JBSWY3DPEHPK3PXP');
    }

    public function testMissingMasterKeyGivesAClearError(): void
    {
        $this->expectException(SecretBoxException::class);
        $this->expectExceptionMessage('APP_ENCRYPTION_KEY is missing or shorter than 32 characters.');
        (new SecretBox(''))->encrypt('geheim');
    }
}
