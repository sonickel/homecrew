<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\Task;
use App\Enum\TaskStatus;
use App\Enum\UserRole;
use App\Service\Household\DayText;
use App\Service\Household\HouseholdContext;
use App\Service\Viewer\ViewerProvider;
use App\Service\Visibility\Assignable;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Texts about a task for rows and the detail page: status with symbol and word, due date, "Ab wann zeigen?".
 * Dates and times of tasks are household dates without time zone and are shown as they are. The texts come translated.
 */
final readonly class TaskPresenter
{
    public function __construct(
        private HouseholdContext $household,
        private TranslatorInterface $translator,
        private ViewerProvider $viewerProvider,
    ) {
    }

    /**
     * @return array{modifier: string, icon: string, text: string} modifier: late · due · possible · later · ok
     */
    #[AsTwigFunction('task_status')]
    public function status(Task $task): array
    {
        if (!$task->isOpen()) {
            return TaskStatus::Uebersprungen === $task->getStatus()
                ? ['modifier' => 'later', 'icon' => 'tabler:player-skip-forward', 'text' => $this->translator->trans('Übersprungen')]
                : ['modifier' => 'ok', 'icon' => 'tabler:circle-check', 'text' => $this->translator->trans('Erledigt')];
        }

        $today = $this->today();
        $params = ['%day%' => DayText::day($task->getDueDate(), $today), '%time%' => $task->getDueTime()?->format('H:i')];
        $timed = null !== $params['%time%'];

        [$modifier, $icon, $text] = match (DueGroup::of($task, $today)) {
            DueGroup::Ueberfaellig => ['late', 'tabler:alert-triangle', 'Überfällig seit %day%'],
            DueGroup::HeuteFaellig => ['due', 'tabler:clock', $timed ? 'Heute, %time% Uhr' : 'Heute'],
            DueGroup::HeuteMoeglich => ['possible', 'tabler:circle-dashed', 'Möglich bis %day%'],
            DueGroup::WennZeitIst => ['later', 'tabler:hourglass', 'Bis %day%'],
            DueGroup::Spaeter => ['later', 'tabler:calendar-event', $timed ? 'Fällig %day%, %time% Uhr' : 'Fällig %day%'],
        };

        return ['modifier' => $modifier, 'icon' => $icon, 'text' => $this->translator->trans($text, $params)];
    }

    /**
     * "Dienstag, 3. November 2026, 10:00 Uhr".
     */
    #[AsTwigFunction('task_due')]
    public function due(Task $task): string
    {
        $time = $task->getDueTime()?->format('H:i');
        $day = DayText::long($task->getDueDate());

        return null === $time ? $day : $this->translator->trans('%day%, %time% Uhr', ['%day%' => $day, '%time%' => $time]);
    }

    #[AsTwigFunction('task_show_from')]
    public function showFrom(Task $task): string
    {
        $window = $task->getWindowStart();
        $visibleFrom = $task->getVisibleFrom();

        return match (true) {
            null !== $window => $this->translator->trans('Ab %day% – täglich unter „Heute möglich“', ['%day%' => DayText::day($window, $this->today())]),
            null !== $visibleFrom => $this->translator->trans('Erst ab %day%', ['%day%' => DayText::day($visibleFrom, $this->today())]),
            default => $this->translator->trans('Ab sofort – unter „Wenn Zeit ist“'),
        };
    }

    /**
     * "Zuletzt: vor 14 Tagen" for an occurrence of a plan – only the day, for everybody (konzept-12 §6);
     * null for one-off tasks and plans never done.
     */
    #[AsTwigFunction('task_last_done')]
    public function lastDone(Task $task): ?string
    {
        $doneAt = $task->getTemplate()?->getLastDoneAt();
        if (null === $doneAt) {
            return null;
        }
        $day = $doneAt->setTimezone($this->household->timezone())->format('Y-m-d');

        return $this->translator->trans('Zuletzt: %ago%', ['%ago%' => DayText::ago($day, $this->today())]);
    }

    /**
     * Who is responsible for a task or plan.
     */
    #[AsTwigFunction('task_assignee')]
    public function assignee(Assignable $item): string
    {
        return Assignment::of($item)->label();
    }

    /**
     * For "Gemeinsam mit …": the manager's name – "dir" for the manager.
     */
    #[AsTwigFunction('with_manager')]
    public function withManager(): string
    {
        return true === $this->viewerProvider->current()?->hasRole(UserRole::Manager) ? $this->translator->trans('dir') : $this->household->managerName();
    }

    private function today(): string
    {
        return $this->household->today()->format('Y-m-d');
    }
}
