<?php

declare(strict_types=1);

namespace App\Service\Visibility;

use App\Enum\AssigneeRole;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Service\Viewer\Viewer;
use Doctrine\ORM\QueryBuilder;

/**
 * Who sees what and who is responsible (konzept-2 §5.2–5.3, decisions M5) – once in PHP for single items
 * (voters) and once as query conditions for lists (repositories); a matrix test keeps both in line.
 *
 * Visibility: "alle" everybody · "zustaendig" the responsible + admins · "admins" admins · "privat" only the creator.
 * Whoever created something always sees it (only persons – the team tablet stores no creator).
 *
 * Responsible: a person (takes precedence; the team tablet is never a person) · role "admins" the admins ·
 * another role everybody with it, role Assistenz also the team tablet · nobody in particular ("wer gerade da ist")
 * every assistant and the team tablet – until the roster knows who is on duty (M9).
 */
final class VisibilityRules
{
    public function canSee(Viewer $viewer, Assignable $item): bool
    {
        $creator = $item->getCreatedBy();
        if (null !== $creator && $viewer->isPerson($creator)) {
            return true;
        }

        return match ($item->getVisibility()) {
            Visibility::Alle => true,
            Visibility::Zustaendig => $viewer->isAdmin() || $this->isResponsible($viewer, $item),
            Visibility::Admins => $viewer->isAdmin(),
            Visibility::Privat => false,
        };
    }

    public function isResponsible(Viewer $viewer, Assignable $item): bool
    {
        $person = $item->getAssigneeUser();
        if (null !== $person) {
            return $viewer->isPerson($person);
        }

        return match ($item->getAssigneeRole()) {
            null, AssigneeRole::Assistenz => $viewer->isTeamTablet() || $viewer->hasRole(UserRole::Assistenz),
            AssigneeRole::Admins => $viewer->isAdmin(),
            AssigneeRole::Manager => $viewer->hasRole(UserRole::Manager),
            AssigneeRole::Angehoerige => $viewer->hasRole(UserRole::Angehoerige),
        };
    }

    /**
     * What is meant for the viewer ("Für dich" on "Heute", handing on): what they are responsible for –
     * at the team tablet also the tasks of single assistants, who may have no login of their own.
     */
    public function isForViewer(Viewer $viewer, Assignable $item): bool
    {
        return $this->isResponsible($viewer, $item)
            || ($viewer->isTeamTablet() && UserRole::Assistenz === $item->getAssigneeUser()?->getRole());
    }

    /**
     * Meant for the assistants: role Assistenz, "wer gerade da ist" or a single assistant (block for admins on "Heute").
     */
    public function isForAssistants(Assignable $item): bool
    {
        $person = $item->getAssigneeUser();

        return null !== $person
            ? UserRole::Assistenz === $person->getRole()
            : \in_array($item->getAssigneeRole(), [null, AssigneeRole::Assistenz], true);
    }

    /**
     * Only persons who may have private content choose "privat" – not the assistants, not the team tablet (konzept-1 §3).
     */
    public function mayKeepPrivate(Viewer $viewer): bool
    {
        return null !== $viewer->getPerson() && !$viewer->hasRole(UserRole::Assistenz);
    }

    /**
     * Restricts a query to what the viewer may see (same rules as canSee()).
     *
     * @param string $alias alias of the entity with visibility, assigneeUser, assigneeRole and createdBy
     */
    public function whereVisible(QueryBuilder $qb, string $alias, Viewer $viewer): void
    {
        $or = [\sprintf("%s.visibility = 'alle'", $alias)];
        if ($viewer->isAdmin()) {
            $or[] = \sprintf("%s.visibility IN ('zustaendig', 'admins')", $alias);
        } else {
            $or[] = \sprintf("(%s.visibility = 'zustaendig' AND %s)", $alias, $this->responsibleCondition($qb, $alias, $viewer));
        }
        if (null !== $viewer->getPerson()) {
            $or[] = \sprintf('%s.createdBy = :vis_person', $alias);
            $qb->setParameter('vis_person', $viewer->getPerson()->getId());
        }

        $qb->andWhere('('.implode(' OR ', $or).')');
    }

    /**
     * Restricts a query to what is meant for the viewer (same rules as isForViewer()).
     *
     * @param string $assigneeAlias alias of the joined assigneeUser (LEFT JOIN)
     */
    public function whereForViewer(QueryBuilder $qb, string $alias, string $assigneeAlias, Viewer $viewer): void
    {
        $condition = $this->responsibleCondition($qb, $alias, $viewer);
        if ($viewer->isTeamTablet()) {
            $condition = \sprintf("(%s OR %s.role = 'assistenz')", $condition, $assigneeAlias);
        }

        $qb->andWhere($condition);
    }

    /**
     * Restricts a query to what is meant for the assistants (same rules as isForAssistants()).
     */
    public function whereForAssistants(QueryBuilder $qb, string $alias, string $assigneeAlias): void
    {
        $qb->andWhere(\sprintf(
            "((%2\$s.id IS NOT NULL AND %2\$s.role = 'assistenz') OR (%1\$s.assigneeUser IS NULL AND (%1\$s.assigneeRole IS NULL OR %1\$s.assigneeRole = 'assistenz')))",
            $alias,
            $assigneeAlias,
        ));
    }

    private function responsibleCondition(QueryBuilder $qb, string $alias, Viewer $viewer): string
    {
        $roles = [];
        $presentCounts = $viewer->isTeamTablet() || $viewer->hasRole(UserRole::Assistenz);
        if ($presentCounts) {
            $roles[] = AssigneeRole::Assistenz->value;
        }
        if ($viewer->isAdmin()) {
            $roles[] = AssigneeRole::Admins->value;
        }
        if ($viewer->hasRole(UserRole::Manager)) {
            $roles[] = AssigneeRole::Manager->value;
        }
        if ($viewer->hasRole(UserRole::Angehoerige)) {
            $roles[] = AssigneeRole::Angehoerige->value;
        }

        $byRole = [];
        if ([] !== $roles) {
            $byRole[] = \sprintf('%s.assigneeRole IN (:vis_roles)', $alias);
            $qb->setParameter('vis_roles', $roles);
        }
        if ($presentCounts) {
            $byRole[] = \sprintf('%s.assigneeRole IS NULL', $alias);
        }
        $condition = [] === $byRole ? '1 = 0' : \sprintf('(%s.assigneeUser IS NULL AND (%s))', $alias, implode(' OR ', $byRole));

        if (null !== $viewer->getPerson()) {
            $condition = \sprintf('(%s.assigneeUser = :vis_person OR %s)', $alias, $condition);
            $qb->setParameter('vis_person', $viewer->getPerson()->getId());
        }

        return $condition;
    }
}
