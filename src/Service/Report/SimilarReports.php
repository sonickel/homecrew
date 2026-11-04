<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\IssueReport;
use App\Repository\IssueReportRepository;
use App\Service\Viewer\Viewer;

/**
 * Open reports with a similar title that the viewer may see ("Schon gemeldet?" – konzept-13 §2).
 */
final readonly class SimilarReports
{
    public const int LIMIT = 5;

    public function __construct(private IssueReportRepository $reportRepository)
    {
    }

    /**
     * @return list<IssueReport>
     */
    public function find(string $title, Viewer $viewer): array
    {
        if ('' === trim($title)) {
            return [];
        }
        $similar = array_filter(
            $this->reportRepository->findOpenVisibleTo($viewer),
            static fn (IssueReport $report): bool => TitleSimilarity::isSimilar($title, $report->getTitle()),
        );

        return \array_slice(array_values($similar), 0, self::LIMIT);
    }
}
