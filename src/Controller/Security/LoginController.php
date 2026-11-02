<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Entity\AppUser;
use App\Security\Permission;
use App\Service\Tablet\DeviceCookie;
use App\Service\Tablet\HomeNetwork;
use App\Service\Tablet\TabletContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class LoginController extends AbstractController
{
    /**
     * Username + password or passkey; the firewall (form_login) handles the POST.
     * At a paired tablet this is the "Admin-Anmeldung": the team tablet may open it, a person logged in may not.
     */
    #[Route('/anmelden', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(
        Request $request,
        AuthenticationUtils $authenticationUtils,
        TabletContext $tabletContext,
        DeviceCookie $deviceCookie,
        HomeNetwork $homeNetwork,
        #[Autowire('%env(int:SESSION_IDLE_MINUTES)%')]
        int $idleMinutes,
    ): Response {
        if ($this->getUser() instanceof AppUser && $this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_home');
        }

        $error = $authenticationUtils->getLastAuthenticationError();

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $error,
            'idle_logout' => 'inaktiv' === $request->query->getString('grund'),
            'blocked_logout' => 'gesperrt' === $request->query->getString('grund'),
            'idle_minutes' => $idleMinutes,
            'team_device' => $tabletContext->teamDevice(),
            'tablet_outside' => null !== $deviceCookie->pairedDevice($request) && !$homeNetwork->contains($request),
            'pin_login' => $this->isGranted(Permission::PIN_LOGIN, $request),
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /**
     * Intercepted by the firewall (logout with CSRF token); never reached.
     */
    #[Route('/abmelden', name: 'app_logout', methods: ['POST'])]
    public function logout(): never
    {
        throw new \LogicException('The firewall handles the logout.');
    }
}
