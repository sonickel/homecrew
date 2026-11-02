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
    case ZweifaktorAppEntfernen = 'zweifaktor.app_entfernen';
    case PasskeyHinzufuegen = 'passkey.hinzufuegen';
    case PasskeyEntfernen = 'passkey.entfernen';
    case EinrichtungManager = 'einrichtung.manager';
    case EinstellungenAendern = 'einstellungen.aendern';
    case PersonAnlegen = 'person.anlegen';
    case PersonAendern = 'person.aendern';
    case PersonSperren = 'person.sperren';
    case PersonEntsperren = 'person.entsperren';
    case PersonAustritt = 'person.austritt';
    case PersonAustrittZuruecknehmen = 'person.austritt_zuruecknehmen';
    case StartPasswort = 'zugang.startpasswort';
    case StartPin = 'zugang.startpin';
    case PinAnmeldung = 'zugang.pin_anmeldung';
    case EinladungErstellen = 'einladung.erstellen';
    case EinladungEinloesen = 'einladung.einloesen';

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
            self::ZweifaktorAppEntfernen => 'Authenticator-App entfernt',
            self::PasskeyHinzufuegen => 'Passkey hinzugefügt',
            self::PasskeyEntfernen => 'Passkey entfernt',
            self::EinrichtungManager => 'Manager eingerichtet',
            self::EinstellungenAendern => 'Einstellungen geändert',
            self::PersonAnlegen => 'Person angelegt',
            self::PersonAendern => 'Person geändert',
            self::PersonSperren => 'Zugang gesperrt',
            self::PersonEntsperren => 'Sperre aufgehoben',
            self::PersonAustritt => 'Austritt eingetragen',
            self::PersonAustrittZuruecknehmen => 'Austritt zurückgenommen',
            self::StartPasswort => 'Start-Passwort erzeugt',
            self::StartPin => 'Start-PIN erzeugt',
            self::PinAnmeldung => 'PIN-Anmeldung ein- oder ausgeschaltet',
            self::EinladungErstellen => 'Einladung erstellt',
            self::EinladungEinloesen => 'Einladung eingelöst',
        };
    }
}
