<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Security\TwoFactor\AuthenticatorApp;
use App\Service\Security\SecretBox;
use OTPHP\TOTP;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class AuthenticatorAppTest extends TestCase
{
    private const string SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';
    private const string NOW = '2026-11-02 10:00:10 UTC';

    public function testSecretHas160Bits(): void
    {
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $this->app()->generateSecret());
    }

    public function testCurrentAndJustExpiredCodesAreAccepted(): void
    {
        $app = $this->app();
        $code = self::codeAt(0);

        self::assertTrue($app->verify(self::SECRET, $code));
        self::assertTrue($app->verify(self::SECRET, ' '.substr($code, 0, 3).' '.substr($code, 3)));
        // Code of the previous 30-second window, typed a few seconds late
        self::assertTrue($app->verify(self::SECRET, self::codeAt(-15)));
        self::assertFalse($app->verify(self::SECRET, self::codeAt(-120)));
        self::assertFalse($app->verify(self::SECRET, 'abcdef'));
    }

    public function testCheckForAPersonDecryptsTheStoredSecret(): void
    {
        $app = $this->app();
        $person = new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        $person->enableAuthenticatorApp($app->encryptSecret(self::SECRET));

        self::assertTrue($app->verifyForUser($person, self::codeAt(0)));
        self::assertStringStartsWith('otpauth://totp/HomeCrew%3Amax?', $app->provisioningUri($person, self::SECRET));
    }

    private function app(): AuthenticatorApp
    {
        return new AuthenticatorApp(new SecretBox('unit-test-master-key-0123456789abcdef'), new MockClock(self::NOW));
    }

    /**
     * Code the phone shows $offset seconds from NOW.
     */
    private static function codeAt(int $offset): string
    {
        return TOTP::createFromSecret(self::SECRET)->at(max(0, (new \DateTimeImmutable(self::NOW))->getTimestamp() + $offset));
    }
}
