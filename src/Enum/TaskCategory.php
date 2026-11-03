<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Category of a task or plan (task.category, task_template.category, konzept-2 §5.1).
 */
enum TaskCategory: string
{
    case Haushalt = 'haushalt';
    case Waesche = 'waesche';
    case Kueche = 'kueche';
    case Hilfsmittel = 'hilfsmittel';
    case Einkauf = 'einkauf';
    case Termin = 'termin';
    case Gespraech = 'gespraech';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Haushalt => 'Haushalt',
            self::Waesche => 'Wäsche',
            self::Kueche => 'Küche',
            self::Hilfsmittel => 'Hilfsmittel',
            self::Einkauf => 'Einkauf',
            self::Termin => 'Termin',
            self::Gespraech => 'Gespräch',
            self::Sonstiges => 'Sonstiges',
        };
    }
}
