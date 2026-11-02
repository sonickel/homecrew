<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\AppUser;
use App\Service\Household\HouseholdContext;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Locked persons and persons whose last working day is over cannot log in (konzept-1 §5 "Austritt").
 * Running sessions end via App\EventSubscriber\AccountStatusSubscriber with the same rule.
 */
final readonly class UserChecker implements UserCheckerInterface
{
    public const string BLOCKED_MESSAGE = 'Dein Zugang ist gesperrt. Bitte wende dich an einen Admin.';

    public function __construct(
        private HouseholdContext $household,
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if ($user instanceof AppUser && $user->isBlockedOn($this->household->today())) {
            throw new CustomUserMessageAccountStatusException(self::BLOCKED_MESSAGE);
        }
    }

    public function checkPostAuth(UserInterface $user, mixed $token = null): void
    {
    }
}
