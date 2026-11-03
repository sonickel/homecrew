<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\AppUser;
use App\Enum\AssigneeRole;
use App\Service\Visibility\Assignable;

/**
 * Who is responsible for a task: a person, a role or nobody in particular ("wer gerade da ist").
 * The key identifies the choice in forms ("da", "rolle-admins", "person-<public id>").
 */
final readonly class Assignment
{
    private const string PRESENT = 'da';

    private function __construct(
        public ?AppUser $person,
        public ?AssigneeRole $role,
    ) {
    }

    public static function person(AppUser $person): self
    {
        return new self($person, null);
    }

    public static function role(AssigneeRole $role): self
    {
        return new self(null, $role);
    }

    public static function present(): self
    {
        return new self(null, null);
    }

    public static function of(Assignable $item): self
    {
        $person = $item->getAssigneeUser();

        return null !== $person ? self::person($person) : new self(null, $item->getAssigneeRole());
    }

    public function key(): string
    {
        return match (true) {
            null !== $this->person => 'person-'.$this->person->getPublicId()->toRfc4122(),
            null !== $this->role => 'rolle-'.$this->role->value,
            default => self::PRESENT,
        };
    }

    public function label(): string
    {
        return $this->person?->getDisplayName() ?? $this->role?->label() ?? 'Wer gerade da ist';
    }

    public function equals(self $other): bool
    {
        return $this->key() === $other->key();
    }
}
