<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\FontSize;
use App\Enum\PersonColor;
use App\Service\Household\HouseholdContext;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

/**
 * What the layout needs on every page: household name, the logged-in person (the team tablet is none)
 * and the display settings – of the person, or of the tablet in its team view.
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

    /**
     * Time zone name for scripts that show the time (screensaver of the team tablet).
     */
    #[AsTwigFunction('household_timezone')]
    public function householdTimezone(): string
    {
        return $this->household->timezone()->getName();
    }

    /**
     * The person logged in (fully, by PIN or in the second step) – null for the team tablet and visitors.
     */
    #[AsTwigFunction('current_person')]
    public function currentPerson(): ?AppUser
    {
        $user = $this->security->getUser();

        return $user instanceof AppUser ? $user : null;
    }

    #[AsTwigFunction('person_color_class')]
    public function personColorClass(AppUser $person): string
    {
        return PersonColor::cssClass($person->getColor());
    }

    #[AsTwigFunction('display_font_size')]
    public function fontSize(): string
    {
        $user = $this->security->getUser();

        $fontSize = match (true) {
            $user instanceof AppUser => $user->getFontSize(),
            $user instanceof Device => $user->getFontSize(),
            default => FontSize::Normal,
        };

        return $fontSize->value;
    }

    #[AsTwigFunction('display_high_contrast')]
    public function highContrast(): bool
    {
        $user = $this->security->getUser();

        return $user instanceof AppUser && $user->isHighContrast();
    }
}
