<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CreatedVia;
use App\Enum\HandoverNoteType;
use App\Repository\HandoverNoteRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entry in the handover book (konzept-2 §7), written by a person or at the team tablet (then the device).
 * Created in M7 because a report may be followed up by a handover; the handover book follows in M10.
 */
#[ORM\Entity(repositoryClass: HandoverNoteRepository::class)]
#[ORM\Table(name: 'handover_note', options: ['comment' => 'Übergabebuch'])]
#[ORM\Index(name: 'ix_handover_feed', columns: ['created_at'])]
#[ORM\Index(name: 'ix_handover_cleanup', columns: ['delete_after'])]
#[ORM\Index(name: 'ix_handover_device', columns: ['device_id'])]
#[ORM\Index(name: 'fk_handover_parent', columns: ['parent_id'])]
#[ORM\Index(name: 'fk_handover_author', columns: ['author_user_id'])]
#[ORM\Index(name: 'fk_handover_task', columns: ['related_task_id'])]
#[ORM\Index(name: 'fk_handover_asset', columns: ['related_asset_id'])]
class HandoverNote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    /** Answer to this note (one level). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?HandoverNote $parent = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'author_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $author;

    /** Written at the team tablet. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'device_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Device $device;

    #[ORM\Column(name: 'note_type', length: 10, enumType: HandoverNoteType::class, options: ['default' => 'info'])]
    private HandoverNoteType $type = HandoverNoteType::Info;

    #[ORM\Column(name: 'body', length: 1000)]
    private string $body;

    #[ORM\Column(name: 'is_pinned', options: ['default' => false])]
    private bool $pinned = false;

    #[ORM\Column(name: 'pinned_until', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pinnedUntil = null;

    #[ORM\Column(name: 'resolved_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'related_task_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Task $relatedTask = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'related_asset_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Asset $relatedAsset = null;

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

    #[ORM\Column(name: 'delete_after', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $deleteAfter;

    public function __construct(string $body, AppUser|Device $by, \DateTimeImmutable $deleteAfter)
    {
        $this->body = $body;
        $this->author = $by instanceof AppUser ? $by : null;
        $this->device = $by instanceof Device ? $by : null;
        $this->deleteAfter = $deleteAfter;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
