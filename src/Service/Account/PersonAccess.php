<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\AppUser;
use App\Enum\AuditAction;
use App\Repository\InvitationRepository;
use App\Repository\WebauthnCredentialRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Household\HouseholdContext;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * What an admin does with another person's access (konzept-1 §4–5, konzept-11 §2). Rights are checked
 * before (App\Security\Voter\PersonVoter); every change is saved together with its audit log entry –
 * never with a password, PIN or code.
 */
final readonly class PersonAccess
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private AccessCodeGenerator $codeGenerator,
        private WebauthnCredentialRepository $credentialRepository,
        private InvitationRepository $invitationRepository,
        private AuditLogger $auditLogger,
        private HouseholdContext $household,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Replaces the password and withdraws an open invitation.
     *
     * @return string the start password, shown once on the access card
     */
    public function setStartPassword(AppUser $person, AppUser $admin): string
    {
        $this->assertActive($person);
        $password = $this->codeGenerator->startPassword();
        $person->changePassword($this->passwordHasher->hashPassword($person, $password), mustChange: true);
        // An open invitation would overwrite the start password later (e.g. a lost access card)
        $this->invitationRepository->deleteForUser($person);
        $this->auditLogger->log(AuditAction::StartPasswort, $admin, $person);

        return $password;
    }

    /**
     * @return string the start PIN, shown once on the access card
     */
    public function setStartPin(AppUser $person, AppUser $admin): string
    {
        $this->assertActive($person);
        $pin = $this->codeGenerator->startPin();
        $person->changePin($this->passwordHasher->hashPassword($person, $pin), mustChange: true);
        $this->auditLogger->log(AuditAction::StartPin, $admin, $person);

        return $pin;
    }

    public function setPinLogin(AppUser $person, bool $enabled, AppUser $admin): void
    {
        $this->assertActive($person);
        $person->enablePinLogin($enabled);
        $this->auditLogger->log(AuditAction::PinAnmeldung, $admin, $person, ['an' => $enabled]);
    }

    /**
     * Authenticator app, recovery codes and all passkeys – for a lost phone. An admin sets it up again at the next login.
     */
    public function resetTwoFactor(AppUser $person, AppUser $admin): void
    {
        $person->resetTwoFactor();
        $removedPasskeys = $this->credentialRepository->deleteForUser($person);
        $this->auditLogger->log(AuditAction::ZweifaktorZuruecksetzen, $admin, $person, ['passkeys_entfernt' => $removedPasskeys]);
    }

    public function lock(AppUser $person, AppUser $admin): void
    {
        $person->lock($this->clock->now());
        $this->auditLogger->log(AuditAction::PersonSperren, $admin, $person);
    }

    public function unlock(AppUser $person, AppUser $admin): void
    {
        $person->unlock();
        $this->auditLogger->log(AuditAction::PersonEntsperren, $admin, $person);
    }

    /**
     * The person stays (anchor for working times); every way to log in is removed at once.
     *
     * @throws \DomainException with a message for the page when the date is not possible
     */
    public function leave(AppUser $person, \DateTimeImmutable $lastDay, AppUser $admin): void
    {
        $lastDay = new \DateTimeImmutable($lastDay->format('Y-m-d')); // plain date like the DATE column
        if ($lastDay->format('Y-m-d') > $this->household->today()->format('Y-m-d')) {
            throw new \DomainException('Den Austritt trägst du am letzten Arbeitstag oder danach ein, nicht im Voraus.');
        }
        if ($lastDay->format('Y-m-d') < $person->getActiveFrom()->format('Y-m-d')) {
            throw new \DomainException('Der letzte Arbeitstag kann nicht vor dem Eintritt liegen.');
        }

        $person->leave($lastDay);
        $person->removeAccess();
        $removedPasskeys = $this->credentialRepository->deleteForUser($person);
        $this->invitationRepository->deleteForUser($person);
        $this->auditLogger->log(AuditAction::PersonAustritt, $admin, $person, [
            'letzter_tag' => $lastDay->format('Y-m-d'),
            'passkeys_entfernt' => $removedPasskeys,
        ]);
    }

    /**
     * For a mistake: the person is active again, but without access – the admin sets it up anew.
     */
    public function undoLeave(AppUser $person, AppUser $admin): void
    {
        $person->undoLeave();
        $this->auditLogger->log(AuditAction::PersonAustrittZuruecknehmen, $admin, $person);
    }

    private function assertActive(AppUser $person): void
    {
        if ($person->hasLeft()) {
            throw new \DomainException('Die Person ist ausgetreten. Nimm zuerst den Austritt zurück.');
        }
    }
}
