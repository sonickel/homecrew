<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AuditVia;
use App\Repository\AuditLogRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Log of sensitive actions (person or device). Never holds IP addresses, passwords, codes or secrets.
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class, readOnly: true)]
#[ORM\Table(name: 'audit_log', options: ['comment' => 'Protokoll sensibler Vorgänge (keine IP-Adressen)'])]
#[ORM\Index(name: 'ix_audit_time', columns: ['created_at'])]
#[ORM\Index(name: 'ix_audit_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'ix_audit_device', columns: ['device_id'])]
#[ORM\Index(name: 'fk_audit_member', columns: ['actor_user_id'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'actor_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $actor;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'device_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Device $device;

    #[ORM\Column(name: 'via', length: 10, enumType: AuditVia::class, options: ['default' => 'app'])]
    private AuditVia $via;

    #[ORM\Column(name: 'action', length: 50)]
    private string $action;

    #[ORM\Column(name: 'entity_type', length: 50, nullable: true)]
    private ?string $entityType;

    #[ORM\Column(name: 'entity_id', type: Types::BIGINT, nullable: true, options: ['unsigned' => true])]
    private ?int $entityId;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'details', type: Types::JSON, nullable: true)]
    private ?array $details;

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

    /**
     * @param array<string, mixed>|null $details
     */
    public function __construct(
        string $action,
        AuditVia $via,
        ?AppUser $actor = null,
        ?Device $device = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null,
    ) {
        $this->action = $action;
        $this->via = $via;
        $this->actor = $actor;
        $this->device = $device;
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        $this->details = $details;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActor(): ?AppUser
    {
        return $this->actor;
    }

    public function getDevice(): ?Device
    {
        return $this->device;
    }

    public function getVia(): AuditVia
    {
        return $this->via;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function getEntityId(): ?int
    {
        return $this->entityId;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
