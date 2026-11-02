<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Actions written to audit_log.action ("area.action", German like the value lists of the schema).
 * The column has no CHECK, the list lives here; label() is what the log page shows.
 */
enum AuditAction: string
{
    case AnmeldungErfolg = 'anmeldung.erfolg';
    case AnmeldungFehlschlag = 'anmeldung.fehlschlag';
    case AnmeldungGesperrt = 'anmeldung.gesperrt';
    case PasswortAendern = 'passwort.aendern';
    case ZweifaktorEinrichten = 'zweifaktor.einrichten';
    case ZweifaktorCodes = 'zweifaktor.codes';
    case ZweifaktorErsatzcode = 'zweifaktor.ersatzcode';
    case ZweifaktorZuruecksetzen = 'zweifaktor.zuruecksetzen';
    case PasskeyHinzufuegen = 'passkey.hinzufuegen';
    case EinrichtungManager = 'einrichtung.manager';
    case EinstellungenAendern = 'einstellungen.aendern';

    public function label(): string
    {
        return match ($this) {
            self::AnmeldungErfolg => 'Angemeldet',
            self::AnmeldungFehlschlag => 'Anmeldung fehlgeschlagen',
            self::AnmeldungGesperrt => 'Anmeldung gesperrt (zu viele Fehlversuche)',
            self::PasswortAendern => 'Passwort geändert',
            self::ZweifaktorEinrichten => 'Zweiten Schritt eingerichtet',
            self::ZweifaktorCodes => 'Neue Wiederherstellungscodes',
            self::ZweifaktorErsatzcode => 'Wiederherstellungscode benutzt',
            self::ZweifaktorZuruecksetzen => 'Zweiten Schritt zurückgesetzt',
            self::PasskeyHinzufuegen => 'Passkey hinzugefügt',
            self::EinrichtungManager => 'Manager eingerichtet',
            self::EinstellungenAendern => 'Einstellungen geändert',
        };
    }
}
