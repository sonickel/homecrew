<?php

declare(strict_types=1);

namespace App\Service\Shopping;

use App\Entity\ShoppingFavorite;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Entity\Task;
use App\Enum\ShoppingSource;
use App\Form\Shopping\ShoppingItemData;
use App\Repository\ShoppingItemRepository;
use App\Service\Notification\NotificationRecipients;
use App\Service\Notification\Notifier;
use App\Service\Report\ReportClosing;
use App\Service\Task\Assignment;
use App\Service\Viewer\Viewer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * What people do on lists (konzept-2 §9): add – by hand, from a favourite or from a report –, tick off, take back,
 * change and remove. Entries carry the person or the team tablet. Whoever takes care of the list is told about
 * entries of others (decision 04.11.); ticking off closes the reports that wait for the entry (ReportClosing).
 * The callers check the rights (App\Security\Voter\ShoppingVoter).
 */
final readonly class ShoppingItems
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ShoppingItemRepository $itemRepository,
        private ReportClosing $reportClosing,
        private Notifier $notifier,
        private NotificationRecipients $recipients,
        private UrlGeneratorInterface $urlGenerator,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Puts the entry on the list – unless an open entry of that name is there already; then that one is returned.
     */
    public function add(ShoppingList $list, ShoppingItemData $data, Viewer $viewer, ShoppingSource $source = ShoppingSource::Manuell, ?Task $sourceTask = null): ShoppingItem
    {
        $name = trim((string) $data->name);
        $existing = $this->itemRepository->findOpenByName($list, $name);
        if (null !== $existing) {
            return $existing;
        }

        $item = new ShoppingItem($list, $name, $viewer->actor(), $source, $sourceTask);
        $item->describe($name, self::text($data->quantity), self::text($data->note), self::text($data->link));
        $this->entityManager->persist($item);
        $this->entityManager->flush();
        $this->announce($item, $viewer);

        return $item;
    }

    public function addFavorite(ShoppingList $list, ShoppingFavorite $favorite, Viewer $viewer): ShoppingItem
    {
        $data = new ShoppingItemData();
        $data->name = $favorite->getName();
        $data->quantity = $favorite->getDefaultQuantity();
        $data->link = $favorite->getLink();

        return $this->add($list, $data, $viewer);
    }

    public function isOnList(ShoppingList $list, string $name): bool
    {
        return null !== $this->itemRepository->findOpenByName($list, trim($name));
    }

    public function check(ShoppingItem $item): void
    {
        if ($item->isDone()) {
            return;
        }
        $item->check($this->clock->now());
        $this->reportClosing->itemChecked($item);
        $this->entityManager->flush();
    }

    /**
     * "Zurückholen": back on the list; a report closed by ticking it off is open again.
     */
    public function uncheck(ShoppingItem $item): void
    {
        if (!$item->isDone()) {
            return;
        }
        $item->uncheck();
        $this->reportClosing->itemUnchecked($item);
        $this->entityManager->flush();
    }

    public function update(ShoppingItem $item, ShoppingItemData $data): void
    {
        $item->describe(trim((string) $data->name), self::text($data->quantity), self::text($data->note), self::text($data->link));
        $this->entityManager->flush();
    }

    public function remove(ShoppingItem $item): void
    {
        $this->reportClosing->itemRemoved($item);
        $this->entityManager->remove($item);
        $this->entityManager->flush();
    }

    /**
     * "Neu auf deiner Liste" for whoever takes care of the list – a list without anybody tells nobody.
     */
    private function announce(ShoppingItem $item, Viewer $viewer): void
    {
        $list = $item->getList();
        $person = $list->getAssigneeUser();
        $role = $list->getAssigneeRole();
        $assignment = match (true) {
            null !== $person => Assignment::person($person),
            null !== $role => Assignment::role($role),
            default => null,
        };
        if (null === $assignment) {
            return;
        }

        $this->notifier->notifyListEntry(
            $list->getName(),
            $this->urlGenerator->generate('app_list_show', ['id' => $list->getId()]),
            $item->getName(),
            $this->recipients->responsibleFor($assignment),
            $viewer->actor(),
        );
    }

    private static function text(?string $text): ?string
    {
        $text = trim((string) $text);

        return '' === $text ? null : $text;
    }
}
