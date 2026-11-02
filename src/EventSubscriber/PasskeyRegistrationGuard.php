<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AppUser;
use App\Service\Account\RecentConfirmation;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A passkey is a complete login. Adding one – also the first – needs a fresh confirmation
 * (App\Service\Account\RecentConfirmation): someone at an unattended open session could otherwise
 * give themselves permanent access.
 */
final readonly class PasskeyRegistrationGuard
{
    public const array ROUTES = ['webauthn.controller.creation.request.add_passkey', 'webauthn.controller.creation.response.add_passkey'];

    public function __construct(
        private Security $security,
        private RecentConfirmation $recentConfirmation,
    ) {
    }

    #[AsEventListener(priority: 4)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !\in_array($event->getRequest()->attributes->get('_route'), self::ROUTES, true)) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof AppUser || $this->recentConfirmation->isRequiredFor($user)) {
            throw new AccessDeniedHttpException('A passkey can only be added after a fresh confirmation.');
        }
    }
}
