<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\Task;
use App\Entity\TaskCompletion;
use App\Enum\CompletionAction;
use App\Enum\Visibility;
use App\Form\Task\TaskData;
use App\Repository\TaskCompletionRepository;
use App\Service\Viewer\Viewer;
use App\Service\Visibility\VisibilityRules;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * What people do with tasks (konzept-2 §5, decisions M5). Completions and hand-overs carry the person –
 * or the team tablet instead of a person. The callers check the rights (App\Security\Voter\TaskVoter).
 *
 * Occurrences of a plan (M6): a change one by one marks them (is_modified); afterwards App\Service\Task\PlanSync
 * brings the plan up to date (next occurrence, "zuletzt erledigt") – under a lock on the plan, taken before the
 * lock on the task as in PlanSync.
 */
final readonly class TaskActions
{
    /** "Rückgängig" shows for 30 seconds; the server accepts it a little longer for slow connections. */
    public const int UNDO_VISIBLE_SECONDS = 30;
    public const int UNDO_ACCEPTED_SECONDS = 40;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskCompletionRepository $completionRepository,
        private VisibilityRules $rules,
        private PlanSync $planSync,
        private ClockInterface $clock,
    ) {
    }

    public function create(TaskData $data, Viewer $viewer): Task
    {
        \assert(null !== $data->dueDate && null !== $data->assignee);
        $task = new Task((string) $data->title, $data->category, $data->dueDate, $viewer->getPerson());
        $this->assertMayChooseVisibility($task, $data, $viewer);
        $this->apply($task, $data);
        $assignee = $this->assigneeFor($task, $data);
        $task->assign($assignee->person, $assignee->role);

        $this->entityManager->persist($task);
        $this->entityManager->flush();

        return $task;
    }

    /**
     * A changed responsibility counts as handing on (who and when is noted). False when the task was closed meanwhile.
     */
    public function update(Task $task, TaskData $data, Viewer $viewer): bool
    {
        $this->assertMayChooseVisibility($task, $data, $viewer);

        return $this->whileOpen($task, function () use ($task, $data, $viewer): void {
            $this->apply($task, $data);
            $assignee = $this->assigneeFor($task, $data);
            if (!$assignee->equals(Assignment::of($task))) {
                $task->handOver($assignee->person, $assignee->role, $viewer->actor(), $this->clock->now());
            }
            $task->markModified();
        });
    }

    /**
     * Completes or skips the task; false when it was closed already (e.g. at the same time at another device).
     * The checklist: null = every point ticked off (one tap on "Erledigt"), else the ids of the ticked points.
     *
     * @param list<int>|null $checkedItemIds
     */
    public function close(Task $task, CompletionAction $action, Viewer $viewer, ?array $checkedItemIds = null): bool
    {
        return $this->whileOpen($task, function () use ($task, $action, $viewer, $checkedItemIds): void {
            $task->close($action);
            $completion = new TaskCompletion($task, $action, $viewer->actor(), $this->clock->now());
            if (CompletionAction::Erledigt === $action) {
                $completion->tickOff($checkedItemIds);
            }
            $this->entityManager->persist($completion);
        });
    }

    /**
     * "Rückgängig": only shortly after closing and only by the same person or tablet; false otherwise.
     */
    public function undo(Task $task, Viewer $viewer): bool
    {
        return $this->entityManager->wrapInTransaction(function () use ($task, $viewer): bool {
            $this->lock($task);
            $completion = $this->completionRepository->findForTask($task);
            if (null === $completion
                || $completion->getAction()->status() !== $task->getStatus()
                || !$completion->isBy($viewer->actor())
                || $this->clock->now()->getTimestamp() - $completion->getDoneAt()->getTimestamp() > self::UNDO_ACCEPTED_SECONDS) {
                return false;
            }

            $this->entityManager->remove($completion);
            $task->reopen();
            $this->planChanged($task);

            return true;
        });
    }

    /**
     * False when the task was closed meanwhile.
     */
    public function postpone(Task $task, \DateTimeImmutable $dueDate): bool
    {
        return $this->whileOpen($task, static fn () => $task->postpone($dueDate));
    }

    /**
     * False when the task was closed meanwhile.
     */
    public function handOver(Task $task, Assignment $to, Viewer $viewer): bool
    {
        if (Visibility::Privat === $task->getVisibility()) {
            throw new \DomainException('A private task always belongs to its creator.');
        }

        return $this->whileOpen($task, fn () => $task->handOver($to->person, $to->role, $viewer->actor(), $this->clock->now()));
    }

    /**
     * Runs a change under a row lock and only while the task is still open (another device may have closed it).
     * Refusals come back as false: an exception would close the entity manager (wrapInTransaction).
     */
    private function whileOpen(Task $task, \Closure $change): bool
    {
        return $this->entityManager->wrapInTransaction(function () use ($task, $change): bool {
            $this->lock($task);
            if (!$task->isOpen()) {
                return false;
            }
            $change();
            $this->planChanged($task);

            return true;
        });
    }

    /**
     * Locks the task – and before it its plan, in the same order as App\Service\Task\PlanSync.
     */
    private function lock(Task $task): void
    {
        $plan = $task->getTemplate();
        if (null !== $plan) {
            $this->entityManager->lock($plan, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($plan);
        }
        $this->entityManager->lock($task, LockMode::PESSIMISTIC_WRITE);
        $this->entityManager->refresh($task);
    }

    /**
     * After a change of an occurrence: the plan's next occurrence and "zuletzt erledigt".
     */
    private function planChanged(Task $task): void
    {
        $plan = $task->getTemplate();
        if (null !== $plan) {
            $this->entityManager->flush();
            $this->planSync->reconcile($plan);
        }
    }

    /**
     * Only the creator may make a task private, and only a manager or relative (never the tablet or an assistant) –
     * otherwise nobody would see it any more.
     */
    private function assertMayChooseVisibility(Task $task, TaskData $data, Viewer $viewer): void
    {
        $creator = $task->getCreatedBy();
        if (Visibility::Privat === $data->visibility && Visibility::Privat !== $task->getVisibility()
            && !($this->rules->mayKeepPrivate($viewer) && null !== $creator && $viewer->isPerson($creator))) {
            throw new \DomainException('Only its creator – a manager or relative – makes a task private.');
        }
    }

    private function apply(Task $task, TaskData $data): void
    {
        \assert(null !== $data->dueDate);
        $task->describe((string) $data->title, $data->description, $data->category, $data->estimatedMinutes, $data->important, $data->requiresManager);
        [$visibleFrom, $windowStart] = $data->showDates();
        $task->schedule($data->dueDate, $data->dueTime, $windowStart, $visibleFrom);
        $task->setVisibility($data->visibility);
    }

    /**
     * A private task always belongs to the person who created it.
     */
    private function assigneeFor(Task $task, TaskData $data): Assignment
    {
        $creator = $task->getCreatedBy();
        if (Visibility::Privat === $task->getVisibility() && null !== $creator) {
            return Assignment::person($creator);
        }
        \assert(null !== $data->assignee);

        return $data->assignee;
    }
}
