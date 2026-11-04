<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Notification;
use App\Security\Permission;
use App\Service\Viewer\ViewerProvider;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Notifications (konzept-9 §5): every person sees their own – the team tablet has none (konzept-1 §3).
 *
 * @extends Voter<string, Notification|null>
 */
final class NotificationVoter extends Voter
{
    public function __construct(private readonly ViewerProvider $viewerProvider)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return Permission::NOTIFICATION_VIEW === $attribute && (null === $subject || $subject instanceof Notification);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $viewer = $this->viewerProvider->fromToken($token);
        if (null === $viewer?->getPerson()) {
            return false;
        }

        return !$subject instanceof Notification || $viewer->isPerson($subject->getUser());
    }
}
