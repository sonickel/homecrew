<?php

declare(strict_types=1);

namespace App\Form\Task;

use App\Entity\Task;
use App\Enum\TaskCategory;
use App\Enum\Visibility;
use App\Service\Task\Assignment;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * What the task form edits ("Neue Aufgabe", "Bearbeiten"); App\Service\Task\TaskActions applies it to the task.
 *
 * "Ab wann zeigen?" (decision M5): at once – then "Wenn Zeit ist" until the due date –, only on the due date,
 * or from a day on – then "Heute möglich" every day until the due date (visible_from = window_start).
 */
final class TaskData
{
    public const string SHOW_NOW = 'sofort';
    public const string SHOW_ON_DUE_DATE = 'am_tag';
    public const string SHOW_FROM = 'ab';

    #[Assert\NotBlank(message: 'Bitte schreib kurz, was zu tun ist.')]
    #[Assert\Length(max: Task::TITLE_MAX, maxMessage: 'Höchstens {{ limit }} Zeichen – Einzelheiten passen in die Beschreibung.')]
    public ?string $title = null;

    #[Assert\Length(max: 2000, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    public ?string $description = null;

    public TaskCategory $category = TaskCategory::Haushalt;

    #[Assert\NotNull(message: 'Bitte wähle einen Tag.')]
    public ?\DateTimeImmutable $dueDate = null;

    public ?\DateTimeImmutable $dueTime = null;

    #[Assert\Choice(choices: [self::SHOW_NOW, self::SHOW_ON_DUE_DATE, self::SHOW_FROM])]
    public string $showFrom = self::SHOW_NOW;

    public ?\DateTimeImmutable $showFromDate = null;

    #[Assert\Range(min: 1, max: 600, notInRangeMessage: 'Bitte eine Dauer von {{ min }} bis {{ max }} Minuten.')]
    public ?int $estimatedMinutes = null;

    public bool $important = false;

    public bool $requiresManager = false;

    #[Assert\NotNull(message: 'Bitte wähle, wer zuständig ist.')]
    public ?Assignment $assignee = null;

    public Visibility $visibility = Visibility::Alle;

    /** Earliest allowed due date (Y-m-d): today for new tasks; for changes also the current due date. */
    public string $earliestDueDate;

    public function __construct(string $earliestDueDate)
    {
        $this->earliestDueDate = $earliestDueDate;
    }

    public static function fromTask(Task $task, string $today): self
    {
        $due = $task->getDueDate();
        $data = new self(min($today, $due->format('Y-m-d')));
        $data->title = $task->getTitle();
        $data->description = $task->getDescription();
        $data->category = $task->getCategory();
        $data->dueDate = $due;
        $data->dueTime = $task->getDueTime();
        $data->estimatedMinutes = $task->getEstimatedMinutes();
        $data->important = $task->isImportant();
        $data->requiresManager = $task->isRequiresManager();
        $data->assignee = Assignment::of($task);
        $data->visibility = $task->getVisibility();

        $from = $task->getWindowStart() ?? $task->getVisibleFrom();
        if (null === $from) {
            $data->showFrom = self::SHOW_NOW;
        } elseif (null === $task->getWindowStart() && $from->format('Y-m-d') === $due->format('Y-m-d')) {
            $data->showFrom = self::SHOW_ON_DUE_DATE;
        } else {
            $data->showFrom = self::SHOW_FROM;
            $data->showFromDate = $from;
        }

        return $data;
    }

    /**
     * visible_from and window_start for the choice "Ab wann zeigen?".
     *
     * @return array{?\DateTimeImmutable, ?\DateTimeImmutable} [visible_from, window_start]
     */
    public function showDates(): array
    {
        return match ($this->showFrom) {
            self::SHOW_ON_DUE_DATE => [$this->dueDate, null],
            self::SHOW_FROM => [$this->showFromDate, $this->showFromDate],
            default => [null, null],
        };
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if (null === $this->dueDate) {
            return;
        }
        $due = $this->dueDate->format('Y-m-d');
        if ($due < $this->earliestDueDate) {
            $context->buildViolation('Der Tag liegt in der Vergangenheit. Bitte wähle heute oder später.')->atPath('dueDate')->addViolation();
        }
        if (self::SHOW_FROM !== $this->showFrom) {
            return;
        }
        if (null === $this->showFromDate) {
            $context->buildViolation('Bitte wähle, ab welchem Tag die Aufgabe erscheinen soll.')->atPath('showFromDate')->addViolation();
        } elseif ($this->showFromDate->format('Y-m-d') > $due) {
            $context->buildViolation('Dieser Tag liegt nach dem Fälligkeitstag. Bitte wähle einen früheren.')->atPath('showFromDate')->addViolation();
        }
    }
}
