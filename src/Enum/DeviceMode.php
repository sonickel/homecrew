<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How a paired tablet is used (device.mode): team view without login, personal PIN login, or both.
 */
enum DeviceMode: string
{
    case Team = 'team';
    case Persoenlich = 'persoenlich';
    case Beides = 'beides';
}
