<?php

declare(strict_types=1);

namespace App\Controller\Tablet;

use App\Service\Tablet\DeviceCookie;
use App\Service\Tablet\DevicePairing;
use App\Service\Tablet\HomeNetwork;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The tablet redeems a pairing code (konzept-12 §5): from the QR code (code after "#", taken over by
 * assets/controllers/fragment_code_controller.js) or typed. Only in the home network – the tablet would not work
 * anywhere else. Success sets the device cookie and opens the start page of the tablet.
 */
final class PairingController extends AbstractController
{
    public function __construct(
        private readonly DevicePairing $pairing,
        private readonly DeviceCookie $deviceCookie,
        private readonly HomeNetwork $homeNetwork,
    ) {
    }

    #[Route('/koppeln', name: 'app_pairing', methods: ['GET', 'POST'])]
    public function pair(
        Request $request,
        #[Autowire(service: 'limiter.pairing')]
        RateLimiterFactoryInterface $limiterFactory,
    ): Response {
        $paired = $this->deviceCookie->pairedDevice($request);
        $error = null;
        if (null === $paired && $request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('pairing', $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            // Only tries from the home network count: from outside, all requests may share the tunnel's address
            $limit = $this->homeNetwork->contains($request) ? $limiterFactory->create($request->getClientIp() ?? 'unbekannt')->consume() : null;
            if (null === $limit) {
                $error = 'Koppeln geht nur im WLAN zu Hause. Verbinde das Tablet mit dem WLAN und versuch es noch einmal.';
            } elseif (!$limit->isAccepted()) {
                // The rate limiter works with the system time, so the waiting time is computed with it as well
                $minutes = max(1, (int) ceil(($limit->getRetryAfter()->getTimestamp() - time()) / 60));
                $error = 1 === $minutes
                    ? 'Zu viele Versuche. Bitte warte eine Minute und versuch es dann noch einmal.'
                    : \sprintf('Zu viele Versuche. Bitte warte %d Minuten und versuch es dann noch einmal.', $minutes);
            } else {
                $result = $this->pairing->pair($request->getPayload()->getString('code'));
                if (null !== $result) {
                    $this->addFlash('ok', \sprintf('Dieses Tablet ist jetzt gekoppelt: „%s“.', $result['device']->getName()));
                    $response = $this->redirectToRoute('app_home', status: Response::HTTP_SEE_OTHER);
                    $response->headers->setCookie($this->deviceCookie->create($result['token'], $request));

                    return $response;
                }
                $error = 'Diesen Code kennt HomeCrew nicht – vielleicht ist er abgelaufen oder schon benutzt. Lass dir in der Verwaltung einen neuen Code zeigen.';
            }
        }

        return $this->render('tablet/pairing.html.twig', [
            'paired' => $paired,
            'error' => $error,
        ], new Response(status: null === $error ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
