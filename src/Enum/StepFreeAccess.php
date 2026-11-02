<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Whether a place can be reached without steps (poi.a11y_step_free).
 */
enum StepFreeAccess: string
{
    case Ja = 'ja';
    case Nein = 'nein';
    case Teilweise = 'teilweise';
}
