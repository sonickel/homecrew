<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Account;

use App\Service\Account\AccessCodeGenerator;
use PHPUnit\Framework\TestCase;

final class AccessCodeGeneratorTest extends TestCase
{
    public function testStartPasswordIsReadableAndRandom(): void
    {
        $generator = new AccessCodeGenerator();
        $passwords = array_map(static fn (): string => $generator->startPassword(), range(1, 50));

        foreach ($passwords as $password) {
            self::assertMatchesRegularExpression('/^[a-hjkmnp-z2-9]{4}-[a-hjkmnp-z2-9]{4}-[a-hjkmnp-z2-9]{4}$/', $password);
        }
        self::assertCount(50, array_unique($passwords));
    }

    public function testStartPinHasSixDigits(): void
    {
        $generator = new AccessCodeGenerator();

        for ($i = 0; $i < 50; ++$i) {
            self::assertMatchesRegularExpression('/^\d{6}$/', $generator->startPin());
        }
    }

    public function testInvitationCodeIsLongEnoughAndUnambiguous(): void
    {
        $generator = new AccessCodeGenerator();
        $codes = array_map(static fn (): string => $generator->invitationCode(), range(1, 50));

        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{24}$/', $code);
        }
        self::assertCount(50, array_unique($codes));
    }

    public function testPairingCodeIsShortButUnambiguous(): void
    {
        $generator = new AccessCodeGenerator();

        for ($i = 0; $i < 50; ++$i) {
            self::assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{12}$/', $generator->pairingCode());
        }
    }

    public function testTypedCodesAreNormalized(): void
    {
        self::assertSame('ABCD2345EFGH', AccessCodeGenerator::normalizeCode(' abcd-2345 efgh '));
        self::assertSame('', AccessCodeGenerator::normalizeCode(' - '));
    }
}
