<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\AppUser;
use App\Enum\AssigneeRole;
use App\Repository\AppUserRepository;
use App\Service\Household\HouseholdContext;

/**
 * The choices for "Zuständig": nobody in particular, the admins, all assistants, all relatives, and every person
 * who still belongs to the household. The manager is chosen by name – role "manager" is the same single person.
 */
final readonly class AssigneeOptions
{
    public function __construct(
        private AppUserRepository $userRepository,
        private HouseholdContext $household,
    ) {
    }

    /**
     * @param Assignment|null $current kept as a choice even if that person has left in the meantime
     *
     * @return list<Assignment>
     */
    public function choices(?Assignment $current = null): array
    {
        $choices = [
            Assignment::present(),
            Assignment::role(AssigneeRole::Admins),
            Assignment::role(AssigneeRole::Assistenz),
            Assignment::role(AssigneeRole::Angehoerige),
        ];
        $today = $this->household->today();
        $persons = array_filter(
            $this->userRepository->findBy([], ['displayName' => 'ASC']),
            static fn (AppUser $person): bool => !$person->isBlockedOn($today),
        );
        foreach ($persons as $person) {
            $choices[] = Assignment::person($person);
        }
        if (null !== $current && [] === array_filter($choices, $current->equals(...))) {
            $choices[] = $current;
        }

        return $choices;
    }
}
