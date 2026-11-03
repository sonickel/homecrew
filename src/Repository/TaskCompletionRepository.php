<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskCompletion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskCompletion>
 */
final class TaskCompletionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TaskCompletion::class);
    }

    public function findForTask(Task $task): ?TaskCompletion
    {
        return $this->findOneBy(['task' => $task]);
    }

    /**
     * Removes the person from completions older than the given time and marks them as anonymized;
     * the tablet stays (it is no person). Returns the number of completions handled.
     */
    public function anonymizeBefore(\DateTimeImmutable $before, \DateTimeImmutable $now): int
    {
        return (int) $this->createQueryBuilder('c')
            ->update()
            ->set('c.user', ':nobody')
            ->set('c.anonymizedAt', ':now')
            ->andWhere('c.anonymizedAt IS NULL')
            ->andWhere('c.doneAt < :before')
            ->setParameter('nobody', null)
            ->setParameter('now', $now)
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
