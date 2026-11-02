<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\AppUser;
use App\Enum\AuditAction;
use App\Enum\TwoFactorMethod;
use App\Repository\WebauthnCredentialRepository;
use App\Security\Permission;
use App\Security\TwoFactor\AuthenticatorApp;
use App\Security\TwoFactor\RecoveryCodeManager;
use App\Service\Account\RecentConfirmation;
use App\Service\Audit\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Fresh confirmation before changing how one logs in (App\Service\Account\RecentConfirmation):
 * like a login – a passkey, or the password plus the second step (code from the app or recovery code).
 * At most 5 attempts in 15 minutes.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
// Never after a PIN login at the shared tablet (App\Security\Voter\AccountVoter)
#[IsGranted(Permission::ACCOUNT_LOGIN_SETTINGS)]
final class ConfirmController extends AbstractController
{
    #[Route('/konto/bestaetigen', name: 'app_account_confirm', methods: ['GET', 'POST'])]
    public function confirm(
        Request $request,
        #[CurrentUser] AppUser $user,
        RecentConfirmation $recentConfirmation,
        UserPasswordHasherInterface $passwordHasher,
        AuthenticatorApp $authenticatorApp,
        RecoveryCodeManager $recoveryCodeManager,
        WebauthnCredentialRepository $credentialRepository,
        AuditLogger $auditLogger,
        #[Autowire(service: 'limiter.account_confirm')]
        RateLimiterFactoryInterface $limiterFactory,
    ): Response {
        $target = RecentConfirmation::target($request->query->getString('weiter'));
        $error = null;

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('account-confirm', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            $limiter = $limiterFactory->create((string) $user->getId());
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                // The rate limiter works with the system time, so the waiting time is computed with it as well
                $minutes = max(1, (int) ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60));
                $error = 1 === $minutes ? 'Zu viele Versuche. Bitte warte eine Minute.' : \sprintf('Zu viele Versuche. Bitte warte %d Minuten.', $minutes);
            } else {
                $password = $request->getPayload()->getString('passwort');
                $code = $request->getPayload()->getString('code');
                $passwordOk = $user->hasPassword() && $passwordHasher->isPasswordValid($user, $password);
                $secondStepOk = !$user->hasTwoFactor()
                    || (TwoFactorMethod::Totp === $user->getTwofaMethod() && $authenticatorApp->verifyForUser($user, $code));
                if ($passwordOk && !$secondStepOk && $recoveryCodeManager->isBackupCode($user, $code)) {
                    $recoveryCodeManager->invalidateBackupCode($user, $code);
                    $auditLogger->log(AuditAction::ZweifaktorErsatzcode, $user, null, ['verbleibend' => $user->countRecoveryCodes()]);
                    $secondStepOk = true;
                }

                if ($passwordOk && $secondStepOk) {
                    $limiter->reset();
                    $recentConfirmation->confirm();

                    return $this->redirectToRoute($target, status: Response::HTTP_SEE_OTHER);
                }
                $error = $user->hasTwoFactor()
                    ? 'Passwort oder Code stimmen nicht. Versuch es noch einmal.'
                    : 'Das Passwort stimmt nicht. Versuch es noch einmal.';
            }
        }

        return $this->render('account/confirm.html.twig', [
            'person' => $user,
            'target' => $target,
            'error' => $error,
            'has_passkeys' => [] !== $credentialRepository->findForUser($user),
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
