<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\AnonymizeTaskCompletions;
use App\Message\PurgeDoneTasks;
use App\MessageHandler\AnonymizeTaskCompletionsHandler;
use App\MessageHandler\PurgeDoneTasksHandler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

/**
 * Retention jobs of tasks (konzept-5 §4.3): the person leaves completions after retention_completion_days
 * (the tablet stays), closed one-off tasks go after retention_done_task_days.
 */
final class TaskRetentionTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testThePersonIsRemovedFromOldCompletions(): void
    {
        $connection = $this->connection();
        $connection->update('app_setting', ['retention_completion_days' => 90], ['id' => 1]);
        $anna = $this->insertPerson();
        $connection->insert('device', ['name' => 'Flur', 'token_hash' => str_repeat('b', 64)]);
        $tablet = (int) $connection->lastInsertId();
        $old = $this->insertClosedTask('2026-08-04 23:59:59', userId: $anna);
        $oldAtTablet = $this->insertClosedTask('2026-08-01 10:00:00', deviceId: $tablet);
        $recent = $this->insertClosedTask('2026-08-05 00:00:00', userId: $anna);
        self::mockTime('2026-11-03 00:00:00 UTC');

        $handled = static::getContainer()->get(AnonymizeTaskCompletionsHandler::class)(new AnonymizeTaskCompletions());

        self::assertSame(2, $handled);
        $rows = $connection->fetchAllAssociativeIndexed('SELECT task_id, user_id, device_id, anonymized_at FROM task_completion');
        self::assertSame([null, null, '2026-11-03 00:00:00'], [$rows[$old]['user_id'], $rows[$old]['device_id'], $rows[$old]['anonymized_at']]);
        self::assertSame([null, $tablet], [$rows[$oldAtTablet]['user_id'], (int) $rows[$oldAtTablet]['device_id']]);
        self::assertSame([$anna, null], [(int) $rows[$recent]['user_id'], $rows[$recent]['anonymized_at']]);
    }

    public function testClosedOneOffTasksAreDeletedAfterTheRetentionPeriod(): void
    {
        $connection = $this->connection();
        $connection->update('app_setting', ['retention_done_task_days' => 365], ['id' => 1]);
        $old = $this->insertClosedTask('2025-11-02 23:59:59');
        $oldSkipped = $this->insertClosedTask('2025-06-01 10:00:00', action: 'uebersprungen');
        $recent = $this->insertClosedTask('2025-11-03 00:00:00');
        $connection->insert('task', ['title' => 'Offen seit Langem', 'category' => 'haushalt', 'due_date' => '2025-01-01']);
        $open = (int) $connection->lastInsertId();
        $connection->executeStatement("INSERT INTO task_template (title, category, recurrence_type, interval_days, starts_on) VALUES ('Reifen', 'hilfsmittel', 'intervall', 14, '2025-01-01')");
        $ofPlan = $this->insertClosedTask('2025-02-01 10:00:00', templateId: (int) $connection->lastInsertId());
        self::mockTime('2026-11-03 00:00:00 UTC');

        $deleted = static::getContainer()->get(PurgeDoneTasksHandler::class)(new PurgeDoneTasks());

        self::assertSame(2, $deleted);
        $remaining = array_map('intval', $connection->fetchFirstColumn('SELECT id FROM task ORDER BY id'));
        self::assertSame([$recent, $open, $ofPlan], $remaining);
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM task_completion WHERE task_id IN (?, ?)', [$old, $oldSkipped]));
    }

    public function testOfEveryPlanTheLatestCompletionsStay(): void
    {
        $connection = $this->connection();
        $connection->update('app_setting', ['retention_done_task_days' => 365, 'completion_keep_last' => 5], ['id' => 1]);
        $weekly = $this->insertPlan('Bettwäsche');
        $rare = $this->insertPlan('Rauchmelder');
        $weeklyTasks = [];
        foreach (['2024-11-04', '2024-11-11', '2024-11-18', '2025-01-06', '2025-01-13', '2025-01-20', '2025-11-03'] as $day) {
            $weeklyTasks[] = $this->insertClosedTask($day.' 10:00:00', action: '2025-01-13' === $day ? 'uebersprungen' : 'erledigt', templateId: $weekly);
        }
        $rareTasks = [$this->insertClosedTask('2023-03-01 10:00:00', templateId: $rare), $this->insertClosedTask('2024-03-01 10:00:00', templateId: $rare)];
        $daily = $this->insertPlan('Müll');
        $dailyTasks = [];
        foreach (['2026-10-28', '2026-10-29', '2026-10-30', '2026-10-31', '2026-11-01', '2026-11-02'] as $day) {
            $dailyTasks[] = $this->insertClosedTask($day.' 10:00:00', templateId: $daily);
        }
        $connection->insert('task', ['template_id' => $weekly, 'occurrence_date' => '2024-11-25', 'title' => 'Bettwäsche', 'category' => 'haushalt', 'due_date' => '2024-11-25']);
        $openOld = (int) $connection->lastInsertId();
        self::mockTime('2026-11-03 00:00:00 UTC');

        $deleted = static::getContainer()->get(PurgeDoneTasksHandler::class)(new PurgeDoneTasks());

        self::assertSame(2, $deleted, 'the two oldest of the weekly plan; the latest five, the rare plan and recent ones stay');
        $remaining = array_map('intval', $connection->fetchFirstColumn('SELECT id FROM task ORDER BY id'));
        self::assertSame([...\array_slice($weeklyTasks, 2), ...$rareTasks, ...$dailyTasks, $openOld], $remaining);
    }

    public function testTheLatestDoneOfAPlanStaysEvenAfterManySkips(): void
    {
        $connection = $this->connection();
        $connection->update('app_setting', ['retention_done_task_days' => 365, 'completion_keep_last' => 5], ['id' => 1]);
        $plan = $this->insertPlan('Fenster putzen');
        $older = $this->insertClosedTask('2024-01-08 10:00:00', templateId: $plan);
        $lastDone = $this->insertClosedTask('2024-02-05 10:00:00', templateId: $plan);
        $skipped = [];
        foreach (['2024-03-04', '2024-04-01', '2024-05-06', '2024-06-03', '2024-07-01'] as $day) {
            $skipped[] = $this->insertClosedTask($day.' 10:00:00', action: 'uebersprungen', templateId: $plan);
        }
        self::mockTime('2026-11-03 00:00:00 UTC');

        $deleted = static::getContainer()->get(PurgeDoneTasksHandler::class)(new PurgeDoneTasks());

        self::assertSame(1, $deleted);
        self::assertSame([$lastDone, ...$skipped], array_map('intval', $connection->fetchFirstColumn('SELECT id FROM task ORDER BY id')));
        self::assertNotContains($older, array_map('intval', $connection->fetchFirstColumn('SELECT id FROM task')));
    }

    private function insertPlan(string $title): int
    {
        $this->connection()->executeStatement(
            "INSERT INTO task_template (title, category, recurrence_type, rrule, starts_on) VALUES (:title, 'haushalt', 'kalender', 'FREQ=WEEKLY;BYDAY=MO', '2024-01-01')",
            ['title' => $title],
        );

        return (int) $this->connection()->lastInsertId();
    }

    private function insertClosedTask(string $doneAt, ?int $userId = null, ?int $deviceId = null, string $action = 'erledigt', ?int $templateId = null): int
    {
        $connection = $this->connection();
        $connection->insert('task', [
            'title' => 'Blumen gießen',
            'category' => 'haushalt',
            'due_date' => substr($doneAt, 0, 10),
            'status' => $action,
            'template_id' => $templateId,
            'occurrence_date' => null === $templateId ? null : substr($doneAt, 0, 10),
        ]);
        $taskId = (int) $connection->lastInsertId();
        $connection->insert('task_completion', ['task_id' => $taskId, 'action' => $action, 'user_id' => $userId, 'device_id' => $deviceId, 'done_at' => $doneAt]);

        return $taskId;
    }

    private function insertPerson(): int
    {
        $this->connection()->insert('app_user', ['public_id' => Uuid::v7()->toBinary(), 'role' => 'assistenz', 'username' => 'anna', 'display_name' => 'Anna', 'active_from' => '2026-01-01']);

        return (int) $this->connection()->lastInsertId();
    }

    private function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }
}
