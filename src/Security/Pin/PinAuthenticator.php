<?php

declare(strict_types=1);

namespace App\Security\Pin;

use App\Entity\AppUser;
use App\Repository\AppUserRepository;
use App\Service\Tablet\PinLogin;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Handles the PIN form of a name tile (/tablet/personen/{publicId}) – checks in App\Service\Tablet\PinLogin.
 * Success gives a PinLoginToken (person + tablet, never admin rights); a failure goes back to the PIN form with the
 * reason. The CSRF token is checked first, so a foreign page cannot use up a person's attempts.
 */
final class PinAuthenticator extends AbstractAuthenticator
{
    public const string ROUTE = 'app_tablet_pin';
    public const string CSRF_ID = 'pin-login';
    private const string DEVICE = 'device_id';

    public function __construct(
        private readonly PinLogin $pinLogin,
        private readonly AppUserRepository $userRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod('POST') && self::ROUTE === $request->attributes->get('_route');
    }

    public function authenticate(Request $request): Passport
    {
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_ID, $request->getPayload()->getString('_token')))) {
            throw new InvalidCsrfTokenException('Invalid CSRF token.');
        }

        $device = $this->pinLogin->deviceFor($request);
        if (null === $device) {
            throw new PinLoginException('An diesem Gerät ist keine Anmeldung mit PIN möglich.');
        }
        $person = $this->userRepository->findOneByPublicId((string) $request->attributes->get('publicId'));
        if (null === $person) {
            throw new PinLoginException('Diese Person gibt es hier nicht. Tippe auf deinen Namen.');
        }

        $this->pinLogin->verify($person, $request->getPayload()->getString('pin'), $device);

        $passport = new SelfValidatingPassport(new UserBadge($person->getUserIdentifier(), static fn (): AppUser => $person));
        $passport->setAttribute(self::DEVICE, $device->getId());

        return $passport;
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        $person = $passport->getUser();
        \assert($person instanceof AppUser);
        $deviceId = $passport->getAttribute(self::DEVICE);
        \assert(\is_int($deviceId));

        return new PinLoginToken($person, $firewallName, $person->getRoles(), $deviceId);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): Response
    {
        // Open steps (new PIN, privacy notice) follow via App\EventSubscriber\AccountSetupSubscriber
        return new RedirectResponse($this->urlGenerator->generate('app_home'), Response::HTTP_SEE_OTHER);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $session = $request->getSession();
        if ($session instanceof FlashBagAwareSessionInterface) {
            $session->getFlashBag()->add('error', $exception instanceof PinLoginException || $exception instanceof CustomUserMessageAccountStatusException
                ? $exception->getMessageKey()
                : 'Das hat nicht geklappt. Bitte tippe noch einmal auf deinen Namen.');
        }

        return new RedirectResponse(
            $this->urlGenerator->generate(self::ROUTE, ['publicId' => (string) $request->attributes->get('publicId')]),
            Response::HTTP_SEE_OTHER,
        );
    }
}
