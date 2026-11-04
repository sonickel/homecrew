<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\ShoppingItem;
use App\Security\Permission;
use App\Service\Viewer\ViewerProvider;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Lists (konzept-1 §3 "Listen nutzen"): every person and the team tablet reads, adds and ticks off.
 * Changing or removing an entry: admins and whoever added it – a person, or the same tablet.
 *
 * @extends Voter<string, ShoppingItem|null>
 */
final class ShoppingVoter extends Voter
{
    public function __construct(private readonly ViewerProvider $viewerProvider)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return (Permission::LIST_USE === $attribute && null === $subject)
            || (Permission::LIST_ITEM_EDIT === $attribute && $subject instanceof ShoppingItem);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $viewer = $this->viewerProvider->fromToken($token);
        if (null === $viewer) {
            return false;
        }
        if (!$subject instanceof ShoppingItem) {
            return true;
        }

        return $viewer->isAdmin() || $subject->isBy($viewer->actor());
    }
}
