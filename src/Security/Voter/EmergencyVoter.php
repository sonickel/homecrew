<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Security\Permission;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * The emergency page (konzept-3 §2): every logged-in person (also by PIN) and the paired tablet in every mode –
 * so emergency numbers are reachable without PIN. Contacts "nur Admins" additionally need Permission::ADMIN.
 *
 * @extends Voter<string, mixed>
 */
final class EmergencyVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return Permission::EMERGENCY_VIEW === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        // Not while the second login step is still open
        if ($token instanceof TwoFactorTokenInterface) {
            return false;
        }
        $user = $token->getUser();

        return $user instanceof Device || $user instanceof AppUser;
    }
}
