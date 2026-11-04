<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AssigneeRole;
use App\Enum\ProofType;
use App\Enum\RecurrenceType;
use App\Enum\TaskCategory;
use App\Enum\TaskPriority;
use App\Enum\Visibility;
use App\Repository\TaskTemplateRepository;
use App\Service\Visibility\Assignable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Recurring task or maintenance plan (konzept-2 §5): one task per occurrence, created by App\Service\Task\PlanSync –
 * by calendar (RRULE, App\Service\Series\Rhythm) or n days after the last completion.
 * Dates are household dates without time zone (DATE/TIME columns): compare them by their Y-m-d string.
 */
#[ORM\Entity(repositoryClass: TaskTemplateRepository::class)]
#[ORM\Table(name: 'task_template', options: ['comment' => 'Wiederkehrende Aufgabe / Wartungsplan'])]
#[ORM\Index(name: 'ix_template', columns: ['is_active'])]
#[ORM\Index(name: 'ix_template_overview', columns: ['next_due_on'])]
#[ORM\Index(name: 'ix_template_unable_list', columns: ['unable_list_id'])]
#[ORM\Index(name: 'fk_template_assignee', columns: ['assignee_user_id'])]
#[ORM\Index(name: 'fk_template_guide', columns: ['guide_id'])]
#[ORM\Index(name: 'fk_template_asset', columns: ['asset_id'])]
#[ORM\Index(name: 'fk_template_poi', columns: ['poi_id'])]
#[ORM\Index(name: 'fk_template_creator', columns: ['created_by_user_id'])]
class TaskTemplate implements Assignable
{
    public const int CHECKLIST_MAX = 20;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'title', length: 150)]
    private string $title;

    #[ORM\Column(name: 'description', type: Types::TEXT, length: 65535, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'category', length: 15, enumType: TaskCategory::class)]
    private TaskCategory $category;

    #[ORM\Column(name: 'estimated_minutes', type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $estimatedMinutes = null;

    #[ORM\Column(name: 'priority', length: 10, enumType: TaskPriority::class, options: ['default' => 'normal'])]
    private TaskPriority $priority = TaskPriority::Normal;

    #[ORM\Column(name: 'requires_manager', options: ['default' => false])]
    private bool $requiresManager = false;

    #[ORM\Column(name: 'proof_type', length: 10, enumType: ProofType::class, options: ['default' => 'keiner'])]
    private ProofType $proofType = ProofType::Keiner;

    /** A certain person – takes precedence over the role. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'assignee_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $assigneeUser = null;

    /** Otherwise a role; no person and no role = "wer gerade da ist". */
    #[ORM\Column(name: 'assignee_role', length: 12, nullable: true, enumType: AssigneeRole::class)]
    private ?AssigneeRole $assigneeRole = null;

    #[ORM\Column(name: 'visibility', length: 12, enumType: Visibility::class, options: ['default' => 'alle'])]
    private Visibility $visibility = Visibility::Alle;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'guide_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Guide $guide = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Asset $asset = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'poi_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Poi $poi = null;

    #[ORM\Column(name: 'recurrence_type', length: 10, enumType: RecurrenceType::class)]
    private RecurrenceType $recurrenceType;

    /** RFC 5545, e.g. FREQ=WEEKLY;BYDAY=MO. */
    #[ORM\Column(name: 'rrule', length: 255, nullable: true)]
    private ?string $rrule = null;

    /** "Alle n Tage nach der letzten Erledigung". */
    #[ORM\Column(name: 'interval_days', type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $intervalDays = null;

    /** Interval plans: "Heute möglich" from this many days after the last completion on. */
    #[ORM\Column(name: 'earliest_after_days', type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $earliestAfterDays = null;

    /** Calendar plans: "Heute möglich" this many days before the day. */
    #[ORM\Column(name: 'window_days', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $windowDays = 0;

    #[ORM\Column(name: 'due_time', type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueTime = null;

    #[ORM\Column(name: 'starts_on', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $startsOn;

    #[ORM\Column(name: 'ends_on', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endsOn = null;

    #[ORM\Column(name: 'paused_from', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pausedFrom = null;

    /** Null with a start = paused until further notice. */
    #[ORM\Column(name: 'paused_until', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pausedUntil = null;

    /** False = ended ("Beenden"); "Wieder aufnehmen" sets it again. */
    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    /** "Zuletzt erledigt" – stays when old tasks are deleted. */
    #[ORM\Column(name: 'last_done_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastDoneAt = null;

    /** Suggestion for "nicht möglich": this list … */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'unable_list_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ShoppingList $unableList = null;

    /** … with this item (e.g. "Flüssigseife"). */
    #[ORM\Column(name: 'unable_item_name', length: 100, nullable: true)]
    private ?string $unableItemName = null;

    /** For the overview, kept up to date by App\Service\Task\PlanSync. */
    #[ORM\Column(name: 'next_due_on', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $nextDueOn = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $createdBy;

    /** Set by the database on insert. */
    #[ORM\Column(
        name: 'created_at',
        type: Types::DATETIME_IMMUTABLE,
        insertable: false,
        updatable: false,
        generated: 'INSERT',
        options: ['default' => new CurrentTimestamp()],
    )]
    private \DateTimeImmutable $createdAt;

    /** Set by the database (ON UPDATE CURRENT_TIMESTAMP). */
    #[ORM\Column(
        name: 'updated_at',
        type: Types::DATETIME_IMMUTABLE,
        insertable: false,
        updatable: false,
        columnDefinition: 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
        generated: 'ALWAYS',
        options: ['default' => new CurrentTimestamp()],
    )]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, TaskTemplateItem> checklist, copied into every occurrence */
    #[ORM\OneToMany(targetEntity: TaskTemplateItem::class, mappedBy: 'template', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['sort' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    /**
     * @param string|int $rhythm the RRULE for "kalender", the number of days for "intervall"
     */
    public function __construct(string $title, TaskCategory $category, RecurrenceType $recurrenceType, string|int $rhythm, \DateTimeImmutable $startsOn, ?AppUser $createdBy = null)
    {
        $this->title = $title;
        $this->category = $category;
        $this->recurrenceType = $recurrenceType;
        if (RecurrenceType::Kalender === $recurrenceType) {
            $this->rrule = (string) $rhythm;
        } else {
            $this->intervalDays = (int) $rhythm;
        }
        $this->startsOn = self::day($startsOn);
        $this->createdBy = $createdBy;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCategory(): TaskCategory
    {
        return $this->category;
    }

    public function getEstimatedMinutes(): ?int
    {
        return $this->estimatedMinutes;
    }

    public function isImportant(): bool
    {
        return TaskPriority::Wichtig === $this->priority;
    }

    public function isRequiresManager(): bool
    {
        return $this->requiresManager;
    }

    /**
     * Title, description and the details every occurrence takes over.
     */
    public function describe(string $title, ?string $description, TaskCategory $category, ?int $estimatedMinutes, bool $important, bool $requiresManager): void
    {
        $this->title = trim($title);
        $description = trim((string) $description);
        $this->description = '' === $description ? null : $description;
        $this->category = $category;
        $this->estimatedMinutes = $estimatedMinutes;
        $this->priority = $important ? TaskPriority::Wichtig : TaskPriority::Normal;
        $this->requiresManager = $requiresManager;
    }

    public function getAssigneeUser(): ?AppUser
    {
        return $this->assigneeUser;
    }

    public function getAssigneeRole(): ?AssigneeRole
    {
        return $this->assigneeRole;
    }

    /**
     * Who is responsible: a person (takes precedence), else a role, else nobody in particular = "wer gerade da ist".
     */
    public function assign(?AppUser $person, ?AssigneeRole $role): void
    {
        $this->assigneeUser = $person;
        $this->assigneeRole = null === $person ? $role : null;
    }

    public function getVisibility(): Visibility
    {
        return $this->visibility;
    }

    public function setVisibility(Visibility $visibility): void
    {
        $this->visibility = $visibility;
    }

    public function getRecurrenceType(): RecurrenceType
    {
        return $this->recurrenceType;
    }

    public function isByCalendar(): bool
    {
        return RecurrenceType::Kalender === $this->recurrenceType;
    }

    public function getRrule(): ?string
    {
        return $this->rrule;
    }

    public function getIntervalDays(): ?int
    {
        return $this->intervalDays;
    }

    public function getEarliestAfterDays(): ?int
    {
        return $this->earliestAfterDays;
    }

    public function getWindowDays(): int
    {
        return $this->windowDays;
    }

    /**
     * By calendar: on the days of the RRULE, "Heute möglich" the given number of days before.
     */
    public function repeatByCalendar(string $rrule, int $windowDays): void
    {
        $this->recurrenceType = RecurrenceType::Kalender;
        $this->rrule = $rrule;
        $this->intervalDays = null;
        $this->earliestAfterDays = null;
        $this->windowDays = max(0, $windowDays);
    }

    /**
     * n days after the last completion; "Heute möglich" from the given number of days on (at most n).
     */
    public function repeatAfterCompletion(int $intervalDays, ?int $earliestAfterDays): void
    {
        if ($intervalDays < 1 || (null !== $earliestAfterDays && ($earliestAfterDays < 0 || $earliestAfterDays > $intervalDays))) {
            throw new \DomainException('The interval needs at least one day; "possible from" may not lie after it.');
        }
        $this->recurrenceType = RecurrenceType::Intervall;
        $this->rrule = null;
        $this->intervalDays = $intervalDays;
        $this->earliestAfterDays = $earliestAfterDays;
        $this->windowDays = 0;
    }

    public function getDueTime(): ?\DateTimeImmutable
    {
        return $this->dueTime;
    }

    public function getStartsOn(): \DateTimeImmutable
    {
        return $this->startsOn;
    }

    public function getEndsOn(): ?\DateTimeImmutable
    {
        return $this->endsOn;
    }

    /**
     * From when to when the plan runs, and at what time its occurrences are due (optional).
     */
    public function schedule(\DateTimeImmutable $startsOn, ?\DateTimeImmutable $endsOn, ?\DateTimeImmutable $dueTime): void
    {
        $startsOn = self::day($startsOn);
        $endsOn = null === $endsOn ? null : self::day($endsOn);
        if (null !== $endsOn && $endsOn->format('Y-m-d') < $startsOn->format('Y-m-d')) {
            throw new \DomainException('A plan cannot end before it starts.');
        }

        $this->startsOn = $startsOn;
        $this->endsOn = $endsOn;
        $this->dueTime = null === $dueTime ? null : new \DateTimeImmutable('1970-01-01 '.$dueTime->format('H:i:00'));
    }

    public function getPausedFrom(): ?\DateTimeImmutable
    {
        return $this->pausedFrom;
    }

    public function getPausedUntil(): ?\DateTimeImmutable
    {
        return $this->pausedUntil;
    }

    /**
     * Pauses the plan from a day on – until a day or (without end) until further notice; no start = no pause.
     */
    public function pause(?\DateTimeImmutable $from, ?\DateTimeImmutable $until): void
    {
        $from = null === $from ? null : self::day($from);
        $until = null === $from || null === $until ? null : self::day($until);
        if (null !== $from && null !== $until && $until->format('Y-m-d') < $from->format('Y-m-d')) {
            throw new \DomainException('A pause cannot end before it starts.');
        }

        $this->pausedFrom = $from;
        $this->pausedUntil = $until;
    }

    /**
     * @param string $day household date Y-m-d
     */
    public function isPausedOn(string $day): bool
    {
        return null !== $this->pausedFrom && $this->pausedFrom->format('Y-m-d') <= $day
            && (null === $this->pausedUntil || $this->pausedUntil->format('Y-m-d') >= $day);
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    /**
     * "Beenden": no more occurrences; the history stays.
     */
    public function end(): void
    {
        $this->isActive = false;
    }

    public function resume(): void
    {
        $this->isActive = true;
    }

    /**
     * True when the plan has occurrences on that day: active, not paused, not ended by its end date.
     *
     * @param string $day household date Y-m-d
     */
    public function runsOn(string $day): bool
    {
        return $this->isActive && !$this->isPausedOn($day) && (null === $this->endsOn || $this->endsOn->format('Y-m-d') >= $day);
    }

    /**
     * @return Collection<int, TaskTemplateItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * The checklist, one label per point in this order; existing points keep their row, the rest is removed.
     *
     * @param list<string> $labels
     */
    public function replaceChecklist(array $labels): void
    {
        $labels = array_slice(array_values(array_filter(array_map(trim(...), $labels), static fn (string $label): bool => '' !== $label)), 0, self::CHECKLIST_MAX);
        $items = array_values($this->items->toArray());
        foreach ($labels as $sort => $label) {
            if (isset($items[$sort])) {
                $items[$sort]->relabel($label);
            } else {
                $this->items->add(new TaskTemplateItem($this, $sort, $label));
            }
        }
        foreach (array_slice($items, \count($labels)) as $surplus) {
            $this->items->removeElement($surplus);
        }
    }

    public function getLastDoneAt(): ?\DateTimeImmutable
    {
        return $this->lastDoneAt;
    }

    public function getNextDueOn(): ?\DateTimeImmutable
    {
        return $this->nextDueOn;
    }

    /**
     * What the overview shows without looking at the occurrences (App\Service\Task\PlanSync keeps it up to date).
     */
    public function recordProgress(?\DateTimeImmutable $nextDueOn, ?\DateTimeImmutable $lastDoneAt): void
    {
        $this->nextDueOn = null === $nextDueOn ? null : self::day($nextDueOn);
        $this->lastDoneAt = $lastDoneAt;
    }

    public function getCreatedBy(): ?AppUser
    {
        return $this->createdBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * A household date as plain date (midnight UTC), so DATE columns store the same day.
     */
    private static function day(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return new \DateTimeImmutable($day->format('Y-m-d'));
    }
}
