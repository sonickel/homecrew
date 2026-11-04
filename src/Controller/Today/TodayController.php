<?php

declare(strict_types=1);

namespace App\Controller\Today;

use App\Entity\Device;
use App\Repository\IssueReportRepository;
use App\Security\Attribute\TeamTabletAllowed;
use App\Security\Permission;
use App\Service\Notification\Inbox;
use App\Service\Notification\TeamNews;
use App\Service\Task\PlanOverview;
use App\Service\Task\TodayTasks;
use App\Service\Viewer\ViewerProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Heute" (konzept-2 §2): the tasks meant for the viewer in groups – for admins in a second block what the
 * assistants have to do, how many reports are open and how many plans are overdue. Persons see what is new for them
 * (unread notifications), the team tablet what is new since yesterday. The team tablet shows its team view
 * (mode "Team"/"Beides") or goes to the name tiles.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[TeamTabletAllowed]
final class TodayController extends AbstractController
{
    /** "Neu seit deinem letzten Besuch": the newest unread notifications, the rest behind the bell. */
    private const int NEWS = 5;

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        ViewerProvider $viewerProvider,
        TodayTasks $todayTasks,
        PlanOverview $plans,
        IssueReportRepository $reportRepository,
        Inbox $inbox,
        TeamNews $teamNews,
    ): Response {
        $user = $this->getUser();
        if ($user instanceof Device && !$this->isGranted(Permission::TEAM_VIEW)) {
            return $this->redirectToRoute('app_tablet_people');
        }
        $this->denyAccessUnlessGranted(Permission::TASK_CREATE);
        $viewer = $viewerProvider->current() ?? throw $this->createAccessDeniedException();
        $person = $viewer->getPerson();

        return $this->render('today/index.html.twig', [
            'person' => $person,
            'device' => $user instanceof Device ? $user : null,
            'mine' => $todayTasks->forViewer($viewer),
            'assistants' => $todayTasks->forAssistants($viewer),
            'overdue_plans' => $plans->countOverdue($viewer),
            'open_reports' => $this->isGranted(Permission::REPORT_MANAGE) ? $reportRepository->countOpen() : null,
            'news' => $inbox->list($viewer, unreadOnly: true, limit: self::NEWS),
            'team_news' => $viewer->isTeamTablet() ? $teamNews->sinceYesterday() : null,
        ]);
    }
}
