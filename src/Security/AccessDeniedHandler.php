<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\AppUser;
use App\Security\Pin\PinLoginToken;
use App\Service\Account\AccountSetup;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;

/**
 * A person with an open mandatory step (e.g. an admin without second factor) is sent to that step
 * instead of seeing "Kein Zutritt" – access_control decides before App\EventSubscriber\AccountSetupSubscriber runs.
 * Everybody else gets the normal 403 page.
 */
final readonly class AccessDeniedHandler implements AccessDeniedHandlerInterface
{
    public function __construct(
        private Security $security,
        private AccountSetup $accountSetup,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function handle(Request $request, AccessDeniedException $accessDeniedException): ?Response
    {
        $user = $this->security->getUser();
        if (!$user instanceof AppUser || !$this->security->isGranted('IS_AUTHENTICATED_FULLY')) {
            return null;
        }

        $step = $this->accountSetup->nextStep($user, $this->security->getToken() instanceof PinLoginToken);

        return null === $step ? null : new RedirectResponse($this->urlGenerator->generate($step), Response::HTTP_SEE_OTHER);
    }
}
