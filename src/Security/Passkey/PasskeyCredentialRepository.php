<?php

declare(strict_types=1);

namespace App\Security\Passkey;

use App\Entity\WebauthnCredential;
use App\Repository\WebauthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Uid\NilUuid;
use Symfony\Component\Uid\Uuid;
use Webauthn\Bundle\Repository\CanSaveCredentialRecord;
use Webauthn\Bundle\Repository\CredentialRecordRepositoryInterface;
use Webauthn\CredentialRecord;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Passkeys in webauthn_credential. The table keeps only what checking a login needs; the rest of the
 * library's credential record is fixed: type "public-key" and attestation "none" (no attestation is requested).
 */
final readonly class PasskeyCredentialRepository implements CredentialRecordRepositoryInterface, CanSaveCredentialRecord
{
    public function __construct(
        private WebauthnCredentialRepository $credentialRepository,
        private PasskeyUserRepository $userRepository,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return list<CredentialRecord>
     */
    public function findAllForUserEntity(PublicKeyCredentialUserEntity $publicKeyCredentialUserEntity): array
    {
        $user = $this->userRepository->findPerson($publicKeyCredentialUserEntity->id);
        if (null === $user) {
            return [];
        }

        return array_map(self::record(...), $this->credentialRepository->findForUser($user));
    }

    public function findOneByCredentialId(string $publicKeyCredentialId): ?CredentialRecord
    {
        $credential = $this->credentialRepository->findOneByCredentialId($publicKeyCredentialId);

        return null === $credential ? null : self::record($credential);
    }

    /**
     * Called after a registration (new passkey) and after every login (new signature counter).
     */
    public function saveCredentialRecord(CredentialRecord $credentialRecord): void
    {
        $credential = $this->credentialRepository->findOneByCredentialId($credentialRecord->publicKeyCredentialId);
        if (null !== $credential) {
            $credential->recordUse($credentialRecord->counter, $this->clock->now());
        } else {
            $user = $this->userRepository->findPerson($credentialRecord->userHandle)
                ?? throw new \RuntimeException('The passkey belongs to no known person.');
            $aaguid = $credentialRecord->aaguid->toBinary();
            $credential = new WebauthnCredential(
                $user,
                $credentialRecord->publicKeyCredentialId,
                $credentialRecord->credentialPublicKey,
                $credentialRecord->counter,
                str_repeat("\0", 16) === $aaguid ? null : $aaguid,
                array_values($credentialRecord->transports),
            );
            $this->entityManager->persist($credential);
        }

        $this->entityManager->flush();
    }

    private static function record(WebauthnCredential $credential): CredentialRecord
    {
        return CredentialRecord::create(
            $credential->getCredentialId(),
            PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
            $credential->getTransports(),
            'none',
            EmptyTrustPath::create(),
            null === $credential->getAaguid() ? new NilUuid() : Uuid::fromBinary($credential->getAaguid()),
            $credential->getPublicKey(),
            $credential->getUser()->getPublicId()->toBinary(),
            $credential->getSignCount(),
        );
    }
}
