<?php

declare(strict_types=1);

namespace App\Service\Viewer;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Security\Permission;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;

/**
 * Builds the viewer from a security token: a person (fully or by PIN), or the team tablet in a mode with
 * team view. Nobody while the second login step is open or at a tablet in mode "Persönlich" without PIN login.
 */
final readonly class ViewerProvider
{
    public function __construct(
        private Security $security,
        private AccessDecisionManagerInterface $accessDecisionManager,
    ) {
    }

    public function current(): ?Viewer
    {
        $token = $this->security->getToken();

        return null === $token ? null : $this->fromToken($token);
    }

    /**
     * For voters, which must decide for the token they are given.
     */
    public function fromToken(TokenInterface $token): ?Viewer
    {
        if ($token instanceof TwoFactorTokenInterface) {
            return null;
        }

        $user = $token->getUser();
        if ($user instanceof Device) {
            return $this->accessDecisionManager->decide($token, [Permission::TEAM_VIEW]) ? Viewer::teamTablet($user) : null;
        }
        if ($user instanceof AppUser) {
            return Viewer::person($user, $this->accessDecisionManager->decide($token, [Permission::ADMIN]));
        }

        return null;
    }
}
