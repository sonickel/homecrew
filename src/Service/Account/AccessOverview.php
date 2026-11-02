<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\AppUser;
use App\Entity\Invitation;
use App\Repository\InvitationRepository;
use App\Service\Household\HouseholdContext;
use Symfony\Component\Clock\ClockInterface;

/**
 * A person's access and status in plain words, for the person list and the access page.
 * Returns plain text; the caller escapes it.
 */
final readonly class AccessOverview
{
    public function __construct(
        private InvitationRepository $invitationRepository,
        private HouseholdContext $household,
        private ClockInterface $clock,
    ) {
    }

    public function summary(AppUser $person): string
    {
        $parts = [];
        if ($person->hasPassword()) {
            $parts[] = $person->isPasswordMustChange() ? 'Handy/PC (Start-Passwort)' : 'Handy/PC';
        }
        if ($person->hasPin()) {
            $parts[] = $person->isPinLoginEnabled() ? 'Tablet-PIN' : 'Tablet-PIN (aus)';
        }
        if (null !== $this->openInvitation($person)) {
            $parts[] = 'Einladung offen';
        }

        return [] === $parts ? 'Kein eigener Zugang (Team-Tablet)' : implode(', ', $parts);
    }

    public function status(AppUser $person): string
    {
        $lastDay = $person->getActiveUntil();

        return match (true) {
            null !== $lastDay => 'Ausgetreten (letzter Arbeitstag '.$lastDay->format('d.m.Y').')',
            $person->isBlockedOn($this->household->today()) => 'Gesperrt',
            default => 'Aktiv',
        };
    }

    public function openInvitation(AppUser $person): ?Invitation
    {
        return null === $person->getId() ? null : $this->invitationRepository->findOpenForUser($person, $this->clock->now());
    }
}
