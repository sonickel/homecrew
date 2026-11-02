<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Enum\InvitationStatus;
use App\Enum\UserRole;
use App\Message\PurgeInvitations;
use App\MessageHandler\PurgeInvitationsHandler;
use App\Repository\InvitationRepository;
use App\Service\Account\InvitationManager;
use App\Service\Account\PersonAccess;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Invitations by QR code: 72 hours, once, a new one replaces the open one, only the hash is stored.
 */
final class InvitationManagerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;

    private AppUser $admin;
    private AppUser $papa;

    protected function setUp(): void
    {
        self::bootKernel();
        self::mockTime('2026-11-02 10:00:00 UTC');
        $this->admin = $this->createPerson(UserRole::Manager, 'max');
        $this->papa = $this->createPerson(UserRole::Angehoerige, 'papa', password: null);
    }

    public function testCodeIsValidFor72HoursAndStoredOnlyAsHash(): void
    {
        ['code' => $code, 'invitation' => $invitation] = $this->manager()->create($this->papa, $this->admin);

        self::assertSame('2026-11-05 10:00:00', $invitation->getExpiresAt()->format('Y-m-d H:i:s'));
        self::assertSame(hash('sha256', $code), $this->entityManager()->getConnection()->fetchOne('SELECT token_hash FROM invitation'));
        // Typed with spaces and in lower case
        $typed = strtolower(implode(' ', str_split($code, 4)));
        self::assertSame(InvitationStatus::Gueltig, $this->manager()->status($this->manager()->findByCode($typed)));

        self::mockTime('2026-11-05 10:00:00 UTC');
        self::assertSame(InvitationStatus::Abgelaufen, $this->manager()->status($this->manager()->findByCode($code)));
    }

    public function testUnknownCode(): void
    {
        self::assertSame(InvitationStatus::Unbekannt, $this->manager()->status($this->manager()->findByCode('ABCD')));
        self::assertSame(InvitationStatus::Unbekannt, $this->manager()->status($this->manager()->findByCode('')));
    }

    public function testRedeemingSetsThePasswordOnce(): void
    {
        ['code' => $code] = $this->manager()->create($this->papa, $this->admin);
        $invitation = $this->manager()->findByCode($code);
        self::assertNotNull($invitation);

        $person = $this->manager()->redeem($invitation, 'Gartenzaun-Laterne-7');

        self::assertSame($this->papa, $person);
        self::assertFalse($person->isPasswordMustChange());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($person, 'Gartenzaun-Laterne-7'));
        self::assertSame(InvitationStatus::Benutzt, $this->manager()->status($invitation));
        self::assertSame(['einladung.erstellen', 'einladung.einloesen'], $this->loggedActions());

        $this->expectException(\DomainException::class);
        $this->manager()->redeem($invitation, 'Noch-ein-Passwort-8');
    }

    public function testAnInvitationIsRedeemedOnlyOnceEvenAtTheSameMoment(): void
    {
        ['code' => $code] = $this->manager()->create($this->papa, $this->admin);
        $invitation = $this->manager()->findByCode($code);
        self::assertNotNull($invitation);
        // Another request redeemed it between our check and our save
        self::assertTrue(static::getContainer()->get(InvitationRepository::class)->claim($invitation, new \DateTimeImmutable()));

        try {
            $this->manager()->redeem($invitation, 'Gartenzaun-Laterne-7');
            self::fail('Die Einladung wurde zweimal eingelöst.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('schon benutzt', $exception->getMessage());
        }
        self::assertFalse($this->papa->hasPassword());
    }

    public function testANewInvitationReplacesTheOpenOne(): void
    {
        ['code' => $first] = $this->manager()->create($this->papa, $this->admin);
        ['code' => $second] = $this->manager()->create($this->papa, $this->admin);

        self::assertSame(InvitationStatus::Unbekannt, $this->manager()->status($this->manager()->findByCode($first)));
        self::assertSame(InvitationStatus::Gueltig, $this->manager()->status($this->manager()->findByCode($second)));
        self::assertSame(1, static::getContainer()->get(InvitationRepository::class)->count([]));
    }

    public function testLockedPersonCannotRedeem(): void
    {
        ['code' => $code] = $this->manager()->create($this->papa, $this->admin);
        static::getContainer()->get(PersonAccess::class)->lock($this->papa, $this->admin);

        self::assertSame(InvitationStatus::Gesperrt, $this->manager()->status($this->manager()->findByCode($code)));
    }

    public function testRetentionJobDeletesExpiredInvitations(): void
    {
        $this->manager()->create($this->papa, $this->admin);
        $handler = static::getContainer()->get(PurgeInvitationsHandler::class);

        self::mockTime('2026-11-05 09:59:59 UTC');
        self::assertSame(0, $handler(new PurgeInvitations()));
        self::mockTime('2026-11-05 10:00:00 UTC');
        self::assertSame(1, $handler(new PurgeInvitations()));
    }

    private function manager(): InvitationManager
    {
        return static::getContainer()->get(InvitationManager::class);
    }
}
