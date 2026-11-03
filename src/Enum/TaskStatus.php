<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * State of a task (task.status); "verschoben" is no state – postponing changes the due date.
 */
enum TaskStatus: string
{
    case Offen = 'offen';
    case Erledigt = 'erledigt';
    case Uebersprungen = 'uebersprungen';
    case NichtMoeglich = 'nicht_moeglich';
}
