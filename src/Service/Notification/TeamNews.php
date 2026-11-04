<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\IssueReport;
use App\Entity\ShoppingList;
use App\Repository\IssueReportRepository;
use App\Repository\ShoppingItemRepository;
use App\Service\Household\HouseholdContext;

/**
 * "Neu seit gestern" at the team tablet (konzept-12 §3), which has no notifications of its own: reports for everybody
 * and new list entries since yesterday morning. Handovers and appointments follow with M10 and M8.
 */
final readonly class TeamNews
{
    public function __construct(
        private IssueReportRepository $reportRepository,
        private ShoppingItemRepository $itemRepository,
        private HouseholdContext $household,
    ) {
    }

    /**
     * @return array{reports: list<IssueReport>, lists: list<array{list: ShoppingList, items: list<string>}>}
     */
    public function sinceYesterday(): array
    {
        $since = $this->household->today()->modify('-1 day');
        $lists = [];
        foreach ($this->itemRepository->findAddedSince($since) as $item) {
            $list = $item->getList();
            $lists[(int) $list->getId()] ??= ['list' => $list, 'items' => []];
            $lists[(int) $list->getId()]['items'][] = $item->getName();
        }

        return ['reports' => $this->reportRepository->findForAllSince($since), 'lists' => array_values($lists)];
    }
}
