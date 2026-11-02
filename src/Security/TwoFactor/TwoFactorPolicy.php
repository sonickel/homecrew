<?php

declare(strict_types=1);

namespace App\Security\TwoFactor;

use App\Entity\AppUser;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Whether admins must set up a second factor before they get admin rights (konzept-1 §2).
 * For now switched off on request (decision 01.11.): ADMIN_2FA_REQUIRED=1 activates it – before real use.
 */
final readonly class TwoFactorPolicy
{
    public function __construct(
        #[Autowire('%env(bool:ADMIN_2FA_REQUIRED)%')]
        private bool $requiredForAdmins,
    ) {
    }

    public function isRequiredFor(AppUser $user): bool
    {
        return $this->requiredForAdmins && $user->isAdmin();
    }
}
