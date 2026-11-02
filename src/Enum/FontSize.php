<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Font size per person or device (app_user.font_size, device.font_size); value is used 1:1 as html[data-font].
 */
enum FontSize: string
{
    case Normal = 'normal';
    case Gross = 'gross';
    case SehrGross = 'sehr_gross';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::Gross => 'Groß',
            self::SehrGross => 'Sehr groß',
        };
    }
}
