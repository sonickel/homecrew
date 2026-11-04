<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\Notification;
use App\Enum\NotificationEvent;
use App\Repository\AppSettingRepository;
use App\Repository\NotificationRepository;
use App\Service\Household\HouseholdContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Creates the in-app notifications (bell, "Neu seit deinem letzten Besuch", konzept-9 §5): only for persons whose
 * defaults want the event (NotificationDefaults), never for whoever caused it. Deleted after
 * retention_notification_days. E-mail and push follow in M13 and stage 4.
 */
final readonly class Notifier
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private NotificationDefaults $defaults,
        private AppSettingRepository $settingRepository,
        private HouseholdContext $household,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param iterable<AppUser> $recipients
     *
     * @return int how many persons were notified
     */
    public function notify(NotificationEvent $event, iterable $recipients, string $title, ?string $link, AppUser|Device|null $actor, bool $urgent = false): int
    {
        $count = 0;
        foreach ($this->filter($event, $recipients, $actor) as $person) {
            $this->entityManager->persist(new Notification($person, $event->kind(), $title, $link, $urgent, $this->deleteAfter()));
            ++$count;
        }
        $this->entityManager->flush();

        return $count;
    }

    /**
     * "Neu auf deiner Liste": while a person has not read the notification about this list yet, the new entry is
     * added to it instead of creating one notification per entry (decision 04.11.).
     *
     * @param iterable<AppUser> $recipients
     */
    public function notifyListEntry(string $listName, string $listLink, string $itemName, iterable $recipients, AppUser|Device $actor): void
    {
        $title = $this->translator->trans('Neu auf deiner Liste „%list%“', ['%list%' => $listName]);
        foreach ($this->filter(NotificationEvent::Aufgabe, $recipients, $actor) as $person) {
            $notification = $this->notificationRepository->findUnreadWithLink($person, $listLink);
            if (null === $notification) {
                $notification = new Notification($person, NotificationEvent::Aufgabe->kind(), $title, $listLink, false, $this->deleteAfter());
                $this->entityManager->persist($notification);
            }
            $items = null === $notification->getBody() ? [] : explode(', ', $notification->getBody());
            if (!\in_array($itemName, $items, true)) {
                $notification->setBody(implode(', ', [...$items, $itemName]));
            }
        }
        $this->entityManager->flush();
    }

    /**
     * @param iterable<AppUser> $recipients
     *
     * @return list<AppUser> each person once, without the actor, only if their defaults want the event
     */
    private function filter(NotificationEvent $event, iterable $recipients, AppUser|Device|null $actor): array
    {
        $persons = [];
        foreach ($recipients as $person) {
            $isActor = $actor instanceof AppUser && ($actor === $person || $actor->getId() === $person->getId());
            if (!$isActor && $this->defaults->inApp($event, $person)) {
                $persons[(int) $person->getId()] = $person;
            }
        }

        return array_values($persons);
    }

    private function deleteAfter(): \DateTimeImmutable
    {
        $days = $this->settingRepository->get()->getRetentionNotificationDays();

        return new \DateTimeImmutable($this->household->today()->modify(\sprintf('+%d days', $days))->format('Y-m-d'));
    }
}
