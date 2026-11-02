<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AppUser;
use App\Security\Permission;
use App\Service\Account\RecentConfirmation;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * A passkey is a complete login. Adding one – also the first – needs a fresh confirmation
 * (App\Service\Account\RecentConfirmation): someone at an unattended open session could otherwise
 * give themselves permanent access. After a PIN login at the shared tablet neither adding nor confirming
 * with a passkey is possible (the endpoints belong to the WebAuthn bundle, so the check lives here).
 */
final readonly class PasskeyRegistrationGuard
{
    public const array ROUTES = ['webauthn.controller.creation.request.add_passkey', 'webauthn.controller.creation.response.add_passkey'];
    public const array CONFIRM_ROUTES = ['webauthn.controller.request.request.confirm', 'webauthn.controller.request.response.confirm'];

    public function __construct(
        private Security $security,
        private RecentConfirmation $recentConfirmation,
    ) {
    }

    #[AsEventListener(priority: 4)]
    public function onKernelRequest(RequestEvent $event): void
    {
        $route = $event->getRequest()->attributes->get('_route');
        $adding = \in_array($route, self::ROUTES, true);
        if (!$event->isMainRequest() || (!$adding && !\in_array($route, self::CONFIRM_ROUTES, true))) {
            return;
        }

        if (!$this->security->isGranted(Permission::ACCOUNT_LOGIN_SETTINGS)) {
            throw new AccessDeniedHttpException('Passkeys need a full login.');
        }
        $user = $this->security->getUser();
        if ($adding && (!$user instanceof AppUser || $this->recentConfirmation->isRequiredFor($user))) {
            throw new AccessDeniedHttpException('A passkey can only be added after a fresh confirmation.');
        }
    }
}
