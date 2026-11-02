<?php

declare(strict_types=1);

namespace App\Security\Passkey;

use App\Entity\AppUser;
use App\Repository\AppUserRepository;
use Symfony\Component\Uid\Uuid;
use Webauthn\Bundle\Repository\PublicKeyCredentialUserEntityRepositoryInterface;
use Webauthn\PublicKeyCredentialUserEntity;

/**
 * Persons as WebAuthn user entities. The user handle stored in the passkey is the public id –
 * never the internal id, so the authenticator learns nothing about the database.
 */
final readonly class PasskeyUserRepository implements PublicKeyCredentialUserEntityRepositoryInterface
{
    public function __construct(
        private AppUserRepository $userRepository,
    ) {
    }

    public function findOneByUsername(string $username): ?PublicKeyCredentialUserEntity
    {
        $user = $this->userRepository->findOneBy(['username' => $username]);

        return null === $user ? null : self::userEntity($user);
    }

    public function findOneByUserHandle(string $userHandle): ?PublicKeyCredentialUserEntity
    {
        $user = $this->findPerson($userHandle);

        return null === $user ? null : self::userEntity($user);
    }

    public function findPerson(string $userHandle): ?AppUser
    {
        if (16 !== \strlen($userHandle)) {
            return null;
        }

        return $this->userRepository->findOneBy(['publicId' => Uuid::fromBinary($userHandle)]);
    }

    public static function userEntity(AppUser $user): PublicKeyCredentialUserEntity
    {
        return PublicKeyCredentialUserEntity::create($user->getUsername(), $user->getPublicId()->toBinary(), $user->getDisplayName());
    }
}
