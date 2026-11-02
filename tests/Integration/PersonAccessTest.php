<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\WebauthnCredential;
use App\Enum\TwoFactorMethod;
use App\Enum\UserRole;
use App\Repository\InvitationRepository;
use App\Service\Account\InvitationManager;
use App\Service\Account\PersonAccess;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * What an admin does with another person's access (App\Service\Account\PersonAccess).
 */
final class PersonAccessTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;

    private AppUser $admin;
    private AppUser $anna;

    protected function setUp(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-02 10:00:00 UTC');
        $this->admin = $this->createPerson(UserRole::Manager, 'max');
        $this->anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
    }

    public function testStartPasswordMustBeChangedAndIsNeverLogged(): void
    {
        $password = $this->service()->setStartPassword($this->anna, $this->admin);

        self::assertTrue($this->anna->isPasswordMustChange());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($this->anna, $password));
        self::assertSame(['zugang.startpasswort'], $this->loggedActions());
        self::assertStringNotContainsString($password, $this->wholeLog());
    }

    public function testStartPasswordWithdrawsAnOpenInvitation(): void
    {
        static::getContainer()->get(InvitationManager::class)->create($this->anna, $this->admin);

        $this->service()->setStartPassword($this->anna, $this->admin);

        self::assertSame(0, static::getContainer()->get(InvitationRepository::class)->count([]));
    }

    public function testStartPinAndPinLogin(): void
    {
        $pin = $this->service()->setStartPin($this->anna, $this->admin);
        $this->service()->setPinLogin($this->anna, true, $this->admin);

        self::assertTrue($this->anna->hasPin());
        self::assertTrue($this->anna->isPinMustChange());
        self::assertTrue($this->anna->isPinLoginEnabled());
        self::assertSame(['zugang.startpin', 'zugang.pin_anmeldung'], $this->loggedActions());
        self::assertStringNotContainsString($pin, $this->wholeLog());
    }

    public function testPinLoginNeedsAPin(): void
    {
        $this->expectException(\LogicException::class);
        $this->service()->setPinLogin($this->anna, true, $this->admin);
    }

    public function testResetRemovesAppCodesAndPasskeys(): void
    {
        $this->enableAuthenticatorApp($this->anna);
        $this->giveRecoveryCodes($this->anna);
        $this->entityManager()->persist(new WebauthnCredential($this->anna, 'cred-anna', 'key', 0, null, []));
        $this->entityManager()->flush();

        $this->service()->resetTwoFactor($this->anna, $this->admin);

        self::assertSame(TwoFactorMethod::Keine, $this->anna->getTwofaMethod());
        self::assertNull($this->anna->getTotpSecretEnc());
        self::assertSame(0, $this->anna->countRecoveryCodes());
        self::assertSame(0, (int) $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM webauthn_credential'));
    }

    public function testLeavingRemovesEveryWayToLogIn(): void
    {
        $this->service()->setStartPassword($this->anna, $this->admin);
        $this->service()->setStartPin($this->anna, $this->admin);
        $this->service()->setPinLogin($this->anna, true, $this->admin);
        $this->enableAuthenticatorApp($this->anna);
        static::getContainer()->get(InvitationManager::class)->create($this->anna, $this->admin);

        $this->service()->leave($this->anna, new \DateTimeImmutable('2026-11-02'), $this->admin);

        self::assertTrue($this->anna->hasLeft());
        self::assertFalse($this->anna->hasPassword());
        self::assertFalse($this->anna->hasPin());
        self::assertFalse($this->anna->isPinLoginEnabled());
        self::assertFalse($this->anna->hasTwoFactor());
        self::assertSame(0, static::getContainer()->get(InvitationRepository::class)->count([]));
        self::assertNotNull($this->entityManager()->find(AppUser::class, $this->anna->getId()), 'Die Person bleibt als Anker.');
    }

    public function testLeavingIsNotEnteredInAdvanceNorBeforeTheStart(): void
    {
        foreach (['2026-11-03', '2025-12-31'] as $day) {
            try {
                $this->service()->leave($this->anna, new \DateTimeImmutable($day), $this->admin);
                self::fail('Austritt am '.$day.' wurde angenommen.');
            } catch (\DomainException) {
                self::assertFalse($this->anna->hasLeft());
            }
        }
    }

    public function testUndoLeavingNeedsANewAccess(): void
    {
        $this->service()->leave($this->anna, new \DateTimeImmutable('2026-11-01'), $this->admin);
        $this->service()->undoLeave($this->anna, $this->admin);

        self::assertFalse($this->anna->hasLeft());
        self::assertFalse($this->anna->hasPassword());
    }

    public function testNoNewAccessForAPersonWhoLeft(): void
    {
        $this->service()->leave($this->anna, new \DateTimeImmutable('2026-11-01'), $this->admin);

        $this->expectException(\DomainException::class);
        $this->service()->setStartPassword($this->anna, $this->admin);
    }

    public function testLockAndUnlock(): void
    {
        $this->service()->lock($this->anna, $this->admin);
        self::assertTrue($this->anna->isBlockedOn(new \DateTimeImmutable('2026-11-02')));

        $this->service()->unlock($this->anna, $this->admin);
        self::assertFalse($this->anna->isBlockedOn(new \DateTimeImmutable('2026-11-02')));
        self::assertSame(['person.sperren', 'person.entsperren'], $this->loggedActions());
    }

    private function service(): PersonAccess
    {
        return static::getContainer()->get(PersonAccess::class);
    }

    private function wholeLog(): string
    {
        return (string) json_encode($this->entityManager()->getConnection()->fetchAllAssociative('SELECT * FROM audit_log'));
    }
}
