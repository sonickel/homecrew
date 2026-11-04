<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskTemplate;
use App\Service\Viewer\Viewer;
use App\Service\Visibility\VisibilityRules;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TaskTemplate>
 */
final class TaskTemplateRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly VisibilityRules $rules,
    ) {
        parent::__construct($registry, TaskTemplate::class);
    }

    /**
     * Every plan the viewer may see ("Routinen & Wartung"), by title.
     *
     * @return list<TaskTemplate>
     */
    public function findVisibleTo(Viewer $viewer): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('assignee')
            ->leftJoin('p.assigneeUser', 'assignee')
            ->orderBy('p.title')
            ->addOrderBy('p.id');
        $this->rules->whereVisible($qb, 'p', $viewer);

        /** @var list<TaskTemplate> $plans */
        $plans = $qb->getQuery()->getResult();

        return $plans;
    }

    /**
     * Plans the viewer may see with an occurrence past its due date ("Routinen & Wartung: 2 überfällig").
     *
     * @param string $today household date Y-m-d
     */
    public function countOverdueVisibleTo(Viewer $viewer, string $today): int
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(DISTINCT p.id)')
            ->join(Task::class, 't', 'WITH', 't.template = p')
            ->andWhere("t.status = 'offen'")
            ->andWhere('t.dueDate < :today')
            ->setParameter('today', $today);
        $this->rules->whereVisible($qb, 'p', $viewer);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
