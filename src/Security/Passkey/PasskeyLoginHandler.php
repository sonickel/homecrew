<?php

declare(strict_types=1);

namespace App\Security\Passkey;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Webauthn\Bundle\Security\Handler\FailureHandler;

/**
 * Answers of the passkey login as JSON for assets/controllers/passkey_controller.js:
 * where to go after success, or a message in plain words.
 */
final readonly class PasskeyLoginHandler implements AuthenticationSuccessHandlerInterface, AuthenticationFailureHandlerInterface, FailureHandler
{
    use TargetPathTrait;

    public const string FAILED_MESSAGE = 'Die Anmeldung mit dem Passkey hat nicht geklappt. Versuch es noch einmal oder melde dich mit Benutzername und Passwort an.';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $target = $request->hasSession() ? $this->getTargetPath($request->getSession(), 'main') : null;
        if ($request->hasSession()) {
            $this->removeTargetPath($request->getSession(), 'main');
        }

        return new JsonResponse(['status' => 'ok', 'redirect' => $target ?? $this->urlGenerator->generate('app_home')]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->onFailure($request, $exception);
    }

    public function onFailure(Request $request, ?\Throwable $exception = null): Response
    {
        // Only messages written for people are shown; technical details stay in the log of the bundle
        $message = $exception instanceof CustomUserMessageAccountStatusException ? $exception->getMessageKey() : self::FAILED_MESSAGE;

        return new JsonResponse(['status' => 'error', 'message' => $message], Response::HTTP_UNAUTHORIZED);
    }
}
