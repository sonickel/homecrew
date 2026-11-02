<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AppUser;
use App\Enum\AuditAction;
use App\Enum\AuditVia;
use App\Repository\DeviceRepository;
use App\Security\Pin\PinLoginToken;
use App\Service\Audit\AuditLogger;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\BackupCodeEvents;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorCodeEvent;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Webauthn\Bundle\Security\Http\Authenticator\WebauthnAuthenticator;

/**
 * Logins in the audit log: success with method, failures and lockouts only for existing persons
 * (never the entered text – a mistyped password in the username field must not end up in the log).
 * PIN logins carry the tablet; wrong PINs are logged by App\Service\Tablet\PinLogin.
 */
final readonly class LoginAuditSubscriber
{
    public function __construct(
        private AuditLogger $auditLogger,
        private DeviceRepository $deviceRepository,
        private ClockInterface $clock,
    ) {
    }

    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();
        // A password login of a person with a second factor is only complete after the second step
        if (!$user instanceof AppUser || $event->getAuthenticatedToken() instanceof TwoFactorTokenInterface) {
            return;
        }

        $user->recordLogin($this->clock->now());
        $token = $event->getAuthenticatedToken();
        if ($token instanceof PinLoginToken) {
            $this->auditLogger->log(AuditAction::AnmeldungErfolg, $user, null, ['methode' => 'pin'], AuditVia::Tablet, $this->deviceRepository->find($token->getDeviceId()));

            return;
        }
        $this->auditLogger->log(AuditAction::AnmeldungErfolg, $user, null, [
            'methode' => $event->getAuthenticator() instanceof WebauthnAuthenticator ? 'passkey' : 'passwort',
            'zweiter_schritt' => $event->getAuthenticator() instanceof TwoFactorAuthenticator,
        ]);
    }

    #[AsEventListener]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $user = $this->knownPerson($event);
        if (null === $user) {
            return;
        }

        $exception = $event->getException();
        $this->auditLogger->log(
            $exception instanceof TooManyLoginAttemptsAuthenticationException ? AuditAction::AnmeldungGesperrt : AuditAction::AnmeldungFehlschlag,
            $user,
            null,
            ['schritt' => $event->getAuthenticator() instanceof TwoFactorAuthenticator ? 'code' : 'passwort'],
        );
    }

    #[AsEventListener(BackupCodeEvents::VALID)]
    public function onRecoveryCodeUsed(TwoFactorCodeEvent $event): void
    {
        $user = $event->getUser();
        if ($user instanceof AppUser) {
            $this->auditLogger->log(AuditAction::ZweifaktorErsatzcode, $user, null, ['verbleibend' => $user->countRecoveryCodes() - 1]);
        }
    }

    private function knownPerson(LoginFailureEvent $event): ?AppUser
    {
        $passport = $event->getPassport();
        if (null === $passport || !$passport->hasBadge(UserBadge::class)) {
            return null;
        }

        try {
            $user = $passport->getUser();
        } catch (AuthenticationException) {
            return null; // unknown username
        }

        return $user instanceof AppUser ? $user : null;
    }
}
