<?php

declare(strict_types=1);

namespace App\Service\Feature;

use App\Enum\ExtensionStatus;
use App\Enum\Feature;
use App\Repository\AppSettingRepository;
use Doctrine\DBAL\Exception as DbalException;
use Twig\Attribute\AsTwigFunction;

/**
 * The one place that decides whether an extension is visible (konzept-5 §1): only when an admin switched it on
 * and the last check was ok. Not set up or failing = invisible, not merely greyed out.
 * Skeleton in M2 (nothing can be set up yet); the setup pages and the health check follow in M13.
 */
final readonly class FeatureAvailability
{
    public function __construct(
        private AppSettingRepository $settingRepository,
    ) {
    }

    #[AsTwigFunction('feature_available')]
    public function isAvailable(Feature|string $feature): bool
    {
        $feature = $feature instanceof Feature ? $feature : Feature::from($feature);
        try {
            $settings = $this->settingRepository->get();
        } catch (DbalException) {
            return false; // no database, e.g. on an error page: nothing optional is shown
        }

        return match ($feature) {
            Feature::Email => $settings->isMailEnabled() && ExtensionStatus::Ok === $settings->getMailStatus(),
            Feature::Ki => $settings->isAiEnabled() && ExtensionStatus::Ok === $settings->getAiKeyStatus(),
        };
    }
}
