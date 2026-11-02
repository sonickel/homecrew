<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Who sees an emergency contact (emergency_contact.visibility): everybody incl. the team tablet, or admins only.
 */
enum EmergencyVisibility: string
{
    case Alle = 'alle';
    case Admins = 'admins';

    public function label(): string
    {
        return match ($this) {
            self::Alle => 'Alle, auch am Team-Tablet',
            self::Admins => 'Nur Admins',
        };
    }
}
