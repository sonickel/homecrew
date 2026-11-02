<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\AppUser;
use App\Security\Passkey\PasskeySupport;
use App\Security\Permission;
use App\Service\Account\InvitationManager;
use App\Service\Account\PersonAccess;
use App\Service\Household\HouseholdContext;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Actions of the page "Verwaltung → Personen → Zugang" (konzept-11 §2): each a POST with CSRF token.
 * Start password, start PIN and invitation answer with the access card – the only moment the plain
 * value exists; it is never stored readable and the page is not cached.
 */
#[Route('/verwaltung/personen/{id}/zugang', requirements: ['id' => '\d+'], methods: ['POST'])]
#[IsGranted(Permission::ADMIN)]
final class PersonAccessController extends AbstractController
{
    public function __construct(
        private readonly PersonAccess $personAccess,
        private readonly InvitationManager $invitationManager,
        private readonly HouseholdContext $household,
        private readonly PasskeySupport $passkeySupport,
    ) {
    }

    #[Route('/start-passwort', name: 'app_admin_person_start_password')]
    public function startPassword(#[MapEntity(id: 'id')] AppUser $person, Request $request, #[CurrentUser] AppUser $admin): Response
    {
        $this->checkAccess($person, $request);
        try {
            $password = $this->personAccess->setStartPassword($person, $admin);
        } catch (\DomainException $exception) {
            return $this->backWithError($person, $exception->getMessage());
        }

        return $this->card($request, $person, ['password' => $password]);
    }

    #[Route('/einladung', name: 'app_admin_person_invitation')]
    public function invitation(#[MapEntity(id: 'id')] AppUser $person, Request $request, #[CurrentUser] AppUser $admin): Response
    {
        $this->checkAccess($person, $request);
        try {
            ['code' => $code, 'invitation' => $invitation] = $this->invitationManager->create($person, $admin);
        } catch (\DomainException $exception) {
            return $this->backWithError($person, $exception->getMessage());
        }

        // The code travels after "#": browsers never send that part to the server, so it ends up in no log
        $url = $this->passkeySupport->linkBase($request).$this->generateUrl('app_invitation').'#'.$code;
        $qrCode = (new Builder(
            writer: new SvgWriter(),
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 280,
            margin: 8,
        ))->build();

        return $this->card($request, $person, [
            'invitation_code' => implode(' ', str_split($code, 4)),
            'invitation_qr' => $qrCode->getDataUri(),
            'invitation_expires' => $invitation->getExpiresAt(),
        ]);
    }

    #[Route('/start-pin', name: 'app_admin_person_start_pin')]
    public function startPin(#[MapEntity(id: 'id')] AppUser $person, Request $request, #[CurrentUser] AppUser $admin): Response
    {
        $this->checkAccess($person, $request);
        try {
            $pin = $this->personAccess->setStartPin($person, $admin);
        } catch (\DomainException $exception) {
            return $this->backWithError($person, $exception->getMessage());
        }

        return $this->card($request, $person, ['pin' => $pin]);
    }

    #[Route('/pin-anmeldung', name: 'app_admin_person_pin_login')]
    public function pinLogin(#[MapEntity(id: 'id')] AppUser $person, Request $request, #[CurrentUser] AppUser $admin): Response
    {
        $this->checkAccess($person, $request);
        $enable = $request->getPayload()->getBoolean('an');
        if ($enable && !$person->hasPin()) {
            return $this->backWithError($person, 'Erzeuge zuerst eine Start-PIN.');
        }
        try {
            $this->personAccess->setPinLogin($person, $enable, $admin);
        } catch (\DomainException $exception) {
            return $this->backWithError($person, $exception->getMessage());
        }

        return $this->back($person, $enable ? 'Die PIN-Anmeldung ist eingeschaltet.' : 'Die PIN-Anmeldung ist ausgeschaltet.');
    }

    #[Route('/zwei-faktor-zuruecksetzen', name: 'app_admin_person_reset_two_factor')]
    public function resetTwoFactor(#[MapEntity(id: 'id')] AppUser $person, Request $request, #[CurrentUser] AppUser $admin): Response
    {
        $this->checkAccess($person, $request);
        $this->personAccess->resetTwoFactor($person, $admin);

        return $this->back($person, \sprintf('Der zweite Schritt von %s ist zurückgesetzt.', $person->getDisplayName()));
    }

    #[Route('/sperren', name: 'app_admin_person_lock')]
    public function lock(#[MapEntity(id: 'id')] AppUser $person, Request $request, #[CurrentUser] AppUser $admin): Response
    {
        $this->checkAccess($person, $request);
        $this->personAccess->lock($person, $admin);

        return $this->back($person, \sprintf('%s ist gesperrt und wird beim nächsten Klick abgemeldet.', $person->getDisplayName()));
    }

    #[Route('/entsperren', name: 'app_admin_person_unlock')]
    public function unlock(#[MapEntity(id: 'id')] AppUser $person, Request $request, #[CurrentUser] AppUser $admin): Response
    {
        $this->checkAccess($person, $request);
        $this->personAccess->unlock($person, $admin);

        return $this->back($person, 'Die Sperre ist aufgehoben.');
    }

    #[Route('/austritt', name: 'app_admin_person_leave')]
    public function leave(#[MapEntity(id: 'id')] AppUser $person, Request $request, #[CurrentUser] AppUser $admin): Response
    {
        $this->checkAccess($person, $request);
        $input = $request->getPayload()->getString('letzter_tag');
        $lastDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $input, $this->household->timezone());
        // createFromFormat() would turn 2026-02-31 into 3 March without complaint
        if (false === $lastDay || $lastDay->format('Y-m-d') !== $input) {
            return $this->backWithError($person, 'Bitte gib den letzten Arbeitstag an.');
        }
        try {
            $this->personAccess->leave($person, $lastDay, $admin);
        } catch (\DomainException $exception) {
            return $this->backWithError($person, $exception->getMessage());
        }

        return $this->back($person, \sprintf('Der Austritt ist eingetragen. %s kann sich nicht mehr anmelden.', $person->getDisplayName()));
    }

    #[Route('/austritt-zuruecknehmen', name: 'app_admin_person_undo_leave')]
    public function undoLeave(#[MapEntity(id: 'id')] AppUser $person, Request $request, #[CurrentUser] AppUser $admin): Response
    {
        $this->checkAccess($person, $request);
        $this->personAccess->undoLeave($person, $admin);

        return $this->back($person, 'Der Austritt ist zurückgenommen. Richte den Zugang bei Bedarf neu ein.');
    }

    private function checkAccess(AppUser $person, Request $request): void
    {
        $this->denyAccessUnlessGranted(Permission::PERSON_ACCESS, $person);
        if (!$this->isCsrfTokenValid('person-access-'.$person->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * @param array<string, mixed> $secret what the card shows once
     */
    private function card(Request $request, AppUser $person, array $secret): Response
    {
        $response = $this->render('admin/person/card.html.twig', [
            'person' => $person,
            'address' => $this->passkeySupport->linkBase($request),
            ...$secret,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function back(AppUser $person, string $message): Response
    {
        $this->addFlash('success', self::html($message));

        return $this->redirectToRoute('admin_person_access', ['entityId' => $person->getId()], Response::HTTP_SEE_OTHER);
    }

    private function backWithError(AppUser $person, string $message): Response
    {
        $this->addFlash('danger', self::html($message));

        return $this->redirectToRoute('admin_person_access', ['entityId' => $person->getId()], Response::HTTP_SEE_OTHER);
    }

    /**
     * EasyAdmin prints flash messages as HTML (|raw) – they contain display names typed by admins.
     */
    private static function html(string $text): string
    {
        return htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
    }
}
