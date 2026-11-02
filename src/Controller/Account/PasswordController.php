<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\AppUser;
use App\Enum\AuditAction;
use App\Form\Account\ChangePasswordType;
use App\Security\Permission;
use App\Service\Account\AccountSetup;
use App\Service\Account\RecentConfirmation;
use App\Service\Audit\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Replace the start password (first login) or change the password ("Mein Konto"). A voluntary change needs
 * a fresh confirmation – otherwise anyone at an open session could take over the account.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
// Never after a PIN login at the shared tablet (App\Security\Voter\AccountVoter)
#[IsGranted(Permission::ACCOUNT_LOGIN_SETTINGS)]
final class PasswordController extends AbstractController
{
    #[Route('/konto/passwort', name: AccountSetup::STEP_PASSWORD, methods: ['GET', 'POST'])]
    public function change(
        Request $request,
        #[CurrentUser] AppUser $user,
        UserPasswordHasherInterface $passwordHasher,
        AuditLogger $auditLogger,
        AccountSetup $accountSetup,
        RecentConfirmation $recentConfirmation,
    ): Response {
        $startPassword = $user->isPasswordMustChange();
        if (!$startPassword && !$recentConfirmation->isFresh()) {
            return $this->redirectToRoute('app_account_confirm', ['weiter' => AccountSetup::STEP_PASSWORD]);
        }

        $form = $this->createForm(ChangePasswordType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $password = (string) $form->get('password')->getData();
            if ($passwordHasher->isPasswordValid($user, $password)) {
                $form->get('password')->get('first')->addError(new FormError('Bitte wähle ein anderes Passwort als das bisherige.'));
            } elseif (str_contains(mb_strtolower($password), mb_strtolower($user->getUsername()))) {
                $form->get('password')->get('first')->addError(new FormError('Das Passwort darf deinen Benutzernamen nicht enthalten.'));
            } else {
                $user->changePassword($passwordHasher->hashPassword($user, $password));
                $auditLogger->log(AuditAction::PasswortAendern, $user, $user);

                $this->addFlash('ok', 'Dein neues Passwort ist gespeichert.');

                return $this->redirectToRoute($accountSetup->nextStep($user) ?? ($startPassword ? 'app_home' : 'app_account'), status: Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('account/password.html.twig', [
            'form' => $form,
            'start_password' => $startPassword,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
