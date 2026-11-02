<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AppUser;
use App\Service\Household\HouseholdContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Ends running sessions of locked persons and persons who left, on their next request – the rule of
 * App\Security\UserChecker at the login, plus: leaving ends a session at once (also sessions without password,
 * e.g. the tablet PIN in M4).
 */
final readonly class AccountStatusSubscriber
{
    public function __construct(
        private Security $security,
        private HouseholdContext $household,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[AsEventListener(priority: 7)]
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        // Leaving removes the access at once (also on the last working day, unlike the login check)
        if (!$user instanceof AppUser || (!$user->isBlockedOn($this->household->today()) && !$user->hasLeft())) {
            return;
        }

        $this->security->logout(false);
        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_login', ['grund' => 'gesperrt'])));
    }
}
