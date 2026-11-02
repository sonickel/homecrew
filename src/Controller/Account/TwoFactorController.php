<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\AppUser;
use App\Entity\WebauthnCredential;
use App\Enum\AuditAction;
use App\Enum\TwoFactorMethod;
use App\Form\Account\AuthenticatorCodeType;
use App\Repository\WebauthnCredentialRepository;
use App\Security\TwoFactor\AuthenticatorApp;
use App\Security\TwoFactor\RecoveryCodeGenerator;
use App\Security\TwoFactor\RecoveryCodeManager;
use App\Security\TwoFactor\TwoFactorPolicy;
use App\Service\Account\AccountSetup;
use App\Service\Account\RecentConfirmation;
use App\Service\Audit\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Second login step (konzept-1 §5): set it up (first login or "Mein Konto") and manage it – authenticator app,
 * passkeys, recovery codes. Works without e-mail. Every change, the first second factor included, needs a fresh
 * confirmation (App\Service\Account\RecentConfirmation) – right after the login that is already given.
 * First recovery codes need none: they only exist together with a second factor that was confirmed.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class TwoFactorController extends AbstractController
{
    /** Encrypted secret of an app setup that is not confirmed yet. */
    private const string PENDING_SECRET = '_hc_pending_totp';

    public function __construct(
        private readonly RecentConfirmation $recentConfirmation,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    #[Route('/konto/zwei-faktor', name: AccountSetup::STEP_TWO_FACTOR, methods: ['GET'])]
    public function choose(#[CurrentUser] AppUser $user): Response
    {
        if ($user->hasTwoFactor()) {
            return $this->alreadySetUp();
        }

        return $this->render('account/two_factor.html.twig', ['admin' => $user->isAdmin()]);
    }

    /**
     * Without a second factor, or instead of a passkey as second factor (then with confirmation).
     */
    #[Route('/konto/zwei-faktor/app', name: 'app_account_two_factor_app', methods: ['GET', 'POST'])]
    public function authenticatorApp(
        Request $request,
        #[CurrentUser] AppUser $user,
        AuthenticatorApp $authenticatorApp,
        AccountSetup $accountSetup,
    ): Response {
        if (TwoFactorMethod::Totp === $user->getTwofaMethod()) {
            return $this->alreadySetUp();
        }
        $switching = $user->hasTwoFactor();
        if ($this->recentConfirmation->isRequiredFor($user)) {
            return $this->redirectToRoute('app_account_confirm', ['weiter' => 'app_account_two_factor_app']);
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
                $this->auditLogger->log(AuditAction::ZweifaktorEinrichten, $user, $user, ['methode' => TwoFactorMethod::Totp->value]);
                if ($switching) {
                    $this->addFlash('ok', 'Die Authenticator-App ist jetzt dein zweiter Schritt. Deine Passkeys funktionieren weiter.');
                }

                return $this->redirectToRoute($accountSetup->nextStep($user) ?? ($switching ? 'app_account' : 'app_home'), status: Response::HTTP_SEE_OTHER);
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
            'back' => $switching ? 'app_account' : AccountSetup::STEP_TWO_FACTOR,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    #[Route('/konto/zwei-faktor/app/entfernen', name: 'app_account_two_factor_app_remove', methods: ['POST'])]
    public function removeAuthenticatorApp(
        Request $request,
        #[CurrentUser] AppUser $user,
        WebauthnCredentialRepository $credentialRepository,
        TwoFactorPolicy $twoFactorPolicy,
    ): Response {
        if (!$this->isCsrfTokenValid('two-factor-app-remove', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        if (!$this->recentConfirmation->isFresh()) {
            return $this->redirectToRoute('app_account_confirm', ['weiter' => 'app_account']);
        }
        if (TwoFactorMethod::Totp !== $user->getTwofaMethod()) {
            return $this->redirectToRoute('app_account');
        }

        $passkeysRemain = [] !== $credentialRepository->findForUser($user);
        if (!$passkeysRemain && $twoFactorPolicy->isRequiredFor($user)) {
            $this->addFlash('error', 'Als Admin brauchst du einen zweiten Schritt. Lege zuerst einen Passkey an.');

            return $this->redirectToRoute('app_account', status: Response::HTTP_SEE_OTHER);
        }

        $user->removeAuthenticatorApp($passkeysRemain);
        $this->auditLogger->log(AuditAction::ZweifaktorAppEntfernen, $user, $user);
        $this->addFlash('ok', $passkeysRemain
            ? 'Die Authenticator-App ist entfernt. Dein zweiter Schritt ist jetzt dein Passkey.'
            : 'Die Authenticator-App ist entfernt. Du meldest dich jetzt nur mit dem Passwort an.');

        return $this->redirectToRoute('app_account', status: Response::HTTP_SEE_OTHER);
    }

    /**
     * First passkey as second factor (setup step).
     */
    #[Route('/konto/zwei-faktor/passkey', name: 'app_account_two_factor_passkey', methods: ['GET'])]
    public function passkey(#[CurrentUser] AppUser $user): Response
    {
        if ($user->hasTwoFactor()) {
            return $this->redirectToRoute('app_account_passkey_add');
        }
        if ($this->recentConfirmation->isRequiredFor($user)) {
            return $this->redirectToRoute('app_account_confirm', ['weiter' => 'app_account_two_factor_passkey']);
        }

        return $this->render('account/two_factor_passkey.html.twig', ['back' => AccountSetup::STEP_TWO_FACTOR]);
    }

    /**
     * A further passkey ("Mein Konto") – with an existing second factor only after a fresh confirmation
     * (App\EventSubscriber\PasskeyRegistrationGuard checks the endpoints as well).
     */
    #[Route('/konto/passkeys/neu', name: 'app_account_passkey_add', methods: ['GET'])]
    public function addPasskey(#[CurrentUser] AppUser $user): Response
    {
        if ($this->recentConfirmation->isRequiredFor($user)) {
            return $this->redirectToRoute('app_account_confirm', ['weiter' => 'app_account_passkey_add']);
        }

        return $this->render('account/two_factor_passkey.html.twig', ['back' => 'app_account']);
    }

    #[Route('/konto/passkeys/{id}/entfernen', name: 'app_account_passkey_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function removePasskey(
        Request $request,
        #[CurrentUser] AppUser $user,
        #[MapEntity(id: 'id')] WebauthnCredential $credential,
        WebauthnCredentialRepository $credentialRepository,
        TwoFactorPolicy $twoFactorPolicy,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($credential->getUser()->getId() !== $user->getId()) {
            throw $this->createNotFoundException('Passkey of another person.');
        }
        if (!$this->isCsrfTokenValid('passkey-remove-'.$credential->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
        if (!$this->recentConfirmation->isFresh()) {
            return $this->redirectToRoute('app_account_confirm', ['weiter' => 'app_account']);
        }

        $lastOne = 1 === \count($credentialRepository->findForUser($user));
        if ($lastOne && TwoFactorMethod::Passkey === $user->getTwofaMethod() && $twoFactorPolicy->isRequiredFor($user)) {
            $this->addFlash('error', 'Als Admin brauchst du einen zweiten Schritt. Richte zuerst die Authenticator-App ein oder lege einen weiteren Passkey an.');

            return $this->redirectToRoute('app_account', status: Response::HTTP_SEE_OTHER);
        }

        $entityManager->remove($credential);
        if ($lastOne) {
            $user->removeLastPasskey();
        }
        $this->auditLogger->log(AuditAction::PasskeyEntfernen, $user, $user);
        $this->addFlash('ok', 'Der Passkey ist entfernt.');

        return $this->redirectToRoute('app_account', status: Response::HTTP_SEE_OTHER);
    }

    /**
     * GET explains, POST creates new codes and shows them once – they are never stored readable.
     * The POST runs without Turbo, so the codes come in the answer itself instead of a redirect.
     * Replacing codes that are still left needs a fresh confirmation.
     */
    #[Route('/konto/zwei-faktor/codes', name: AccountSetup::STEP_RECOVERY_CODES, methods: ['GET', 'POST'])]
    public function recoveryCodes(
        Request $request,
        #[CurrentUser] AppUser $user,
        RecoveryCodeGenerator $generator,
        RecoveryCodeManager $recoveryCodeManager,
    ): Response {
        if (!$user->hasTwoFactor()) {
            return $this->redirectToRoute(AccountSetup::STEP_TWO_FACTOR);
        }
        $replacing = $user->countRecoveryCodes() > 0;
        if ($replacing && !$this->recentConfirmation->isFresh()) {
            return $this->redirectToRoute('app_account_confirm', ['weiter' => AccountSetup::STEP_RECOVERY_CODES]);
        }

        $codes = null;
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('recovery-codes', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }
            $codes = $generator->generate();
            $recoveryCodeManager->replace($user, $codes);
            $this->auditLogger->log(AuditAction::ZweifaktorCodes, $user, $user);
        }

        $response = $this->render('account/recovery_codes.html.twig', [
            'codes' => $codes,
            'replacing' => $replacing,
            'done' => $replacing ? 'app_account' : 'app_home',
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function alreadySetUp(): Response
    {
        $this->addFlash('info', 'Dein zweiter Schritt ist schon eingerichtet.');

        return $this->redirectToRoute('app_account');
    }
}
