<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
final class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * Retention job: removes entries logged before the given point in time (UTC).
     */
    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdAt < :before')
            ->setParameter('before', $before->setTimezone(new \DateTimeZone('UTC')), 'datetime_immutable')
            ->getQuery()
            ->execute();
    }
}
