<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Rhythm of a plan (task_template.recurrence_type): by calendar (RRULE) or n days after the last completion.
 */
enum RecurrenceType: string
{
    case Kalender = 'kalender';
    case Intervall = 'intervall';
}
