<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\AppUser;
use App\Service\Household\HouseholdContext;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Blocked persons and persons whose employment ended cannot log in (konzept-1 §5 "Austritt").
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
        if (!$user instanceof AppUser) {
            return;
        }

        $activeUntil = $user->getActiveUntil();
        $left = null !== $activeUntil && $activeUntil->format('Y-m-d') < $this->household->today()->format('Y-m-d');
        if (null !== $user->getDisabledAt() || $left) {
            throw new CustomUserMessageAccountStatusException(self::BLOCKED_MESSAGE);
        }
    }

    public function checkPostAuth(UserInterface $user, mixed $token = null): void
    {
    }
}
