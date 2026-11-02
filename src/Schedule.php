<?php

declare(strict_types=1);

namespace App;

use App\Message\PurgeAuditLog;
use App\Message\PurgeInvitations;
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

            // Retention jobs at night (household time)
            ->add(RecurringMessage::every('1 day', new PurgeAuditLog(), from: new \DateTimeImmutable('03:30', new \DateTimeZone('Europe/Berlin'))))
            ->add(RecurringMessage::every('1 day', new PurgeInvitations(), from: new \DateTimeImmutable('03:35', new \DateTimeZone('Europe/Berlin'))))
        ;
    }
}
