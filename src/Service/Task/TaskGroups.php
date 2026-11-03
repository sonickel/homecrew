<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\Task;

/**
 * Open tasks sorted into the groups of "Heute" – each group in the order the tasks should be done.
 */
final readonly class TaskGroups
{
    /**
     * @param array<value-of<DueGroup>, list<Task>> $groups
     */
    private function __construct(
        private array $groups,
    ) {
    }

    /**
     * @param list<Task> $tasks
     * @param string     $today household date Y-m-d
     */
    public static function sort(array $tasks, string $today): self
    {
        $groups = array_fill_keys(array_map(static fn (DueGroup $group): string => $group->value, DueGroup::cases()), []);
        foreach ($tasks as $task) {
            $groups[DueGroup::of($task, $today)->value][] = $task;
        }
        foreach ($groups as &$group) {
            usort($group, self::compare(...));
        }

        return new self($groups);
    }

    /**
     * @return list<array{group: DueGroup, tasks: list<Task>}> only groups with tasks, in the order of DueGroup
     */
    public function nonEmpty(): array
    {
        $result = [];
        foreach (DueGroup::cases() as $group) {
            if ([] !== $this->groups[$group->value]) {
                $result[] = ['group' => $group, 'tasks' => $this->groups[$group->value]];
            }
        }

        return $result;
    }

    /**
     * @return list<Task>
     */
    public function get(DueGroup $group): array
    {
        return $this->groups[$group->value];
    }

    public function count(): int
    {
        return array_sum(array_map(\count(...), $this->groups));
    }

    /**
     * Earlier due date first, then by time (tasks without time after those with), important ones first, then by title.
     */
    private static function compare(Task $a, Task $b): int
    {
        return [$a->getDueDate()->format('Y-m-d'), $a->getDueTime()?->format('H:i') ?? '99:99', $a->isImportant() ? 0 : 1, mb_strtolower($a->getTitle())]
            <=> [$b->getDueDate()->format('Y-m-d'), $b->getDueTime()?->format('H:i') ?? '99:99', $b->isImportant() ? 0 : 1, mb_strtolower($b->getTitle())];
    }
}
