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
