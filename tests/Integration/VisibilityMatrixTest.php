<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\Task;
use App\Enum\AssigneeRole;
use App\Enum\TaskCategory;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Service\Viewer\Viewer;
use App\Service\Visibility\VisibilityRules;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Visibility × responsibility × creator × viewer: the query conditions of VisibilityRules return exactly
 * the tasks the PHP rules allow – for lists ("Offene Aufgaben"), "Für dich" and the admins' assistants block.
 */
final class VisibilityMatrixTest extends KernelTestCase
{
    /** @var array<string, AppUser> */
    private array $people = [];

    /** @var list<Task> */
    private array $tasks = [];

    private Device $tablet;

    protected function setUp(): void
    {
        $entityManager = $this->entityManager();
        foreach ([['max', UserRole::Manager, true], ['schwester', UserRole::Angehoerige, true], ['papa', UserRole::Angehoerige, false], ['anna', UserRole::Assistenz, false], ['ben', UserRole::Assistenz, false]] as [$name, $role, $admin]) {
            $this->people[$name] = new AppUser($role, $name, ucfirst($name), new \DateTimeImmutable('2026-01-01'), $admin);
            $entityManager->persist($this->people[$name]);
        }
        $this->tablet = new Device('Flur', hash('sha256', 'matrix'));
        $entityManager->persist($this->tablet);

        $assignees = [...array_keys($this->people), ...AssigneeRole::cases(), null];
        foreach (Visibility::cases() as $visibility) {
            foreach ($assignees as $assignee) {
                foreach (['max', 'papa', 'anna', null] as $creator) {
                    $task = new Task('Aufgabe', TaskCategory::Haushalt, new \DateTimeImmutable('2026-11-03'), null === $creator ? null : $this->people[$creator]);
                    $task->assign(\is_string($assignee) ? $this->people[$assignee] : null, $assignee instanceof AssigneeRole ? $assignee : null);
                    $task->setVisibility($visibility);
                    $entityManager->persist($task);
                    $this->tasks[] = $task;
                }
            }
        }
        $entityManager->flush();
    }

    public function testQueriesApplyTheSameRulesAsPhp(): void
    {
        $rules = new VisibilityRules();
        $viewers = [
            'Max' => Viewer::person($this->people['max'], true),
            'Schwester' => Viewer::person($this->people['schwester'], true),
            'Schwester per PIN' => Viewer::person($this->people['schwester'], false),
            'Papa' => Viewer::person($this->people['papa'], false),
            'Anna' => Viewer::person($this->people['anna'], false),
            'Ben' => Viewer::person($this->people['ben'], false),
            'Team-Tablet' => Viewer::teamTablet($this->tablet),
        ];

        foreach ($viewers as $name => $viewer) {
            $visible = array_filter($this->tasks, static fn (Task $task): bool => $rules->canSee($viewer, $task));
            $qb = $this->query();
            $rules->whereVisible($qb, 't', $viewer);
            self::assertSame(self::ids($visible), self::resultIds($qb), $name.': sichtbar');

            $forViewer = array_filter($visible, static fn (Task $task): bool => $rules->isForViewer($viewer, $task));
            $qb = $this->query();
            $rules->whereVisible($qb, 't', $viewer);
            $rules->whereForViewer($qb, 't', 'assignee', $viewer);
            self::assertSame(self::ids($forViewer), self::resultIds($qb), $name.': für dich');

            $forAssistants = array_filter($visible, $rules->isForAssistants(...));
            $qb = $this->query();
            $rules->whereVisible($qb, 't', $viewer);
            $rules->whereForAssistants($qb, 't', 'assignee');
            self::assertSame(self::ids($forAssistants), self::resultIds($qb), $name.': Aufgaben der Assistenz');
        }
    }

    private function query(): QueryBuilder
    {
        return $this->entityManager()->createQueryBuilder()
            ->select('t')
            ->from(Task::class, 't')
            ->leftJoin('t.assigneeUser', 'assignee');
    }

    /**
     * @param array<Task> $tasks
     *
     * @return list<int>
     */
    private static function ids(array $tasks): array
    {
        $ids = array_map(static fn (Task $task): int => (int) $task->getId(), array_values($tasks));
        sort($ids);

        return $ids;
    }

    /**
     * @return list<int>
     */
    private static function resultIds(QueryBuilder $qb): array
    {
        /** @var list<Task> $tasks */
        $tasks = $qb->getQuery()->getResult();

        return self::ids($tasks);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
