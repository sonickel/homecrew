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
 * Second step after the password for persons with an authenticator app: the 6-digit code
 * (recovery codes are accepted by scheb/2fa-backup-code before this provider is asked).
 */
#[AutoconfigureTag('scheb_two_factor.provider', ['alias' => self::NAME])]
final readonly class AuthenticatorAppProvider implements TwoFactorProviderInterface
{
    public const string NAME = 'app';

    public function __construct(
        private AuthenticatorApp $authenticatorApp,
        private TwoFactorFormRenderer $formRenderer,
    ) {
    }

    public function beginAuthentication(AuthenticationContextInterface $context): bool
    {
        $user = $context->getUser();

        return $user instanceof AppUser && TwoFactorMethod::Totp === $user->getTwofaMethod();
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
        return $user instanceof AppUser && $this->authenticatorApp->verifyForUser($user, $authenticationCode);
    }

    public function getFormRenderer(): TwoFactorFormRendererInterface
    {
        return $this->formRenderer;
    }
}
