<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\IssueReport;
use App\Security\Permission;
use App\Service\Viewer\ViewerProvider;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Reports (konzept-1 §3, konzept-13 §6): everybody incl. the team tablet reports and sees the open reports without
 * the sender; reports "nur für Admins" only admins see – also not the person who sent them. The report log, handing on
 * and closing: admins only (Permission::ADMIN – never after a PIN login, never at the team tablet).
 *
 * @extends Voter<string, IssueReport|null>
 */
final class ReportVoter extends Voter
{
    public function __construct(private readonly ViewerProvider $viewerProvider)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return match ($attribute) {
            Permission::REPORT_CREATE => null === $subject,
            Permission::REPORT_VIEW, Permission::REPORT_CONFIRM => $subject instanceof IssueReport,
            Permission::REPORT_MANAGE => null === $subject || $subject instanceof IssueReport,
            default => false,
        };
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $viewer = $this->viewerProvider->fromToken($token);
        if (null === $viewer) {
            return false;
        }
        if (Permission::REPORT_MANAGE === $attribute) {
            return $viewer->isAdmin();
        }
        if (!$subject instanceof IssueReport) {
            return true;
        }
        if ($subject->isForAdminsOnly() && !$viewer->isAdmin()) {
            $vote?->addReason('The report is for admins only.');

            return false;
        }

        return Permission::REPORT_VIEW === $attribute || $subject->isOpen();
    }
}
