<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use App\Entity\TaskCompletion;
use App\Entity\TaskTemplate;
use App\Enum\CompletionAction;
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
     * Every open task the viewer may see, also one-off tasks showing later ("Offene Aufgaben") – occurrences of plans
     * only once they show; what comes next is under "Routinen & Wartung".
     *
     * @param string $today household date Y-m-d
     *
     * @return list<Task>
     */
    public function findOpenVisibleTo(Viewer $viewer, string $today): array
    {
        $qb = $this->openVisibleTo($viewer)
            ->andWhere('t.template IS NULL OR t.visibleFrom IS NULL OR t.visibleFrom <= :today')
            ->setParameter('today', $today);

        return $this->result($qb);
    }

    /**
     * Open occurrences of a plan by their original day (App\Service\Task\PlanSync).
     *
     * @return list<Task>
     */
    public function findOpenOccurrences(TaskTemplate $plan): array
    {
        /** @var list<Task> $tasks */
        $tasks = $this->createQueryBuilder('t')
            ->andWhere('t.template = :plan')
            ->andWhere("t.status = 'offen'")
            ->setParameter('plan', $plan)
            ->orderBy('t.occurrenceDate')
            ->addOrderBy('t.id')
            ->getQuery()
            ->getResult();

        return $tasks;
    }

    /**
     * The open occurrence due first – the one "Jetzt erledigt" closes.
     */
    public function findFirstOpenOccurrence(TaskTemplate $plan): ?Task
    {
        return $this->findFirstOpenOccurrences([$plan])[(int) $plan->getId()] ?? null;
    }

    /**
     * The open occurrence due first of each of these plans, by plan id.
     *
     * @param list<TaskTemplate> $plans
     *
     * @return array<int, Task>
     */
    public function findFirstOpenOccurrences(array $plans): array
    {
        if ([] === $plans) {
            return [];
        }

        /** @var list<Task> $tasks */
        $tasks = $this->createQueryBuilder('t')
            ->andWhere('t.template IN (:plans)')
            ->andWhere("t.status = 'offen'")
            ->setParameter('plans', $plans)
            ->orderBy('t.dueDate', 'DESC')
            ->addOrderBy('t.occurrenceDate', 'DESC')
            ->getQuery()
            ->getResult();

        // Latest first, so the earliest of each plan is written last
        $first = [];
        foreach ($tasks as $task) {
            $first[(int) $task->getTemplate()?->getId()] = $task;
        }

        return $first;
    }

    /**
     * Original days of all occurrences (whatever their status) from a day on.
     *
     * @param string $from household date Y-m-d
     *
     * @return list<string> Y-m-d
     */
    public function findOccurrenceDaysFrom(TaskTemplate $plan, string $from): array
    {
        /** @var list<string> $days a column result comes back as the database writes it (Y-m-d) */
        $days = $this->createQueryBuilder('t')
            ->select('t.occurrenceDate')
            ->andWhere('t.template = :plan')
            ->andWhere('t.occurrenceDate >= :from')
            ->setParameter('plan', $plan)
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleColumnResult();

        return $days;
    }

    /**
     * Original days of the closed occurrences from a day on (e.g. done early with "Jetzt erledigt").
     *
     * @param string $from household date Y-m-d
     *
     * @return list<string> Y-m-d
     */
    public function findClosedOccurrenceDaysFrom(TaskTemplate $plan, string $from): array
    {
        /** @var list<string> $days a column result comes back as the database writes it (Y-m-d) */
        $days = $this->createQueryBuilder('t')
            ->select('t.occurrenceDate')
            ->andWhere('t.template = :plan')
            ->andWhere('t.occurrenceDate >= :from')
            ->andWhere("t.status <> 'offen'")
            ->setParameter('plan', $plan)
            ->setParameter('from', $from)
            ->getQuery()
            ->getSingleColumnResult();

        return $days;
    }

    /**
     * The latest original day among the occurrences due by a day – an open occurrence before it is superseded.
     *
     * @param string $day household date Y-m-d
     *
     * @return string|null Y-m-d
     */
    public function findLatestOccurrenceDueBy(TaskTemplate $plan, string $day): ?string
    {
        return $this->day($this->createQueryBuilder('t')
            ->select('MAX(t.occurrenceDate)')
            ->andWhere('t.template = :plan')
            ->andWhere('t.dueDate <= :day')
            ->setParameter('plan', $plan)
            ->setParameter('day', $day)
            ->getQuery()
            ->getSingleScalarResult());
    }

    /**
     * @return string|null Y-m-d
     */
    public function findLatestOccurrenceDay(TaskTemplate $plan): ?string
    {
        return $this->day($this->createQueryBuilder('t')
            ->select('MAX(t.occurrenceDate)')
            ->andWhere('t.template = :plan')
            ->setParameter('plan', $plan)
            ->getQuery()
            ->getSingleScalarResult());
    }

    /**
     * @return string|null Y-m-d
     */
    public function findEarliestOpenDueDate(TaskTemplate $plan): ?string
    {
        return $this->day($this->createQueryBuilder('t')
            ->select('MIN(t.dueDate)')
            ->andWhere('t.template = :plan')
            ->andWhere("t.status = 'offen'")
            ->setParameter('plan', $plan)
            ->getQuery()
            ->getSingleScalarResult());
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

    /**
     * Closed occurrences of plans whose completion is older than the given time – except the latest $keepLast
     * of each plan and its latest "erledigt" (retention job; "zuletzt erledigt" and the history stay).
     */
    public function deleteClosedOccurrencesBefore(\DateTimeImmutable $before, int $keepLast): int
    {
        /** @var list<array{task: int|string, plan: int|string, action: CompletionAction, doneAt: \DateTimeImmutable}> $closed */
        $closed = $this->getEntityManager()->createQueryBuilder()
            ->select('IDENTITY(c.task) AS task', 'IDENTITY(t.template) AS plan', 'c.action', 'c.doneAt')
            ->from(TaskCompletion::class, 'c')
            ->join('c.task', 't')
            ->andWhere('t.template IS NOT NULL')
            ->andWhere("t.status <> 'offen'")
            ->orderBy('t.template')
            ->addOrderBy('c.doneAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $ids = [];
        $kept = [];
        $lastDoneKept = [];
        foreach ($closed as $row) {
            $plan = (int) $row['plan'];
            $kept[$plan] = ($kept[$plan] ?? 0) + 1;
            $lastDone = CompletionAction::Erledigt === $row['action'] && !isset($lastDoneKept[$plan]);
            if ($lastDone) {
                $lastDoneKept[$plan] = true;
            }
            if (!$lastDone && $kept[$plan] > $keepLast && $row['doneAt'] < $before) {
                $ids[] = (int) $row['task'];
            }
        }
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
        // With the plan and the checklist for "Zuletzt: …" and "Checkliste: 3 Punkte" in the rows
        $qb = $this->createQueryBuilder('t')
            ->addSelect('assignee', 'plan', 'items')
            ->leftJoin('t.assigneeUser', 'assignee')
            ->leftJoin('t.template', 'plan')
            ->leftJoin('t.items', 'items')
            ->andWhere("t.status = 'offen'");
        $this->rules->whereVisible($qb, 't', $viewer);

        return $qb;
    }

    private function day(mixed $value): ?string
    {
        return \is_string($value) && '' !== $value ? substr($value, 0, 10) : null;
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
