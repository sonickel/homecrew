<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppUser;
use App\Entity\WebauthnCredential;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<WebauthnCredential>
 */
final class WebauthnCredentialRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WebauthnCredential::class);
    }

    public function findOneByCredentialId(string $credentialId): ?WebauthnCredential
    {
        return $this->findOneBy(['credentialId' => $credentialId]);
    }

    /**
     * @return list<WebauthnCredential>
     */
    public function findForUser(AppUser $user): array
    {
        return $this->findBy(['user' => $user], ['id' => 'ASC']);
    }

    public function findNewestForUser(AppUser $user): ?WebauthnCredential
    {
        return $this->findOneBy(['user' => $user], ['id' => 'DESC']);
    }

    public function deleteForUser(AppUser $user): int
    {
        return $this->createQueryBuilder('c')
            ->delete()
            ->where('c.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
