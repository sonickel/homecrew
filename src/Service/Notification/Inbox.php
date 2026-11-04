<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Enum\NotificationKind;
use App\Repository\NotificationRepository;
use App\Service\Viewer\Viewer;

/**
 * The notifications a person sees in this session (bell, "Neu seit deinem letzten Besuch"). An admin logged in
 * without the admin right – by PIN at the tablet – sees no notifications about reports: they may belong to reports
 * for admins only, and a PIN never gives admin rights (konzept-1 §5). After a full login they are all there.
 */
final readonly class Inbox
{
    public function __construct(private NotificationRepository $notificationRepository)
    {
    }

    public function countUnread(Viewer $viewer): int
    {
        $person = $viewer->getPerson();

        return null === $person ? 0 : $this->notificationRepository->countUnread($person, $this->hidden($viewer));
    }

    /**
     * @return list<Notification> newest first
     */
    public function list(Viewer $viewer, bool $unreadOnly = false, ?int $limit = null): array
    {
        $person = $viewer->getPerson();

        return null === $person ? [] : $this->notificationRepository->findForPerson($person, $this->hidden($viewer), $unreadOnly, $limit);
    }

    public function shows(Viewer $viewer, Notification $notification): bool
    {
        $person = $viewer->getPerson();

        return null !== $person && $viewer->isPerson($notification->getUser()) && $notification->getKind() !== $this->hidden($viewer);
    }

    public function markAllRead(Viewer $viewer, \DateTimeImmutable $at): void
    {
        $person = $viewer->getPerson();
        if (null !== $person) {
            $this->notificationRepository->markAllRead($person, $this->hidden($viewer), $at);
        }
    }

    private function hidden(Viewer $viewer): ?NotificationKind
    {
        return true === $viewer->getPerson()?->isAdmin() && !$viewer->isAdmin() ? NotificationKind::Meldung : null;
    }
}
