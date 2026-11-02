<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\AppUser;
use App\Enum\FontSize;
use App\Service\Household\HouseholdContext;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

/**
 * What the layout needs on every page: household name and the display settings of the person
 * (the team tablet's own font size follows in M4).
 */
final readonly class LayoutExtension
{
    public function __construct(
        private HouseholdContext $household,
        private Security $security,
    ) {
    }

    #[AsTwigFunction('household_name')]
    public function householdName(): string
    {
        return $this->household->name();
    }

    #[AsTwigFunction('display_font_size')]
    public function fontSize(): string
    {
        $user = $this->security->getUser();

        return ($user instanceof AppUser ? $user->getFontSize() : FontSize::Normal)->value;
    }

    #[AsTwigFunction('display_high_contrast')]
    public function highContrast(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof AppUser && $user->isHighContrast();
    }
}
