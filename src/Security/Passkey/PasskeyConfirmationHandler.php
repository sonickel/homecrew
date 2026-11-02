<?php

declare(strict_types=1);

namespace App\Security\Passkey;

use App\Entity\AppUser;
use App\Repository\WebauthnCredentialRepository;
use App\Service\Account\RecentConfirmation;
use Doctrine\ORM\EntityManagerInterface;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\ClockInterface;
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
 * Fresh confirmation with a passkey ("Mein Konto"). The bundle has checked the signature of the passkey
 * named in the answer; here it must also belong to the person who is logged in – on a shared device the
 * browser may offer passkeys of others.
 */
final readonly class PasskeyConfirmationHandler implements SuccessHandler, FailureHandler
{
    public const string FAILED_MESSAGE = 'Die Bestätigung mit dem Passkey hat nicht geklappt. Versuch es noch einmal oder bestätige mit deinem Passwort.';
    public const string FOREIGN_MESSAGE = 'Dieser Passkey gehört nicht zu dir. Nimm deinen eigenen Passkey oder bestätige mit deinem Passwort.';

    public function __construct(
        private Security $security,
        private WebauthnCredentialRepository $credentialRepository,
        private RecentConfirmation $recentConfirmation,
        private UrlGeneratorInterface $urlGenerator,
        private ClockInterface $clock,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function onSuccess(
        Request $request,
        ?PublicKeyCredential $publicKeyCredential = null,
        ?PublicKeyCredentialOptions $publicKeyCredentialOptions = null,
        ?PublicKeyCredentialUserEntity $userEntity = null,
    ): Response {
        $user = $this->security->getUser();
        $credential = $this->credentialRepository->findOneByCredentialId(self::rawId($request));
        if (!$user instanceof AppUser || null === $credential) {
            return $this->onFailure($request);
        }
        if ($credential->getUser()->getId() !== $user->getId()) {
            return new JsonResponse(['status' => 'error', 'message' => self::FOREIGN_MESSAGE], Response::HTTP_FORBIDDEN);
        }

        $credential->recordUse($credential->getSignCount(), $this->clock->now());
        $this->entityManager->flush();
        $this->recentConfirmation->confirm();

        return new JsonResponse(['status' => 'ok', 'redirect' => $this->urlGenerator->generate(RecentConfirmation::target($request->query->getString('weiter')))]);
    }

    public function onFailure(Request $request, ?\Throwable $exception = null): Response
    {
        return new JsonResponse(['status' => 'error', 'message' => self::FAILED_MESSAGE], Response::HTTP_BAD_REQUEST);
    }

    private static function rawId(Request $request): string
    {
        $data = json_decode($request->getContent(), true);
        $rawId = \is_array($data) && \is_string($data['rawId'] ?? null) ? $data['rawId'] : '';

        try {
            return Base64UrlSafe::decodeNoPadding(rtrim($rawId, '='));
        } catch (\RangeException) {
            return '';
        }
    }
}
