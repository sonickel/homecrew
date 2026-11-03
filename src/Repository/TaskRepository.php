<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskCompletion;
use App\Service\Viewer\Viewer;
use App\Service\Visibility\VisibilityRules;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
final class TaskRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly VisibilityRules $rules,
    ) {
        parent::__construct($registry, Task::class);
    }

    /**
     * Open tasks already showing on "Heute" (visible_from reached) that are meant for the viewer.
     *
     * @param string $today household date Y-m-d
     *
     * @return list<Task>
     */
    public function findOpenForViewer(Viewer $viewer, string $today): array
    {
        $qb = $this->openVisibleTo($viewer)
            ->andWhere('t.visibleFrom IS NULL OR t.visibleFrom <= :today')
            ->setParameter('today', $today);
        $this->rules->whereForViewer($qb, 't', 'assignee', $viewer);

        return $this->result($qb);
    }

    /**
     * Open tasks already showing that are meant for the assistants – the admins' second block on "Heute".
     *
     * @return list<Task>
     */
    public function findOpenForAssistants(Viewer $viewer, string $today): array
    {
        $qb = $this->openVisibleTo($viewer)
            ->andWhere('t.visibleFrom IS NULL OR t.visibleFrom <= :today')
            ->setParameter('today', $today);
        $this->rules->whereForAssistants($qb, 't', 'assignee');

        return $this->result($qb);
    }

    /**
     * Every open task the viewer may see, also those showing later ("Offene Aufgaben").
     *
     * @return list<Task>
     */
    public function findOpenVisibleTo(Viewer $viewer): array
    {
        return $this->result($this->openVisibleTo($viewer));
    }

    /**
     * Closed one-off tasks whose completion is older than the given time (retention job).
     */
    public function deleteClosedOneOffBefore(\DateTimeImmutable $before): int
    {
        $ids = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(c.task)')
            ->from(TaskCompletion::class, 'c')
            ->join('c.task', 't')
            ->andWhere('t.template IS NULL')
            ->andWhere("t.status <> 'offen'")
            ->andWhere('c.doneAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getSingleColumnResult();
        if ([] === $ids) {
            return 0;
        }

        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->andWhere('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->execute();
    }

    private function openVisibleTo(Viewer $viewer): QueryBuilder
    {
        $qb = $this->createQueryBuilder('t')
            ->addSelect('assignee')
            ->leftJoin('t.assigneeUser', 'assignee')
            ->andWhere("t.status = 'offen'");
        $this->rules->whereVisible($qb, 't', $viewer);

        return $qb;
    }

    /**
     * @return list<Task>
     */
    private function result(QueryBuilder $qb): array
    {
        /** @var list<Task> $tasks */
        $tasks = $qb->orderBy('t.dueDate')->addOrderBy('t.id')->getQuery()->getResult();

        return $tasks;
    }
}
