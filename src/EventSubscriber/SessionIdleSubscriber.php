<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Security\Device\DeviceToken;
use App\Service\Tablet\TabletContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Ends a personal login after SESSION_IDLE_MINUTES without any request (decision 01.11.: 8 hours).
 * At a paired tablet much earlier (App\Service\Tablet\TabletContext::personIdleSeconds()) – and then the tablet
 * goes back to its team view instead of the login page. The team tablet itself never times out.
 * Runs right after the firewall, before the account setup steps.
 */
final readonly class SessionIdleSubscriber
{
    public const string LAST_SEEN = '_hc_last_seen';

    public function __construct(
        private Security $security,
        private TabletContext $tabletContext,
        private ClockInterface $clock,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%env(int:SESSION_IDLE_MINUTES)%')]
        private int $idleMinutes,
    ) {
    }

    #[AsEventListener(priority: 6)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $token = $this->security->getToken();
        if (!$event->isMainRequest() || !$request->hasPreviousSession() || null === $token || $token instanceof DeviceToken) {
            return;
        }

        $session = $request->getSession();
        $now = $this->clock->now()->getTimestamp();
        $lastSeen = $session->get(self::LAST_SEEN);
        $tabletSeconds = $this->tabletContext->personIdleSeconds();
        if (\is_int($lastSeen) && $now - $lastSeen > ($tabletSeconds ?? $this->idleMinutes * 60)) {
            $this->security->logout(false);
            $event->setResponse(new RedirectResponse(null === $tabletSeconds
                ? $this->urlGenerator->generate('app_login', ['grund' => 'inaktiv'])
                : $this->urlGenerator->generate('app_home')));

            return;
        }

        $session->set(self::LAST_SEEN, $now);
    }

    /**
     * A new login starts its own idle time (a PIN login must not inherit the age of the team view before it).
     */
    #[AsEventListener]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        if ($request->hasSession()) {
            $request->getSession()->set(self::LAST_SEEN, $this->clock->now()->getTimestamp());
        }
    }
}
