<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Task;
use App\Enum\Visibility;
use App\Security\Permission;
use App\Service\Viewer\ViewerProvider;
use App\Service\Visibility\VisibilityRules;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Tasks (konzept-1 §3, decisions M5): every person and the team tablet sees "Heute" and creates one-off tasks;
 * whoever sees a task completes, skips or postpones it; handing on: admins every task they see, everybody else –
 * also the team tablet – what they are responsible for; changing: admins and the creator. Private tasks are
 * not handed on (they always belong to their creator).
 *
 * @extends Voter<string, Task|null>
 */
final class TaskVoter extends Voter
{
    private const array ON_TASK = [Permission::TASK_VIEW, Permission::TASK_ACT, Permission::TASK_HANDOVER, Permission::TASK_EDIT];

    public function __construct(
        private readonly ViewerProvider $viewerProvider,
        private readonly VisibilityRules $rules,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return Permission::TASK_CREATE === $attribute || (\in_array($attribute, self::ON_TASK, true) && $subject instanceof Task);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $viewer = $this->viewerProvider->fromToken($token);
        if (null === $viewer) {
            return false;
        }
        if (Permission::TASK_CREATE === $attribute) {
            return true;
        }

        \assert($subject instanceof Task);
        if (!$this->rules->canSee($viewer, $subject)) {
            $vote?->addReason('The task is not visible to the viewer.');

            return false;
        }

        return match ($attribute) {
            Permission::TASK_VIEW => true,
            Permission::TASK_ACT => $subject->isOpen(),
            Permission::TASK_HANDOVER => $subject->isOpen()
                && Visibility::Privat !== $subject->getVisibility()
                && ($viewer->isAdmin() || $this->rules->isForViewer($viewer, $subject)),
            Permission::TASK_EDIT => $subject->isOpen()
                && ($viewer->isAdmin() || (null !== $subject->getCreatedBy() && $viewer->isPerson($subject->getCreatedBy()))),
            default => false,
        };
    }
}
