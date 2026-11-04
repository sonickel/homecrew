<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppUser;
use App\Enum\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<AppUser>
 */
final class AppUserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AppUser::class);
    }

    /**
     * The assisted person – there is at most one (unique index on manager_marker).
     */
    public function findManager(): ?AppUser
    {
        return $this->findOneBy(['role' => UserRole::Manager]);
    }

    public function findOneByPublicId(string $publicId): ?AppUser
    {
        return Uuid::isValid($publicId) ? $this->findOneBy(['publicId' => Uuid::fromString($publicId)]) : null;
    }

    /**
     * Persons who can read notifications: not locked, not left and with a way to log in (password, PIN login or
     * passkey). Nobody else gets notifications – they would never be read.
     *
     * @return list<AppUser>
     */
    public function findReachable(\DateTimeImmutable $today): array
    {
        /** @var list<AppUser> $persons */
        $persons = $this->createQueryBuilder('u')
            ->andWhere('u.disabledAt IS NULL')
            ->andWhere('u.activeUntil IS NULL OR u.activeUntil >= :today')
            ->andWhere('u.passwordHash IS NOT NULL OR u.pinLoginEnabled = true OR EXISTS (SELECT w.id FROM App\Entity\WebauthnCredential w WHERE w.user = u)')
            ->setParameter('today', $today->format('Y-m-d'))
            ->orderBy('u.displayName')
            ->getQuery()
            ->getResult();

        return $persons;
    }

    /**
     * Name tiles at the tablet: persons with PIN login switched on who may log in today, by name.
     *
     * @return list<AppUser>
     */
    public function findForPinLogin(\DateTimeImmutable $today): array
    {
        /** @var list<AppUser> $persons */
        $persons = $this->createQueryBuilder('u')
            ->andWhere('u.pinLoginEnabled = true')
            ->andWhere('u.disabledAt IS NULL')
            ->andWhere('u.activeUntil IS NULL OR u.activeUntil >= :today')
            ->setParameter('today', $today->format('Y-m-d'))
            ->orderBy('u.displayName')
            ->getQuery()
            ->getResult();

        return $persons;
    }
}
