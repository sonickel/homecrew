<?php

declare(strict_types=1);

namespace App\Controller\Tablet;

use App\Entity\AppUser;
use App\Repository\AppUserRepository;
use App\Security\Attribute\TeamTabletAllowed;
use App\Security\Permission;
use App\Security\Pin\PinAuthenticator;
use App\Service\Household\HouseholdContext;
use App\Service\Tablet\PinLogin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Persönlich anmelden" at the tablet (konzept-12 §2): tap the own name, type the PIN.
 * The PIN form is handled by App\Security\Pin\PinAuthenticator; access by App\Security\Voter\TabletVoter.
 */
#[TeamTabletAllowed]
final class PinLoginController extends AbstractController
{
    public function __construct(
        private readonly PinLogin $pinLogin,
        private readonly AppUserRepository $userRepository,
    ) {
    }

    #[Route('/tablet/personen', name: 'app_tablet_people', methods: ['GET'])]
    public function people(Request $request, HouseholdContext $household): Response
    {
        if ($this->getUser() instanceof AppUser) {
            return $this->redirectToRoute('app_home');
        }
        $this->denyAccessUnlessGranted(Permission::PIN_LOGIN, $request);

        return $this->render('tablet/people.html.twig', [
            'people' => $this->userRepository->findForPinLogin($household->today()),
            'team_view' => $this->isGranted(Permission::TEAM_VIEW),
        ]);
    }

    /**
     * GET shows the PIN form; the POST never reaches this action (the authenticator answers it).
     */
    #[Route('/tablet/personen/{publicId}', name: PinAuthenticator::ROUTE, methods: ['GET', 'POST'])]
    public function pin(Request $request, string $publicId): Response
    {
        if ($this->getUser() instanceof AppUser) {
            return $this->redirectToRoute('app_home');
        }
        $this->denyAccessUnlessGranted(Permission::PIN_LOGIN, $request);
        $person = $this->userRepository->findOneByPublicId($publicId);
        if (null === $person || !$this->pinLogin->canUse($person)) {
            throw $this->createNotFoundException('No PIN login for this person.');
        }

        $response = $this->render('tablet/pin.html.twig', [
            'person' => $person,
            'csrf_id' => PinAuthenticator::CSRF_ID,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
