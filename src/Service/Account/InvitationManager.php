<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\AppUser;
use App\Entity\Invitation;
use App\Enum\AuditAction;
use App\Enum\InvitationStatus;
use App\Repository\InvitationRepository;
use App\Service\Audit\AuditLogger;
use App\Service\Household\HouseholdContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Invitation by QR code (konzept-1 §4): valid 72 hours, once; a new invitation replaces an open one.
 * The database only knows the SHA-256 hash of the code.
 */
final readonly class InvitationManager
{
    public function __construct(
        private AccessCodeGenerator $codeGenerator,
        private InvitationRepository $invitationRepository,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private AuditLogger $auditLogger,
        private HouseholdContext $household,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array{code: string, invitation: Invitation} the plain code is shown once on the access card
     */
    public function create(AppUser $person, AppUser $admin): array
    {
        if ($person->hasLeft()) {
            throw new \DomainException('Die Person ist ausgetreten. Nimm zuerst den Austritt zurück.');
        }

        $this->invitationRepository->deleteForUser($person);
        $code = $this->codeGenerator->invitationCode();
        $invitation = new Invitation(
            $person,
            self::hash($code),
            $this->clock->now()->modify(\sprintf('+%d hours', Invitation::VALID_HOURS)),
            $admin,
        );
        $this->entityManager->persist($invitation);
        $this->auditLogger->log(AuditAction::EinladungErstellen, $admin, $person, [
            'gueltig_bis' => $invitation->getExpiresAt()->setTimezone($this->household->timezone())->format('d.m.Y H:i'),
        ]);

        return ['code' => $code, 'invitation' => $invitation];
    }

    public function findByCode(string $code): ?Invitation
    {
        $code = AccessCodeGenerator::normalizeInvitationCode($code);

        return '' === $code ? null : $this->invitationRepository->findOneByTokenHash(self::hash($code));
    }

    public function find(int $id): ?Invitation
    {
        return $this->invitationRepository->find($id);
    }

    public function status(?Invitation $invitation): InvitationStatus
    {
        return match (true) {
            null === $invitation => InvitationStatus::Unbekannt,
            null !== $invitation->getUsedAt() => InvitationStatus::Benutzt,
            $invitation->isExpired($this->clock->now()) => InvitationStatus::Abgelaufen,
            $invitation->getUser()->isBlockedOn($this->household->today()) || $invitation->getUser()->hasLeft() => InvitationStatus::Gesperrt,
            default => InvitationStatus::Gueltig,
        };
    }

    /**
     * The person sets their own password; the invitation is used up.
     *
     * @throws \DomainException when the invitation is no longer valid
     */
    public function redeem(Invitation $invitation, #[\SensitiveParameter] string $password): AppUser
    {
        $status = $this->status($invitation);
        if (InvitationStatus::Gueltig !== $status) {
            throw new \DomainException($status->message());
        }

        $now = $this->clock->now();
        if (!$this->invitationRepository->claim($invitation, $now)) {
            throw new \DomainException(InvitationStatus::Benutzt->message());
        }
        $invitation->markUsed($now);
        $person = $invitation->getUser();
        $person->changePassword($this->passwordHasher->hashPassword($person, $password));
        $this->auditLogger->log(AuditAction::EinladungEinloesen, $person, $person);

        return $person;
    }

    private static function hash(string $normalizedCode): string
    {
        return hash('sha256', $normalizedCode);
    }
}
