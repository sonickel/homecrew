<?php

declare(strict_types=1);

namespace App;

use App\Message\AnonymizeIssueReports;
use App\Message\AnonymizeTaskCompletions;
use App\Message\GenerateTaskOccurrences;
use App\Message\PurgeAuditLog;
use App\Message\PurgeCheckedShoppingItems;
use App\Message\PurgeDoneTasks;
use App\Message\PurgeInvitations;
use App\Message\PurgeIssueReports;
use App\Message\PurgeNotifications;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Recurring jobs; run by the worker: php bin/console messenger:consume scheduler_default.
 */
#[AsSchedule]
final class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        return (new SymfonySchedule())
            ->stateful($this->cache) // ensure missed tasks are executed
            ->processOnlyLastMissedRun(true) // ensure only last missed task is run

            // Occurrences of the plans for the new day, before the retention jobs
            ->add(RecurringMessage::every('1 day', new GenerateTaskOccurrences(), from: new \DateTimeImmutable('03:20', new \DateTimeZone('Europe/Berlin'))))

            // Retention jobs at night (household time)
            ->add(RecurringMessage::every('1 day', new PurgeAuditLog(), from: new \DateTimeImmutable('03:30', new \DateTimeZone('Europe/Berlin'))))
            ->add(RecurringMessage::every('1 day', new PurgeInvitations(), from: new \DateTimeImmutable('03:35', new \DateTimeZone('Europe/Berlin'))))
            ->add(RecurringMessage::every('1 day', new AnonymizeTaskCompletions(), from: new \DateTimeImmutable('03:40', new \DateTimeZone('Europe/Berlin'))))
            ->add(RecurringMessage::every('1 day', new PurgeDoneTasks(), from: new \DateTimeImmutable('03:45', new \DateTimeZone('Europe/Berlin'))))
            ->add(RecurringMessage::every('1 day', new AnonymizeIssueReports(), from: new \DateTimeImmutable('03:50', new \DateTimeZone('Europe/Berlin'))))
            ->add(RecurringMessage::every('1 day', new PurgeIssueReports(), from: new \DateTimeImmutable('03:55', new \DateTimeZone('Europe/Berlin'))))
            ->add(RecurringMessage::every('1 day', new PurgeNotifications(), from: new \DateTimeImmutable('04:00', new \DateTimeZone('Europe/Berlin'))))
            ->add(RecurringMessage::every('1 day', new PurgeCheckedShoppingItems(), from: new \DateTimeImmutable('04:05', new \DateTimeZone('Europe/Berlin'))))
        ;
    }
}
