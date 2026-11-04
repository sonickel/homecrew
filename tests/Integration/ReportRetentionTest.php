<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\AnonymizeIssueReports;
use App\Message\PurgeCheckedShoppingItems;
use App\Message\PurgeIssueReports;
use App\Message\PurgeNotifications;
use App\MessageHandler\AnonymizeIssueReportsHandler;
use App\MessageHandler\PurgeCheckedShoppingItemsHandler;
use App\MessageHandler\PurgeIssueReportsHandler;
use App\MessageHandler\PurgeNotificationsHandler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

/**
 * Retention jobs of reports, notifications and lists (konzept-13 §8, konzept-9 §7): the person leaves reports after
 * retention_completion_days (the tablet stays), closed reports and notifications go after their delete date, open
 * reports stay; entries ticked off before today disappear from the lists.
 */
final class ReportRetentionTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testThePersonIsRemovedFromOldReports(): void
    {
        $connection = $this->connection();
        $connection->update('app_setting', ['retention_completion_days' => 90], ['id' => 1]);
        $anna = $this->insertPerson();
        $connection->insert('device', ['name' => 'Flur', 'token_hash' => str_repeat('c', 64)]);
        $tablet = (int) $connection->lastInsertId();
        $old = $this->insertReport('2026-08-05 23:59:59', ['created_by_user_id' => $anna]);
        $oldAtTablet = $this->insertReport('2026-08-01 10:00:00', ['created_by_device_id' => $tablet]);
        $recent = $this->insertReport('2026-08-06 00:00:00', ['created_by_user_id' => $anna]);
        self::mockTime('2026-11-04 00:00:00 UTC');

        $handled = static::getContainer()->get(AnonymizeIssueReportsHandler::class)(new AnonymizeIssueReports());

        self::assertSame(2, $handled);
        $rows = $connection->fetchAllAssociativeIndexed('SELECT id, created_by_user_id, created_by_device_id, anonymized_at FROM issue_report');
        self::assertSame([null, '2026-11-04 00:00:00'], [$rows[$old]['created_by_user_id'], $rows[$old]['anonymized_at']]);
        self::assertSame($tablet, (int) $rows[$oldAtTablet]['created_by_device_id']);
        self::assertSame([$anna, null], [(int) $rows[$recent]['created_by_user_id'], $rows[$recent]['anonymized_at']]);
    }

    public function testClosedReportsGoAfterTheirDeleteDateOpenOnesStay(): void
    {
        $closed = ['status' => 'erledigt', 'closed_at' => '2025-11-01 10:00:00'];
        $this->insertReport('2025-10-01 10:00:00', [...$closed, 'delete_after' => '2026-11-03']);
        $lastDay = $this->insertReport('2025-10-01 10:00:00', [...$closed, 'status' => 'verworfen', 'delete_after' => '2026-11-04']);
        $open = $this->insertReport('2024-01-01 10:00:00', []);
        self::mockTime('2026-11-04 08:00:00 UTC');

        $deleted = static::getContainer()->get(PurgeIssueReportsHandler::class)(new PurgeIssueReports());

        self::assertSame(1, $deleted);
        self::assertSame([$lastDay, $open], array_map('intval', $this->connection()->fetchFirstColumn('SELECT id FROM issue_report ORDER BY id')));
    }

    public function testNotificationsGoAfterTheirDeleteDate(): void
    {
        $connection = $this->connection();
        $anna = $this->insertPerson();
        foreach (['2026-11-03', '2026-11-04'] as $deleteAfter) {
            $connection->insert('notification', ['user_id' => $anna, 'kind' => 'meldung', 'title' => 'Neue Meldung', 'delete_after' => $deleteAfter]);
        }
        self::mockTime('2026-11-04 08:00:00 UTC');

        $deleted = static::getContainer()->get(PurgeNotificationsHandler::class)(new PurgeNotifications());

        self::assertSame(1, $deleted);
        self::assertSame(['2026-11-04'], $connection->fetchFirstColumn('SELECT delete_after FROM notification'));
    }

    public function testEntriesTickedOffBeforeTodayDisappear(): void
    {
        $connection = $this->connection();
        $connection->insert('shopping_list', ['name' => 'Einkauf']);
        $list = (int) $connection->lastInsertId();
        // Household time Europe/Berlin: today began on 3 November 23:00 UTC
        $rows = [['Milch', 1, '2026-11-03 22:59:59'], ['Brot', 1, '2026-11-03 23:00:00'], ['Äpfel', 0, null]];
        foreach ($rows as [$name, $done, $doneAt]) {
            $connection->insert('shopping_item', ['list_id' => $list, 'name' => $name, 'is_done' => $done, 'done_at' => $doneAt]);
        }
        self::mockTime('2026-11-04 08:00:00 UTC');

        $deleted = static::getContainer()->get(PurgeCheckedShoppingItemsHandler::class)(new PurgeCheckedShoppingItems());

        self::assertSame(1, $deleted);
        self::assertSame(['Brot', 'Äpfel'], $connection->fetchFirstColumn('SELECT name FROM shopping_item ORDER BY id'));
    }

    /**
     * @param array<string, mixed> $columns
     */
    private function insertReport(string $createdAt, array $columns): int
    {
        $this->connection()->insert('issue_report', ['public_id' => Uuid::v7()->toBinary(), 'kind' => 'leer', 'title' => 'Seife leer', 'created_at' => $createdAt, ...$columns]);

        return (int) $this->connection()->lastInsertId();
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
