<?php

declare(strict_types=1);

namespace App\Form\Task;

use App\Entity\Task;
use App\Entity\TaskTemplate;
use App\Entity\TaskTemplateItem;
use App\Enum\TaskCategory;
use App\Enum\Visibility;
use App\Service\Series\Rhythm;
use App\Service\Task\Assignment;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * What the plan form edits ("Neuer Plan", "Bearbeiten"); App\Service\Task\PlanActions applies it to the plan.
 * "Wie oft?" by calendar (App\Service\Series\Rhythm; yearly on the day of the start) or n days after the last
 * completion; only the fields of the chosen rhythm count.
 */
final class PlanData
{
    public const string AFTER_COMPLETION = 'nach_erledigung';
    public const array RHYTHMS = [Rhythm::DAILY, Rhythm::WEEKLY, Rhythm::MONTHLY, Rhythm::YEARLY, self::AFTER_COMPLETION];

    #[Assert\NotBlank(message: 'Bitte schreib kurz, was zu tun ist.')]
    #[Assert\Length(max: Task::TITLE_MAX, maxMessage: 'Höchstens {{ limit }} Zeichen – Einzelheiten passen in die Beschreibung.')]
    public ?string $title = null;

    #[Assert\Length(max: 2000, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $description = null;

    public TaskCategory $category = TaskCategory::Haushalt;

    #[Assert\Range(min: 1, max: 600, notInRangeMessage: 'Bitte eine Dauer von {{ min }} bis {{ max }} Minuten.')]
    public ?int $estimatedMinutes = null;

    public bool $important = false;

    public bool $requiresManager = false;

    #[Assert\Choice(choices: self::RHYTHMS)]
    public string $rhythm = Rhythm::WEEKLY;

    // The numbers of a rhythm are checked only for the chosen one (validate()) – the others are hidden in the form

    public ?int $everyDays = 1;

    public ?int $everyWeeks = 1;

    /** @var list<string> RFC 5545 weekdays */
    public array $weekdays = [];

    public ?int $everyMonths = 1;

    public ?int $monthDay = 1;

    public ?int $afterDays = 14;

    public ?int $earliestAfterDays = null;

    public ?int $windowDays = 0;

    #[Assert\NotNull(message: 'Bitte wähle, ab wann der Plan gilt.')]
    public ?\DateTimeImmutable $startsOn = null;

    public ?\DateTimeImmutable $endsOn = null;

    public ?\DateTimeImmutable $dueTime = null;

    #[Assert\NotNull(message: 'Bitte wähle, wer zuständig ist.')]
    public ?Assignment $assignee = null;

    public Visibility $visibility = Visibility::Alle;

    /** One point per line. */
    public ?string $checklist = null;

    public ?\DateTimeImmutable $pausedFrom = null;

    public ?\DateTimeImmutable $pausedUntil = null;

    /** Earliest allowed start (Y-m-d): today for new plans; for changes also the current start. */
    public string $earliestStart;

    public function __construct(string $earliestStart)
    {
        $this->earliestStart = $earliestStart;
    }

    public static function fromPlan(TaskTemplate $plan, string $today): self
    {
        $data = new self(min($today, $plan->getStartsOn()->format('Y-m-d')));
        $data->title = $plan->getTitle();
        $data->description = $plan->getDescription();
        $data->category = $plan->getCategory();
        $data->estimatedMinutes = $plan->getEstimatedMinutes();
        $data->important = $plan->isImportant();
        $data->requiresManager = $plan->isRequiresManager();
        $data->startsOn = $plan->getStartsOn();
        $data->endsOn = $plan->getEndsOn();
        $data->dueTime = $plan->getDueTime();
        $data->assignee = Assignment::of($plan);
        $data->visibility = $plan->getVisibility();
        $data->checklist = implode("\n", array_map(static fn (TaskTemplateItem $item): string => $item->getLabel(), [...$plan->getItems()]));
        $data->pausedFrom = $plan->getPausedFrom();
        $data->pausedUntil = $plan->getPausedUntil();
        $data->windowDays = $plan->getWindowDays();

        if (!$plan->isByCalendar()) {
            $data->rhythm = self::AFTER_COMPLETION;
            $data->afterDays = $plan->getIntervalDays();
            $data->earliestAfterDays = $plan->getEarliestAfterDays();

            return $data;
        }

        $rhythm = Rhythm::fromRrule((string) $plan->getRrule());
        if (null === $rhythm) {
            return $data;
        }
        $data->rhythm = $rhythm->frequency;
        if (Rhythm::DAILY === $rhythm->frequency) {
            $data->everyDays = $rhythm->every;
        } elseif (Rhythm::WEEKLY === $rhythm->frequency) {
            $data->everyWeeks = $rhythm->every;
            $data->weekdays = $rhythm->weekdays;
        } elseif (Rhythm::MONTHLY === $rhythm->frequency) {
            $data->everyMonths = $rhythm->every;
            $data->monthDay = $rhythm->monthDay;
        }

        return $data;
    }

    public function isAfterCompletion(): bool
    {
        return self::AFTER_COMPLETION === $this->rhythm;
    }

    /**
     * The rhythm by calendar; yearly on the day of the start.
     */
    public function calendarRhythm(): Rhythm
    {
        \assert(null !== $this->startsOn);

        return match ($this->rhythm) {
            Rhythm::DAILY => Rhythm::daily((int) $this->everyDays),
            Rhythm::WEEKLY => Rhythm::weekly($this->weekdays, (int) $this->everyWeeks),
            Rhythm::MONTHLY => Rhythm::monthly((int) $this->monthDay, (int) $this->everyMonths),
            default => Rhythm::yearly((int) $this->startsOn->format('j'), (int) $this->startsOn->format('n')),
        };
    }

    /**
     * @return list<string>
     */
    public function checklistLabels(): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split('/\R/', (string) $this->checklist) ?: []), static fn (string $label): bool => '' !== $label));
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if (Rhythm::WEEKLY === $this->rhythm && [] === $this->weekdays) {
            $context->buildViolation('Bitte wähle mindestens einen Wochentag.')->atPath('weekdays')->addViolation();
        }
        // [value, smallest, largest, required] of the fields the chosen rhythm shows
        $numbers = match ($this->rhythm) {
            Rhythm::DAILY => ['everyDays' => [$this->everyDays, 1, 365, true], 'windowDays' => [$this->windowDays, 0, 60, false]],
            Rhythm::WEEKLY => ['everyWeeks' => [$this->everyWeeks, 1, 52, true], 'windowDays' => [$this->windowDays, 0, 60, false]],
            Rhythm::MONTHLY => ['monthDay' => [$this->monthDay, 1, 31, true], 'everyMonths' => [$this->everyMonths, 1, 12, true], 'windowDays' => [$this->windowDays, 0, 60, false]],
            self::AFTER_COMPLETION => ['afterDays' => [$this->afterDays, 1, 365, true], 'earliestAfterDays' => [$this->earliestAfterDays, 0, 365, false]],
            default => ['windowDays' => [$this->windowDays, 0, 60, false]],
        };
        foreach ($numbers as $path => [$value, $min, $max, $required]) {
            if ((null === $value && $required) || (null !== $value && ($value < $min || $value > $max))) {
                $context->buildViolation('Bitte eine Zahl von {{ min }} bis {{ max }}.')
                    ->setParameters(['{{ min }}' => (string) $min, '{{ max }}' => (string) $max])
                    ->atPath($path)
                    ->addViolation();
            }
        }
        if ($this->isAfterCompletion() && null !== $this->earliestAfterDays && null !== $this->afterDays && $this->earliestAfterDays > $this->afterDays) {
            $context->buildViolation('Das ist später als die Fälligkeit. Bitte wähle höchstens {{ days }} Tage.')->setParameter('{{ days }}', (string) $this->afterDays)->atPath('earliestAfterDays')->addViolation();
        }
        if (null !== $this->startsOn && $this->startsOn->format('Y-m-d') < $this->earliestStart) {
            $context->buildViolation('Der Tag liegt in der Vergangenheit. Bitte wähle heute oder später.')->atPath('startsOn')->addViolation();
        }
        if (null !== $this->startsOn && null !== $this->endsOn && $this->endsOn->format('Y-m-d') < $this->startsOn->format('Y-m-d')) {
            $context->buildViolation('Das Ende liegt vor dem Beginn.')->atPath('endsOn')->addViolation();
        }
        if (null === $this->pausedFrom && null !== $this->pausedUntil) {
            $context->buildViolation('Bitte wähle auch, ab wann die Pause gilt.')->atPath('pausedFrom')->addViolation();
        }
        if (null !== $this->pausedFrom && null !== $this->pausedUntil && $this->pausedUntil->format('Y-m-d') < $this->pausedFrom->format('Y-m-d')) {
            $context->buildViolation('Die Pause endet vor ihrem Beginn.')->atPath('pausedUntil')->addViolation();
        }

        $labels = $this->checklistLabels();
        if (\count($labels) > TaskTemplate::CHECKLIST_MAX) {
            $context->buildViolation('Höchstens {{ limit }} Punkte.')->setParameter('{{ limit }}', (string) TaskTemplate::CHECKLIST_MAX)->atPath('checklist')->addViolation();
        }
        foreach ($labels as $label) {
            if (mb_strlen($label) > TaskTemplateItem::LABEL_MAX) {
                $context->buildViolation('Ein Punkt hat höchstens {{ limit }} Zeichen.')->setParameter('{{ limit }}', (string) TaskTemplateItem::LABEL_MAX)->atPath('checklist')->addViolation();
                break;
            }
        }
    }
}
