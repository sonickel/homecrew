<?php

declare(strict_types=1);

namespace App\Service\Household;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use Doctrine\DBAL\Exception as DbalException;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Household name, time zone and "today" as the household sees it. Reads app_setting once per request
 * and falls back to defaults when the database is unreachable, so error pages still render.
 */
final class HouseholdContext implements ResetInterface
{
    public const string FALLBACK_NAME = 'HomeCrew';
    public const string FALLBACK_TIMEZONE = 'Europe/Berlin';

    private ?AppSetting $settings = null;
    private bool $loaded = false;

    public function __construct(
        private readonly AppSettingRepository $settingRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    public function name(): string
    {
        return $this->settings()?->getHouseholdName() ?? self::FALLBACK_NAME;
    }

    public function timezone(): \DateTimeZone
    {
        try {
            return new \DateTimeZone($this->settings()?->getTimezone() ?? self::FALLBACK_TIMEZONE);
        } catch (\Exception) {
            return new \DateTimeZone(self::FALLBACK_TIMEZONE);
        }
    }

    /**
     * Current date in the household's time zone, at midnight.
     */
    public function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone($this->timezone())->setTime(0, 0);
    }

    public function reset(): void
    {
        $this->settings = null;
        $this->loaded = false;
    }

    private function settings(): ?AppSetting
    {
        if (!$this->loaded) {
            $this->loaded = true;
            try {
                $this->settings = $this->settingRepository->get();
            } catch (DbalException|\LogicException) {
                $this->settings = null;
            }
        }

        return $this->settings;
    }
}
