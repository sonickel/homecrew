<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskCompletion;
use App\Entity\TaskTemplate;
use App\Enum\CompletionAction;
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
     * When an occurrence of the plan was closed last – with this action only, or with any.
     */
    public function findLastClosedAt(TaskTemplate $plan, ?CompletionAction $action = null): ?\DateTimeImmutable
    {
        $qb = $this->createQueryBuilder('c')
            ->select('MAX(c.doneAt)')
            ->join('c.task', 't')
            ->andWhere('t.template = :plan')
            ->setParameter('plan', $plan);
        if (null !== $action) {
            $qb->andWhere('c.action = :action')->setParameter('action', $action->value);
        }
        $doneAt = $qb->getQuery()->getSingleScalarResult();

        return \is_string($doneAt) ? new \DateTimeImmutable($doneAt, new \DateTimeZone('UTC')) : null;
    }

    /**
     * Every completion of the plan that still exists, newest first ("Verlauf").
     *
     * @return list<TaskCompletion>
     */
    public function findHistory(TaskTemplate $plan): array
    {
        /** @var list<TaskCompletion> $completions */
        $completions = $this->createQueryBuilder('c')
            ->addSelect('t', 'r', 'u', 'd')
            ->join('c.task', 't')
            ->leftJoin('c.results', 'r')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.device', 'd')
            ->andWhere('t.template = :plan')
            ->setParameter('plan', $plan)
            ->orderBy('c.doneAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $completions;
    }

    /**
     * The latest "erledigt" of each of these plans, by plan id ("Zuletzt erledigt" with the person, for admins).
     *
     * @param list<TaskTemplate> $plans
     *
     * @return array<int, TaskCompletion>
     */
    public function findLastDoneOfPlans(array $plans): array
    {
        if ([] === $plans) {
            return [];
        }

        /** @var list<TaskCompletion> $completions */
        $completions = $this->createQueryBuilder('c')
            ->addSelect('t', 'u', 'd')
            ->join('c.task', 't')
            ->leftJoin('c.user', 'u')
            ->leftJoin('c.device', 'd')
            ->andWhere('t.template IN (:plans)')
            ->andWhere("c.action = 'erledigt'")
            ->setParameter('plans', $plans)
            ->orderBy('c.doneAt')
            ->addOrderBy('c.id')
            ->getQuery()
            ->getResult();

        $last = [];
        foreach ($completions as $completion) {
            $plan = $completion->getTask()->getTemplate();
            \assert(null !== $plan && null !== $plan->getId());
            $last[$plan->getId()] = $completion;
        }

        return $last;
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
