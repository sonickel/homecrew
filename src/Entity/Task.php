<?php

declare(strict_types=1);

namespace App\Entity;

use App\Doctrine\Type\TinyIntType;
use App\Enum\AssigneeRole;
use App\Enum\CompletionAction;
use App\Enum\CreatedVia;
use App\Enum\DueRule;
use App\Enum\ProofType;
use App\Enum\TaskCategory;
use App\Enum\TaskPriority;
use App\Enum\TaskStatus;
use App\Enum\Visibility;
use App\Repository\TaskRepository;
use App\Service\Visibility\Assignable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Task (konzept-2 §5): one-off, or an occurrence of a plan (template + occurrence_date), also talking points.
 * Occurrences come from App\Service\Task\PlanSync and follow their plan until changed one by one (is_modified).
 *
 * Dates are household dates without time zone (DATE/TIME columns): compare them by their Y-m-d string.
 * When it shows on "Heute" (App\Service\Task\TodayTasks): from visible_from (empty = at once), "Heute möglich"
 * from window_start, due on due_date, overdue afterwards.
 */
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\Table(name: 'task', options: ['comment' => 'Aufgabe: einmalig oder Vorkommen einer Vorlage, auch Gesprächspunkte'])]
#[ORM\UniqueConstraint(name: 'uq_task_occurrence', columns: ['template_id', 'occurrence_date'])]
#[ORM\Index(name: 'ix_task_today', columns: ['status', 'due_date'])]
#[ORM\Index(name: 'ix_task_assignee', columns: ['assignee_user_id', 'status'])]
#[ORM\Index(name: 'ix_task_assigned_by', columns: ['assigned_by_user_id'])]
#[ORM\Index(name: 'ix_task_assigned_device', columns: ['assigned_by_device_id'])]
#[ORM\Index(name: 'fk_task_guide', columns: ['guide_id'])]
#[ORM\Index(name: 'fk_task_asset', columns: ['asset_id'])]
#[ORM\Index(name: 'fk_task_poi', columns: ['poi_id'])]
#[ORM\Index(name: 'fk_task_creator', columns: ['created_by_user_id'])]
class Task implements Assignable
{
    public const int TITLE_MAX = 150;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    /** Null = one-off task. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?TaskTemplate $template = null;

    /** Original day of the plan – stays when the task is postponed. */
    #[ORM\Column(name: 'occurrence_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $occurrenceDate = null;

    /** Postponed, handed on or changed one by one – then a change of the plan leaves the occurrence alone. */
    #[ORM\Column(name: 'is_modified', options: ['default' => false])]
    private bool $modified = false;

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

    /** "Nur gemeinsam mit dem Manager". */
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

    /** Who handed the task on last: a person … */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'assigned_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $assignedBy = null;

    /** … or the team tablet. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'assigned_by_device_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Device $assignedByDevice = null;

    #[ORM\Column(name: 'assigned_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $assignedAt = null;

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

    #[ORM\Column(name: 'due_rule', length: 16, enumType: DueRule::class, options: ['default' => 'datum'])]
    private DueRule $dueRule = DueRule::Datum;

    #[ORM\Column(name: 'visible_from', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $visibleFrom = null;

    #[ORM\Column(name: 'window_start', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $windowStart = null;

    #[ORM\Column(name: 'due_date', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $dueDate;

    #[ORM\Column(name: 'due_time', type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueTime = null;

    #[ORM\Column(name: 'status', length: 15, enumType: TaskStatus::class, options: ['default' => 'offen'])]
    private TaskStatus $status = TaskStatus::Offen;

    #[ORM\Column(name: 'postponed_count', type: TinyIntType::NAME, options: ['unsigned' => true, 'default' => 0])]
    private int $postponedCount = 0;

    #[ORM\Column(name: 'created_via', length: 10, enumType: CreatedVia::class, options: ['default' => 'app'])]
    private CreatedVia $createdVia = CreatedVia::App;

    /** Null when created at the team tablet (or the person was deleted). */
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

    /** @var Collection<int, TaskItem> checklist, copied from the plan */
    #[ORM\OneToMany(targetEntity: TaskItem::class, mappedBy: 'task', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['sort' => 'ASC', 'id' => 'ASC'])]
    private Collection $items;

    public function __construct(string $title, TaskCategory $category, \DateTimeImmutable $dueDate, ?AppUser $createdBy = null)
    {
        $this->title = $title;
        $this->category = $category;
        $this->dueDate = self::day($dueDate);
        $this->createdBy = $createdBy;
        $this->items = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Makes the task the occurrence of a plan on its original day (only App\Service\Task\PlanSync creates them).
     */
    public function belongTo(TaskTemplate $plan, \DateTimeImmutable $occurrenceDate): void
    {
        $this->template = $plan;
        $this->occurrenceDate = self::day($occurrenceDate);
        $this->createdVia = CreatedVia::System;
    }

    public function getTemplate(): ?TaskTemplate
    {
        return $this->template;
    }

    public function getOccurrenceDate(): ?\DateTimeImmutable
    {
        return $this->occurrenceDate;
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    /**
     * An occurrence changed one by one keeps its state when the plan changes; one-off tasks have no plan to follow.
     */
    public function markModified(): void
    {
        if (null !== $this->occurrenceDate) {
            $this->modified = true;
        }
    }

    /**
     * @return Collection<int, TaskItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * Takes over the plan's checklist – unless it is the same already (no new rows for every run of the generator).
     */
    public function copyChecklist(TaskTemplate $plan): void
    {
        $labels = static fn (iterable $items): array => array_map(static fn (TaskItem|TaskTemplateItem $item): string => $item->getLabel(), [...$items]);
        if ($labels($this->items) === $labels($plan->getItems())) {
            return;
        }

        $this->items->clear();
        foreach ($plan->getItems() as $item) {
            $this->items->add(new TaskItem($this, $item));
        }
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

    public function getPriority(): TaskPriority
    {
        return $this->priority;
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
     * Title, description and the details shown in the task row.
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

    /**
     * Hands the task on and notes who did it and when – a person or the team tablet, never both.
     */
    public function handOver(?AppUser $person, ?AssigneeRole $role, AppUser|Device $by, \DateTimeImmutable $at): void
    {
        $this->assign($person, $role);
        $this->assignedBy = $by instanceof AppUser ? $by : null;
        $this->assignedByDevice = $by instanceof Device ? $by : null;
        $this->assignedAt = $at;
        $this->markModified();
    }

    public function getAssignedBy(): ?AppUser
    {
        return $this->assignedBy;
    }

    public function getAssignedByDevice(): ?Device
    {
        return $this->assignedByDevice;
    }

    public function getAssignedAt(): ?\DateTimeImmutable
    {
        return $this->assignedAt;
    }

    public function getVisibility(): Visibility
    {
        return $this->visibility;
    }

    public function setVisibility(Visibility $visibility): void
    {
        $this->visibility = $visibility;
    }

    public function getVisibleFrom(): ?\DateTimeImmutable
    {
        return $this->visibleFrom;
    }

    public function getWindowStart(): ?\DateTimeImmutable
    {
        return $this->windowStart;
    }

    public function getDueDate(): \DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function getDueTime(): ?\DateTimeImmutable
    {
        return $this->dueTime;
    }

    /**
     * When the task is due and from when it shows; window start and "show from" may not lie after the due date.
     */
    public function schedule(\DateTimeImmutable $dueDate, ?\DateTimeImmutable $dueTime, ?\DateTimeImmutable $windowStart, ?\DateTimeImmutable $visibleFrom): void
    {
        $dueDate = self::day($dueDate);
        $windowStart = null === $windowStart ? null : self::day($windowStart);
        $visibleFrom = null === $visibleFrom ? null : self::day($visibleFrom);
        if (($windowStart?->format('Y-m-d') ?? '') > $dueDate->format('Y-m-d') || ($visibleFrom?->format('Y-m-d') ?? '') > $dueDate->format('Y-m-d')) {
            throw new \DomainException('The window and "visible from" may not start after the due date.');
        }

        $this->dueDate = $dueDate;
        $this->dueTime = null === $dueTime ? null : new \DateTimeImmutable('1970-01-01 '.$dueTime->format('H:i:00'));
        $this->windowStart = $windowStart;
        $this->visibleFrom = $visibleFrom;
    }

    /**
     * Moves the due date; window start and "show from" move by the same number of days.
     */
    public function postpone(\DateTimeImmutable $dueDate): void
    {
        $days = (int) $this->dueDate->diff(self::day($dueDate))->format('%r%a');
        $shift = static fn (?\DateTimeImmutable $day): ?\DateTimeImmutable => $day?->modify(\sprintf('%+d days', $days));
        $this->schedule($dueDate, $this->dueTime, $shift($this->windowStart), $shift($this->visibleFrom));
        $this->postponedCount = min(255, $this->postponedCount + 1);
        $this->markModified();
    }

    public function getStatus(): TaskStatus
    {
        return $this->status;
    }

    public function isOpen(): bool
    {
        return TaskStatus::Offen === $this->status;
    }

    public function close(CompletionAction $action): void
    {
        if (!$this->isOpen()) {
            throw new \DomainException('Only an open task can be closed.');
        }
        $this->status = $action->status();
    }

    public function reopen(): void
    {
        $this->status = TaskStatus::Offen;
    }

    public function getPostponedCount(): int
    {
        return $this->postponedCount;
    }

    public function getCreatedVia(): CreatedVia
    {
        return $this->createdVia;
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
