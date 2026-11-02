<?php

declare(strict_types=1);

namespace App\Controller;

use App\Enum\EmergencyCategory;
use App\Repository\EmergencyContactRepository;
use App\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Notfall" (konzept-3 §2): 112 and the emergency contacts by category with "wann anrufen" and call links.
 * Reachable from the top bar of every page – also at the tablet without PIN. Contacts "nur Admins" only with
 * the admin permission (so never by PIN or as team tablet). Emergency plans follow with the guides (M12).
 */
#[IsGranted(Permission::EMERGENCY_VIEW)]
final class EmergencyController extends AbstractController
{
    #[Route('/notfall', name: 'app_emergency', methods: ['GET'])]
    public function index(EmergencyContactRepository $contactRepository): Response
    {
        return $this->render('emergency/index.html.twig', [
            'groups' => $contactRepository->findForEmergencyPage($this->isGranted(Permission::ADMIN)),
            'categories' => EmergencyCategory::cases(),
            'admin' => $this->isGranted(Permission::ADMIN),
        ]);
    }
}
