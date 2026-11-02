<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeAuditLog;
use App\Repository\AppSettingRepository;
use App\Repository\AuditLogRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgeAuditLogHandler
{
    public function __construct(
        private AppSettingRepository $settingRepository,
        private AuditLogRepository $auditLogRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PurgeAuditLog $message): int
    {
        $days = $this->settingRepository->get()->getRetentionAuditDays();

        return $this->auditLogRepository->deleteOlderThan($this->clock->now()->modify(\sprintf('-%d days', $days)));
    }
}
