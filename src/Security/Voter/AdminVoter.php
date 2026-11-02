<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AppUser;
use App\Security\Permission;
use App\Security\TwoFactor\TwoFactorPolicy;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Admin rights (konzept-1 §2, konzept-11 §1): only for a person with the admin flag who is fully logged in –
 * never while the second step is still open, never via tablet PIN or team tablet (M4) – and, when the
 * policy demands it, has a second factor.
 *
 * @extends Voter<string, mixed>
 */
final class AdminVoter extends Voter
{
    public function __construct(
        private readonly TwoFactorPolicy $twoFactorPolicy,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return Permission::ADMIN === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($token instanceof TwoFactorTokenInterface) {
            $vote?->addReason('The second login step is not finished.');

            return false;
        }

        $user = $token->getUser();
        if (!$user instanceof AppUser || !$user->isAdmin() || null !== $user->getDisabledAt()) {
            return false;
        }

        if ($this->twoFactorPolicy->isRequiredFor($user) && !$user->hasTwoFactor()) {
            $vote?->addReason('Admins need a second factor.');

            return false;
        }

        return true;
    }
}
