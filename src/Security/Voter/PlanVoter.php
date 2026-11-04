<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\TaskTemplate;
use App\Security\Permission;
use App\Service\Viewer\ViewerProvider;
use App\Service\Visibility\VisibilityRules;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Plans (konzept-1 §3, decisions M6): every person and the team tablet sees "Routinen & Wartung" with the plans
 * visible to them (same rules as tasks); admins and persons with the switch "wiederkehrende Aufgaben" create plans
 * and change every plan they see – also after a PIN login, never the team tablet.
 *
 * @extends Voter<string, TaskTemplate|null>
 */
final class PlanVoter extends Voter
{
    public function __construct(
        private readonly ViewerProvider $viewerProvider,
        private readonly VisibilityRules $rules,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [Permission::PLAN_VIEW, Permission::PLAN_MANAGE], true) && (null === $subject || $subject instanceof TaskTemplate);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $viewer = $this->viewerProvider->fromToken($token);
        if (null === $viewer) {
            return false;
        }
        if ($subject instanceof TaskTemplate && !$this->rules->canSee($viewer, $subject)) {
            $vote?->addReason('The plan is not visible to the viewer.');

            return false;
        }
        if (Permission::PLAN_VIEW === $attribute) {
            return true;
        }

        return $viewer->isAdmin() || true === $viewer->getPerson()?->canManageRecurringTasks();
    }
}
