<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\AppUser;
use App\Security\Permission;
use App\Security\Pin\PinLoginToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * "Mein Konto" after a PIN login at the shared tablet (decision 02.11.): only the PIN, display settings and the
 * privacy notice. Password, second factor, passkeys and recovery codes need a full login – a PIN is no
 * confirmation (App\Service\Account\RecentConfirmation) and the tablet is used by everybody.
 *
 * @extends Voter<string, mixed>
 */
final class AccountVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [Permission::ACCOUNT_LOGIN_SETTINGS, Permission::PIN_CHANGE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if (!$token->getUser() instanceof AppUser) {
            return false;
        }

        $pinLogin = $token instanceof PinLoginToken;
        if (Permission::ACCOUNT_LOGIN_SETTINGS === $attribute && $pinLogin) {
            $vote?->addReason('Login data can only be changed after a full login.');
        }

        return Permission::PIN_CHANGE === $attribute ? $pinLogin : !$pinLogin;
    }
}
