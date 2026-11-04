<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeIssueReports;
use App\Repository\IssueReportRepository;
use App\Service\Household\HouseholdContext;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The delete date is set when a report is closed (closing day + retention_report_days).
 */
#[AsMessageHandler]
final readonly class PurgeIssueReportsHandler
{
    public function __construct(
        private IssueReportRepository $reportRepository,
        private HouseholdContext $household,
    ) {
    }

    public function __invoke(PurgeIssueReports $message): int
    {
        return $this->reportRepository->deleteExpired($this->household->today());
    }
}
