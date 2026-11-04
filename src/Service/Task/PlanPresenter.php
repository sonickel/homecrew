<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\TaskTemplate;
use App\Service\Household\DayText;
use App\Service\Household\HouseholdContext;
use App\Service\Series\Rhythm;
use App\Service\Series\RhythmText;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Texts about a plan for "Routinen & Wartung": status with symbol and word, rhythm, window and pause.
 * The texts come translated.
 */
final readonly class PlanPresenter
{
    public function __construct(
        private TaskPresenter $taskPresenter,
        private RhythmText $rhythmText,
        private HouseholdContext $household,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{modifier: string, icon: string, text: string} modifier as in TaskPresenter::status()
     */
    #[AsTwigFunction('plan_status')]
    public function status(PlanRow $row): array
    {
        $until = $row->plan->getPausedUntil();

        return match ($row->state) {
            PlanState::Beendet => ['modifier' => 'later', 'icon' => 'tabler:circle-off', 'text' => $this->translator->trans('Beendet')],
            PlanState::Pausiert => ['modifier' => 'later', 'icon' => 'tabler:player-pause', 'text' => null === $until
                ? $this->translator->trans('Pausiert')
                : $this->translator->trans('Pausiert bis %day%', ['%day%' => DayText::day($until, $this->today())])],
            PlanState::NichtsGeplant => ['modifier' => 'later', 'icon' => 'tabler:calendar-event', 'text' => $this->translator->trans('Nichts mehr geplant')],
            default => $this->taskPresenter->status($row->next ?? throw new \LogicException('A plan with this state has an open occurrence.')),
        };
    }

    /**
     * The rhythm in words, e.g. "Jeden Montag" or "Alle 14 Tage nach der letzten Erledigung".
     */
    #[AsTwigFunction('plan_rhythm')]
    public function rhythm(TaskTemplate $plan): string
    {
        if (!$plan->isByCalendar()) {
            return $this->rhythmText->afterCompletion((int) $plan->getIntervalDays());
        }
        $rhythm = Rhythm::fromRrule((string) $plan->getRrule());

        return null === $rhythm ? $this->translator->trans('Nach eigener Regel') : $this->rhythmText->describe($rhythm);
    }

    /**
     * When it is possible before it is due; null when only on the day itself.
     */
    #[AsTwigFunction('plan_window')]
    public function window(TaskTemplate $plan): ?string
    {
        $earliest = $plan->getEarliestAfterDays();
        $days = $plan->getWindowDays();

        return match (true) {
            0 === $earliest => $this->translator->trans('Gleich nach der Erledigung wieder möglich'),
            1 === $earliest => $this->translator->trans('Schon nach einem Tag möglich'),
            null !== $earliest => $this->translator->trans('Schon nach %count% Tagen möglich', ['%count%' => $earliest]),
            1 === $days => $this->translator->trans('Schon einen Tag vorher möglich'),
            $days > 1 => $this->translator->trans('Schon %count% Tage vorher möglich', ['%count%' => $days]),
            default => null,
        };
    }

    /**
     * The pause, unless it is over.
     */
    #[AsTwigFunction('plan_pause')]
    public function pause(TaskTemplate $plan): ?string
    {
        $from = $plan->getPausedFrom();
        $until = $plan->getPausedUntil();
        $today = $this->today();
        if (null === $from || (null !== $until && $until->format('Y-m-d') < $today)) {
            return null;
        }

        return null === $until
            ? $this->translator->trans('Ab %from% bis auf Weiteres', ['%from%' => DayText::long($from)])
            : $this->translator->trans('%from% bis %until%', ['%from%' => DayText::long($from), '%until%' => DayText::long($until)]);
    }

    private function today(): string
    {
        return $this->household->today()->format('Y-m-d');
    }
}
