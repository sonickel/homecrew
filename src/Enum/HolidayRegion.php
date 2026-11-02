<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Public holidays of the household's place (app_setting.holiday_region); used by the calendar in M8.
 * Bavaria knows local holidays: Assumption Day only in mostly catholic communities, Peace Festival only in Augsburg.
 */
enum HolidayRegion: string
{
    case Bayern = 'BY';
    case BayernKatholisch = 'BY-KATH';
    case Augsburg = 'BY-AUGSBURG';

    public function label(): string
    {
        return match ($this) {
            self::Bayern => 'Bayern',
            self::BayernKatholisch => 'Bayern mit Mariä Himmelfahrt',
            self::Augsburg => 'Augsburg (mit Mariä Himmelfahrt und Friedensfest)',
        };
    }
}
