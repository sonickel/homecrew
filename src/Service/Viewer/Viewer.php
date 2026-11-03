<?php

declare(strict_types=1);

namespace App\Service\Viewer;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\UserRole;

/**
 * Who looks at the app right now: a logged-in person or the team tablet without personal login.
 * "Admin" means App\Security\Permission::ADMIN – never after a PIN login, never at the team tablet.
 * Repositories and App\Service\Visibility\VisibilityRules get the viewer instead of the security token.
 */
final readonly class Viewer
{
    private function __construct(
        private ?AppUser $person,
        private ?Device $device,
        private bool $admin,
    ) {
    }

    public static function person(AppUser $person, bool $admin): self
    {
        return new self($person, null, $admin);
    }

    public static function teamTablet(Device $device): self
    {
        return new self(null, $device, false);
    }

    public function getPerson(): ?AppUser
    {
        return $this->person;
    }

    public function isTeamTablet(): bool
    {
        return null !== $this->device;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    /**
     * The team tablet has no role of its own – it sees what the role Assistenz sees (konzept-12 §1).
     */
    public function hasRole(UserRole $role): bool
    {
        return $this->person?->getRole() === $role;
    }

    public function isPerson(AppUser $person): bool
    {
        return null !== $this->person
            && ($this->person === $person || (null !== $person->getId() && $this->person->getId() === $person->getId()));
    }

    /**
     * Who is stored as having done something: the person, or the tablet instead of a person.
     */
    public function actor(): AppUser|Device
    {
        $actor = $this->person ?? $this->device;
        \assert(null !== $actor);

        return $actor;
    }
}
