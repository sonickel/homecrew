<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\AppUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Heute" – placeholder until the tasks arrive in M5.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(#[CurrentUser] AppUser $user): Response
    {
        return $this->render('home/index.html.twig', [
            'person' => $user,
        ]);
    }
}
