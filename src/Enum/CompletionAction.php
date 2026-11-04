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

    /**
     * As it reads after a date in the history: "3. November 2026 erledigt".
     */
    public function label(): string
    {
        return match ($this) {
            self::Erledigt => 'erledigt',
            self::Uebersprungen => 'übersprungen',
            self::NichtMoeglich => 'nicht möglich',
        };
    }
}
