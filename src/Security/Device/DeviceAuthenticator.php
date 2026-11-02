<?php

declare(strict_types=1);

namespace App\Security\Device;

use App\Entity\Device;
use App\Service\Tablet\DeviceCookie;
use App\Service\Tablet\HomeNetwork;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * The team tablet (konzept-12): a paired device in the home network is "logged in" as itself – no person,
 * only ROLE_TEAM_DEVICE. Works like remember-me: only when the session holds no login yet, and lazily.
 *
 * Outside the home network nothing happens here (a person may still log in, the login page explains the rest).
 * Only an unknown or unpaired device loses its cookie and gets a short note; any other failure (database down)
 * keeps the cookie, and the request goes on without login. The login throttling of persons does not count the
 * tablet (App\Security\LoginRateLimiter). App\EventSubscriber\TabletRequestSubscriber re-checks network and cookie
 * on every later request.
 */
final class DeviceAuthenticator extends AbstractAuthenticator
{
    public const string CLEAR_COOKIE = '_hc_device_clear';
    /** Set while this authenticator works on the request (from authenticate() to the end of the login events). */
    public const string IN_PROGRESS = '_hc_device_login';
    public const string UNPAIRED_MESSAGE = 'Dieses Tablet ist nicht mehr gekoppelt. Ein Admin kann es unter Verwaltung → Geräte neu koppeln.';

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly DeviceCookie $deviceCookie,
        private readonly HomeNetwork $homeNetwork,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        // Never replace a login from the session (person or tablet)
        if (null !== $this->tokenStorage->getToken()) {
            return false;
        }
        if (null === $this->deviceCookie->token($request) || !$this->homeNetwork->contains($request)) {
            return false;
        }

        // null: the firewall may stay lazy
        return null;
    }

    public function authenticate(Request $request): Passport
    {
        $request->attributes->set(self::IN_PROGRESS, true);
        try {
            $device = $this->deviceCookie->lookUp($request);
        } catch (DbalException $exception) {
            throw new AuthenticationServiceException('The device could not be looked up.', 0, $exception);
        }
        if (null === $device || $device->isRevoked()) {
            throw new DeviceUnpairedException();
        }

        return new SelfValidatingPassport(new UserBadge($device->getUserIdentifier(), static fn (): Device => $device));
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $device = $passport->getUser();

        return new DeviceToken($device, $firewallName, $device->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        if (!$exception instanceof DeviceUnpairedException) {
            return null;
        }

        $request->attributes->set(self::CLEAR_COOKIE, true);
        $session = $request->hasSession() ? $request->getSession() : null;
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('info', self::UNPAIRED_MESSAGE);
        }

        return null;
    }

    /**
     * After the login events (the throttling listener runs at priority 0): the request is done with the tablet.
     * The throttling notes the tablet's identifier as "last username" – the login form must not show "geraet-1".
     */
    #[AsEventListener(priority: -64)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        if ($event->getAuthenticator() instanceof self) {
            self::finish($event->getRequest());
        }
    }

    #[AsEventListener(priority: -64)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        if ($event->getAuthenticator() instanceof self) {
            self::finish($event->getRequest());
        }
    }

    private static function finish(Request $request): void
    {
        $request->attributes->remove(self::IN_PROGRESS);
        $request->attributes->remove(SecurityRequestAttributes::LAST_USERNAME);
    }
}
