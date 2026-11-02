<?php

declare(strict_types=1);

namespace App\Security\Passkey;

use App\Entity\AppUser;
use App\Enum\AuditAction;
use App\Enum\TwoFactorMethod;
use App\Repository\WebauthnCredentialRepository;
use App\Service\Account\AccountSetup;
use App\Service\Audit\AuditLogger;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Webauthn\Bundle\Security\Handler\FailureHandler;
use Webauthn\Bundle\Security\Handler\SuccessHandler;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialOptions;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * After a passkey was added: give it the name the person typed, make it the second factor
 * (if there is none yet), log it and tell the page where to continue.
 */
final readonly class PasskeyRegisteredHandler implements SuccessHandler, FailureHandler
{
    public const string FAILED_MESSAGE = 'Der Passkey konnte nicht gespeichert werden. Versuch es noch einmal.';

    public function __construct(
        private Security $security,
        private WebauthnCredentialRepository $credentialRepository,
        private AuditLogger $auditLogger,
        private AccountSetup $accountSetup,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function onSuccess(
        Request $request,
        ?PublicKeyCredential $publicKeyCredential = null,
        ?PublicKeyCredentialOptions $publicKeyCredentialOptions = null,
        ?PublicKeyCredentialUserEntity $userEntity = null,
    ): Response {
        $user = $this->security->getUser();
        $credential = $user instanceof AppUser ? $this->credentialRepository->findNewestForUser($user) : null;
        if (!$user instanceof AppUser || null === $credential) {
            return $this->onFailure($request);
        }

        $credential->setLabel(self::label($request->query->getString('bezeichnung')));
        $hadSecondFactor = $user->hasTwoFactor();
        $user->addPasskeyAsTwoFactor();

        $this->auditLogger->log(AuditAction::PasskeyHinzufuegen, $user, $credential);
        if (!$hadSecondFactor) {
            $this->auditLogger->log(AuditAction::ZweifaktorEinrichten, $user, $user, ['methode' => TwoFactorMethod::Passkey->value]);
        }

        $next = $this->accountSetup->nextStep($user) ?? ($hadSecondFactor ? 'app_account' : 'app_home');

        return new JsonResponse(['status' => 'ok', 'redirect' => $this->urlGenerator->generate($next)], Response::HTTP_CREATED);
    }

    public function onFailure(Request $request, ?\Throwable $exception = null): Response
    {
        return new JsonResponse(['status' => 'error', 'message' => self::FAILED_MESSAGE], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Free text from the page: one line, at most 100 characters, otherwise no name.
     */
    private static function label(string $input): ?string
    {
        $label = trim((string) preg_replace('/[\p{C}]+/u', ' ', $input));

        return '' === $label ? null : mb_substr($label, 0, 100);
    }
}
