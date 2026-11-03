<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AssigneeRole;
use App\Repository\ShoppingListRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A list such as "Einkauf" or "Besorgt die Familie" with the person or role who takes care of it (konzept-2 §9).
 * Created in M5 because plans suggest a list for "nicht möglich"; the list pages follow in M7.
 */
#[ORM\Entity(repositoryClass: ShoppingListRepository::class)]
#[ORM\Table(name: 'shopping_list', options: ['comment' => 'Mehrere Listen (Einkauf, Besorgen durch Familie …) – Ziel u. a. bei „nicht möglich"'])]
#[ORM\UniqueConstraint(name: 'uq_shopping_list_name', columns: ['name'])]
#[ORM\Index(name: 'ix_shopping_list_assignee', columns: ['assignee_user_id'])]
class ShoppingList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 100)]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'assignee_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $assigneeUser = null;

    #[ORM\Column(name: 'assignee_role', length: 12, nullable: true, enumType: AssigneeRole::class)]
    private ?AssigneeRole $assigneeRole = null;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'sort', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    private int $sort = 0;

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

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
