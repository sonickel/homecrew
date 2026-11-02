<?php

declare(strict_types=1);

namespace App\Security\TwoFactor;

use App\Entity\AppUser;
use App\Enum\AuditAction;
use App\Service\Audit\AuditLogger;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvent;
use Scheb\TwoFactorBundle\Security\TwoFactor\Event\TwoFactorAuthenticationEvents;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;

/**
 * At most 5 attempts at the second step per person within 15 minutes (codes from the app and recovery codes).
 * Throwing during the attempt makes scheb show the form again with the waiting time.
 */
final readonly class TwoFactorThrottling
{
    public function __construct(
        #[Autowire(service: 'limiter.two_factor')]
        private RateLimiterFactoryInterface $limiterFactory,
        private AuditLogger $auditLogger,
    ) {
    }

    #[AsEventListener(TwoFactorAuthenticationEvents::ATTEMPT)]
    public function onAttempt(TwoFactorAuthenticationEvent $event): void
    {
        $token = $event->getToken();
        $limit = $this->limiterFactory->create($token->getUserIdentifier())->consume();
        if ($limit->isAccepted()) {
            return;
        }

        // The login form is not involved here, so App\EventSubscriber\LoginAuditSubscriber cannot see the lock
        $user = $token->getUser();
        if ($user instanceof AppUser) {
            $this->auditLogger->log(AuditAction::AnmeldungGesperrt, $user, null, ['schritt' => 'code']);
        }

        // The rate limiter works with the system time, so the waiting time is computed with it as well
        $minutes = (int) ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60);

        throw new TooManyLoginAttemptsAuthenticationException(max(1, $minutes));
    }

    #[AsEventListener(TwoFactorAuthenticationEvents::COMPLETE)]
    public function onComplete(TwoFactorAuthenticationEvent $event): void
    {
        $this->limiterFactory->create($event->getToken()->getUserIdentifier())->reset();
    }
}
