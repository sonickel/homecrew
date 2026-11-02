<?php

declare(strict_types=1);

namespace App\Security\TwoFactor;

use App\Entity\AppUser;
use App\Enum\TwoFactorMethod;
use Scheb\TwoFactorBundle\Security\TwoFactor\AuthenticationContextInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorFormRendererInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\TwoFactorProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Persons whose second factor is a passkey log in with the passkey (a complete login on its own).
 * After a password they must give a recovery code – without this provider the password alone would do.
 * Valid recovery codes are accepted by scheb/2fa-backup-code before this provider is asked, so it rejects everything.
 */
#[AutoconfigureTag('scheb_two_factor.provider', ['alias' => self::NAME])]
final readonly class PasskeyRecoveryProvider implements TwoFactorProviderInterface
{
    public const string NAME = 'passkey';

    public function __construct(
        private TwoFactorFormRenderer $formRenderer,
    ) {
    }

    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();

        return $user instanceof AppUser && TwoFactorMethod::Passkey === $user->getTwofaMethod();
    }

    public function needsPreparation(): bool
    {
        return false;
    }

    public function prepareAuthentication(object $user): void
    {
    }

    public function validateAuthenticationCode(object $user, string $authenticationCode): bool
    {
        return false;
    }

    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this->formRenderer;
    }
}
