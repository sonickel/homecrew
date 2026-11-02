<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AppUser;
use App\Security\Permission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Managing persons ("Verwaltung → Personen", konzept-11 §2) – only admins (App\Security\Voter\AdminVoter), and:
 * - the manager's entry is changed by the manager only; nobody else resets, locks or ends the manager's access
 *   (the way back is the recovery codes or app:manager:reset-2fa, konzept-1 §5);
 * - nobody manages their own access here – that is "Mein Konto" – so no admin locks themselves out.
 *
 * @extends Voter<string, AppUser>
 */
final class PersonVoter extends Voter
{
    public function __construct(
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [Permission::PERSON_EDIT, Permission::PERSON_ACCESS], true) && $subject instanceof AppUser;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof AppUser || !$this->accessDecisionManager->decide($token, [Permission::ADMIN])) {
            return false;
        }

        $self = $user === $subject || (null !== $user->getId() && $user->getId() === $subject->getId());
        if ($subject->isManager() && !$self) {
            $vote?->addReason('Only the manager changes the manager.');

            return false;
        }
        if (Permission::PERSON_ACCESS === $attribute && $self) {
            $vote?->addReason('The own access is managed in "Mein Konto".');

            return false;
        }

        return true;
    }
}
