<?php

declare(strict_types=1);

namespace App\Controller\Account;

use App\Entity\AppUser;
use App\Form\Account\ChangePinType;
use App\Security\Permission;
use App\Security\Pin\PinLoginException;
use App\Security\Pin\PinLoginToken;
use App\Service\Account\AccountSetup;
use App\Service\Tablet\PinLogin;
use App\Service\Tablet\TabletContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Own PIN after a PIN login at the tablet: the start PIN is replaced at the first PIN login (mandatory step),
 * later the person may change it with the current PIN. A forgotten PIN: an admin creates a new start PIN.
 */
#[IsGranted(Permission::PIN_CHANGE)]
final class PinController extends AbstractController
{
    #[Route('/konto/pin', name: AccountSetup::STEP_PIN, methods: ['GET', 'POST'])]
    public function change(
        Request $request,
        #[CurrentUser] AppUser $user,
        PinLogin $pinLogin,
        TabletContext $tabletContext,
        AccountSetup $accountSetup,
        Security $security,
    ): Response {
        $device = $tabletContext->pairedDevice();
        if (null === $device) {
            throw $this->createAccessDeniedException('The PIN can only be changed at the tablet.');
        }

        // Still the PIN of the login: a PIN replaced since then (e.g. a new start PIN) ends the login first
        // (App\EventSubscriber\TabletRequestSubscriber), so nobody skips the current PIN that way
        $startPin = $user->isPinMustChange();
        $form = $this->createForm(ChangePinType::class, null, ['ask_current' => !$startPin]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if (!$startPin) {
                    $pinLogin->verify($user, (string) $form->get('current')->getData(), $device);
                }
                $pinLogin->change($user, (string) $form->get('pin')->getData(), $device);
                $token = $security->getToken();
                if ($token instanceof PinLoginToken) {
                    $token->rememberPin($user);
                }
                $this->addFlash('ok', 'Deine neue PIN ist gespeichert.');

                return $this->redirectToRoute($accountSetup->nextStep($user, pinLogin: true) ?? ($startPin ? 'app_home' : 'app_account'), status: Response::HTTP_SEE_OTHER);
            } catch (PinLoginException $exception) {
                $form->get('current')->addError(new FormError($exception->getMessageKey()));
            } catch (\DomainException $exception) {
                $form->get('pin')->get('first')->addError(new FormError($exception->getMessage()));
            }
        }

        $response = $this->render('account/pin.html.twig', [
            'form' => $form,
            'start_pin' => $startPin,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
