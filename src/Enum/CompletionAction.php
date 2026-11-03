<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How a task was closed (task_completion.action); "nicht möglich" creates a report (M7).
 */
enum CompletionAction: string
{
    case Erledigt = 'erledigt';
    case Uebersprungen = 'uebersprungen';
    case NichtMoeglich = 'nicht_moeglich';

    public function status(): TaskStatus
    {
        return match ($this) {
            self::Erledigt => TaskStatus::Erledigt,
            self::Uebersprungen => TaskStatus::Uebersprungen,
            self::NichtMoeglich => TaskStatus::NichtMoeglich,
        };
    }
}
