# CLAUDE.md – HomeCrew

Diese Datei liest Claude Code bei jedem Start. Kurz halten; Details stehen in `docs/` und in den Projekt-Skills unter `.claude/skills/`.

## Projekt
**HomeCrew** – Web-App für Menschen mit persönlicher Assistenz im Arbeitgebermodell (Bayern, Bezirk, Minijobs im Haushaltsscheckverfahren). **Eine Installation = ein Haushalt** (keine Mandanten). Rollen: Manager (genau einer, immer Admin), Angehörige (optional Admin), Assistenz (nie Admin). Dazu das **Team-Tablet** (Gerät ohne Personenanmeldung). Externe Dienste (Physio, Caritas …) sind keine Nutzer, nur Besuche im Kalender.

**Fachliche Vorgabe**: `docs/konzept/` – vor jeder Aufgabe das passende Konzept lesen. Einstieg und maßgeblich bei Widersprüchen: @docs/konzept/kontext.md
Abweichungen vom Konzept nur nach Rückfrage; danach das Konzept-Dokument mit anpassen. `docs/archiv/` ist historisch und nicht maßgeblich.

## Umgebung
- **Lokal (jetzt)**: WSL2 Debian, Projekt unter `/var/www/homecrew`, VS Code im WSL-Modus. PHP 8.4 CLI, Composer, **Symfony CLI** als Webserver (`symfony server:start -d` → https://localhost:8000, im Windows-Browser erreichbar; `localhost` statt IP, weil Passkeys keine IP-Adresse akzeptieren), **MariaDB lokal in WSL**. Zugangsdaten nur in `.env.local` (nie committen, nie ausgeben).
- **Lokal bewusst nicht**: Docker, FrankenPHP, Mercure, whisper, cloudflared. Die App muss ohne diese Dienste voll funktionieren (Live-Aktualisierung optional, Fallback: normales Neuladen/Turbo).
- **Später**: Raspberry Pi 5 mit Docker Compose (FrankenPHP inkl. Mercure, MariaDB in derselben Version wie lokal, Worker, cloudflared, optional whisper). Deshalb: Konfiguration nur über Umgebungsvariablen/Einstellungen, keine absoluten Pfade, keine Sonderwege für lokal im Code.

## Technik
- PHP 8.4, **Symfony 7.4 LTS**, Doctrine ORM + Migrations, MariaDB
- Twig + Symfony UX (Turbo, Stimulus, TwigComponent, LiveComponent), **AssetMapper – kein Node-Build**, reines CSS mit Design-Tokens; reine Web-App, kein Offline-Modus
- EasyAdmin für reine Stammdaten-Masken; eigene Oberflächen für Abläufe
- Messenger (lokal Doctrine-Transport; Worker bei Bedarf `php bin/console messenger:consume async scheduler_default -vv`), Scheduler, php-rrule (ein gemeinsamer Serien-Generator), yasumi, PhpSpreadsheet, WebAuthn, scheb/2fa, reset-password, Mailer, HtmlSanitizer
- KI (Claude API) und Spracherkennung (whisper) sind **Erweiterungen** – erst Stufe 2
- Tests: PHPUnit (Unit + funktional), Datenbanktests mit eigener Test-DB

## Befehle
- Server: `symfony server:start -d` · `symfony server:stop` · Logs: `symfony server:log`
- Migrationen: `php bin/console doctrine:migrations:diff` (Ergebnis immer von Hand prüfen!) · `php bin/console doctrine:migrations:migrate`
- Qualität: `composer qa` (CS-Fixer, Twig/YAML/Container-Lint, PHPStan Level 8, PHPUnit – bricht beim ersten Fehler ab) · `composer qa:fix` (Code-Stil automatisch korrigieren)
- Referenzschema testen: `mariadb homecrew_smoke < docs/datenbank/schema.sql && mariadb homecrew_smoke < docs/datenbank/smoke_test.sql` (leere DB; letzte Zeile muss „ALLE PRÜFUNGEN OK" sein)

## Arbeitsweise
- Umsetzung in **Meilensteinen** nach `docs/plan-stufe-1.md`; Ablauf siehe Skill `meilenstein`: Plan-Modus → Plan bestätigen lassen → umsetzen → `composer qa` → Review (Agent `reviewer`) → Commit.
- Kleine, überprüfbare Schritte; nach jedem Meilenstein Plan-Datei abhaken.
- Skills nutzen: `meilenstein` (Ablauf), `symfony-qualitaet` (Code), `homecrew-ui` (Oberfläche/Design), `datenbank-migrationen` (Doctrine ↔ schema.sql), `datenschutz-sicherheit` (Prüfliste).
- Sprache: Kommunikation, Commit-Nachrichten und Oberflächentexte **deutsch**; Code, Bezeichner, Kommentare im Code englisch. Wertelisten deutsch wie im Schema.

## Datenbank
- Fachliche Referenz: `docs/datenbank/schema.sql` (+ `smoke_test.sql`). Doctrine-Entities und Migrationen müssen dazu passen (Namen, Typen, Constraints).
- Änderungen nur über Doctrine-Migrations; CHECKs und generierte Spalten von Hand in die Migration; **nie** `doctrine:schema:update`; eine Migration darf nie ungemappte Tabellen/Spalten löschen.
- Konventionen: englisch snake_case, Wertelisten deutsch (PHP Backed Enums), Geld `DECIMAL(10,2)`, Zeitpunkte UTC, Geplantes in Ortszeit; Serien = Vorlage + Vorkommen mit Ursprungstag und `is_modified`.

## Fachliche Regeln (Kurzfassung – Details in den Konzepten)
- **Rechte** immer über Voter (Rolle + Admin-Recht + Schalter); Sichtbarkeit alle/zuständig/admins/privat
- **Team-Tablet**: eigener Security-Kontext (Geräte-Token, `ROLE_TEAM_DEVICE`, kein `app_user`); sieht nur, was die Rolle Assistenz sähe; nur gekoppelt + Heimnetz; keine Zeiterfassung, Nachrichten, privaten oder Admin-Inhalte; Einträge tragen das Gerät statt der Person
- **Erweiterungen** (KI, E-Mail, Spracherkennung, Push): Sichtbarkeit nur über `FeatureAvailability` – nicht eingerichtet = unsichtbar; Geheimnisse (`*_enc`) nie an Browser, Log oder Export; Ändern nur nach erneuter 2FA
- **Besuche** externer Dienste: reine Info, für alle sichtbar, keine Zuständigkeit/Begleitung
- **Meldungen**: „nicht möglich" erzeugt genau eine Meldung; Admins werden nach ihren Regeln benachrichtigt; automatischer Abschluss
- **„Zuletzt erledigt"**: für alle nur Datum; Person bzw. „Team-Tablet" nur für Admins
- **Admins**: 2FA/Passkey Pflicht; PIN und Team-Modus geben nie Admin-Rechte
- **Arbeitszeiten**: Assistenz sieht 3 Monate, bearbeitet nur am selben Tag; danach nur Admins mit Begründung; „Dienstplan übernehmen" überschreibt nie
- **Datenschutz**: nur Anzeigenamen; keine Diagnosen, Medikamente, Adressen, SV-/Steuernummern; Löschfristen
- **Barrierefreiheit**: WCAG 2.2 AA, Bedienelemente ≥ 48 px, Status nie nur über Farbe, Schriftgröße pro Person/Gerät
- Keine externen Skripte, Schriften oder Analysen (alles lokal ausliefern)

## Gelernt
<!-- Fehler und Korrekturen hier festhalten, damit sie sich nicht wiederholen. -->
- CSS nie aus JavaScript importieren: AssetMapper macht daraus `data:`-Module, die die strikte CSP blockiert – dann läuft gar kein JS (Turbo/Stimulus). BrowserKit-Tests merken das nicht; nach Änderungen an Assets im echten Browser prüfen (Konsole ohne CSP-Fehler, `typeof Turbo === "object"`). (M0)
- Die lokale CA der Symfony CLI liegt unter `~/.config/symfony-cli/certs/` (nicht `~/.symfony5`). (M0)
