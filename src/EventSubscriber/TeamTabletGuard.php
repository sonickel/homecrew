<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Security\Attribute\TeamTabletAllowed;
use App\Security\Device\DeviceToken;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ControllerArgumentsEvent;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * The team tablet (no personal login) only reaches controllers marked with #[TeamTabletAllowed] –
 * an allow list instead of hoping every new page checks for the tablet (review M4) – and the few routes
 * of bundles it needs.
 */
final readonly class TeamTabletGuard
{
    /** Passkey login for the "Admin-Anmeldung" at the tablet (konzept-12 §5). */
    public const array BUNDLE_ROUTES = [
        'webauthn.controller.security.main.request.options',
        'webauthn.controller.security.main.request.result',
    ];

    public function __construct(
        private Security $security,
    ) {
    }

    #[AsEventListener]
    public function onKernelControllerArguments(ControllerArgumentsEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->security->getToken() instanceof DeviceToken) {
            return;
        }

        if ([] === $event->getAttributes(TeamTabletAllowed::class)
            && !\in_array($event->getRequest()->attributes->get('_route'), self::BUNDLE_ROUTES, true)) {
            throw new AccessDeniedException('This page is not meant for the team tablet.');
        }
    }
}
