<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Security\Permission;
use App\Security\TwoFactor\TwoFactorPolicy;
use App\Security\Voter\AdminVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class AdminVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{AppUser, bool, int}>
     */
    public static function persons(): iterable
    {
        yield 'Manager mit 2FA' => [self::person(UserRole::Manager, twoFactor: true), true, VoterInterface::ACCESS_GRANTED];
        yield 'Manager ohne 2FA, Pflicht an' => [self::person(UserRole::Manager), true, VoterInterface::ACCESS_DENIED];
        yield 'Manager ohne 2FA, Pflicht aus' => [self::person(UserRole::Manager), false, VoterInterface::ACCESS_GRANTED];
        yield 'Angehörige mit Admin-Recht und 2FA' => [self::person(UserRole::Angehoerige, admin: true, twoFactor: true), true, VoterInterface::ACCESS_GRANTED];
        yield 'Angehörige ohne Admin-Recht mit 2FA' => [self::person(UserRole::Angehoerige, twoFactor: true), true, VoterInterface::ACCESS_DENIED];
        yield 'Angehörige ohne Admin-Recht, Pflicht aus' => [self::person(UserRole::Angehoerige), false, VoterInterface::ACCESS_DENIED];
        yield 'Assistenz mit 2FA' => [self::person(UserRole::Assistenz, twoFactor: true), false, VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('persons')]
    public function testAdminRight(AppUser $person, bool $twoFactorRequired, int $expected): void
    {
        $voter = new AdminVoter(new TwoFactorPolicy($twoFactorRequired));

        self::assertSame($expected, $voter->vote(new UsernamePasswordToken($person, 'main'), null, [Permission::ADMIN]));
    }

    public function testNoAdminRightWhileTheSecondStepIsOpen(): void
    {
        $person = self::person(UserRole::Manager, twoFactor: true);
        $token = new TwoFactorToken(new UsernamePasswordToken($person, 'main'), null, 'main', ['app']);

        self::assertSame(VoterInterface::ACCESS_DENIED, (new AdminVoter(new TwoFactorPolicy(false)))->vote($token, null, [Permission::ADMIN]));
    }

    public function testNoAdminRightWithoutLogin(): void
    {
        self::assertSame(VoterInterface::ACCESS_DENIED, (new AdminVoter(new TwoFactorPolicy(false)))->vote(new NullToken(), null, [Permission::ADMIN]));
    }

    public function testOtherAttributesAreLeftToOtherVoters(): void
    {
        $token = new UsernamePasswordToken(self::person(UserRole::Manager), 'main');

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, (new AdminVoter(new TwoFactorPolicy(true)))->vote($token, null, ['ROLE_ADMIN']));
    }

    private static function person(UserRole $role, bool $admin = false, bool $twoFactor = false): AppUser
    {
        $person = new AppUser($role, 'p'.$role->value, 'P '.$role->value, new \DateTimeImmutable('2026-01-01'), $admin || UserRole::Manager === $role);
        if ($twoFactor) {
            $person->enableAuthenticatorApp('encrypted');
        }

        return $person;
    }
}
