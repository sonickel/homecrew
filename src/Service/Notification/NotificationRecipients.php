<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\AppUser;
use App\Repository\AppUserRepository;
use App\Service\Household\HouseholdContext;
use App\Service\Task\Assignment;
use App\Service\Viewer\Viewer;
use App\Service\Visibility\Assignable;
use App\Service\Visibility\VisibilityRules;

/**
 * Who is to be told: the admins, or whoever is responsible for a task or list – a person, everybody with a role,
 * for "wer gerade da ist" the assistants (as App\Service\Visibility\VisibilityRules decides, decision 04.11.).
 * Only persons who can log in (App\Repository\AppUserRepository::findReachable).
 */
final readonly class NotificationRecipients
{
    public function __construct(
        private AppUserRepository $userRepository,
        private VisibilityRules $rules,
        private HouseholdContext $household,
    ) {
    }

    /**
     * @return list<AppUser>
     */
    public function admins(): array
    {
        return array_values(array_filter($this->reachable(), static fn (AppUser $person): bool => $person->isAdmin()));
    }

    /**
     * @return list<AppUser>
     */
    public function responsibleFor(Assignment $assignment): array
    {
        return array_values(array_filter(
            $this->reachable(),
            fn (AppUser $person): bool => $this->rules->isAssigned($this->viewer($person), $assignment->person, $assignment->role),
        ));
    }

    /**
     * Whoever is responsible for the task and may see it.
     *
     * @return list<AppUser>
     */
    public function responsibleForTask(Assignable $task): array
    {
        return array_values(array_filter(
            $this->responsibleFor(Assignment::of($task)),
            fn (AppUser $person): bool => $this->rules->canSee($this->viewer($person), $task),
        ));
    }

    /**
     * @return list<AppUser>
     */
    private function reachable(): array
    {
        return $this->userRepository->findReachable($this->household->today());
    }

    /**
     * The person as viewer – admin by their right, not by the current login (that only matters for what they may do).
     */
    private function viewer(AppUser $person): Viewer
    {
        return Viewer::person($person, $person->isAdmin());
    }
}
