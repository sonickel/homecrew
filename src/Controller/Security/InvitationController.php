<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Entity\AppUser;
use App\Enum\InvitationStatus;
use App\Form\Account\ChangePasswordType;
use App\Service\Account\AccountSetup;
use App\Service\Account\InvitationManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Redeem an invitation (konzept-1 §4): code from the QR code or typed → choose a password → logged in.
 * The QR code carries the code after "#", which never reaches the server; assets/controllers/fragment_code_controller.js
 * puts it into the form. The session only keeps the id of a checked invitation, never the code.
 */
final class InvitationController extends AbstractController
{
    private const string SESSION_KEY = '_hc_invitation';

    public function __construct(
        private readonly InvitationManager $invitationManager,
    ) {
    }

    #[Route('/einladung', name: 'app_invitation', methods: ['GET', 'POST'])]
    public function enter(
        Request $request,
        #[Autowire(service: 'limiter.invitation')]
        RateLimiterFactoryInterface $limiterFactory,
    ): Response {
        $error = null;
        if (!$this->getUser() instanceof AppUser && $request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('invitation', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $limit = $limiterFactory->create($request->getClientIp() ?? 'unbekannt')->consume();
            if (!$limit->isAccepted()) {
                // The rate limiter works with the system time, so the waiting time is computed with it as well
                $minutes = max(1, (int) ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60));
                $error = 1 === $minutes
                    ? 'Zu viele Versuche. Bitte warte eine Minute und versuch es dann noch einmal.'
                    : \sprintf('Zu viele Versuche. Bitte warte %d Minuten und versuch es dann noch einmal.', $minutes);
            } else {
                $invitation = $this->invitationManager->findByCode($request->getPayload()->getString('code'));
                $status = $this->invitationManager->status($invitation);
                if (InvitationStatus::Gueltig === $status && null !== $invitation) {
                    $request->getSession()->set(self::SESSION_KEY, $invitation->getId());

                    return $this->redirectToRoute('app_invitation_password', status: Response::HTTP_SEE_OTHER);
                }
                $error = $status->message();
            }
        }

        return $this->render('security/invitation.html.twig', [
            'error' => $error,
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    #[Route('/einladung/passwort', name: 'app_invitation_password', methods: ['GET', 'POST'])]
    public function password(Request $request, Security $security, AccountSetup $accountSetup): Response
    {
        $session = $request->getSession();
        $id = $session->get(self::SESSION_KEY);
        $invitation = !$this->getUser() instanceof AppUser && \is_int($id) ? $this->invitationManager->find($id) : null;
        $status = $this->invitationManager->status($invitation);
        if (null === $invitation || InvitationStatus::Gueltig !== $status) {
            $session->remove(self::SESSION_KEY);
            if (null !== $invitation) {
                $this->addFlash('error', $status->message());
            }

            return $this->redirectToRoute('app_invitation');
        }

        $person = $invitation->getUser();
        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $password = (string) $form->get('password')->getData();
            if (str_contains(mb_strtolower($password), $person->getUsername())) {
                $form->get('password')->get('first')->addError(new FormError('Das Passwort darf deinen Benutzernamen nicht enthalten.'));
            } else {
                $session->remove(self::SESSION_KEY);
                try {
                    $this->invitationManager->redeem($invitation, $password);
                } catch (\DomainException $exception) {
                    // Redeemed a moment earlier in another window
                    $this->addFlash('error', $exception->getMessage());

                    return $this->redirectToRoute('app_invitation', status: Response::HTTP_SEE_OTHER);
                }

                return $this->continueAfterRedeem($person, $security, $accountSetup);
            }
        }

        $response = $this->render('security/invitation_password.html.twig', [
            'person' => $person,
            'form' => $form,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    /**
     * Logged in right away – unless the person already has a second factor: the invitation must not bypass it.
     */
    private function continueAfterRedeem(AppUser $person, Security $security, AccountSetup $accountSetup): Response
    {
        if ($person->hasTwoFactor()) {
            $this->addFlash('ok', 'Dein Passwort ist gespeichert. Melde dich jetzt mit Benutzername, Passwort und deinem zweiten Schritt an.');

            return $this->redirectToRoute('app_login', status: Response::HTTP_SEE_OTHER);
        }

        $security->login($person, 'form_login', 'main');
        $this->addFlash('ok', 'Dein Passwort ist gespeichert. Willkommen bei HomeCrew!');

        return $this->redirectToRoute($accountSetup->nextStep($person) ?? 'app_home', status: Response::HTTP_SEE_OTHER);
    }
}
