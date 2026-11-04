<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\Task;
use App\Entity\TaskTemplate;
use App\Enum\CompletionAction;
use App\Enum\Visibility;
use App\Repository\TaskCompletionRepository;
use App\Repository\TaskRepository;
use App\Repository\TaskTemplateRepository;
use App\Service\Household\HouseholdContext;
use App\Service\Series\OccurrenceGenerator;
use App\Service\Series\SeriesRule;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Brings the occurrences of a plan in line with the plan (konzept-2 §5.4, decisions M6) – at night, after the
 * plan was saved and after an occurrence was closed, reopened or postponed. Running it again changes nothing.
 *
 * By calendar: occurrences for the next HORIZON_DAYS days, at least the next day not closed yet. Open occurrences
 * follow the plan unless changed one by one (is_modified) – overdue ones keep their day; who may see an occurrence
 * always follows the plan. A missed one goes as soon as a later one is due, so at most one stays overdue.
 * After completion: exactly one open occurrence, due n days after the last close.
 * A paused or ended plan keeps only the occurrences changed one by one.
 *
 * Locks the plan row first, then works on its tasks – App\Service\Task\TaskActions locks in the same order.
 */
final readonly class PlanSync
{
    public const int HORIZON_DAYS = 14;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskTemplateRepository $planRepository,
        private TaskRepository $taskRepository,
        private TaskCompletionRepository $completionRepository,
        private OccurrenceGenerator $generator,
        private HouseholdContext $household,
    ) {
    }

    /**
     * In its own transaction, with the plan row locked (the plan's own changes are saved before).
     */
    public function sync(TaskTemplate $plan): void
    {
        $this->entityManager->wrapInTransaction(function () use ($plan): void {
            $this->entityManager->lock($plan, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($plan);
            $this->reconcile($plan);
        });
    }

    /**
     * Every plan – the nightly run. Returns the number of plans.
     */
    public function syncAll(): int
    {
        $plans = $this->planRepository->findAll();
        foreach ($plans as $plan) {
            $this->sync($plan);
        }

        return \count($plans);
    }

    /**
     * Within a transaction in which the caller has locked the plan already.
     */
    public function reconcile(TaskTemplate $plan): void
    {
        $today = $this->household->today()->format('Y-m-d');
        if ($plan->isByCalendar()) {
            $this->reconcileCalendar($plan, $today);
        } else {
            $this->reconcileInterval($plan, $today);
        }
        $this->entityManager->flush();

        $nextDue = $this->taskRepository->findEarliestOpenDueDate($plan);
        $plan->recordProgress(
            null === $nextDue ? null : new \DateTimeImmutable($nextDue),
            $this->completionRepository->findLastClosedAt($plan, CompletionAction::Erledigt),
        );
        $this->entityManager->flush();
    }

    private function reconcileCalendar(TaskTemplate $plan, string $today): void
    {
        $wanted = [];
        if ($plan->isActive()) {
            $rule = $this->rule($plan);
            $until = (new \DateTimeImmutable($today))->modify(\sprintf('+%d days', self::HORIZON_DAYS))->format('Y-m-d');
            foreach ($this->generator->between($rule, $today, $until) as $at) {
                $wanted[$at->format('Y-m-d')] = true;
            }
            // At least the next day not closed yet – also after "Jetzt erledigt" long before (monthly, yearly)
            $next = $this->nextDayNotClosed($plan, $rule, $today);
            if (null !== $next) {
                $wanted[$next] = true;
            }
        }

        $open = $this->taskRepository->findOpenOccurrences($plan);
        $kept = [];
        foreach ($open as $task) {
            $day = self::occurrenceDay($task);
            if ($task->isModified()) {
                // Changed one by one: keeps its state – but who may see it always follows the plan
                $this->applyVisibility($plan, $task);
                $kept[] = $task;
            } elseif ($day < $today) {
                // Overdue: keeps its day, takes over the rest
                $this->applyContent($plan, $task);
                $kept[] = $task;
            } elseif (isset($wanted[$day])) {
                $this->apply($plan, $task, $day, $this->calendarWindow($plan, $day));
                $kept[] = $task;
            } else {
                $this->entityManager->remove($task);
            }
        }
        $this->entityManager->flush();

        $existing = array_flip($this->taskRepository->findOccurrenceDaysFrom($plan, $today));
        foreach (array_keys($wanted) as $day) {
            if (!isset($existing[$day])) {
                $this->create($plan, (string) $day, (string) $day, $this->calendarWindow($plan, (string) $day));
            }
        }
        $this->entityManager->flush();

        // Missed: gone as soon as a later occurrence is due; of a paused or ended plan unless changed one by one
        $latestDue = $this->taskRepository->findLatestOccurrenceDueBy($plan, $today);
        foreach ($kept as $task) {
            if ($task->getDueDate()->format('Y-m-d') >= $today) {
                continue;
            }
            if ((null !== $latestDue && self::occurrenceDay($task) < $latestDue) || (!$task->isModified() && !$plan->runsOn($today))) {
                $this->entityManager->remove($task);
            }
        }
    }

    private function reconcileInterval(TaskTemplate $plan, string $today): void
    {
        $lastClosed = $this->completionRepository->findLastClosedAt($plan);
        $base = $lastClosed?->setTimezone($this->household->timezone())->format('Y-m-d');
        $due = $this->intervalDue($plan, $base, $today);

        // One open occurrence: the earliest (e.g. reopened by "Rückgängig"), more ones from before go
        $first = null;
        foreach ($this->taskRepository->findOpenOccurrences($plan) as $task) {
            if (null === $first) {
                $first = $task;
            } elseif (!$task->isModified()) {
                $this->entityManager->remove($task);
            }
        }

        $earliest = $plan->getEarliestAfterDays();
        $window = null !== $due && null !== $base && null !== $earliest ? self::plusDays($base, $earliest) : null;
        if (null !== $first && $first->isModified()) {
            $this->applyVisibility($plan, $first);
        }
        if (null === $due) {
            if (null !== $first && !$first->isModified()) {
                $this->entityManager->remove($first);
            }
        } elseif (null === $first) {
            // The original day only keeps occurrences apart here: never before one that exists already
            $latest = $this->taskRepository->findLatestOccurrenceDay($plan);
            $this->create($plan, null === $latest ? $due : max($due, self::plusDays($latest, 1)), $due, $window);
        } elseif (!$first->isModified()) {
            $this->apply($plan, $first, $due, $window);
        }
    }

    /**
     * The first day of the rule from today on without a closed occurrence; null when the series has none left.
     *
     * @return string|null Y-m-d
     */
    private function nextDayNotClosed(TaskTemplate $plan, SeriesRule $rule, string $today): ?string
    {
        $closed = array_flip($this->taskRepository->findClosedOccurrenceDaysFrom($plan, $today));
        $from = $today;
        // Bounded: each step skips one closed occurrence
        for ($step = 0; $step <= \count($closed); ++$step) {
            $day = $this->generator->next($rule, $from)?->format('Y-m-d');
            if (null === $day || !isset($closed[$day])) {
                return $day;
            }
            $from = self::plusDays($day, 1);
        }

        return null;
    }

    /**
     * n days after the last close (without one: the first day of the plan), after a pause that covers it or today;
     * null when the plan has ended or is paused until further notice.
     *
     * @return string|null Y-m-d
     */
    private function intervalDue(TaskTemplate $plan, ?string $base, string $today): ?string
    {
        $intervalDays = $plan->getIntervalDays();
        \assert(null !== $intervalDays);
        $due = null === $base ? $plan->getStartsOn()->format('Y-m-d') : self::plusDays($base, $intervalDays);
        if ($plan->isPausedOn($due) || $plan->isPausedOn($today)) {
            $until = $plan->getPausedUntil();
            if (null === $until) {
                return null;
            }
            $due = max($due, self::plusDays($until->format('Y-m-d'), 1));
        }

        $endsOn = $plan->getEndsOn()?->format('Y-m-d');

        return !$plan->isActive() || (null !== $endsOn && $due > $endsOn) ? null : $due;
    }

    /**
     * "Heute möglich" from window_days before the day on (none for 0).
     *
     * @return string|null Y-m-d
     */
    private function calendarWindow(TaskTemplate $plan, string $day): ?string
    {
        return 0 === $plan->getWindowDays() ? null : self::plusDays($day, -$plan->getWindowDays());
    }

    private function create(TaskTemplate $plan, string $occurrenceDay, string $due, ?string $window): void
    {
        $task = new Task($plan->getTitle(), $plan->getCategory(), new \DateTimeImmutable($due), $plan->getCreatedBy());
        $task->belongTo($plan, new \DateTimeImmutable($occurrenceDay));
        $this->apply($plan, $task, $due, $window);
        $this->entityManager->persist($task);
    }

    /**
     * The occurrence takes over the plan as it is now: what, who, who sees it, when and the checklist.
     */
    private function apply(TaskTemplate $plan, Task $task, string $due, ?string $window): void
    {
        $this->applyContent($plan, $task);
        $dueDate = new \DateTimeImmutable($due);
        $windowStart = null === $window ? null : new \DateTimeImmutable(min($window, $due));
        $task->schedule($dueDate, $plan->getDueTime(), $windowStart, $windowStart ?? $dueDate);
    }

    /**
     * Everything but the dates: what, who, who sees it and the checklist.
     */
    private function applyContent(TaskTemplate $plan, Task $task): void
    {
        $task->describe($plan->getTitle(), $plan->getDescription(), $plan->getCategory(), $plan->getEstimatedMinutes(), $plan->isImportant(), $plan->isRequiresManager());
        $task->assign($plan->getAssigneeUser(), $plan->getAssigneeRole());
        $task->setVisibility($plan->getVisibility());
        $task->copyChecklist($plan);
    }

    /**
     * Who may see an occurrence always follows its plan, also when it was changed one by one (konzept-12 §5: nothing
     * for admins only or private at the team tablet); a private one belongs to its owner.
     */
    private function applyVisibility(TaskTemplate $plan, Task $task): void
    {
        $task->setVisibility($plan->getVisibility());
        if (Visibility::Privat === $plan->getVisibility()) {
            $task->assign($plan->getAssigneeUser(), $plan->getAssigneeRole());
        }
    }

    private function rule(TaskTemplate $plan): SeriesRule
    {
        $rrule = $plan->getRrule();
        \assert(null !== $rrule);

        return new SeriesRule(
            $rrule,
            $plan->getStartsOn()->format('Y-m-d'),
            $this->household->timezone(),
            $plan->getEndsOn()?->format('Y-m-d'),
            $plan->getPausedFrom()?->format('Y-m-d'),
            $plan->getPausedUntil()?->format('Y-m-d'),
        );
    }

    private static function occurrenceDay(Task $task): string
    {
        $day = $task->getOccurrenceDate();
        \assert(null !== $day);

        return $day->format('Y-m-d');
    }

    private static function plusDays(string $day, int $days): string
    {
        return (new \DateTimeImmutable($day))->modify(\sprintf('%+d days', $days))->format('Y-m-d');
    }
}
