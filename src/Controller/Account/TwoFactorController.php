<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\AppUser;
use App\Enum\AuditAction;
use App\Enum\TwoFactorMethod;
use App\Form\Account\AuthenticatorCodeType;
use App\Security\TwoFactor\AuthenticatorApp;
use App\Security\TwoFactor\RecoveryCodeGenerator;
use App\Security\TwoFactor\RecoveryCodeManager;
use App\Service\Account\AccountSetup;
use App\Service\Audit\AuditLogger;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Set up the second login step: authenticator app or passkey, then recovery codes (konzept-1 §5).
 * Works without e-mail. Managing it later ("Mein Konto") follows in M3.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TwoFactorController extends AbstractController
{
    /** Encrypted secret of an app setup that is not confirmed yet. */
    private const string PENDING_SECRET = '_hc_pending_totp';

    #[Route('/konto/zwei-faktor', name: AccountSetup::STEP_TWO_FACTOR, methods: ['GET'])]
    public function choose(#[CurrentUser] AppUser $user): Response
    {
        if ($user->hasTwoFactor()) {
            return $this->alreadySetUp();
        }

        return $this->render('account/two_factor.html.twig', ['admin' => $user->isAdmin()]);
    }

    #[Route('/konto/zwei-faktor/app', name: 'app_account_two_factor_app', methods: ['GET', 'POST'])]
    public function authenticatorApp(
        Request $request,
        #[CurrentUser] AppUser $user,
        AuthenticatorApp $authenticatorApp,
        AuditLogger $auditLogger,
        AccountSetup $accountSetup,
    ): Response {
        if ($user->hasTwoFactor()) {
            return $this->alreadySetUp();
        }

        $session = $request->getSession();
        $pending = $session->get(self::PENDING_SECRET);
        if (!\is_string($pending)) {
            $pending = $authenticatorApp->encryptSecret($authenticatorApp->generateSecret());
            $session->set(self::PENDING_SECRET, $pending);
        }
        $secret = $authenticatorApp->decryptSecret($pending);

        $form = $this->createForm(AuthenticatorCodeType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($authenticatorApp->verify($secret, (string) $form->get('code')->getData())) {
                $user->enableAuthenticatorApp($pending);
                $session->remove(self::PENDING_SECRET);
                $auditLogger->log(AuditAction::ZweifaktorEinrichten, $user, $user, ['methode' => TwoFactorMethod::Totp->value]);

                return $this->redirectToRoute($accountSetup->nextStep($user) ?? 'app_home', status: Response::HTTP_SEE_OTHER);
            }
            $form->get('code')->addError(new FormError('Der Code stimmt nicht. Gib den Code ein, den die App gerade zeigt.'));
        }

        $qrCode = (new Builder(
            writer: new SvgWriter(),
            data: $authenticatorApp->provisioningUri($user, $secret),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 240,
            margin: 8,
        ))->build();

        $response = $this->render('account/two_factor_app.html.twig', [
            'form' => $form,
            'qr_code' => $qrCode->getDataUri(),
            'secret_groups' => str_split($secret, 4),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/konto/zwei-faktor/passkey', name: 'app_account_two_factor_passkey', methods: ['GET'])]
    public function passkey(#[CurrentUser] AppUser $user): Response
    {
        if ($user->hasTwoFactor()) {
            return $this->alreadySetUp();
        }

        return $this->render('account/two_factor_passkey.html.twig');
    }

    /**
     * GET explains, POST creates new codes and shows them once – they are never stored readable.
     * The POST runs without Turbo, so the codes come in the answer itself instead of a redirect.
     */
    #[Route('/konto/zwei-faktor/codes', name: AccountSetup::STEP_RECOVERY_CODES, methods: ['GET', 'POST'])]
    public function recoveryCodes(
        Request $request,
        #[CurrentUser] AppUser $user,
        RecoveryCodeGenerator $generator,
        RecoveryCodeManager $recoveryCodeManager,
        AuditLogger $auditLogger,
    ): Response {
        if (!$user->hasTwoFactor()) {
            return $this->redirectToRoute(AccountSetup::STEP_TWO_FACTOR);
        }
        // Replacing existing codes needs a fresh confirmation ("Mein Konto", M3) – otherwise anyone at an
        // open session could quietly swap them; new codes only when none are left
        if ($user->countRecoveryCodes() > 0) {
            $this->addFlash('info', 'Du hast noch Wiederherstellungscodes.');

            return $this->redirectToRoute('app_home');
        }

        $codes = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('recovery-codes', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $codes = $generator->generate();
            $recoveryCodeManager->replace($user, $codes);
            $auditLogger->log(AuditAction::ZweifaktorCodes, $user, $user);
        }

        $response = $this->render('account/recovery_codes.html.twig', ['codes' => $codes]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function alreadySetUp(): Response
    {
        $this->addFlash('info', 'Dein zweiter Schritt ist schon eingerichtet.');

        return $this->redirectToRoute('app_home');
    }
}
