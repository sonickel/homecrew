<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\TaskTemplate;
use App\Enum\RecurrenceType;
use App\Enum\Visibility;
use App\Form\Task\PlanData;
use App\Service\Viewer\Viewer;
use App\Service\Visibility\VisibilityRules;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Creating, changing, ending and resuming plans (konzept-2 §5, decisions M6); afterwards App\Service\Task\PlanSync
 * brings the occurrences up to date. The callers check the rights (App\Security\Voter\PlanVoter).
 */
final readonly class PlanActions
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private VisibilityRules $rules,
        private PlanSync $planSync,
    ) {
    }

    public function create(PlanData $data, Viewer $viewer): TaskTemplate
    {
        \assert(null !== $data->startsOn);
        $plan = $data->isAfterCompletion()
            ? new TaskTemplate((string) $data->title, $data->category, RecurrenceType::Intervall, (int) $data->afterDays, $data->startsOn, $viewer->getPerson())
            : new TaskTemplate((string) $data->title, $data->category, RecurrenceType::Kalender, $data->calendarRhythm()->rrule(), $data->startsOn, $viewer->getPerson());
        $this->apply($plan, $data, $viewer);

        $this->entityManager->persist($plan);
        $this->entityManager->flush();
        $this->planSync->sync($plan);

        return $plan;
    }

    public function update(TaskTemplate $plan, PlanData $data, Viewer $viewer): void
    {
        $this->apply($plan, $data, $viewer);
        $this->entityManager->flush();
        $this->planSync->sync($plan);
    }

    /**
     * "Beenden": its open occurrences go (unless changed one by one), the history stays.
     */
    public function end(TaskTemplate $plan): void
    {
        $plan->end();
        $this->entityManager->flush();
        $this->planSync->sync($plan);
    }

    public function resume(TaskTemplate $plan): void
    {
        $plan->resume();
        $this->entityManager->flush();
        $this->planSync->sync($plan);
    }

    private function apply(TaskTemplate $plan, PlanData $data, Viewer $viewer): void
    {
        \assert(null !== $data->startsOn && null !== $data->assignee);
        // Only its creator – a manager or relative – keeps a plan private, as with tasks
        $creator = $plan->getCreatedBy();
        $mayBePrivate = $this->rules->mayKeepPrivate($viewer) && (null === $creator || $viewer->isPerson($creator));
        if (Visibility::Privat === $data->visibility && Visibility::Privat !== $plan->getVisibility() && !$mayBePrivate) {
            throw new \DomainException('Only its creator – a manager or relative – makes a plan private.');
        }

        $plan->describe((string) $data->title, $data->description, $data->category, $data->estimatedMinutes, $data->important, $data->requiresManager);
        if ($data->isAfterCompletion()) {
            $plan->repeatAfterCompletion((int) $data->afterDays, $data->earliestAfterDays);
        } else {
            $plan->repeatByCalendar($data->calendarRhythm()->rrule(), (int) $data->windowDays);
        }
        $plan->schedule($data->startsOn, $data->endsOn, $data->dueTime);
        $plan->pause($data->pausedFrom, $data->pausedUntil);
        $plan->setVisibility($data->visibility);
        $privateOwner = Visibility::Privat === $data->visibility ? ($plan->getCreatedBy() ?? $viewer->getPerson()) : null;
        $assignee = null !== $privateOwner ? Assignment::person($privateOwner) : $data->assignee;
        $plan->assign($assignee->person, $assignee->role);
        $plan->replaceChecklist($data->checklistLabels());
    }
}
