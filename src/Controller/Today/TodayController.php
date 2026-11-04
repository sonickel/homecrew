<?php

declare(strict_types=1);

namespace App\Controller\Today;

use App\Entity\Device;
use App\Security\Attribute\TeamTabletAllowed;
use App\Security\Permission;
use App\Service\Task\PlanOverview;
use App\Service\Task\TodayTasks;
use App\Service\Viewer\ViewerProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Heute" (konzept-2 §2): the tasks meant for the viewer in groups – for admins in a second block what the
 * assistants have to do and how many plans are overdue. The team tablet shows its team view (mode "Team"/"Beides")
 * or goes to the name tiles.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
#[TeamTabletAllowed]
final class TodayController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(ViewerProvider $viewerProvider, TodayTasks $todayTasks, PlanOverview $plans): Response
    {
        $user = $this->getUser();
        if ($user instanceof Device && !$this->isGranted(Permission::TEAM_VIEW)) {
            return $this->redirectToRoute('app_tablet_people');
        }
        $this->denyAccessUnlessGranted(Permission::TASK_CREATE);
        $viewer = $viewerProvider->current() ?? throw $this->createAccessDeniedException();

        return $this->render('today/index.html.twig', [
            'person' => $viewer->getPerson(),
            'device' => $user instanceof Device ? $user : null,
            'mine' => $todayTasks->forViewer($viewer),
            'assistants' => $todayTasks->forAssistants($viewer),
            'overdue_plans' => $plans->countOverdue($viewer),
        ]);
    }
}
