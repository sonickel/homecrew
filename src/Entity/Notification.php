<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NotificationKind;
use App\Repository\NotificationRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * In-app notification of a person (bell, "Neu seit deinem letzten Besuch", konzept-9 §5) – created by
 * App\Service\Notification\Notifier, deleted after retention_notification_days. The team tablet gets none.
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notification', options: ['comment' => 'Benachrichtigungen in der App (Glocke, „seit deinem letzten Besuch") – Push nur optional'])]
#[ORM\Index(name: 'ix_notification_inbox', columns: ['user_id', 'read_at', 'created_at'])]
#[ORM\Index(name: 'ix_notification_cleanup', columns: ['delete_after'])]
class Notification
{
    public const int TITLE_MAX = 150;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AppUser $user;

    #[ORM\Column(name: 'kind', length: 12, enumType: NotificationKind::class)]
    private NotificationKind $kind;

    #[ORM\Column(name: 'title', length: 150)]
    private string $title;

    #[ORM\Column(name: 'body', length: 500, nullable: true)]
    private ?string $body = null;

    /** Path inside the app, e.g. /meldungen/…. */
    #[ORM\Column(name: 'link', length: 255, nullable: true)]
    private ?string $link;

    #[ORM\Column(name: 'is_urgent', options: ['default' => false])]
    private bool $urgent;

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

    #[ORM\Column(name: 'read_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(name: 'pushed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $pushedAt = null;

    #[ORM\Column(name: 'mailed_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $mailedAt = null;

    #[ORM\Column(name: 'delete_after', type: Types::DATE_IMMUTABLE)]
    private \DateTimeImmutable $deleteAfter;

    public function __construct(AppUser $user, NotificationKind $kind, string $title, ?string $link, bool $urgent, \DateTimeImmutable $deleteAfter)
    {
        $this->user = $user;
        $this->kind = $kind;
        $this->title = mb_substr($title, 0, self::TITLE_MAX);
        $this->link = $link;
        $this->urgent = $urgent;
        $this->deleteAfter = $deleteAfter;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): AppUser
    {
        return $this->user;
    }

    public function getKind(): NotificationKind
    {
        return $this->kind;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function setBody(?string $body): void
    {
        $this->body = null === $body ? null : mb_substr($body, 0, 500);
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function isUrgent(): bool
    {
        return $this->urgent;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isRead(): bool
    {
        return null !== $this->readAt;
    }

    public function markRead(\DateTimeImmutable $at): void
    {
        $this->readAt ??= $at;
    }

    public function getDeleteAfter(): \DateTimeImmutable
    {
        return $this->deleteAfter;
    }
}
