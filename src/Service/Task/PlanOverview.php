<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\TaskTemplate;
use App\Repository\TaskCompletionRepository;
use App\Repository\TaskRepository;
use App\Repository\TaskTemplateRepository;
use App\Service\Household\HouseholdContext;
use App\Service\Viewer\Viewer;

/**
 * "Routinen & Wartung" (konzept-2 §5.6): the plans the viewer may see by urgency, ended ones apart; who did it
 * last only for admins.
 */
final readonly class PlanOverview
{
    public function __construct(
        private TaskTemplateRepository $planRepository,
        private TaskRepository $taskRepository,
        private TaskCompletionRepository $completionRepository,
        private HouseholdContext $household,
    ) {
    }

    /**
     * @return array{current: list<PlanRow>, ended: list<PlanRow>}
     */
    public function forViewer(Viewer $viewer): array
    {
        $plans = $this->planRepository->findVisibleTo($viewer);
        $rows = $this->rows($plans, $viewer);
        usort($rows, static fn (PlanRow $a, PlanRow $b): int => [$a->state->rank(), $a->next?->getDueDate()->format('Y-m-d') ?? '9999-12-31', mb_strtolower($a->plan->getTitle())]
            <=> [$b->state->rank(), $b->next?->getDueDate()->format('Y-m-d') ?? '9999-12-31', mb_strtolower($b->plan->getTitle())]);

        return [
            'current' => array_values(array_filter($rows, static fn (PlanRow $row): bool => PlanState::Beendet !== $row->state)),
            'ended' => array_values(array_filter($rows, static fn (PlanRow $row): bool => PlanState::Beendet === $row->state)),
        ];
    }

    public function row(TaskTemplate $plan, Viewer $viewer): PlanRow
    {
        return $this->rows([$plan], $viewer)[0];
    }

    /**
     * For the admins' hint on "Heute"; 0 for everybody else.
     */
    public function countOverdue(Viewer $viewer): int
    {
        return $viewer->isAdmin() ? $this->planRepository->countOverdueVisibleTo($viewer, $this->today()) : 0;
    }

    /**
     * @param list<TaskTemplate> $plans
     *
     * @return list<PlanRow>
     */
    private function rows(array $plans, Viewer $viewer): array
    {
        $today = $this->today();
        $next = $this->taskRepository->findFirstOpenOccurrences($plans);
        $lastDone = $viewer->isAdmin() ? $this->completionRepository->findLastDoneOfPlans($plans) : [];

        return array_map(static function (TaskTemplate $plan) use ($next, $lastDone, $today): PlanRow {
            $id = (int) $plan->getId();

            return new PlanRow($plan, $next[$id] ?? null, PlanState::of($plan, $next[$id] ?? null, $today), $lastDone[$id] ?? null);
        }, $plans);
    }

    private function today(): string
    {
        return $this->household->today()->format('Y-m-d');
    }
}
