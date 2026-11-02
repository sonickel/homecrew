<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Result of checking an invitation code; message() is what the invitation page says.
 */
enum InvitationStatus
{
    case Gueltig;
    case Unbekannt;
    case Benutzt;
    case Abgelaufen;
    case Gesperrt;

    public function message(): string
    {
        return match ($this) {
            self::Gueltig => '',
            self::Unbekannt => 'Diesen Einladungscode gibt es nicht (mehr). Prüf die Zeichen auf dem Kärtchen. Ist die Einladung älter als 72 Stunden oder wurde sie ersetzt, frag einen Admin nach einer neuen.',
            self::Benutzt => 'Diese Einladung wurde schon benutzt. Melde dich mit deinem Benutzernamen und Passwort an oder frag einen Admin nach einer neuen Einladung.',
            self::Abgelaufen => 'Diese Einladung ist abgelaufen (sie gilt 72 Stunden). Bitte frag einen Admin nach einer neuen.',
            self::Gesperrt => 'Dein Zugang ist gesperrt. Bitte wende dich an einen Admin.',
        };
    }
}
