<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AppUser;
use App\Security\Device\DeviceAuthenticator;
use App\Security\Device\DeviceToken;
use App\Security\Pin\PinLoginToken;
use App\Service\Tablet\DeviceCookie;
use App\Service\Tablet\HomeNetwork;
use App\Service\Tablet\PinLogin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Re-checks a tablet session on every request (konzept-12 §5), right after the firewall:
 * - the team tablet only while its cookie is sent and the request comes from the home network;
 * - a PIN login only while a new PIN login would work just as well: same tablet (paired, cookie sent, mode with PIN login),
 *   from the home network (with "PIN nur im Heimnetz"), PIN login still on for the person – and the same PIN.
 * Otherwise the session ends. Also notes "last seen" (at most every 5 minutes) and renews the cookie then;
 * a logout at the tablet leads back to its start page.
 */
final readonly class TabletRequestSubscriber
{
    private const string RENEW_COOKIE = '_hc_device_renew';
    private const int LAST_SEEN_STEP_SECONDS = 300;

    public function __construct(
        private Security $security,
        private DeviceCookie $deviceCookie,
        private HomeNetwork $homeNetwork,
        private PinLogin $pinLogin,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        private ClockInterface $clock,
    ) {
    }

    #[AsEventListener(priority: 7)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $token = $this->security->getToken();
        $device = $this->deviceCookie->pairedDevice($request);
        $atHome = $this->homeNetwork->contains($request);

        $ended = match (true) {
            $token instanceof DeviceToken => !$atHome || $device?->getId() !== $token->getDevice()->getId(),
            $token instanceof PinLoginToken => $this->pinLoginEnded($token, $request),
            default => false,
        };
        if ($ended) {
            $this->security->logout(false);
            // Outside the home network the login page explains why the tablet shows nothing
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate($atHome ? 'app_home' : 'app_login'), Response::HTTP_SEE_OTHER));

            return;
        }

        $now = $this->clock->now();
        $lastSeen = $device?->getLastSeenAt();
        if (null !== $device && (null === $lastSeen || $now->getTimestamp() - $lastSeen->getTimestamp() >= self::LAST_SEEN_STEP_SECONDS)) {
            $device->touch($now);
            $this->entityManager->flush();
            $request->attributes->set(self::RENEW_COOKIE, true);
        }
    }

    /**
     * Also ends the login when an admin switches the PIN login off, creates a new start PIN or sets the tablet to "Team".
     */
    private function pinLoginEnded(PinLoginToken $token, Request $request): bool
    {
        $person = $token->getUser();

        return $this->pinLogin->deviceFor($request)?->getId() !== $token->getDeviceId()
            || !$person instanceof AppUser
            || !$this->pinLogin->canUse($person)
            || !$token->hasSamePin($person);
    }

    /**
     * Logging out at a paired tablet leads back to its team view (or name tiles), not to the login page.
     * Runs after Symfony's default logout listener (priority 64) and replaces its redirect.
     */
    #[AsEventListener]
    public function onLogout(LogoutEvent $event): void
    {
        if (null !== $this->deviceCookie->pairedDevice($event->getRequest())) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_home'), Response::HTTP_SEE_OTHER));
        }
    }

    #[AsEventListener]
    public function onKernelResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest()) {
            return;
        }

        if ($request->attributes->getBoolean(DeviceAuthenticator::CLEAR_COOKIE)) {
            $event->getResponse()->headers->setCookie($this->deviceCookie->clear($request));
        } elseif ($request->attributes->getBoolean(self::RENEW_COOKIE) && null !== $token = $this->deviceCookie->token($request)) {
            $event->getResponse()->headers->setCookie($this->deviceCookie->create($token, $request));
        }
    }
}
