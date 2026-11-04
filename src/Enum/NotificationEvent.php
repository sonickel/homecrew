<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Events a person can be notified about (konzept-13 §7, notification_preference.event_type from M13).
 * Who gets which event by default: App\Service\Notification\NotificationDefaults.
 */
enum NotificationEvent: string
{
    case Meldung = 'meldung';
    case MeldungErledigt = 'meldung_erledigt';
    case Frage = 'frage';
    case UebergabeWichtig = 'uebergabe_wichtig';
    case Nachricht = 'nachricht';
    case Dienst = 'dienst';
    case Urlaub = 'urlaub';
    case Aufgabe = 'aufgabe';
    case Gespraech = 'gespraech';
    case Termin = 'termin';
    case Frist = 'frist';
    case Checkliste = 'checkliste';
    case Anleitung = 'anleitung';
    case Wartung = 'wartung';

    public function kind(): NotificationKind
    {
        return match ($this) {
            self::Meldung, self::MeldungErledigt => NotificationKind::Meldung,
            self::Frage, self::UebergabeWichtig => NotificationKind::Uebergabe,
            self::Nachricht => NotificationKind::Nachricht,
            self::Dienst => NotificationKind::Dienst,
            self::Urlaub => NotificationKind::Urlaub,
            self::Aufgabe, self::Gespraech, self::Wartung => NotificationKind::Aufgabe,
            self::Termin => NotificationKind::Termin,
            self::Frist => NotificationKind::Erinnerung,
            self::Checkliste => NotificationKind::Checkliste,
            self::Anleitung => NotificationKind::System,
        };
    }
}
