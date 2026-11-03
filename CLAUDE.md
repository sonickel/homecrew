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
- Migrationen: `php bin/console doctrine:migrations:diff` (nur Hilfe/Gegenprobe – Vorgehen: Konzept 6 §6) · `php bin/console doctrine:migrations:migrate`
- Qualität: `composer qa` (CS-Fixer, Twig/YAML/Container-Lint, PHPStan Level 8, Test-DB migrieren, PHPUnit – bricht beim ersten Fehler ab) · `composer qa:fix` (Code-Stil automatisch korrigieren)
- Schema-Abgleich: `composer schema:check` (migriert `homecrew_check` von Null und vergleicht mit der Referenz in `homecrew_smoke`)
- Referenzschema testen: `mariadb -e "DROP DATABASE IF EXISTS homecrew_smoke; CREATE DATABASE homecrew_smoke CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" && mariadb homecrew_smoke < docs/datenbank/schema.sql && mariadb homecrew_smoke < docs/datenbank/smoke_test.sql` (letzte Zeile muss „ALLE PRÜFUNGEN OK" sein)
- Testdaten (nur dev): `php bin/console doctrine:fixtures:load` (leert alle Tabellen, legt Einstellungen + Testpersonen neu an)

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
- **Admins**: 2FA/Passkey Pflicht (Schalter `ADMIN_2FA_REQUIRED`, vorerst aus – vor dem Echtbetrieb einschalten); Admin-Recht nur über `AdminVoter` (`Permission::ADMIN`), nie über eine Rolle; PIN und Team-Modus geben nie Admin-Rechte
- **Arbeitszeiten**: Assistenz sieht 3 Monate, bearbeitet nur am selben Tag; danach nur Admins mit Begründung; „Dienstplan übernehmen" überschreibt nie
- **Datenschutz**: nur Anzeigenamen; keine Diagnosen, Medikamente, Adressen, SV-/Steuernummern; Löschfristen
- **Barrierefreiheit**: WCAG 2.2 AA, Bedienelemente ≥ 48 px, Status nie nur über Farbe, Schriftgröße pro Person/Gerät
- Keine externen Skripte, Schriften oder Analysen (alles lokal ausliefern)

## Gelernt
<!-- Fehler und Korrekturen hier festhalten, damit sie sich nicht wiederholen. -->
- CSS nie aus JavaScript importieren: AssetMapper macht daraus `data:`-Module, die die strikte CSP blockiert – dann läuft gar kein JS (Turbo/Stimulus). BrowserKit-Tests merken das nicht; nach Änderungen an Assets im echten Browser prüfen (Konsole ohne CSP-Fehler, `typeof Turbo === "object"`). (M0)
- Die lokale CA der Symfony CLI liegt unter `~/.config/symfony-cli/certs/` (nicht `~/.symfony5`). (M0)
- dama/doctrine-test-bundle hält je Verbindungsname eine offene Verbindung und nutzt sie auch bei geänderter `DATABASE_URL` weiter – ein Test, der die DB unerreichbar macht, muss `StaticDriver::setKeepStaticConnections(false)` setzen (und danach zurück). Die Testumgebung liest `.env.local` nicht, Zugangsdaten stehen in `.env.test.local`. (M1)
- Tests mit `createClient(['debug' => false])` prüfen den Container-Cache in `var/cache/test` nicht auf Aktualität – nach Konfigurationsänderungen laufen sie sonst mit altem Container (z. B. ohne Security). `composer qa` leert deshalb vorher den Test-Cache. (M2)
- Rate-Limiter und `cache.app` liegen in Tests im Dateicache und überdauern Tests – Testklassen mit Anmeldungen starten über `PeopleTrait::startClient()`, das die Pools leert. Ein Array-Cache hilft nicht: Symfony setzt ihn nach jedem Request zurück. (M2)
- Die Firewall ist „lazy": `access_control` greift vor dem Abmelden. Pfade, die mitten im zweiten Anmeldeschritt erreichbar sein müssen (`/abmelden`), brauchen eine eigene `PUBLIC_ACCESS`-Regel. (M2)
- EasyAdmin gibt Werte aus `formatValue()` als HTML aus (`|raw`) – dort immer selbst escapen. Generische `Field::new()` ersetzt EasyAdmin durch einen geratenen Typ (dabei geht `formatValue` verloren); virtuelle Spalten brauchen einen expliziten Template-Pfad. (M2)
- Die Symfony CLI meldet `https://127.0.0.1:8000` und setzt im Webserver `DEFAULT_URI` auf diese Adresse (überschreibt `.env`). Über eine IP-Adresse lehnen Browser und Passwort-Manager Passkeys ab – lokal immer `https://localhost:8000` öffnen. Die App blendet Passkeys über IP-Adressen aus und verlinkt die Adresse mit Namen (`PasskeySupport`). (M2)
- In Attributen von Twig-Components (`<twig:X :intro="'…'">`) beendet ein gerades `"` das Attribut – deutsche Anführungszeichen dort als „…“ schreiben (nicht „…"). (M3)
- EasyAdmin-`ChoiceField` mit Enum-Werten schickt Indizes (`0`, `1`) statt der Werte; für Enums im Formular `Field::new(…)->setFormType(EnumType::class)` nehmen. Formularfehler stehen in `.invalid-feedback`. (M3)
- `DATE`-Werte als reines Datum anlegen (`new \DateTimeImmutable($tag->format('Y-m-d'))`): Ein Datum in Ortszeit um Mitternacht zeigt das Datumsfeld (UTC) sonst als Vortag. (M3)
- In Tests leert `EntityManager::clear()` alle vorher geladenen Entities – wer mehrere braucht, lädt sie nach einem einzigen `clear()`. Nach einer Client-Anfrage läuft ein neuer Kernel: Entities vor Service-Aufrufen neu laden. (M3)
- Die Symfony CLI schickt lokal `X-Forwarded-For: 127.0.0.1` mit. Heimnetz-/IP-Prüfungen nie an „irgendein Weiterleitungs-Header" knüpfen, sondern alle genannten Adressen prüfen (`HomeNetwork`); nie `getClientIp()` für Sicherheitsentscheidungen. (M4)
- Der Smoke-Test fügt nicht jeden Wert einer Werteliste ein: `emergency_contact.category` war kürzer als `'dienstleister'`. Bei neuen Enums prüfen, dass jeder Wert in die Spalte passt (Test, der alle Fälle speichert). (M4)
- BrowserKit: Test-Cookies mit Domain `localhost` setzen (`new Cookie($name, $value, null, '/', 'localhost')`), sonst trifft ein `Set-Cookie` zum Löschen sie nicht. `PeopleTrait::startClient()` leert `cache.app` – Daten im Cache (z. B. Kopplungscodes) brauchen für den zweiten Browser `createClient()`. (M4)
- Die `login_throttling` der Firewall prüft jeden Passport mit `UserBadge`, auch eigene Authenticatoren (Tablet), und setzt dabei `LAST_USERNAME`. Ohne `symfony/lock` zählen Rate-Limiter parallele Anfragen nicht sicher (`lock_factory: auto` = keine Sperre). Zähler, die pro Datensatz in der DB stehen, unter Zeilensperre lesen und schreiben. (M4)
- `EntityManager::wrapInTransaction()` schließt den EntityManager bei jeder Exception – erwartete Fehler (z. B. falsche PIN) erst nach dem Commit werfen. (M4)
- Twig löst `obj.person` zuerst über eine Methode `person()` auf – auch eine **statische** Fabrikmethode – und erst danach `getPerson()`. Objekte mit solchen Fabrikmethoden (`Viewer::person()`) nicht direkt ins Template, Werte getrennt übergeben. `field_name(form.x)` markiert das Feld als ausgegeben; für zusätzliche Eingaben `form.x.vars.full_name`. (M5)
- Einspaltige CSS-Raster brauchen `grid-template-columns: minmax(0, 1fr)`, sonst wächst die Spalte bei „sehr groß" über die Bildschirmbreite (Knöpfe abgeschnitten). (M5)
- Tests mit `mockTime()` und zweitem Schritt: Die App prüft TOTP-Codes gegen ihre (feste) Uhr – Code mit `PeopleTrait::codeAt()` erzeugen. (M5)
- Fixtures nicht in der Entwicklungsdatenbank neu laden, wenn dort eigene Daten stehen (eigenes Manager-Passwort): zum Prüfen in eine Wegwerf-DB `homecrew_…` laden. (M5)
- Turbo führt Inline-Skripte einer neu geladenen Seite aus, der Browser prüft sie aber gegen die CSP der **ersten** Seite (anderer Nonce) → Konsolenfehler „Executing inline script violates …“. Kam von der Web-Debug-Toolbar (dev). `assets/app.js` setzt deshalb bei `turbo:before-render` an allen Inline-Skripten `data-turbo-eval="false"`; eigene Inline-Skripte gibt es ohnehin nicht. (M5)
