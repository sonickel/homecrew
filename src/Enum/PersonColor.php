<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Colour of a person's name tile at the tablet (app_user.color, #rrggbb). A fixed palette instead of a free colour
 * picker: every colour carries white bold text with a contrast of at least 4.5:1 (checked 02.11., M4).
 * The CSS has one class per colour (assets/styles/components.css) – inline styles are blocked by the CSP.
 */
enum PersonColor: string
{
    case Blau = '#1e4d8c';
    case Gruen = '#2e7d4f';
    case Ocker = '#8a5300';
    case Lila = '#6b3fa0';
    case Petrol = '#0f6e73';
    case Beere = '#a1306b';
    case Schiefer = '#4a5560';
    case Rost = '#a5420e';

    public function label(): string
    {
        return match ($this) {
            self::Blau => 'Blau',
            self::Gruen => 'Grün',
            self::Ocker => 'Ocker',
            self::Lila => 'Lila',
            self::Petrol => 'Petrol',
            self::Beere => 'Beere',
            self::Schiefer => 'Schiefer',
            self::Rost => 'Rost',
        };
    }

    /**
     * CSS class of the tile; colours from before the palette (or none) get a neutral tile.
     */
    public static function cssClass(?string $stored): string
    {
        $color = null === $stored ? null : self::tryFrom(strtolower($stored));

        return 'hc-color-'.strtolower($color->name ?? 'neutral');
    }
}
