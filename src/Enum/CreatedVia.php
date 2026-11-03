<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Where an entry comes from (created_via of tasks, handovers, appointments, reports, chat messages).
 */
enum CreatedVia: string
{
    case App = 'app';
    case Ki = 'ki';
    case System = 'system';
}
