<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ShoppingSource;
use App\Repository\ShoppingItemRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Entry on a list (konzept-2 §9), added by a person or at the team tablet (then the device, konzept-12 §5).
 * Ticking off an entry that came from a report closes the report (App\Service\Report\ReportClosing);
 * ticked entries are removed in the night (App\MessageHandler\PurgeCheckedShoppingItemsHandler).
 */
#[ORM\Entity(repositoryClass: ShoppingItemRepository::class)]
#[ORM\Table(name: 'shopping_item', options: ['comment' => 'Einkaufsliste (Warnungen über profile_keyword)'])]
#[ORM\Index(name: 'ix_shopping_open', columns: ['list_id', 'is_done', 'category'])]
#[ORM\Index(name: 'ix_shopping_device', columns: ['created_by_device_id'])]
#[ORM\Index(name: 'fk_shopping_poi', columns: ['poi_id'])]
#[ORM\Index(name: 'fk_shopping_task', columns: ['source_task_id'])]
#[ORM\Index(name: 'fk_shopping_creator', columns: ['created_by_user_id'])]
class ShoppingItem
{
    public const int NAME_MAX = 100;
    public const int QUANTITY_MAX = 30;
    public const int NOTE_MAX = 255;
    public const int LINK_MAX = 500;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'list_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ShoppingList $list;

    #[ORM\Column(name: 'name', length: 100)]
    private string $name;

    #[ORM\Column(name: 'quantity', length: 30, nullable: true)]
    private ?string $quantity = null;

    /** Shop – places follow in M12. */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'poi_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Poi $poi = null;

    #[ORM\Column(name: 'category', length: 30, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(name: 'note', length: 255, nullable: true)]
    private ?string $note = null;

    /** https only (ck_shopping_link); opens a page outside HomeCrew. */
    #[ORM\Column(name: 'link', length: 500, nullable: true)]
    private ?string $link = null;

    #[ORM\Column(name: 'is_done', options: ['default' => false])]
    private bool $done = false;

    #[ORM\Column(name: 'done_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $doneAt = null;

    #[ORM\Column(name: 'source', length: 10, enumType: ShoppingSource::class, options: ['default' => 'manuell'])]
    private ShoppingSource $source;

    /** The task that was not possible ("nicht möglich" → on the list). */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'source_task_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Task $sourceTask = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $createdBy;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'created_by_device_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Device $createdByDevice;

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

    public function __construct(ShoppingList $list, string $name, AppUser|Device $by, ShoppingSource $source = ShoppingSource::Manuell, ?Task $sourceTask = null)
    {
        $this->list = $list;
        $this->name = $name;
        $this->createdBy = $by instanceof AppUser ? $by : null;
        $this->createdByDevice = $by instanceof Device ? $by : null;
        $this->source = $source;
        $this->sourceTask = $sourceTask;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getList(): ShoppingList
    {
        return $this->list;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function getLink(): ?string
    {
        return $this->link;
    }

    public function describe(string $name, ?string $quantity, ?string $note, ?string $link): void
    {
        $this->name = $name;
        $this->quantity = $quantity;
        $this->note = $note;
        $this->link = $link;
    }

    public function isDone(): bool
    {
        return $this->done;
    }

    public function getDoneAt(): ?\DateTimeImmutable
    {
        return $this->doneAt;
    }

    public function check(\DateTimeImmutable $at): void
    {
        $this->done = true;
        $this->doneAt = $at;
    }

    public function uncheck(): void
    {
        $this->done = false;
        $this->doneAt = null;
    }

    public function getSource(): ShoppingSource
    {
        return $this->source;
    }

    public function getSourceTask(): ?Task
    {
        return $this->sourceTask;
    }

    public function getCreatedBy(): ?AppUser
    {
        return $this->createdBy;
    }

    public function getCreatedByDevice(): ?Device
    {
        return $this->createdByDevice;
    }

    /**
     * True for the person or tablet that added the entry.
     */
    public function isBy(AppUser|Device $actor): bool
    {
        $by = $actor instanceof AppUser ? $this->createdBy : $this->createdByDevice;

        return null !== $by && ($by === $actor || (null !== $actor->getId() && $by->getId() === $actor->getId()));
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
