<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Task;
use App\Service\Task\Assignment;
use App\Service\Viewer\ViewerProvider;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;

/**
 * One task as a row: status with symbol and word (never colour alone, App\Service\Task\TaskPresenter), title as
 * link to the details, details as short items and "Erledigt" as large button on the right.
 */
#[AsTwigComponent]
final class TaskRow
{
    public Task $task;

    /** Where "Erledigt" leads back to: "heute" or "aufgaben". */
    public string $back = 'heute';

    public function __construct(
        private readonly ViewerProvider $viewerProvider,
    ) {
    }

    /**
     * Who is responsible – left out when it is the viewer personally.
     */
    public function assignee(): ?string
    {
        $person = $this->task->getAssigneeUser();
        if (null !== $person && true === $this->viewerProvider->current()?->isPerson($person)) {
            return null;
        }

        return Assignment::of($this->task)->label();
    }
}
