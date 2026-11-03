<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How a task becomes due (task.due_rule): on its date, or at the next shift of a person (with the roster, M9/M10).
 */
enum DueRule: string
{
    case Datum = 'datum';
    case NaechsterDienst = 'naechster_dienst';
}
