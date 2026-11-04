<?php

declare(strict_types=1);

namespace App\Service\Notification;

use App\Service\Viewer\ViewerProvider;
use Twig\Attribute\AsTwigFunction;

/**
 * The number at the bell in the top bar: unread notifications the person sees in this session (0 at the team tablet).
 */
final readonly class NotificationBell
{
    public function __construct(
        private ViewerProvider $viewerProvider,
        private Inbox $inbox,
    ) {
    }

    #[AsTwigFunction('unread_notifications')]
    public function unread(): int
    {
        $viewer = $this->viewerProvider->current();

        return null === $viewer ? 0 : $this->inbox->countUnread($viewer);
    }
}
