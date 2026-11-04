<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeCheckedShoppingItems;
use App\Repository\ShoppingItemRepository;
use App\Service\Household\HouseholdContext;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * "Heute abgehakt" can be taken back until the end of the day; afterwards the entries are gone.
 */
#[AsMessageHandler]
final readonly class PurgeCheckedShoppingItemsHandler
{
    public function __construct(
        private ShoppingItemRepository $itemRepository,
        private HouseholdContext $household,
    ) {
    }

    public function __invoke(PurgeCheckedShoppingItems $message): int
    {
        return $this->itemRepository->deleteCheckedBefore($this->household->today());
    }
}
