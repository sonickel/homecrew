<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AnonymizeIssueReports;
use App\Repository\AppSettingRepository;
use App\Repository\IssueReportRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AnonymizeIssueReportsHandler
{
    public function __construct(
        private AppSettingRepository $settingRepository,
        private IssueReportRepository $reportRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AnonymizeIssueReports $message): int
    {
        $days = $this->settingRepository->get()->getRetentionCompletionDays();
        $now = $this->clock->now();

        return $this->reportRepository->anonymizeBefore($now->modify(\sprintf('-%d days', $days)), $now);
    }
}
