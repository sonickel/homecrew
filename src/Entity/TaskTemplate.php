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
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Recurring task or maintenance plan (konzept-2 §5): creates one task per occurrence.
 * Created in M5 because tasks refer to their plan; plans, the series generator and "Routinen & Wartung" follow in M6.
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
class TaskTemplate
{
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

    #[ORM\Column(name: 'earliest_after_days', type: Types::SMALLINT, nullable: true, options: ['unsigned' => true])]
    private ?int $earliestAfterDays = null;

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

    #[ORM\Column(name: 'paused_until', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pausedUntil = null;

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

    /** For the overview, kept up to date by the scheduler. */
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
        $this->startsOn = $startsOn;
        $this->createdBy = $createdBy;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCategory(): TaskCategory
    {
        return $this->category;
    }

    public function getRecurrenceType(): RecurrenceType
    {
        return $this->recurrenceType;
    }

    public function getLastDoneAt(): ?\DateTimeImmutable
    {
        return $this->lastDoneAt;
    }
}
