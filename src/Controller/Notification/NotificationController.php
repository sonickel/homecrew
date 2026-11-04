<?php

declare(strict_types=1);

namespace App\Controller\Notification;

use App\Entity\Notification;
use App\Security\Permission;
use App\Service\Notification\Inbox;
use App\Service\Viewer\Viewer;
use App\Service\Viewer\ViewerProvider;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * The bell (konzept-9 §5): the own notifications of a person – never at the team tablet. A notification counts as read
 * when it is opened or "Alle als gelesen markieren" is tapped; never by merely loading a page (Turbo loads links ahead).
 */
#[IsGranted(Permission::NOTIFICATION_VIEW)]
final class NotificationController extends AbstractController
{
    /** Where "Als gelesen markieren" leads back to – a fixed list, never a URL from the request. */
    private const array BACK = ['heute' => 'app_home', 'benachrichtigungen' => 'app_notification_index'];

    public function __construct(
        private readonly ViewerProvider $viewerProvider,
        private readonly Inbox $inbox,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/benachrichtigungen', name: 'app_notification_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('notification/index.html.twig', [
            'notifications' => $this->inbox->list($this->viewer()),
        ]);
    }

    /**
     * Marks the notification as read and opens what it is about – only pages inside HomeCrew.
     */
    #[Route('/benachrichtigungen/{id}/oeffnen', name: 'app_notification_open', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function open(Notification $notification, Request $request): Response
    {
        if (!$this->isGranted(Permission::NOTIFICATION_VIEW, $notification) || !$this->inbox->shows($this->viewer(), $notification)) {
            throw $this->createNotFoundException('Unknown notification.');
        }
        $this->checkToken($request);
        $notification->markRead($this->clock->now());
        $this->entityManager->flush();

        $link = (string) $notification->getLink();
        if (!self::isInternal($link)) {
            return $this->redirectToRoute('app_notification_index', status: Response::HTTP_SEE_OTHER);
        }

        return $this->redirect($link, Response::HTTP_SEE_OTHER);
    }

    #[Route('/benachrichtigungen/gelesen', name: 'app_notification_read_all', methods: ['POST'])]
    public function readAll(Request $request): Response
    {
        $this->checkToken($request);
        $this->inbox->markAllRead($this->viewer(), $this->clock->now());
        $back = $request->getPayload()->getString('zurueck');

        return $this->redirectToRoute(self::BACK[$back] ?? 'app_notification_index', status: Response::HTTP_SEE_OTHER);
    }

    /**
     * A path of this app ("/meldungen/…") – no other host, no protocol-relative "//host", no control characters.
     */
    public static function isInternal(string $link): bool
    {
        return str_starts_with($link, '/') && !str_starts_with($link, '//') && !str_contains($link, '\\') && 1 !== preg_match('/[\x00-\x1F\x7F]/', $link);
    }

    private function viewer(): Viewer
    {
        return $this->viewerProvider->current() ?? throw $this->createAccessDeniedException();
    }

    private function checkToken(Request $request): void
    {
        if (!$this->isCsrfTokenValid('submit', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
