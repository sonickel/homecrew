<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Second factor of a person (app_user.twofa_method).
 */
enum TwoFactorMethod: string
{
    case Keine = 'keine';
    case Passkey = 'passkey';
    case Totp = 'totp';
    case Email = 'email';
}
