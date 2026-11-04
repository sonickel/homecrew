<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Kind of an in-app notification (notification.kind) – decides the symbol in the bell's list.
 */
enum NotificationKind: string
{
    case Nachricht = 'nachricht';
    case Meldung = 'meldung';
    case Dienst = 'dienst';
    case Urlaub = 'urlaub';
    case Aufgabe = 'aufgabe';
    case Termin = 'termin';
    case Uebergabe = 'uebergabe';
    case Erinnerung = 'erinnerung';
    case Checkliste = 'checkliste';
    case System = 'system';

    public function icon(): string
    {
        return match ($this) {
            self::Meldung => 'tabler:speakerphone',
            self::Aufgabe => 'tabler:list-check',
            self::Termin, self::Dienst, self::Urlaub => 'tabler:calendar-event',
            default => 'tabler:bell',
        };
    }
}
