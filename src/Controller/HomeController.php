<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Heute" – placeholder until the tasks arrive in M5. The team tablet sees its team view (mode "Team"/"Beides")
 * or goes to the name tiles (mode "Persönlich").
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        $user = $this->getUser();
        if ($user instanceof Device) {
            if (!$this->isGranted(Permission::TEAM_VIEW)) {
                return $this->redirectToRoute('app_tablet_people');
            }

            return $this->render('tablet/team_home.html.twig', [
                'device' => $user,
            ]);
        }
        if (!$user instanceof AppUser) {
            throw $this->createAccessDeniedException();
        }

        return $this->render('home/index.html.twig', [
            'person' => $user,
        ]);
    }
}
