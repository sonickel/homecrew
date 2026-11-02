<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What a person is in the household (app_user.role); admin rights are a separate flag.
 */
enum UserRole: string
{
    case Manager = 'manager';
    case Assistenz = 'assistenz';
    case Angehoerige = 'angehoerige';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'Manager',
            self::Assistenz => 'Assistenz',
            self::Angehoerige => 'Angehörige',
        };
    }
}
