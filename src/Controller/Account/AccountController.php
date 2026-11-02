<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\AppUser;
use App\Form\Account\DisplaySettingsType;
use App\Repository\WebauthnCredentialRepository;
use App\Security\TwoFactor\TwoFactorPolicy;
use App\Service\Account\RecentConfirmation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * "Mein Konto" (konzept-1 §5, §7): display settings for everybody; how one logs in can only be changed
 * after a fresh confirmation (App\Service\Account\RecentConfirmation).
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class AccountController extends AbstractController
{
    #[Route('/konto', name: 'app_account', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        #[CurrentUser] AppUser $user,
        EntityManagerInterface $entityManager,
        WebauthnCredentialRepository $credentialRepository,
        RecentConfirmation $recentConfirmation,
        TwoFactorPolicy $twoFactorPolicy,
    ): Response {
        $form = $this->createForm(DisplaySettingsType::class, [
            'fontSize' => $user->getFontSize(),
            'highContrast' => $user->isHighContrast(),
        ]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{fontSize: \App\Enum\FontSize, highContrast: bool} $data */
            $data = $form->getData();
            $user->changeDisplay($data['fontSize'], $data['highContrast']);
            $entityManager->flush();
            $this->addFlash('ok', 'Die Darstellung ist gespeichert.');

            return $this->redirectToRoute('app_account', status: Response::HTTP_SEE_OTHER);
        }

        $response = $this->render('account/index.html.twig', [
            'person' => $user,
            'display_form' => $form,
            'passkeys' => $credentialRepository->findForUser($user),
            'confirmed_until' => $recentConfirmation->isFresh() ? $recentConfirmation->validUntil() : null,
            'two_factor_required' => $twoFactorPolicy->isRequiredFor($user),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
