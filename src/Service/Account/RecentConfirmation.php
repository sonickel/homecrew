<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\AppUser;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Webauthn\Bundle\Security\Http\Authenticator\WebauthnAuthenticator;

/**
 * Changing how one logs in (password, second factor, passkeys, recovery codes) needs a fresh confirmation:
 * the person proved within the last minutes that it is them – at the login or on /konto/bestaetigen.
 * Someone at an open, unattended session can therefore not take the account over.
 */
final readonly class RecentConfirmation
{
    public const int VALID_MINUTES = 5;
    private const string SESSION_KEY = '_hc_confirmed_at';
    /** Pages that may ask for a confirmation and are opened again afterwards (route names without parameters). */
    private const array TARGETS = ['app_account', 'app_account_password', 'app_account_recovery_codes', 'app_account_passkey_add', 'app_account_two_factor_app', 'app_account_two_factor_passkey'];

    /**
     * Where to go after the confirmation: only known pages, never an address from outside.
     */
    public static function target(string $route): string
    {
        return \in_array($route, self::TARGETS, true) ? $route : 'app_account';
    }

    public function __construct(
        private RequestStack $requestStack,
        private ClockInterface $clock,
    ) {
    }

    public function confirm(): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, $this->clock->now()->getTimestamp());
    }

    /**
     * Whether a change of how this person logs in must wait for a confirmation. Also for the first second factor
     * or passkey: at an open session somebody else could otherwise make their own phone the key to the account.
     * Right after a login nothing is asked again (the login counts as confirmation).
     */
    public function isRequiredFor(AppUser $user): bool
    {
        return ($user->hasPassword() || $user->hasTwoFactor()) && !$this->isFresh();
    }

    public function isFresh(): bool
    {
        $validUntil = $this->validUntil();

        return null !== $validUntil && $this->clock->now() < $validUntil;
    }

    public function validUntil(): ?\DateTimeImmutable
    {
        $session = $this->requestStack->getMainRequest()?->hasSession() ? $this->requestStack->getSession() : null;
        $confirmedAt = $session?->get(self::SESSION_KEY);

        return \is_int($confirmedAt)
            ? $this->clock->now()->setTimestamp($confirmedAt + self::VALID_MINUTES * 60)
            : null;
    }

    /**
     * A complete personal login (password + second step, or passkey) counts as confirmation –
     * never other ways in such as a tablet PIN (M4).
     */
    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $authenticator = $event->getAuthenticator();
        $personalLogin = $authenticator instanceof FormLoginAuthenticator || $authenticator instanceof TwoFactorAuthenticator || $authenticator instanceof WebauthnAuthenticator;
        if ($personalLogin && !$event->getAuthenticatedToken() instanceof TwoFactorTokenInterface && $event->getRequest()->hasSession()) {
            $event->getRequest()->getSession()->set(self::SESSION_KEY, $this->clock->now()->getTimestamp());
        }
    }
}
