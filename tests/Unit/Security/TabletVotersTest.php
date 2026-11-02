<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\DeviceMode;
use App\Enum\UserRole;
use App\Security\Device\DeviceToken;
use App\Security\Permission;
use App\Security\Pin\PinLoginToken;
use App\Security\Voter\AccountVoter;
use App\Security\Voter\EmergencyVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Who may see the emergency page and who may change login data or the PIN (team tablet, PIN login, full login).
 * TEAM_VIEW and PIN_LOGIN are covered by the functional tests (they need the request and the database).
 */
final class TabletVotersTest extends TestCase
{
    private static function person(): AppUser
    {
        return new AppUser(UserRole::Assistenz, 'anna', 'Anna', new \DateTimeImmutable('2026-01-01'));
    }

    private static function deviceToken(): DeviceToken
    {
        $device = new Device('Flur', hash('sha256', 'token'));
        $device->setMode(DeviceMode::Persoenlich);

        return new DeviceToken($device, 'main', $device->getRoles());
    }

    /**
     * @return iterable<string, array{TokenInterface, int, int, int}> token, EMERGENCY_VIEW, ACCOUNT_LOGIN_SETTINGS, PIN_CHANGE
     */
    public static function tokens(): iterable
    {
        $granted = VoterInterface::ACCESS_GRANTED;
        $denied = VoterInterface::ACCESS_DENIED;

        yield 'volle Anmeldung' => [new UsernamePasswordToken(self::person(), 'main', ['ROLE_USER']), $granted, $granted, $denied];
        yield 'PIN-Anmeldung' => [new PinLoginToken(self::person(), 'main', ['ROLE_USER'], 1), $granted, $denied, $granted];
        yield 'Team-Tablet (auch Modus Persönlich)' => [self::deviceToken(), $granted, $denied, $denied];
        yield 'zweiter Schritt offen' => [new TwoFactorToken(new UsernamePasswordToken(self::person(), 'main'), null, 'main', ['app']), $denied, $granted, $denied];
        yield 'nicht angemeldet' => [new NullToken(), $denied, $denied, $denied];
    }

    #[DataProvider('tokens')]
    public function testDecisions(TokenInterface $token, int $emergency, int $loginSettings, int $pinChange): void
    {
        self::assertSame($emergency, (new EmergencyVoter())->vote($token, null, [Permission::EMERGENCY_VIEW]));
        self::assertSame($loginSettings, (new AccountVoter())->vote($token, null, [Permission::ACCOUNT_LOGIN_SETTINGS]));
        self::assertSame($pinChange, (new AccountVoter())->vote($token, null, [Permission::PIN_CHANGE]));
    }
}
