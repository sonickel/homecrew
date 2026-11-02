<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Message\PurgeAuditLog;
use App\MessageHandler\PurgeAuditLogHandler;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Retention job of the audit log (retention_audit_days, default 365 days).
 */
final class PurgeAuditLogTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testEntriesOlderThanTheRetentionPeriodAreDeleted(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        $connection->update('app_setting', ['retention_audit_days' => 30], ['id' => 1]);
        foreach (['2026-10-01 23:59:59', '2026-10-02 00:00:00', '2026-11-01 12:00:00'] as $time) {
            $connection->insert('audit_log', ['via' => 'system', 'action' => 'einrichtung.manager', 'created_at' => $time]);
        }
        self::mockTime('2026-11-01 00:00:00 UTC');

        $deleted = static::getContainer()->get(PurgeAuditLogHandler::class)(new PurgeAuditLog());

        self::assertSame(1, $deleted);
        self::assertSame(['2026-10-02 00:00:00', '2026-11-01 12:00:00'], $connection->fetchFirstColumn('SELECT created_at FROM audit_log ORDER BY created_at'));
    }
}
