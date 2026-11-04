<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CreatedVia;
use App\Enum\ReportFollowUp;
use App\Enum\ReportKind;
use App\Enum\ReportStatus;
use App\Enum\ReportVisibility;
use App\Repository\IssueReportRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Report (konzept-13): something is empty, broken, missing or a task was not possible. Goes to the admins (bell,
 * "Heute", report log) and closes on its own when its list entry is ticked off or its task is done
 * (App\Service\Report\ReportClosing). Sent by a person or at the team tablet (then the device); the person is
 * removed after retention_completion_days, closed reports are deleted after retention_report_days.
 */
#[ORM\Entity(repositoryClass: IssueReportRepository::class)]
#[ORM\Table(name: 'issue_report', options: ['comment' => 'Meldungen – erscheinen bei den Admins (Heute, Glocke, optional E-Mail) und im Meldungs-Log'])]
#[ORM\UniqueConstraint(name: 'uq_report_public', columns: ['public_id'])]
#[ORM\UniqueConstraint(name: 'uq_report_task', columns: ['task_id'])]
#[ORM\Index(name: 'ix_report_status', columns: ['status', 'created_at'])]
#[ORM\Index(name: 'ix_report_cleanup', columns: ['delete_after'])]
#[ORM\Index(name: 'ix_report_anon', columns: ['anonymized_at', 'created_at'])]
#[ORM\Index(name: 'ix_report_asset', columns: ['asset_id'])]
#[ORM\Index(name: 'ix_report_poi', columns: ['poi_id'])]
#[ORM\Index(name: 'ix_report_creator', columns: ['created_by_user_id'])]
#[ORM\Index(name: 'ix_report_device', columns: ['created_by_device_id'])]
#[ORM\Index(name: 'ix_report_item', columns: ['follow_up_shopping_item_id'])]
#[ORM\Index(name: 'ix_report_follow_task', columns: ['follow_up_task_id'])]
#[ORM\Index(name: 'ix_report_handover', columns: ['follow_up_handover_id'])]
#[ORM\Index(name: 'ix_report_closer', columns: ['closed_by_user_id'])]
class IssueReport
{
    public const int TITLE_MAX = 150;
    public const int NOTE_MAX = 1000;
    public const int CLOSE_NOTE_MAX = 255;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'public_id', type: UuidType::NAME)]
    private Uuid $publicId;

    #[ORM\Column(name: 'kind', length: 15, enumType: ReportKind::class)]
    private ReportKind $kind;

    #[ORM\Column(name: 'title', length: 150)]
    private string $title;

    #[ORM\Column(name: 'note', length: 1000, nullable: true)]
    private ?string $note = null;

    #[ORM\Column(name: 'is_urgent', options: ['default' => false])]
    private bool $urgent = false;

    #[ORM\Column(name: 'visibility', length: 10, enumType: ReportVisibility::class, options: ['default' => 'alle'])]
    private ReportVisibility $visibility = ReportVisibility::Alle;

    /** The task that was not possible – one report per task (uq_report_task). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'task_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Task $task;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'asset_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Asset $asset = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'poi_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Poi $poi = null;

    /** Only admins see who it was; null at the team tablet and after the anonymisation. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $createdBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_device_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Device $createdByDevice;

    #[ORM\Column(name: 'created_via', length: 10, enumType: CreatedVia::class, options: ['default' => 'app'])]
    private CreatedVia $createdVia = CreatedVia::App;

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

    /** "Auch bemerkt" instead of a second report. */
    #[ORM\Column(name: 'confirm_count', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $confirmCount = 0;

    #[ORM\Column(name: 'last_confirmed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastConfirmedAt = null;

    #[ORM\Column(name: 'status', length: 12, enumType: ReportStatus::class, options: ['default' => 'offen'])]
    private ReportStatus $status = ReportStatus::Offen;

    #[ORM\Column(name: 'follow_up', length: 10, nullable: true, enumType: ReportFollowUp::class)]
    private ?ReportFollowUp $followUp = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'follow_up_shopping_item_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?ShoppingItem $followUpItem = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'follow_up_task_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Task $followUpTask = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'follow_up_handover_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?HandoverNote $followUpHandover = null;

    #[ORM\Column(name: 'closed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    /** Null on a closed report = closed automatically (entry ticked off, task done). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'closed_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $closedBy = null;

    #[ORM\Column(name: 'close_note', length: 255, nullable: true)]
    private ?string $closeNote = null;

    #[ORM\Column(name: 'anonymized_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $anonymizedAt = null;

    /** Set when the report is closed (retention_report_days). */
    #[ORM\Column(name: 'delete_after', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deleteAfter = null;

    public function __construct(ReportKind $kind, string $title, AppUser|Device $by, ?Task $task = null)
    {
        $this->publicId = Uuid::v7();
        $this->kind = $kind;
        $this->title = $title;
        $this->createdBy = $by instanceof AppUser ? $by : null;
        $this->createdByDevice = $by instanceof Device ? $by : null;
        $this->task = $task;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicId(): Uuid
    {
        return $this->publicId;
    }

    public function getKind(): ReportKind
    {
        return $this->kind;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function isUrgent(): bool
    {
        return $this->urgent;
    }

    public function getVisibility(): ReportVisibility
    {
        return $this->visibility;
    }

    public function isForAdminsOnly(): bool
    {
        return ReportVisibility::Admins === $this->visibility;
    }

    public function describe(?string $note, bool $urgent, ReportVisibility $visibility): void
    {
        $this->note = $note;
        $this->urgent = $urgent;
        $this->visibility = $visibility;
    }

    public function getTask(): ?Task
    {
        return $this->task;
    }

    public function getCreatedBy(): ?AppUser
    {
        return $this->createdBy;
    }

    public function getCreatedByDevice(): ?Device
    {
        return $this->createdByDevice;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getConfirmCount(): int
    {
        return $this->confirmCount;
    }

    public function getLastConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->lastConfirmedAt;
    }

    public function confirm(\DateTimeImmutable $at): void
    {
        ++$this->confirmCount;
        $this->lastConfirmedAt = $at;
    }

    public function getStatus(): ReportStatus
    {
        return $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function getFollowUp(): ?ReportFollowUp
    {
        return $this->followUp;
    }

    public function getFollowUpItem(): ?ShoppingItem
    {
        return $this->followUpItem;
    }

    public function getFollowUpTask(): ?Task
    {
        return $this->followUpTask;
    }

    /**
     * The report is now taken care of by this list entry or task; replaces an earlier follow-up.
     */
    public function followUpWith(ShoppingItem|Task $followUp): void
    {
        $this->followUp = $followUp instanceof ShoppingItem ? ReportFollowUp::Liste : ReportFollowUp::Aufgabe;
        $this->followUpItem = $followUp instanceof ShoppingItem ? $followUp : null;
        $this->followUpTask = $followUp instanceof Task ? $followUp : null;
        $this->followUpHandover = null;
        if ($this->isOpen()) {
            $this->status = ReportStatus::InArbeit;
        }
    }

    /**
     * The follow-up is gone (entry removed from its list): the report is open again.
     */
    public function dropFollowUp(): void
    {
        $this->followUp = null;
        $this->followUpItem = null;
        $this->followUpTask = null;
        $this->followUpHandover = null;
        if ($this->isOpen()) {
            $this->status = ReportStatus::Offen;
        }
    }

    /**
     * The follow-up did not work out (task skipped or not possible, list switched off): open again, so the admins
     * notice it; the link stays.
     */
    public function followUpFailed(): void
    {
        if ($this->isOpen()) {
            $this->status = ReportStatus::Offen;
        }
    }

    /**
     * "Rückgängig" after the follow-up failed: back to "in Arbeit".
     */
    public function resumeFollowUp(): void
    {
        if (ReportStatus::Offen === $this->status && null !== $this->followUp) {
            $this->status = ReportStatus::InArbeit;
        }
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function getClosedBy(): ?AppUser
    {
        return $this->closedBy;
    }

    public function getCloseNote(): ?string
    {
        return $this->closeNote;
    }

    /**
     * Closes the report: by an admin, or automatically (no person) when its follow-up is done.
     */
    public function close(ReportStatus $status, \DateTimeImmutable $at, ?AppUser $by, ?string $note, \DateTimeImmutable $deleteAfter): void
    {
        if ($status->isOpen()) {
            throw new \LogicException('A report closes as "erledigt" or "verworfen".');
        }
        $this->status = $status;
        $this->closedAt = $at;
        $this->closedBy = $by;
        $this->closeNote = $note;
        $this->deleteAfter = $deleteAfter;
    }

    public function isClosedAutomatically(): bool
    {
        return ReportStatus::Erledigt === $this->status && null === $this->closedBy;
    }

    /**
     * Back to "in Arbeit" (with a follow-up) or "offen" – after a ticked entry was taken back.
     */
    public function reopen(): void
    {
        $this->status = null === $this->followUp ? ReportStatus::Offen : ReportStatus::InArbeit;
        $this->closedAt = null;
        $this->closedBy = null;
        $this->closeNote = null;
        $this->deleteAfter = null;
    }

    public function getAnonymizedAt(): ?\DateTimeImmutable
    {
        return $this->anonymizedAt;
    }

    public function getDeleteAfter(): ?\DateTimeImmutable
    {
        return $this->deleteAfter;
    }
}
