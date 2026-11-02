<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AppUser;
use App\Entity\Invitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invitation>
 */
final class InvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invitation::class);
    }

    public function findOneByTokenHash(string $tokenHash): ?Invitation
    {
        return $this->findOneBy(['tokenHash' => $tokenHash]);
    }

    /**
     * The invitation a person can still redeem (there is at most one, a new one replaces the old).
     */
    public function findOpenForUser(AppUser $user, \DateTimeImmutable $now): ?Invitation
    {
        return $this->createQueryBuilder('i')
            ->where('i.user = :user')
            ->andWhere('i.usedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Marks the invitation as used – only if nobody did that before (two redemptions at the same moment).
     */
    public function claim(Invitation $invitation, \DateTimeImmutable $now): bool
    {
        return 1 === $this->createQueryBuilder('i')
            ->update()
            ->set('i.usedAt', ':now')
            ->where('i.id = :id')
            ->andWhere('i.usedAt IS NULL')
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->setParameter('id', $invitation->getId())
            ->getQuery()
            ->execute();
    }

    public function deleteForUser(AppUser $user): int
    {
        return $this->createQueryBuilder('i')
            ->delete()
            ->where('i.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Expired invitations are useless (konzept-5 §4.3 "Reset-Links nach Ablauf").
     */
    public function deleteExpired(\DateTimeImmutable $now): int
    {
        return $this->createQueryBuilder('i')
            ->delete()
            ->where('i.expiresAt <= :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }
}
