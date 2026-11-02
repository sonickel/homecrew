<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AppUser;
use App\Security\Pin\PinLoginToken;
use App\Service\Account\AccountSetup;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Sends a logged-in person to the next mandatory step (App\Service\Account\AccountSetup) –
 * runs after the firewall (priority 8), so the token is known.
 */
final readonly class AccountSetupSubscriber
{
    // The emergency button stays usable during the steps (konzept-12 §5: always visible)
    private const array ALWAYS_ALLOWED = ['app_logout', 'app_emergency', '_wdt', '_profiler'];

    public function __construct(
        private Security $security,
        private AccountSetup $accountSetup,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[AsEventListener(priority: 4)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof AppUser || !$this->security->isGranted('IS_AUTHENTICATED_FULLY')) {
            return;
        }

        $step = $this->accountSetup->nextStep($user, $this->security->getToken() instanceof PinLoginToken);
        $route = (string) $event->getRequest()->attributes->get('_route', '');
        if (null === $step || $this->accountSetup->belongsToStep($step, $route) || \in_array($route, self::ALWAYS_ALLOWED, true)) {
            return;
        }

        $url = $this->urlGenerator->generate($step);
        // Turbo and fetch calls follow a 303 with GET
        $event->setResponse(new RedirectResponse($url, Response::HTTP_SEE_OTHER));
    }
}
