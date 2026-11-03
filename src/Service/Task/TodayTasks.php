<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Repository\TaskRepository;
use App\Service\Household\HouseholdContext;
use App\Service\Viewer\Viewer;

/**
 * The tasks on "Heute": what is meant for the viewer – and for admins in a separate block what the assistants
 * have to do (decision M5).
 */
final readonly class TodayTasks
{
    public function __construct(
        private TaskRepository $taskRepository,
        private HouseholdContext $household,
    ) {
    }

    public function forViewer(Viewer $viewer): TaskGroups
    {
        $today = $this->today();

        return TaskGroups::sort($this->taskRepository->findOpenForViewer($viewer, $today), $today);
    }

    /**
     * Null for everybody but admins.
     */
    public function forAssistants(Viewer $viewer): ?TaskGroups
    {
        if (!$viewer->isAdmin()) {
            return null;
        }
        $today = $this->today();

        return TaskGroups::sort($this->taskRepository->findOpenForAssistants($viewer, $today), $today);
    }

    /**
     * Every open task the viewer may see, also those not showing yet.
     */
    public function allOpen(Viewer $viewer): TaskGroups
    {
        $today = $this->today();

        return TaskGroups::sort($this->taskRepository->findOpenVisibleTo($viewer), $today);
    }

    public function today(): string
    {
        return $this->household->today()->format('Y-m-d');
    }
}
