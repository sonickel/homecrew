<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Ends a personal login after SESSION_IDLE_MINUTES without any request (decision 01.11.: 8 hours).
 * Runs right after the firewall, before the account setup steps.
 */
final readonly class SessionIdleSubscriber
{
    public const string LAST_SEEN = '_hc_last_seen';

    public function __construct(
        private Security $security,
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
        if (!$event->isMainRequest() || !$request->hasPreviousSession() || null === $this->security->getToken()) {
            return;
        }

        $session = $request->getSession();
        $now = $this->clock->now()->getTimestamp();
        $lastSeen = $session->get(self::LAST_SEEN);
        if (\is_int($lastSeen) && $now - $lastSeen > $this->idleMinutes * 60) {
            $this->security->logout(false);
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login', ['grund' => 'inaktiv'])));

            return;
        }

        $session->set(self::LAST_SEEN, $now);
    }
}
