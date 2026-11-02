<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AppUser;
use App\Security\Device\DeviceToken;
use App\Security\Permission;
use App\Service\Tablet\PinLogin;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * What a paired tablet offers without personal login (konzept-12 §2):
 * - TEAM_VIEW: the team tablet in mode "Team" or "Beides" (the team tablet exists only in the home network);
 * - PIN_LOGIN: name tiles and PIN in mode "Persönlich" or "Beides" – in the home network, or anywhere when an admin
 *   switched off "PIN-Anmeldung nur im Heimnetz" (decision 02.11.; the team view stays home-only).
 *
 * @extends Voter<string, mixed>
 */
final class TabletVoter extends Voter
{
    public function __construct(
        private readonly PinLogin $pinLogin,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return Permission::TEAM_VIEW === $attribute || (Permission::PIN_LOGIN === $attribute && $subject instanceof Request);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (Permission::TEAM_VIEW === $attribute) {
            return $token instanceof DeviceToken && $token->getDevice()->getMode()->showsTeamView();
        }

        \assert($subject instanceof Request);
        if ($token->getUser() instanceof AppUser) {
            $vote?->addReason('A person is logged in already.');

            return false;
        }

        return null !== $this->pinLogin->deviceFor($subject);
    }
}
