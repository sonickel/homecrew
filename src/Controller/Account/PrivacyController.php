<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\AppUser;
use App\Repository\AppSettingRepository;
use App\Repository\AppUserRepository;
use App\Security\Permission;
use App\Service\Account\AccountSetup;
use App\Service\Account\PrivacyNotice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Privacy notice (konzept-5 §4): a step after the first login, later readable and printable in "Mein Konto"
 * (also to hand it to persons without an own login).
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class PrivacyController extends AbstractController
{
    #[Route('/konto/datenschutz', name: AccountSetup::STEP_PRIVACY, methods: ['GET', 'POST'])]
    public function notice(
        Request $request,
        #[CurrentUser] AppUser $user,
        PrivacyNotice $privacyNotice,
        AccountSetup $accountSetup,
        AppUserRepository $userRepository,
        AppSettingRepository $settingRepository,
        ClockInterface $clock,
        EntityManagerInterface $entityManager,
    ): Response {
        $needsAcknowledgement = $privacyNotice->needsAcknowledgement($user);
        if ($needsAcknowledgement && $request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('privacy-notice', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $user->acknowledgePrivacyNotice(PrivacyNotice::VERSION, $clock->now());
            $entityManager->flush();

            return $this->redirectToRoute($accountSetup->nextStep($user, $this->isGranted(Permission::PIN_CHANGE)) ?? 'app_home', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('account/privacy.html.twig', [
            'needs_acknowledgement' => $needsAcknowledgement,
            'acknowledged_at' => $user->getPrivacyNoticeAckAt(),
            'manager' => $userRepository->findManager(),
            'settings' => $settingRepository->get(),
            'version' => PrivacyNotice::VERSION,
        ]);
    }
}
