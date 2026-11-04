<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AssigneeRole;
use App\Repository\ShoppingListRepository;
use Doctrine\DBAL\Schema\DefaultExpression\CurrentTimestamp;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * A list such as "Einkauf" or "Besorgt die Familie" with the person or role who takes care of it (konzept-2 §9).
 * Everybody incl. the team tablet uses all active lists; admins keep them in Verwaltung → Listen. Lists are not
 * deleted, only switched off – plans and reports keep pointing at them.
 */
#[ORM\Entity(repositoryClass: ShoppingListRepository::class)]
#[ORM\Table(name: 'shopping_list', options: ['comment' => 'Mehrere Listen (Einkauf, Besorgen durch Familie …) – Ziel u. a. bei „nicht möglich"'])]
#[ORM\UniqueConstraint(name: 'uq_shopping_list_name', columns: ['name'])]
#[ORM\Index(name: 'ix_shopping_list_assignee', columns: ['assignee_user_id'])]
#[UniqueEntity(fields: ['name'], message: 'Eine Liste mit diesem Namen gibt es schon.')]
class ShoppingList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(name: 'name', length: 100)]
    #[Assert\NotBlank(message: 'Bitte gib einen Namen ein.')]
    #[Assert\Length(max: 100, maxMessage: 'Höchstens {{ limit }} Zeichen.')]
    private string $name;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'assignee_user_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AppUser $assigneeUser = null;

    #[ORM\Column(name: 'assignee_role', length: 12, nullable: true, enumType: AssigneeRole::class)]
    private ?AssigneeRole $assigneeRole = null;

    #[ORM\Column(name: 'is_active', options: ['default' => true])]
    private bool $isActive = true;

    #[ORM\Column(name: 'sort', type: Types::SMALLINT, options: ['unsigned' => true, 'default' => 0])]
    #[Assert\Range(min: 0, max: 999, notInRangeMessage: 'Bitte eine Zahl von {{ min }} bis {{ max }}.')]
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

    public function __construct(string $name = '')
    {
        $this->name = $name;
    }

    #[Assert\Callback]
    public function validateAssignee(ExecutionContextInterface $context): void
    {
        if (null !== $this->assigneeUser && null !== $this->assigneeRole) {
            $context->buildViolation('Wähle eine Person oder eine Rolle, nicht beides.')->atPath('assigneeRole')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = trim((string) $name);
    }

    /**
     * Who takes care of the list: a person – or a role (both null = nobody in particular).
     */
    public function getAssigneeUser(): ?AppUser
    {
        return $this->assigneeUser;
    }

    public function setAssigneeUser(?AppUser $assigneeUser): void
    {
        $this->assigneeUser = $assigneeUser;
    }

    public function getAssigneeRole(): ?AssigneeRole
    {
        return $this->assigneeRole;
    }

    public function setAssigneeRole(?AssigneeRole $assigneeRole): void
    {
        $this->assigneeRole = $assigneeRole;
    }

    public function hasAssignee(): bool
    {
        return null !== $this->assigneeUser || null !== $this->assigneeRole;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setActive(bool $active): void
    {
        $this->isActive = $active;
    }

    public function getSort(): int
    {
        return $this->sort;
    }

    public function setSort(?int $sort): void
    {
        $this->sort = $sort ?? 0;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
