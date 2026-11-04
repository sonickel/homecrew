<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeNotifications;
use App\Repository\NotificationRepository;
use App\Service\Household\HouseholdContext;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * The delete date is set when a notification is created (day + retention_notification_days).
 */
#[AsMessageHandler]
final readonly class PurgeNotificationsHandler
{
    public function __construct(
        private NotificationRepository $notificationRepository,
        private HouseholdContext $household,
    ) {
    }

    public function __invoke(PurgeNotifications $message): int
    {
        return $this->notificationRepository->deleteExpired($this->household->today());
    }
}
