---
name: symfony-qualitaet
description: Code-Qualität, Architektur und Tests für HomeCrew (PHP 8.4, Symfony 7.4 LTS, Doctrine). Verwenden bei jedem Schreiben oder Ändern von PHP-Code, Konfiguration, Tests und beim Einrichten der Qualitätswerkzeuge.
---

# Code-Qualität in HomeCrew

## Grundhaltung
Einfacher, gut lesbarer Symfony-Standard vor Cleverness. Eine Person soll den Code später ohne KI verstehen und ändern können.

## Struktur
- Symfony-Standardstruktur, nach Bereichen gruppiert:
  - `src/Controller/<Bereich>/` – dünn: Eingabe → Service → Antwort
  - `src/Entity/`, `src/Repository/` – Doctrine; Repositories mit sprechenden Methoden (`findOpenForTeamDevice()`), kein Query-Bau im Controller
  - `src/Service/<Bereich>/` – Fachlogik (z. B. `Series/OccurrenceGenerator`, `Report/ReportCloser`, `Feature/FeatureAvailability`)
  - `src/Security/Voter/` – **jede** Rechteentscheidung; Controller rufen `denyAccessUnlessGranted()`
  - `src/Enum/` – PHP Backed Enums für alle Wertelisten, Werte exakt wie im Schema (deutsch, z. B. `nicht_moeglich`)
  - `src/Form/`, `src/Twig/Components/`, `src/MessageHandler/`, `src/Scheduler/`
- Bereiche: Account (Personen, Anmeldung, Geräte), Today, Task, Report (Meldungen), Calendar (Termine, Besuche, Serien), Shift (Dienstplan, Zeiten), Knowledge, ListItem, Notification, Admin, Extension.

## PHP-Regeln
- `declare(strict_types=1);`, Klassen standardmäßig `final`, `readonly` wo möglich, Konstruktor-Injection, keine statischen Helfer mit Zustand.
- Zeit nur über `Symfony\Component\Clock\ClockInterface` (testbar); `DateTimeImmutable`; Speichern in UTC, Anzeige in `app_setting.timezone`.
- Geld als `string`/Decimal (keine Floats); Berechnungen mit `bcmath` oder Ganzzahl-Cent.
- Keine Geheimnisse, Personennamen oder Inhalte in Logs. Exceptions mit fachlich verständlicher Meldung für die Oberfläche.
- Texte in Templates über den Translator (`{{ 'Erledigt'|trans }}`, Quelltexte deutsch) – vorbereitet für spätere Übersetzung.
- Keine neuen Abhängigkeiten ohne kurzen Grund im Plan (Wartung, Lizenz, Pi-tauglich).

## Doctrine
- Attribute-Mapping; Tabellen- und Spaltennamen explizit wie in `docs/datenbank/schema.sql`.
- Enums mit `enumType`, Datumsfelder `*_immutable`.
- Keine Lazy-Loading-Kaskaden in Listen: gezielte Joins/Fetch im Repository.
- Siehe Skill `datenbank-migrationen` für Migrationen.

## Sicherheit & Rechte
- Rechte nie im Template allein prüfen – Template nutzt `is_granted()` nur für die Anzeige, die eigentliche Prüfung macht der Controller/Voter.
- Sichtbarkeit (`alle`/`zustaendig`/`admins`/`privat`) zentral in einem Service/Voter, nicht in jeder Query neu erfinden; Repository-Methoden bekommen den „Betrachter" (Person oder Team-Gerät) übergeben.
- CSRF auf allen Formularen und Aktionen (Turbo-Formulare eingeschlossen).
- Details: Skill `datenschutz-sicherheit`.

## Tests
- PHPUnit; Unit-Tests für Services, Voter, Enums, Berechnungen (Serien-Generator, Zeiten, später Lohn).
- Funktionale Tests (`WebTestCase`) je Seite/Aktion: erlaubt/verboten je Rolle **und** Team-Tablet.
- Datenbanktests gegen eigene Test-DB (`homecrew_test`), Transaktionen pro Test zurückrollen (z. B. dama/doctrine-test-bundle); Testdaten über Factories/Fixtures.
- Ein Bugfix bekommt zuerst einen fehlschlagenden Test.

## Qualitätswerkzeuge (in Meilenstein 0 einrichten)
- **PHP-CS-Fixer** (Regelsatz `@Symfony` + `declare_strict_types`)
- **PHPStan** Level 8 mit `phpstan-symfony` und `phpstan-doctrine` (Baseline nur in Ausnahmefällen)
- `bin/console lint:twig templates`, `lint:yaml config`, `lint:container`
- Composer-Skript `qa`, das alles nacheinander ausführt und bei Fehlern abbricht; zusätzlich `qa:fix` für automatische Korrekturen.

## Vor dem Commit
`composer qa` grün · keine auskommentierten Code-Reste, `dump()`/`dd()` · keine TODOs ohne Eintrag in der Plan-Datei.
