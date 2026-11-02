<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Group of an emergency contact (emergency_contact.category); the emergency page lists them in this order.
 */
enum EmergencyCategory: string
{
    case Angehoerige = 'angehoerige';
    case Arzt = 'arzt';
    case Notdienst = 'notdienst';
    case Dienstleister = 'dienstleister';
    case Sonstiges = 'sonstiges';

    public function label(): string
    {
        return match ($this) {
            self::Angehoerige => 'Angehörige',
            self::Arzt => 'Ärzte',
            self::Notdienst => 'Notdienste',
            self::Dienstleister => 'Dienstleister',
            self::Sonstiges => 'Sonstiges',
        };
    }
}
