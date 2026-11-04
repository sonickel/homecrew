# Plan Stufe 1 – Alltag & Kalender

Stand: 01.11.2026 · Grundlage: `docs/konzept/kontext.md` §4 (Stufe 1) und die Konzepte 1, 2, 3, 8, 9, 11, 12, 13 · Ablauf je Meilenstein: Skill `meilenstein` (Plan → Ok → Umsetzen → `composer qa` → Review → Commit)

## Regeln für alle Meilensteine
- Jeder Meilenstein endet lauffähig: `composer qa` grün, Migrationen laufen auf einer leeren Datenbank von Null durch, der Schema-Abgleich mit der Referenz ist grün (ab M1), und das Ergebnis ist im Browser prüfbar – Tablet quer (1024 × 768), Handy (390 × 844), Schriftgröße „sehr groß", nur Tastatur.
- Rechte immer über Voter; funktionale Tests für jede Rolle **und** das Team-Tablet (erlaubt/verboten).
- Keine toten Knöpfe: Eine Funktion erscheint erst in dem Meilenstein, der sie umsetzt (z. B. „Melden" ab M7, „Passwort vergessen" erst mit E-Mail in M13).
- Löschjobs und Druckansichten kommen mit der Funktion, deren Daten sie betreffen.
- Neue Pakete nur mit kurzer Begründung im Meilenstein-Plan; die vorgesehenen stehen je Meilenstein unter „Pakete".
- Testzugänge aus Fixtures (nur dev/test): Manager, Angehörige mit und ohne Admin-Recht, Assistenz „Anna" (mit PIN), Assistenz „Ben" (ohne Zugang), ein gekoppeltes Team-Tablet.
- Abweichungen vom Konzept nur nach Rückfrage; danach Konzept anpassen.

## Migrationsstrategie (in M1 erprobt, festgeschrieben in Konzept 6 §6)
1. Tabellen entstehen immer **vollständig** nach `schema.sql` – keine halben Tabellen, die später ergänzt werden.
2. Eine Tabelle entsteht in dem Meilenstein, der sie braucht, **zusammen mit allen Tabellen, auf die sie per Fremdschlüssel verweist** (transitiv). Solche „mitgezogenen" Tabellen bekommen Entity + Migration, aber noch keine Oberfläche.
3. Je Meilenstein eine Migration: `CREATE TABLE` **wörtlich aus `schema.sql`**, Startdaten dazu; `doctrine:migrations:diff` nur als Hilfe und als Gegenprobe („No changes detected"). Mapping-Regeln gegen Scheinänderungen: Konzept 6 §6.2.
4. Invariante: `composer schema:check` – jede migrierte Tabelle ist identisch mit derselben Tabelle der Referenz.
5. Technische Tabellen außerhalb der Referenz: `doctrine_migration_versions`, ab M11 `messenger_messages`.
6. Fachliche Schemaänderung → zuerst `schema.sql` + `smoke_test.sql`, Smoke-Test grün, dann Migration.

## Übersicht
| M | Inhalt | neue Tabellen (in Klammern: mitgezogen, ohne Oberfläche) | Konzepte |
|---|---|---|---|
| 0 | Grundgerüst, Qualitätswerkzeuge, Layout | – | 5, Skill `homecrew-ui` |
| 1 | Referenzschema, Smoke-Test, Migrationsstrategie | `app_setting`, `app_user` | 6 |
| 2 | Einrichtung, Anmeldung, Admin-Menü-Gerüst | `webauthn_credential`, `audit_log`, (`device`) | 1, 5, 11 |
| 3 | Personen und persönliche Zugänge | `invitation` | 1, 11 |
| 4 | Team-Tablet und Notfall | `emergency_contact`, (`poi`) | 12, 3 §2, 11 |
| 5 | „Heute" und einmalige Aufgaben | `task`, `task_completion`, (`task_template`, `guide`, `asset`, `shopping_list`) | 2, 12 |
| 6 | Wartungspläne, Serien-Generator, „Routinen & Wartung" | `task_template_item`, `task_item`, `task_item_result`, (`asset_component`) | 2 §5 |
| 7 | Listen, Meldungen, Benachrichtigungen in der App | `shopping_item`, `shopping_favorite`, `issue_report`, `notification`, (`handover_note`) | 13, 2 §9, 9 §5 |
| 8 | Kalender: Termine, externe Dienste, Besuche, Serien | `visitor`, `appointment_series`, `appointment`, `appointment_participant`, `appointment_item` | 8, 2 §8 |
| 9 | Dienstplan, Arbeitszeiten, Urlaub und Krankheit | `shift_pattern`, `planned_shift`, `time_entry`, `time_entry_change`, `absence` | 4 §3–4, 12 §4, 8 |
| 10 | Übergabebuch, Gesprächspunkte, Abwesenheit des Managers | `handover_read`, `manager_absence` | 2 §6–7, 8 §7 |
| 11 | Fotos und Videos | `media`, `upload_session`, (`chat_conversation`, `chat_message`) | 3 §5 |
| 12 | Wissen, „Wer kennt was", Orte, Profil | `guide_version`, `guide_step`, `guide_step_media`, `guide_tag`, `guide_feedback`, `guide_acknowledgement`, `recipe_ingredient`, `poi_guide`, `profile_item`, `profile_keyword` | 3 |
| 13 | Erweiterungen, E-Mail, Benachrichtigungsregeln | `notification_preference`, `password_reset_request` | 1 §6, 5, 7 §2, 9, 11, 13 §7 |

Nicht in Stufe 1 (27 Tabellen): Personal & Abrechnung, Checklisten, Sätze/Grenzen, Chat-Teilnehmer, Hilfsmittel-Sollwerte/Messwerte, KI, Übersetzung, Push, Kalender-Abo, Erinnerungen.

---

### M0 – Grundgerüst, Qualitätswerkzeuge, Layout
**Ziel:** Leere, aber vorzeigbare Symfony-7.4-App im bestehenden Repository, mit Qualitätsprüfung und dem HomeCrew-Layout.

**Umfang**
- Symfony 7.4 LTS (Skeleton) ins bestehende Repository; `docs/`, `.claude/`, `CLAUDE.md` bleiben; `.gitignore` inkl. `.env.local`.
- Pakete: Twig, Security, Doctrine ORM + Migrations, Form, Validator, Translation, Mailer, Messenger, Scheduler, Uid, Clock, RateLimiter, HtmlSanitizer, AssetMapper, StimulusBundle, UX Turbo, TwigComponent, LiveComponent, UX Icons (Icons lokal); dev: Maker, Debug, WebProfiler, PHPUnit, PHP-CS-Fixer, PHPStan (+ symfony, doctrine, phpunit). Zusätzlich: `nelmio/security-bundle` (CSP mit Nonces, Sicherheits-Header, Permissions-Policy), `twig/extra-bundle` + `twig/intl-extra` (deutsches Datum über ext-intl), `symfony/monolog-bundle` (Logs; Symfony-Standard).
- `composer qa` (CS-Fixer-Prüfung, PHPStan Level 8, `lint:twig`/`lint:yaml`/`lint:container`, PHPUnit) und `composer qa:fix`.
- Layout nach Skill `homecrew-ui`: `tokens.css` (inkl. hoher Kontrast), `base.css`, `components.css`, `print.css` mit Cascade Layers; Atkinson Hyperlegible Next 400/700 als woff2 lokal (mit Lizenzdatei); `base.html.twig` mit Kopfleiste (Datum groß), `data-font`/`data-contrast`; Komponenten `Button`, `PageHeader`, `EmptyState`.
- Startseite `/` als Platzhalter („Einrichtung folgt").
- Strikte Content-Security-Policy (nur eigene Quellen, Nonce für die Importmap), Sicherheits-Header, deutsche Fehlerseiten 403/404/500, Sprache `de`, Texte über den Translator.
- Lokaler Zugriff über `https://localhost:8000` (Passkeys erlauben keine IP-Adresse); Symfony-CA im Windows-Browser vertrauen; CLAUDE.md entsprechend anpassen.

**Tabellen:** keine.

**Akzeptanzkriterien**
- [x] `symfony check:requirements` ohne Fehler; `symfony server:start -d` → https://localhost:8000 zeigt die Startseite in HomeCrew-Schrift und -Farben, ohne Zertifikatswarnung.
- [x] `composer qa` grün; `composer qa:fix` behebt Stilfehler.
- [x] Funktionaler Test: Startseite 200, CSP-Header gesetzt, HTML ohne externe URLs.
- [x] Netzwerkansicht des Browsers: keine Anfrage an fremde Hosts (in Chrome über das DevTools-Protokoll geprüft: nur `localhost`, keine Konsolen-/CSP-Fehler, Turbo geladen).
- [x] 1024 × 768, 390 × 844 und Schriftgröße „sehr groß": nichts wird abgeschnitten; sichtbarer Fokus bei Tastaturbedienung (Screenshots per Chrome/DevTools-Protokoll, kein horizontales Scrollen).

**Abweichungen:** Schrift-Grundgröße auf Wunsch 75 % (13,5 px Handy / 15 px Tablet statt 18/20 px), Skill `homecrew-ui` angepasst. Zusätzlich Gestaltungsseite `/_gestaltung` (nur dev) und Enum `App\Enum\FontSize`. Messenger bis M11 `sync://`. Lokale CA der Symfony CLI liegt unter `~/.config/symfony-cli/certs/`.

---

### M1 – Referenzschema, Smoke-Test, Migrationsstrategie
**Ziel:** Das Referenzschema läuft nachweislich auf der lokalen MariaDB; die Migrationsstrategie ist an zwei echten Tabellen erprobt und dokumentiert.

**Umfang**
- Datenbanken `homecrew`, `homecrew_test`, `homecrew_smoke` (Benutzer `homecrew` mit Rechten auf `homecrew%`); `DATABASE_URL` in `.env.local` (trägt Andreas selbst ein) mit `serverVersion=11.8.6-MariaDB`, `charset=utf8mb4`; Verbindung mit Zeitzone `+00:00`.
- `schema.sql` + `smoke_test.sql` auf leerer `homecrew_smoke`; Fehler zuerst in der Referenz beheben und in Konzept 6 vermerken.
- **MariaDB 11.8 LTS** als Zielversion (Entscheidung 01.11.): kontext.md (Entscheidungs-Log), Konzept 5, Konzept 6, Kopf von `schema.sql` und Skill `datenbank-migrationen` anpassen.
- Pilot der Strategie: Entities `AppSetting` und `AppUser` (ohne Oberfläche) mit Enums, erste Migration inkl. aller CHECKs, generierter Spalte `manager_marker` mit eindeutigem Index und Startzeile in `app_setting`.
- Lösungen für typische Scheinänderungen festlegen: `TINYINT(1)` ↔ bool, `TINYINT UNSIGNED`, `SMALLINT UNSIGNED`, `CHAR(n)`, `BINARY(16)` (UUID v7), `VARBINARY` (`*_enc`), `JSON`, `TIME`, `DEFAULT CURRENT_TIMESTAMP ON UPDATE …`, generierte Spalten, Tabellenkommentare.
- Vergleichsskript unter `bin/` + Composer-Skript (z. B. `composer schema:check`): Datenbank frisch von Null migrieren und mit `homecrew_smoke` vergleichen.
- Test-Datenbank mit `dama/doctrine-test-bundle`; Datenbanktests bilden die Smoke-Regeln für `app_setting`/`app_user` nach.
- Strategie in Konzept 6 §6 festschreiben.
- PHPStan: `phpstan-doctrine` bekommt einen `objectManagerLoader` (erste Entities).
- Fehlerseiten müssen auch ohne erreichbare Datenbank rendern (das Layout liest ab jetzt bzw. M2 Daten aus der DB) – Test dafür.

**Pakete:** dama/doctrine-test-bundle, doctrine/doctrine-fixtures-bundle.
**Tabellen:** neu `app_setting`, `app_user`.

**Akzeptanzkriterien**
- [x] Smoke-Test endet mit „ALLE PRÜFUNGEN OK" (Ergebnis in Konzept 6 §8 vermerkt: 7 A, 92 B, 13 C – Referenz unverändert).
- [x] `doctrine:migrations:migrate` läuft auf leerer DB durch; `doctrine:schema:validate` ohne Mapping-Fehler und „in sync"; ein erneutes `doctrine:migrations:diff` meldet „No changes detected" (auch als Test `MappingTest`).
- [x] `composer schema:check` meldet `app_setting` und `app_user` identisch zur Referenz (Gegenprobe: eingebaute Abweichungen werden gefunden).
- [x] PHPUnit: alle Smoke-Regeln der beiden Tabellen (B43–B46, B50–B52, B87–B92) plus die übrigen CHECKs als Datenbanktests; ORM-Durchlauf, UTC-Verbindung.
- [x] Browser: Startseite zeigt den Haushaltsnamen aus `app_setting` („Mein Haushalt"), 1024 × 768 und 390 × 844 mit „sehr groß" ohne horizontales Scrollen.
- [x] MariaDB-Version überall 11.8 LTS; Strategie in Konzept 6 §6 dokumentiert.

**Abweichungen:** Vierte Datenbank `homecrew_check` als Ziel des Abgleichs; der Abgleich baut `homecrew_smoke` jedes Mal neu auf (Smoke-Befehl in CLAUDE.md legt die DB deshalb vorher neu an). Abgleich als Dev-Befehl `app:schema:check` statt Skript unter `bin/` (dafür `symfony/process` als dev-Paket); er kann nur `<App-DB>_smoke` und `<App-DB>_check` löschen (`CheckDatabases`, getestet) und gilt ohne migrierte Tabelle als fehlgeschlagen. Die Migration übernimmt `CREATE TABLE` wörtlich aus `schema.sql`, weil DBAL Bool-Spalten als `TINYINT` statt `TINYINT(1)` schreibt und CHECKs/generierte Spalten/`ON UPDATE` nicht kennt; dazu `TinyIntType` und `MariaDbSchemaManager` gegen Scheinänderungen. Startseite: Überschrift = Haushaltsname, „Willkommen bei HomeCrew" als Einleitung. Fixtures: Einstellungen + Max (Manager), Schwester (Angehörige, Admin), Papa (Angehörige), Anna, Ben – noch ohne Zugangsdaten (M2–M4). Test „Fehlerseiten ohne Datenbank": `/` → deutsche 500-Seite, 404 ohne DB.

---

### M2 – Einrichtung, Anmeldung, Admin-Menü-Gerüst
**Ziel:** Der Manager wird eingerichtet, meldet sich sicher an (Passwort + Authenticator-App oder Passkey) und erreicht ein schlankes Admin-Menü.

**Umfang**
- Befehl `app:setup` legt den Manager an (Benutzername, Anzeigename, Start-Passwort) – nur solange es keinen gibt; zusätzlich Server-Befehl zum Zurücksetzen der 2FA des Managers (Konzept 1 §5).
- Anmeldung `/anmelden`: Benutzername + Passwort, Passkey, Abmelden; Start-Passwort muss geändert werden.
- 2FA-Pflicht für Admins: ohne 2FA nur die Einrichtung erreichbar (Authenticator-App per QR oder Passkey); Wiederherstellungscodes einmal anzeigen; TOTP-Geheimnis verschlüsselt (libsodium, Hauptschlüssel als Symfony-Secret).
- Rate-Limiting für Anmeldung und 2FA-Code; sichere Session-Cookies; Abmeldung nach Inaktivität.
- `FeatureAvailability` als Grundgerüst (E-Mail/KI: nicht eingerichtet) → z. B. kein „Passwort vergessen".
- „Verwaltung" `/verwaltung` (EasyAdmin) nur für Admins nach vollständiger 2FA, nur mit vorhandenen Masken: Einstellungen „Allgemein" (Haushaltsname, Zeitzone, Feiertagsregion, Auto-Sperre, PIN nur im Heimnetz, Ansichtszeitraum, max. Dienstdauer, Videolänge, Freigabe-Modus), „Löschfristen", „Protokoll".
- Protokoll für Anmeldung, 2FA einrichten/zurücksetzen, Einstellungen – ohne IP, ohne Geheimnisse; Scheduler mit erstem Löschjob (Protokoll nach `retention_audit_days`).
- Startseite nach Anmeldung: „Heute"-Platzhalter mit Datum.
- Layout nutzt die Einstellungen aus `app_setting`: Zeitzone für Datum/Anzeige (statt fest `Europe/Berlin` in `config/packages/twig.yaml`), Haushaltsname im Titel; Schriftgröße/Kontrast aus `app_user`.

**Pakete:** easycorp/easyadmin-bundle, scheb/2fa-bundle (+ totp, backup-code), web-auth/webauthn-symfony-bundle, endroid/qr-code.
**Tabellen:** neu `webauthn_credential`, `audit_log`; mitgezogen `device` (`audit_log` verweist darauf); genutzt `app_user`, `app_setting`.

**Akzeptanzkriterien**
- [x] `php bin/console app:setup` legt den Manager an; ein zweiter Aufruf wird abgelehnt (`CommandsTest`).
- [x] Erste Anmeldung: Start-Passwort ändern → 2FA einrichten → Wiederherstellungscodes → „Heute"; vorher ist keine andere Seite erreichbar (`FirstLoginTest`, mit `ADMIN_2FA_REQUIRED=1`; mit Schalter aus entfällt nur der 2FA-Schritt).
- [ ] Anmeldung mit Passwort + Authenticator-Code und mit Passkey unter https://localhost:8000; jeder Wiederherstellungscode funktioniert genau einmal. – Passwort + Code und Wiederherstellungscodes per Test (`TwoFactorLoginTest`); Passkey-Endpunkte per Test (`PasskeyEndpointsTest`, `PasskeyStorageTest`). **Offen:** Passkey und Authenticator-App einmal im echten Browser/Handy durchspielen (Andreas).
- [x] Nach wiederholten Fehlversuchen verständliche Sperrmeldung mit Wartezeit (Passwort und zweiter Schritt, je 5 Versuche / 15 min).
- [x] `/verwaltung`: Nicht-Admin 403, nicht angemeldet → Anmeldung; gespeicherte Einstellungen stehen im Protokoll (alt → neu).
- [x] TOTP-Geheimnis nicht im Klartext in der Datenbank (Test).
- [x] Nirgends „Passwort vergessen" oder E-Mail-Optionen.
- [x] Funktionale Tests: nicht angemeldet, Admin ohne 2FA (Schalter an und aus), Admin mit 2FA, Nicht-Admin, Assistenz.
- [ ] Browser: Anmeldeseite bei 1024 × 768 und 500 px Breite geprüft (Screenshot, JS läuft trotz CSP), Verwaltung per `curl` (alle Skripte mit Nonce). **Offen (Andreas):** angemeldete Seiten bei 390 × 844, „sehr groß", nur Tastatur, Konsole ohne CSP-Fehler in `/verwaltung` – das automatische Prüfen über das DevTools-Protokoll war aus WSL nicht möglich (Port nach außen gesperrt).

**Abweichungen** (Entscheidungen 01.11. abends, mit Andreas abgestimmt):
- **2FA-Pflicht für Admins schaltbar** über `ADMIN_2FA_REQUIRED` – auf Wunsch vorerst `0`, vor dem Echtbetrieb auf `1` (dann Einrichtung bei der ersten Anmeldung erzwungen). Ohne Pflicht zeigt „Heute" Admins ohne 2FA einen Hinweis mit „Zweiten Schritt einrichten". Tests laufen mit `1` (`.env.test`), die Variante `0` hat eigene Tests.
- **Passkey = vollständige Anmeldung** (Benutzerverifikation Pflicht, ohne Benutzername). Wer Passkey als zweiten Schritt hat, kommt mit Passwort nur zusammen mit einem Wiederherstellungscode weiter (`PasskeyRecoveryProvider`).
- **Abmeldung nach 8 h** ohne Anfrage (`SESSION_IDLE_MINUTES=480`), Hinweis auf der Anmeldeseite; Session-Lebensdauer passend.
- **Löschfristen-Maske nur mit Stufe-1-Fristen** (Übergaben, Erledigungen, erledigte Aufgaben, letzte n je Wartungsplan, Meldungen, Benachrichtigungen, Protokoll).
- **`device.font_size`** in `schema.sql` + `smoke_test.sql` ergänzt (Regel B93, offener Punkt 3 erledigt); Oberfläche in M4.
- **TOTP ohne `scheb/2fa-totp`:** eigener scheb-Provider (`AuthenticatorAppProvider`) mit `spomky-labs/otphp`; das Geheimnis wird erst beim Prüfen entschlüsselt und liegt nie am User-Objekt oder in der Session. Codes gelten 15 s über ihr Fenster hinaus und nur einmal (`code_reuse_cache`).
- Zusätzliches Paket per Importmap: `@simplewebauthn/browser` (lokal abgelegt) für `assets/controllers/passkey_controller.js`.
- Löschjob ohne Cron-Paket: `RecurringMessage::every('1 day')` ab 03:30.
- Hauptschlüssel `APP_ENCRYPTION_KEY` (≥ 32 zufällige Zeichen, BLAKE2b → secretbox-Schlüssel); dev in `.env.dev.local` (von Claude Code erzeugt, nicht committet), test fest in `.env.test`.
- Nach dem zweiten Schritt geht es zur ursprünglich angeforderten Seite (z. B. `/verwaltung`).
- Ohne Datenbank: Anmeldeseite rendert mit „HomeCrew", Absenden zeigt die deutsche Fehlerseite (Test angepasst).
- `/abmelden` braucht in `access_control` eine eigene öffentliche Regel (die „lazy" Firewall prüft den Zugriff vor dem Abmelden – sonst geht „Abbrechen" im zweiten Schritt nicht); geschützt bleibt es über das CSRF-Token.
- Offene Pflichtschritte werden auch bei verbotenen Seiten angezeigt (`AccessDeniedHandler` leitet um statt 403).
- Feiertagsregion als Enum `HolidayRegion` (Bayern · mit Mariä Himmelfahrt · Augsburg).
- Passkeys: RP-ID per `WEBAUTHN_RP_ID`, leer = Host der Anfrage.
- Schrift-`@font-face` in `assets/styles/fonts.css` ausgelagert (Layout und Verwaltung nutzen dieselbe lokale Schrift).
- `composer qa` leert vor PHPUnit den Test-Cache (siehe „Gelernt" in CLAUDE.md).
- Pakete statt `scheb/2fa-totp`: `spomky-labs/otphp` ^11.5 und `paragonie/constant_time_encoding` ^3.1 (vorher schon dessen Abhängigkeiten); Importmap `@simplewebauthn/browser` 14.0.0.
- **Nach dem Review:** Passkeys lassen sich nur hinzufügen, solange noch kein zweiter Schritt besteht (`PasskeyRegistrationGuard`); `/konto/passwort` nur für das Start-Passwort; Wiederherstellungscodes nur neu, wenn keine mehr übrig sind – freiwilliges Ändern mit frischer Bestätigung kommt mit „Mein Konto" (M3). Wiederherstellungscodes als HMAC mit Serverschlüssel (`RecoveryCodeManager`, `SecretBox::fingerprint`). Sessions in `var/sessions/<env>` mit 10 h Lebensdauer (PHP-Standard 24 min hätte die 8 h unterlaufen). Protokoll in Klartext ohne Spalten- und Tabellennamen (`AuditLogPresenter`), Sperre im zweiten Schritt wird protokolliert. `app:setup` prüft zuerst den Hauptschlüssel.
- **Nach dem ersten Browsertest (Passkey mit Bitwarden schlug fehl):** HomeCrew war über `https://127.0.0.1:8000` geöffnet – darauf lehnen Browser und Passwort-Manager Passkeys ab. Über eine IP-Adresse blendet HomeCrew Passkeys jetzt aus und verlinkt die Adresse mit Namen (bei 127.0.0.1 `localhost`, sonst `DEFAULT_URI`); Fehler beim Passkey werden in Klartext erklärt statt verschwiegen.

---

### M3 – Personen und persönliche Zugänge
**Ziel:** Admins legen Personen mit Rolle, Admin-Recht und passendem Zugang an; jede Person verwaltet ihr Konto.

**Umfang**
- Verwaltung → Personen: anlegen/bearbeiten (Rolle, Admin-Recht, Benutzername, Anzeigename, Farbe, Eintritt); Regeln genau ein Manager, Manager immer Admin, Assistenz nie Admin (Formular + Datenbank).
- Zugang: keiner (nur Team-Tablet) · Tablet-PIN (Start-PIN, PIN-Anmeldung an/aus) · Handy/PC (Start-Passwort mit Änderungspflicht oder Einladung per QR-Code, einmalig, 72 h).
- ~~Schalter je Person: wiederkehrende Aufgaben, Anleitungen veröffentlichen, Termine anlegen, KI.~~ → mit der jeweiligen Funktion (M6, M8, M12, Stufe 2), siehe Abweichungen.
- Admins setzen Passwort, PIN und 2FA zurück; sperren; Austritt (Zugang entfernt, Person bleibt als Anker).
- Druckansicht Zugangs-Kärtchen; Start-Passwort/-PIN nur direkt nach dem Setzen sichtbar.
- „Mein Konto": Passwort ändern, 2FA und Passkeys verwalten, neue Wiederherstellungscodes, Schriftgröße (normal/groß/sehr groß), hoher Kontrast.
- Angehörige mit Admin-Recht richten bei der ersten Anmeldung 2FA ein. (PIN-Anmeldung selbst: M4.)

**Tabellen:** neu `invitation`; genutzt `app_user`, `webauthn_credential`, `audit_log`.

**Akzeptanzkriterien**
- [x] Assistenz „Ben" ohne Zugang anlegen: steht in der Liste, kann sich nirgends anmelden (`PersonAdminTest`).
- [x] Angehörige mit Admin-Recht + Start-Passwort: erste Anmeldung erzwingt Passwortwechsel, Datenschutzhinweis und 2FA (`PersonAccessPageTest`).
- [x] Einladungs-QR einlösen → Passwort setzen → angemeldet; zweites Einlösen und abgelaufene Einladung werden verständlich abgelehnt (`InvitationTest`; QR-Weg mit JavaScript im echten Chrome unter Windows geprüft: `/einladung#CODE` → „Hallo Ben").
- [x] Assistenz nicht zum Admin machbar, kein zweiter Manager (Formular: `PersonAdminTest`, `PersonValidationTest`; Datenbank: `AppUserRulesTest` aus M1).
- [x] Austritt: Anmeldung gesperrt, Person bleibt als „ausgetreten" sichtbar.
- [x] Schriftgröße „sehr groß" und hoher Kontrast wirken sofort und bleiben gespeichert (`AccountTest`).
- [x] Zugangs-Kärtchen druckt schwarz-weiß auf A4 (Druck-PDF über Chrome headless geprüft).
- [x] Änderungen an Personen/Rechten im Protokoll (ohne Passwörter/PINs); Tests für Voter, Formularregeln, Einladungen.
- [ ] Browser: „Mein Konto", Datenschutzhinweis, Personenliste, Zugang und Kärtchen bei 1024 px und 500 px (sehr groß) als Screenshot geprüft (angemeldete Seiten per `curl` gerendert, Chrome headless). **Offen (Andreas):** 390 × 844, nur Tastatur, Passkey bestätigen/hinzufügen/entfernen im echten Browser, QR-Code mit dem Handy scannen (geht erst mit erreichbarem Rechner, siehe offener Punkt 4).

**Abweichungen** (mit Andreas abgestimmt, 02.11.):
- **Schalter je Person** (wiederkehrende Aufgaben, Termine anlegen, Anleitungen veröffentlichen, KI) kommen mit ihrer Funktion: M6, M8, M12, KI in Stufe 2 (nur sichtbar, wenn eingerichtet) – Regel „keine toten Knöpfe". Farbe und PIN-Anmeldung an/aus sind da (wirken ab M4).
- **Datenschutzhinweis** (offener Punkt 2) als Pflichtschritt nach dem Passwortwechsel, vor der 2FA; Text in einfacher Sprache aus Konzept 5 §4, **Entwurf – vor dem Echtbetrieb prüfen**; Version als Konstante (`PrivacyNotice::VERSION`); jederzeit unter „Mein Konto" lesbar und druckbar (für Personen ohne Zugang).
- **Manager-Schutz:** Den Manager bearbeitet nur der Manager selbst (andere Admins: kein Bearbeiten, Zurücksetzen, Sperren, Austritt – Rückweg über Wiederherstellungscodes bzw. `app:manager:reset-2fa`). Niemand ändert die eigene Rolle oder das eigene Admin-Recht; den eigenen Zugang verwaltet man unter „Mein Konto". Rolle „Manager" ist im Formular nie wählbar (`PersonVoter`).
- **Start-Passwort und Start-PIN erzeugt die App** (12 Zeichen in drei Gruppen ohne verwechselbare Zeichen; 6 Ziffern) und zeigt sie einmal auf dem Kärtchen (Antwort auf den POST, `no-store`).
- **Austritt** nur mit Datum heute oder früher; entfernt sofort Passwort, PIN, zweiten Schritt, Passkeys und Einladungen; „Austritt zurücknehmen" für Irrtümer (Zugang danach neu einrichten).
- **Einladungscode nur im URL-Fragment** (`/einladung#CODE`, erreicht den Server nicht und damit kein Log); `invitation_code_controller.js` übernimmt ihn ins Formular, ohne JavaScript tippt man ihn ab. 24 Zeichen (≈ 119 Bit), in der Datenbank nur SHA-256; eine neue Einladung ersetzt die offene; 10 Versuche / 15 min je IP. Hat die Person schon einen zweiten Schritt, folgt nach dem Einlösen die normale Anmeldung (sonst würde die Einladung die 2FA umgehen). Löschjob `PurgeInvitations` täglich.
- **Frische Bestätigung** (`RecentConfirmation`, 5 Minuten): Passkey oder Passwort + Code (App oder Wiederherstellungscode), ohne zweiten Schritt nur Passwort; 5 Versuche / 15 min. Eine vollständige Anmeldung zählt als Bestätigung. Nötig für Passwort ändern, neue Wiederherstellungscodes, Wechsel Passkey → App, weiteren Passkey (bei vorhandener 2FA), App oder Passkey entfernen. Bestätigung per Passkey über einen eigenen Endpunkt des WebAuthn-Bundles (`PasskeyConfirmationHandler` prüft, dass der Passkey der angemeldeten Person gehört). Admins mit 2FA-Pflicht können ihren letzten zweiten Schritt nicht entfernen.
- **Laufende Sitzungen** gesperrter/ausgetretener Personen enden beim nächsten Klick (`AccountStatusSubscriber`, gleiche Regel wie `UserChecker` über `AppUser::isBlockedOn()`).
- Verwaltung: Personen (Liste, Anlegen, Bearbeiten) mit EasyAdmin; Seite „Zugang" im Verwaltungs-Layout, ihre Aktionen als einzelne POST-Formulare mit CSRF (`PersonAccessController`), Bestätigung riskanter Aktionen durch Aufklappen (`<details>`, ohne Skript). Personen werden nicht gelöscht. Das Kärtchen ist eine Druckansicht im HomeCrew-Layout.
- E-Mail-Adresse der Person und Einladung per E-Mail erst mit M13 (nur mit eingerichteter E-Mail).
- Team-Tablet-Tests für Personen und „Mein Konto" (Tablet bekommt keinen Zugriff) kommen mit dem Geräte-Kontext in M4.
- **Nach dem Review:** Auch der **erste** zweite Schritt und der erste Passkey brauchen eine frische Bestätigung (sonst könnte jemand an einer offenen Sitzung sein Handy zum Schlüssel machen und die Wiederherstellungscodes sehen – Lücke aus M2); die Bestätigungsseite ist im Pflichtschritt erreichbar, direkt nach der Anmeldung wird nichts erneut gefragt. Als Bestätigung zählen nur Formular-, 2FA- und Passkey-Anmeldungen (keine PIN). Meldungen der Verwaltung mit Anzeigenamen werden maskiert (EasyAdmin gibt Flash-Meldungen als HTML aus). Ein neues Start-Passwort zieht eine offene Einladung zurück; Einlösen ist atomar (`InvitationRepository::claim`); Austritt beendet laufende Sitzungen sofort; ungültige Datumsangaben werden abgelehnt; Hinweis beim Zurücksetzen der 2FA: laufende Anmeldung auf dem verlorenen Gerät endet erst mit einem neuen Start-Passwort; Text für unbekannte Codes nennt auch „abgelaufen/ersetzt".

---

### M4 – Team-Tablet und Notfall
**Ziel:** Ein gekoppeltes Tablet zeigt ohne Anmeldung die Team-Ansicht; der Notfall-Knopf führt immer zu 112 und den Notfallkontakten.

**Umfang**
- Verwaltung → Geräte: Tablet koppeln (kurzlebiger Kopplungs-QR) → Geräte-Token als Cookie (in der DB nur als Hash), umbenennen, Modus (Team · Persönlich · Beides), Bildschirmschoner (Minuten), zuletzt aktiv, entkoppeln (sofort wirkungslos).
- Eigener Security-Kontext: Geräte-Token → Geräte-Benutzer mit `ROLE_TEAM_DEVICE`, kein `app_user`; nur gültig, nicht entkoppelt und aus dem Heimnetz (Netze per Umgebungsvariable, lokal Loopback); Anfragen über Proxy/Tunnel gelten nie als Heimnetz.
- Modus Team: Team-Startseite (Platzhalter „Heute" bis M5) · Persönlich: Namenskacheln + PIN · Beides: Team-Ansicht + „Persönlich anmelden".
- PIN-Anmeldung: nur Personen mit aktivierter PIN, nur gekoppeltes Tablet im Heimnetz, Sperre nach Fehlversuchen, Start-PIN ändern, **nie Admin-Rechte**; Abmeldung nach `tablet_lock_seconds`.
- „Admin-Anmeldung" am Tablet: volle Anmeldung mit 2FA; nach Abmelden oder 5 Minuten Inaktivität zurück in den Team-Modus.
- Bildschirmschoner (Stimulus) nach `dim_after_seconds`; Notfall-Knopf bleibt sichtbar.
- Notfall: Knopf in der Kopfleiste jeder Seite → `/notfall`: 112 groß, Kontakte nach Kategorie mit „wann anrufen" und Anruf-Link, Sichtbarkeit alle/nur Admins; Bildschirmschoner zeigt 112 + Kontakte mit „auf dem Sperrbildschirm zeigen". Pflege: Verwaltung → Notfall-Infos. Notfallpläne folgen in M12.
- Protokoll: koppeln, Modus ändern, entkoppeln.

**Tabellen:** neu `emergency_contact`; mitgezogen `poi` (Oberfläche M12); genutzt `device`, `app_user`, `audit_log`.

**Akzeptanzkriterien**
- [ ] Tablet (zweites Browserprofil) koppeln → Team-Ansicht ohne Anmeldung; nach Entkoppeln sofort „Dieses Tablet ist nicht mehr gekoppelt" (Text: „Tablet" statt „Gerät"). – Per Test (`PairingTest`, `TeamTabletTest`: Code einlösen, Cookie, Team-Ansicht, Entkoppeln → Hinweis und Cookie gelöscht) und per `curl` gegen den echten Server. **Offen (Andreas):** einmal mit zweitem Chrome-Profil und QR-Code bzw. abgetipptem Code im echten Browser.
- [x] Geräte-Token von außerhalb des Heimnetzes (andere IP bzw. Tunnel-Header) wird abgelehnt (`TeamTabletTest`, auch laufende Sitzung).
- [x] Team-Tablet erreicht weder `/verwaltung` noch „Mein Konto" (`TeamTabletTest`: 7 Seiten → 403).
- [x] Falsche PIN → „Die PIN stimmt nicht. Noch 2 Versuche, dann 5 Minuten Pause."; ein Admin hat nach PIN-Anmeldung keine Admin-Rechte (`PinLoginTest`, `AdminVoterTest`).
- [x] Admin-Anmeldung am Tablet → nach Abmelden bzw. 5 Minuten zurück im Team-Modus (`TabletIdleTest`, feste Uhr).
- [ ] Bildschirmschoner nach eingestellter Zeit; Antippen weckt; Notfall ohne PIN erreichbar. – Notfall ohne PIN per Test (`EmergencyTest`), Bildschirmschoner-Inhalt per Test und Screenshot. **Offen (Andreas):** Zeitablauf und Aufwecken (Stimulus) im echten Browser – das DevTools-Protokoll ist aus WSL weiterhin nicht erreichbar.
- [x] Notfallkontakt „nur Admins" am Team-Tablet unsichtbar (`EmergencyTest`: auch nicht per PIN-Anmeldung eines Admins, nicht auf dem Sperrbildschirm).
- [x] Tests: Heimnetz-Prüfung (Unit, IPv4/IPv6/gemappt/Proxy/Tunnel: `HomeNetworkTest`), Geräte-Authenticator, Modi, Notfall-Sichtbarkeit, PIN-Regeln, Voter.
- [ ] Browser: Team-Ansicht, Bildschirmschoner, Kacheln, PIN, Notfall, Koppeln, Anmeldung bei 1024 × 768 und 500 px, Team-Ansicht/Kacheln/Notfall zusätzlich mit „sehr groß"; Verwaltung → Geräte, Kopplungscode, Notfall-Infos bei 1024 px (Screenshots per `curl` + Chrome headless). **Offen (Andreas):** 390 × 844, nur Tastatur, Konsole ohne CSP-Fehler.

**Abweichungen** (mit Andreas abgestimmt, 02.11.):
- **„PIN-Anmeldung nur im Heimnetz" aus** heißt: am gekoppelten Tablet geht die PIN auch außerhalb des Heimnetzes (z. B. über den Tunnel); die Team-Ansicht gibt es trotzdem nur im Heimnetz. Die Anmeldeseite erklärt das am Tablet und bietet dann „Persönlich anmelden".
- **Heimnetz-Regel** (Anlass: die Symfony CLI schickt lokal `X-Forwarded-For: 127.0.0.1`): die direkte Gegenstelle (`REMOTE_ADDR`, nie `getClientIp()`) **und** jede Adresse aus `X-Forwarded-For`, `Forwarded` und `X-Real-IP` müssen in `HOME_NETWORKS` liegen; Cloudflare-Kennzeichen (`CF-Connecting-IP`, `CF-Ray`, `CF-IPCountry`, `CDN-Loop`, `True-Client-IP`) → nie Heimnetz. Weiterleitungs-Header machen die Prüfung nur strenger. `HOME_NETWORKS` lokal `127.0.0.0/8,::1/128`; leere Liste = kein Heimnetz.
- **„Mein Konto" nach PIN-Anmeldung:** nur „PIN ändern" (mit aktueller PIN), Darstellung, Datenschutzhinweis; Passwort, zweiter Schritt, Passkeys, Wiederherstellungscodes und Bestätigung sind gesperrt (`AccountVoter`, auch die Passkey-Endpunkte des Bundles über `PasskeyRegistrationGuard`).
- **PIN-Regeln:** genau 6 Ziffern, nicht lauter gleiche, keine Folge wie 123456/987654, nicht die bisherige. 3 Fehlversuche → 5 Minuten Pause je Person (`pin_failed_count`, `pin_locked_until`), dazu 15 Versuche / 15 min je Tablet. Die Start-PIN wird bei der ersten PIN-Anmeldung ersetzt (Pflichtschritt vor dem Datenschutzhinweis). PIN ändern nur am Tablet, nicht am Handy/PC.
- **Kopplung:** Admin gibt den Namen ein → Kopplungscode (12 Zeichen ≈ 59 Bit, 10 Minuten, einmalig) als QR `…/koppeln#CODE` und zum Abtippen; nur als SHA-256 im Cache (`cache.app`), keine Tabelle. Einlösen nur im Heimnetz, 10 Versuche / 15 min je IP. Ein schon gekoppeltes Tablet wird nicht neu gekoppelt. `invitation_code_controller.js` heißt jetzt `fragment_code_controller.js` (Einladung und Kopplung). Damit ist der Punkt „Tokens nicht ins Log" aus „Später" auch für die Kopplung erledigt.
- **Geräte-Token:** 32 Zufallsbytes im Cookie `hc_device` (HttpOnly, Secure über HTTPS, SameSite=Lax, 400 Tage, bei „zuletzt aktiv" – höchstens alle 5 Minuten – verlängert); in der DB nur SHA-256. Nur ein unbekanntes oder entkoppeltes Token wird gelöscht (`DeviceUnpairedException`); ist die Datenbank kurz weg, bleibt das Cookie. Die Anmeldesperre der Firewall (25 Fehlversuche je Adresse) zählt die Anmeldung des Tablets nicht und sperrt sie nicht (`LoginRateLimiter`).
- **Rückkehr in den Team-Modus:** jede volle Anmeldung an einem gekoppelten Tablet endet nach 5 Minuten ohne Bedienung, eine PIN-Anmeldung nach „Auto-Sperre" (`tablet_lock_seconds`, Standard 3 min, wählbar 1 min bis 4 h); Abmelden und Ablauf führen zur Startseite des Tablets. Als Bedienung zählt für den Server nur eine Anfrage, nicht Scrollen oder Lesen – statt eines Lebenszeichens (Review M4) lässt sich die Auto-Sperre bis 4 Stunden stellen (Entscheidung Andreas); die Admin-Anmeldung bleibt bei 5 Minuten. Eine PIN-Anmeldung endet außerdem, sobald sie neu nicht mehr ginge: anderes oder entkoppeltes Tablet, Modus „Team", von außen (mit „PIN nur im Heimnetz"), PIN-Anmeldung aus – oder eine andere PIN (z. B. neue Start-PIN vom Admin; das Token merkt sich einen Fingerabdruck des PIN-Hashes, die eigene neue PIN übernimmt es). Im Browser öffnet ein Skript (Stimulus bzw. in der Verwaltung `assets/admin/tablet_session.js`) nach Ablauf die Seite neu, abmelden tut der Server (`SessionIdleSubscriber`) – ohne Formular, weil die zustandslose CSRF-Prüfung beim Abmelden ohne Stimulus nicht zuverlässig ist. Jede Anmeldung startet eine eigene Leerlaufzeit.
- **Kachelfarben:** feste Palette mit 8 Farben (`PersonColor`, weiße Schrift ≥ 4,5 : 1 geprüft) statt Farbwähler; ältere Farben → graue Kachel (Punkt aus „Später" erledigt). Bei hohem Kontrast weiße Kacheln mit schwarzem Rahmen.
- **Schriftgröße:** Team-Ansicht und Kacheln nutzen `device.font_size`; nach einer Anmeldung gilt die Einstellung der Person.
- **Notfall:** „nur Admins" = `Permission::ADMIN` (also nie per PIN oder am Team-Tablet); Sperrbildschirm nur „alle" **und** Haken. Der Knopf „Notfall" steht für jede angemeldete Person und das Tablet (jeder Modus) in der Kopfleiste; ohne Anmeldung gibt es keine Notfalldaten. Ort-Verknüpfung erst mit M12.
- **Referenzschema korrigiert:** `emergency_contact.category` war `VARCHAR(12)`, der eigene CHECK erlaubt aber `'dienstleister'` (13 Zeichen) → `VARCHAR(15)` in `schema.sql`, Positivfall im `smoke_test.sql` (wieder „ALLE PRÜFUNGEN OK"), Migration, Entity, Datenbanktest. Ein Prüfskript über das ganze Schema fand keine weitere Werteliste, die länger als ihre Spalte ist.
- `TEXT`-Spalten brauchen im Mapping `length: 65535` (sonst `LONGTEXT`) – in Konzept 6 §6.2 ergänzt.
- Verwaltung → Geräte: Liste ohne „Gekoppelt am" (Breite bei 1024 px), „Zuletzt aktiv" in Haushaltszeit oder „noch nie"; Notfall-Infos-Liste ohne Beziehung/zweite Nummer/Reihenfolge (nur im Formular), weil EasyAdmin leere Werte als „Null" zeigt.
- Kopfleiste: am Tablet ohne Personenanmeldung nur Datum und „Notfall"; Anmeldeseite am Tablet mit Hinweis „Admin-Anmeldung" und „Zurück".
- Fixtures: Tablet „Flur" (Modus Beides, Dev-Token `AppFixtures::TABLET_TOKEN`), vier Notfallkontakte (einer „nur Admins").
- Icons `tabler:phone`, `tabler:first-aid-kit`, `tabler:users` lokal importiert.
- **Neues Paket `symfony/lock`** (Review M4, `LOCK_DSN=flock`): Ohne Lock-Komponente zählten die Rate-Limiter (Anmeldung, 2FA, Einladung, Kopplung, PIN je Tablet) parallele Anfragen nicht sicher. Ein Kopplungscode wird unter Sperre eingelöst; die Fehlversuche einer Person zählt `PinLogin` unter einer Zeilensperre (`SELECT … FOR UPDATE`), sodass parallele Anfragen nicht mehr als 3 Versuche bekommen. Zähler fürs Koppeln erst nach der Heimnetz-Prüfung.
- **Review M4 außerdem:** Notfall-Knopf auch während der Pflichtschritte (Start-PIN, Datenschutzhinweis …); Hinweis auf der Anmeldeseite am Tablet, das Passwort nicht im Browser zu speichern; Benutzername-Feld am Tablet bleibt leer (die Anmeldesperre merkte sich „geraet-1"); deutsche Anführungszeichen in Code und Vorlagen einheitlich „…“.

---

### M5 – „Heute" und einmalige Aufgaben
**Ziel:** Alle sehen auf „Heute" die für sie bestimmten Aufgaben, legen welche an, erledigen sie, und Admins geben sie weiter.

**Umfang**
- Zentraler „Betrachter" (Person oder Team-Gerät) und ein Sichtbarkeits-Dienst für alle/zuständig/admins/privat und Zuständigkeit (Admins · Rolle · Person · „wer gerade da ist" – bis M9: jede angemeldete Assistenz und das Team-Tablet).
- „Heute": Kopf mit Datum (persönlich: „Hallo Anna"), Aufgaben in Gruppen Überfällig · Heute fällig · Heute möglich · Wenn Zeit ist, Status mit Symbol + Wort; Kacheln nur für vorhandene Bereiche; Team-Tablet lädt „Heute" regelmäßig neu (Turbo, ohne Mercure).
- Einmalige Aufgabe anlegen (alle inkl. Team-Tablet): Titel, Beschreibung, Kategorie, Dauer, Priorität, „nur gemeinsam mit Manager", Stichtag/Zeitfenster/Uhrzeit, Zuständigkeit, Sichtbarkeit.
- „Erledigt" sofort mit 10 Sekunden „Rückgängig"; überspringen; verschieben. („Nicht möglich" kommt mit den Meldungen in M7.)
- Weitergeben: Admins alle, Angehörige eigene; Erledigung speichert Person **oder** Gerät.
- Löschjobs: Personenbezug an Erledigungen nach `retention_completion_days`; erledigte einmalige Aufgaben nach `retention_done_task_days`.

**Tabellen:** neu `task`, `task_completion`; mitgezogen `task_template`, `guide`, `asset`, `shopping_list` (Oberflächen M6, M12, Stufe 2, M7).

**Akzeptanzkriterien**
- [x] Szenario A1: Team-Tablet zeigt sofort die Aufgaben für alle, Rolle Assistenz und „wer da ist" – keine privaten oder „nur Admins" (`TaskPagesTest`, im Browser mit den Fixtures: 6 von 10 Aufgaben).
- [x] Private Aufgabe sieht nur die Person, die sie angelegt hat; „zuständig" sieht die zuständige Person + Admins (`TaskPagesTest`, `VisibilityRulesTest`).
- [x] Erledigt am Team-Tablet → Gerät gespeichert, keine Person; „Rückgängig" innerhalb **30 s** stellt den alten Zustand her (`TaskPagesTest`, `TaskActionsTest`; Server nimmt bis 40 s an).
- [x] Szenario A10: Admin gibt eine Aufgabe an die Rolle Assistenz weiter; alle anderen – **auch Assistenz und Team-Tablet** – geben weiter, wofür sie zuständig sind, Fremdes → 403 (`TaskPagesTest`, `TaskVoterTest`; geändert 03.11.).
- [x] Sichtbarkeits-Matrix als Tests (Sichtbarkeit × Zuständigkeit × Ersteller × Betrachter, PHP-Regel gegen Datenbankabfrage: `VisibilityMatrixTest`); Löschjobs mit fester Uhr getestet (`TaskRetentionTest`).
- [ ] Browser: „Heute" am Team-Tablet und als Admin, „Neue Aufgabe" bei 1024 px; „Heute" bei 500 px mit „sehr groß" (Screenshots per `curl` + Chrome headless; ein Überlauf bei schmaler Breite wurde dabei gefunden und behoben). **Offen (Andreas):** 390 × 844, nur Tastatur, Konsole ohne CSP-Fehler, im echten Browser „Erledigt" → „Rückgängig" (Morph, Scrollposition bleibt) und das Neuladen am Tablet alle 60 s (Stimulus/Turbo laufen in den Screenshots nicht).

**Abweichungen** (mit Andreas abgestimmt, 02./03.11.):
- **Gruppen auf „Heute"**: Überfällig = Fälligkeitstag vorbei · Heute fällig · Heute möglich = Zeitfenster läuft (`window_start` ≤ heute) · Wenn Zeit ist = schon sichtbar (`visible_from` leer oder erreicht), aber später fällig. Formular „Ab wann zeigen?": ab sofort · erst am Fälligkeitstag (`visible_from` = Fälligkeitstag) · ab einem Tag (`visible_from` = `window_start`). Fälligkeitstag beim Anlegen heute oder später.
- **„Heute" zeigt, was für einen bestimmt ist** (sichtbar **und** zuständig); am Team-Tablet zusätzlich Aufgaben einzelner Assistenzkräfte, die für alle sichtbar sind (Ben ohne Zugang). **Admins und Manager** sehen darunter getrennt „Aufgaben der Assistenz" (Rolle Assistenz, „wer gerade da ist", einzelne Assistenzkräfte). Alles andere, was man sehen darf, zeigt die neue Seite „Offene Aufgaben" (`/aufgaben`). Kacheln in M5: „Offene Aufgaben" und „Notfall".
- **Sichtbarkeit**: „zuständig" streng – das Team-Tablet ist nie eine bestimmte Person (Hinweis im Formular). Wer eine Aufgabe angelegt hat, sieht sie immer (Personen). „Privat" wählen nur Admins und Angehörige; zuständig ist dann immer die Person selbst, private Aufgaben werden nicht weitergegeben, Admins sehen fremde private nicht. „Admin" = `Permission::ADMIN` (nach PIN-Anmeldung nie).
- **Weitergeben**: Admins/Manager jede Aufgabe, die sie sehen (auch anderer Rollen); alle anderen – auch Assistenz und Team-Tablet – was sie zuständig sind (Rechte-Matrix geändert). **Referenzschema ergänzt:** `task.assigned_by_device_id` (Weitergeben am Tablet, Gerät statt Person) in `schema.sql`, `smoke_test.sql` (Positivfall + C11, wieder „ALLE PRÜFUNGEN OK") und Migration. Eine geänderte Zuständigkeit beim Bearbeiten zählt als Weitergeben.
- **Zusätzlich**: „Bearbeiten" für Admins und wer die Aufgabe angelegt hat; kein Löschen. Erledigen, Überspringen, Verschieben (ein Tipp im kleinen Wochenkalender, Wunsch 03.11.; Zeitfenster und „zeigen ab" wandern mit) darf, wer die Aufgabe sieht. „Rückgängig" 30 s statt 10 s (Skill `homecrew-ui` angepasst), nur dieselbe Person bzw. dasselbe Tablet. Detailseite: wer angelegt, weitergegeben, erledigt hat, nur für Admins (Person bzw. „am Team-Tablet").
- **Team-Tablet per Freigabeliste** (aus „Später"): Tablet-Anfragen ohne Personenanmeldung erreichen nur Controller mit `#[TeamTabletAllowed]` (`TeamTabletGuard`); ein Test vergleicht alle freigegebenen Routen mit einer festen Liste. Eine volle Admin-Anmeldung am Tablet hat alle Rechte wie am PC (Test), weiterhin 5 Minuten Leerlauf (M4).
- **Neuladen am Tablet**: alle 60 s per Turbo-Morph (Scrollposition bleibt), nicht während „Rückgängig" läuft oder ein Feld den Fokus hat, nie bei Personen (hielte sonst die Anmeldung wach). Der Bildschirmschoner ist `data-turbo-permanent`.
- **Löschjobs** (nachts 03:40/03:45): an Erledigungen nach `retention_completion_days` die Person entfernen (`anonymized_at`; das Gerät bleibt), abgeschlossene einmalige Aufgaben nach `retention_done_task_days` löschen; Aufgaben aus Plänen regelt M6.
- **Formular-Theme**: Radios und Checkboxen als Optionszeilen (ganze Zeile antippbar) für alle Formulare.
- **Kopfleiste** (Wunsch 03.11.): Knopf „Heute" (Haus-Symbol) auf jeder Seite außer „Heute" – für Personen und das Team-Tablet; die Verwaltung hat ihr „Zurück zu Heute" schon.
- **Wochenkalender** (Wunsch 03.11.): „Verschieben" ohne Datumsfeld – zwei Wochen als Tageknöpfe (`WeekPicker`, ein Tipp), „Frühere/Spätere Wochen"; vergangene Tage und der bisherige Tag sind nicht wählbar. Wird in M8 fürs Verschieben von Terminen und Besuchen wiederverwendet.
- **Review M5:** Passkey-Anmeldung am Tablet war durch die Freigabeliste gesperrt → Bundle-Routen der Passkey-Anmeldung freigegeben, der Freigabe-Test prüft jetzt alle Routen. Erledigt/übersprungen: Uhrzeit und Person nur für Admins, sonst nur das Datum. Zweites „Erledigt“ von einer alten Seite → Hinweis „schon abgeschlossen“ statt 403; Bearbeiten, Verschieben und Weitergeben laufen wie Erledigen unter Zeilensperre und nur bei offenen Aufgaben. „Privat“ stellt nur, wer die Aufgabe angelegt hat. Aktionen auf „Heute“ stehen auch im DOM vorn (Tab-Reihenfolge), Überschriftenebenen auf „Offene Aufgaben“, „Rückgängig“-Hinweis hält bei Fokus/Zeiger an, Texte mit Platzhaltern, „Gemeinsam mit dir“ für den Manager. Konzept 7 an die Rechte-Matrix angeglichen.
- **Nachtrag 03.11. (nach dem Commit, Rückmeldung Andreas):** Formulare **kompakter** – Datum und Uhrzeit in einer Zeile, kurze Felder nebeneinander, wenige Optionen nebeneinander, „Wer sieht die Aufgabe?" als Auswahlliste, „Ab diesem Tag" erscheint erst bei „Ab einem Tag" (Stimulus `reveal`), knappere Abstände; Muster im Skill `homecrew-ui` („Formulare: kompakt") für alle weiteren Seiten. „Neue Aufgabe" bei 1024 px etwa 1100 statt 1750 px hoch. **CSP-Meldung in der Konsole** nach Seitenwechseln: kam von den Inline-Skripten der Web-Debug-Toolbar (nur dev), die Turbo unter der CSP der ersten Seite ausführen wollte – Turbo führt jetzt nie Inline-Skripte einer neuen Seite aus (`assets/app.js`); die Toolbar erscheint nach einem vollen Neuladen wieder.
- Fixtures: 10 Aufgaben relativ zu heute (jede Gruppe, Zuständigkeit und Sichtbarkeit, eine private der Schwester). Die Entwicklungsdatenbank wurde nicht neu geladen (eigenes Manager-Passwort), die Beispielaufgaben sind dort ergänzt.
- Icons `tabler:check`, `plus`, `player-skip-forward`, `arrow-forward-up`, `pencil`, `arrow-back-up`, `calendar-event`, `hourglass`, `circle-dashed`, `list-check`, `star` lokal importiert.

---

### M6 – Wartungspläne, Serien-Generator, „Routinen & Wartung"
**Ziel:** Wiederkehrende Aufgaben entstehen zuverlässig aus Plänen; die Übersicht zeigt, was fällig ist und wann es zuletzt erledigt wurde.

**Umfang**
- Schalter „wiederkehrende Aufgaben" je Person in Verwaltung → Personen (aus M3 hierher verschoben; Angehörige und Assistenz).
- Verwaltung → Wartungspläne / wiederkehrende Aufgaben (Admins; Personen mit Schalter): Rhythmus nach Kalender (täglich, wöchentlich, Wochentage, monatlich …) oder Intervall nach letzter Erledigung, Zeitfenster, Checkliste, Zuständigkeit, Sichtbarkeit, Pausieren.
- Gemeinsamer Serien-Generator (`Series/OccurrenceGenerator`, php-rrule): Vorkommen mit Ursprungstag, eindeutig je Vorlage und Tag, einzeln geänderte Vorkommen bleiben unberührt; läuft täglich per Scheduler und beim Speichern. Wird in M8 und M9 wiederverwendet.
- Checkliste beim Erledigen abhaken (Sollwerte und Messwerte erst Stufe 2).
- „Routinen & Wartung" `/routinen`: Pläne nach Dringlichkeit – Status (Symbol + Wort), zuständig, Rhythmus, zuletzt erledigt (**nur Datum**; Admins zusätzlich Person bzw. „Team-Tablet"), nächste Fälligkeit; Detail mit Verlauf und „Jetzt erledigt".
- „Heute" der Admins: „Routinen & Wartung: 2 überfällig".
- Löschjob: alte Erledigungen löschen, die letzten `completion_keep_last` je Plan bleiben.

**Pakete:** rlanvin/php-rrule.
**Tabellen:** neu `task_template_item`, `task_item`, `task_item_result`; mitgezogen `asset_component`; genutzt `task_template`, `task`, `task_completion`.

**Akzeptanzkriterien**
- [x] „Bettwäsche wechseln jeden Montag" erzeugt die Vorkommen für den Vorlauf; ein zweiter Generatorlauf erzeugt keine Doppelungen (`PlanSyncTest`).
- [x] Einzeln verschobenes Vorkommen bleibt bei Planänderung erhalten (`PlanSyncTest`; ebenso ein weitergegebenes mit seiner Zuständigkeit).
- [x] Intervall „alle 14 Tage nach Erledigung" rechnet ab der Erledigung; pausierter Plan erzeugt nichts (`PlanSyncTest`: auch „frühestens nach n Tagen", Überspringen, „Rückgängig", Pause mit und ohne Ende, Beenden).
- [x] Assistenz und Team-Tablet sehen „zuletzt erledigt" nur als Datum; Admins sehen Person bzw. „Team-Tablet" (`PlanPagesTest`, Übersicht und Verlauf).
- [x] Assistenz ohne Schalter kann keine Pläne anlegen (403), mit Schalter schon (`PlanPagesTest`, `PlanVoterTest`; Angehörige ohne Schalter und Team-Tablet ebenfalls 403).
- [x] Unit-Tests Generator: Monatsende, Schaltjahr, Zeitumstellung (Ortszeit), Pause, Ende (`OccurrenceGeneratorTest`, `RhythmTest`).
- [ ] Browser: `/routinen`, Plan-Detail, Aufgabe mit Checkliste und „Heute" als Tablet und als Admin bei 1024 px, `/routinen` bei 500 px mit „sehr groß", „Neuer Plan" bei 1024 px (Screenshots per `curl` + Chrome headless gegen eine Wegwerf-Datenbank mit den Fixtures). **Offen (Andreas):** 390 × 844, nur Tastatur, Konsole ohne CSP-Fehler, im echten Browser das Ein- und Ausblenden der Rhythmus-Felder („Neuer Plan", Stimulus `reveal` läuft in den Screenshots nicht) sowie „Jetzt erledigt" → „Rückgängig".

**Abweichungen** (mit Andreas abgestimmt, 03.11.):
- **Referenzschema ergänzt:** `task.is_modified` (wie bei Terminen und Dienstplan; Konzept 6 sah es für alle Serien vor) mit CHECK `ck_task_modified` (nur Vorkommen); `smoke_test.sql` Positivfall + **B94** – wieder „ALLE PRÜFUNGEN OK" (94 Regeln). Als einzeln geändert gilt ein Vorkommen nach Verschieben, Weitergeben oder Bearbeiten; Bearbeiten eines Vorkommens weist darauf hin („ändert nur dieses Mal").
- **Verpasstes wird ersetzt:** Ein überfälliges offenes Vorkommen entfällt, sobald ein späteres Vorkommen desselben Plans fällig ist – pro Plan höchstens ein überfälliges, im Verlauf steht dazu nichts. Pausieren und Beenden entfernen offene, nicht einzeln geänderte Vorkommen (auch überfällige).
- **Schalter „wiederkehrende Aufgaben"** = wie Admins: anlegen und jeden sichtbaren Plan ändern, pausieren, beenden – auch nach PIN-Anmeldung, nie das Team-Tablet. Das Feld steht in Verwaltung → Personen unter „Schalter", Änderungen landen im Protokoll.
- **Checkliste:** ein Tipp auf „Erledigt" (auf „Heute" und „Jetzt erledigt") hakt alle Punkte ab; auf der Detailseite hakt man einzelne Punkte ab und drückt dann „Erledigt"; der Verlauf zeigt bei Teilerledigung „3 von 4 Punkten".
- **Vorlauf** 14 Tage, mindestens das nächste Vorkommen (auch ein Jahresplan hat immer eines für „Jetzt erledigt"). Intervallpläne haben genau ein offenes Vorkommen: fällig n Tage nach dem letzten Abschluss (erledigt oder übersprungen), ohne Abschluss am Beginn; fällt es in eine Pause, am Tag danach.
- **„Offene Aufgaben"** zeigt Vorkommen aus Plänen erst, wenn sie erscheinen (`visible_from`); was danach kommt, steht unter „Routinen & Wartung".
- **Pflege der Pläne unter `/routinen`** (eigene Seiten statt EasyAdmin, weil Personen mit Schalter keine Verwaltung haben); das Verwaltungsmenü verlinkt unter „Alltag → Wartungspläne" dorthin. Pläne werden nicht gelöscht, nur beendet und wieder aufgenommen (beendete stehen eingeklappt unter „Beendet").
- **Rhythmen im Formular:** täglich/alle n Tage · wöchentlich an Wochentagen, alle n Wochen · monatlich am Tag 1–31 (in kürzeren Monaten der letzte Tag), alle n Monate · jährlich am Tag des Beginns · n Tage nach der letzten Erledigung (optional „schon möglich nach m Tagen"). „Am ersten Montag im Monat" u. Ä. gibt es noch nicht.
- **Nicht in M6:** Nachweis (Foto/Kommentar), Fotos/Videos am Plan (M11), Vorschlag bei „nicht möglich" (M7), Verknüpfungen zu Anleitung/Hilfsmittel/Ort (M12/Stufe 2), Sollwerte und Messwerte (Stufe 2).
- **Nachtlauf** `GenerateTaskOccurrences` um 03:20 (vor den Löschjobs); außerdem nach dem Speichern eines Plans und nach jeder Aktion an einem Vorkommen. Sperr-Reihenfolge überall erst Plan, dann Aufgabe.
- **„Heute"**: Kachel „Routinen & Wartung" für alle; Admins sehen zusätzlich „Routinen & Wartung: n überfällig", nur wenn etwas überfällig ist. Aufgabenzeilen zeigen bei Vorkommen „Zuletzt: vor 14 Tagen" (nur Datum) und „Checkliste: 3 Punkte".
- Fixtures: sechs Pläne (wöchentlich mit Checkliste, zweimal pro Woche mit verpasstem Vorkommen, alle 14 Tage nach Erledigung – überfällig, monatlich mit Vorlauf, jährlich nur für Admins, pausiert) mit Verlauf von Anna und vom Tablet; Papa hat den Schalter. Die Einmal-Aufgaben „Bettwäsche wechseln" und „Müll rausbringen" sind jetzt Pläne.
- Icons `tabler:repeat`, `player-pause`, `player-play`, `circle-off`, `square`, `square-check` lokal importiert.
- **Review M6:** Wer ein Vorkommen sehen darf, folgt **immer** dem Plan – auch bei überfälligen und einzeln geänderten (eine engere Sichtbarkeit wie „nur Admins" oder „privat" erreicht sofort alle offenen Aufgaben, nichts bleibt am Team-Tablet stehen); überfällige übernehmen auch Titel, Zuständigkeit und Checkliste, behalten aber ihren Tag. Nach „Jetzt erledigt" lange vor dem Termin entsteht gleich das nächste Vorkommen (bei Monats- und Jahresplänen fehlte es sonst). Das Formular prüft nur die Zahlen des gewählten Rhythmus (leeres „Am Tag" gab einen Fehler 500). Der Löschjob behält zusätzlich immer das jüngste „erledigt" je Plan. Wer den Schalter hat, darf auch einzelne Vorkommen bearbeiten. Zeitlich abgelaufene Pläne zeigen kein „Beenden"; Texte „Nichts mehr geplant", „Schon nach einem Tag möglich"; Verlauf benennt jeden Abschluss (auch „nicht möglich" ab M7); Hinweis „Bitte keine Gesundheitsangaben" auch an der Checkliste.

---

### M7 – Listen, Meldungen, Benachrichtigungen in der App
**Ziel:** Was fehlt oder kaputt ist, landet sicher bei den Admins und schließt sich oft von selbst.

**Umfang**
- Listen: Verwaltung → Listen (Name, zuständige Rolle/Person), Lieblingsartikel; alle inkl. Team-Tablet lesen, ergänzen, abhaken; Druckansicht je Liste.
- Großer Knopf „Melden" auf „Heute" (alle inkl. Team-Tablet): leer · kaputt · fehlt · sonstiges → Titel mit Vorschlägen (Lieblingsartikel, offene Meldungen) → optional Notiz, dringend, „nur für Admins" → optional Folgeaktion (auf eine Liste · Aufgabe für Person/Rolle · nur melden); Hinweis „Bitte keine Gesundheitsangaben".
- „Auch bemerkt" statt Doppelmeldung.
- „Nicht möglich" bei Aufgaben → genau eine Meldung mit Grund + Folgeaktion; der Vorschlag am Wartungsplan (`unable_list_id`, `unable_item_name`) ist vorausgefüllt (ein Tipp).
- Status offen → in Arbeit → erledigt/verworfen; automatischer Abschluss, wenn der Listeneintrag abgehakt bzw. die Aufgabe erledigt ist.
- Offene Meldungen für alle (Titel, Datum, Status, ohne Absender); „nur für Admins" nur für Admins.
- Meldungs-Log `/meldungen` für Admins: Filter (offen/erledigt/alle, Art, Zeitraum), Absender (Person/„Team-Tablet"), Folgeaktion mit Link, weitergeben, schließen, verwerfen, Verlauf, „Was fehlt oft?".
- Benachrichtigungen in der App: Glocke + „Neu seit deinem letzten Besuch" (Team-Tablet: „neu seit gestern"); Ereignisse: neue Meldung, Meldung erledigt, neuer Eintrag auf deiner Liste, Aufgabe für dich; Standards je Rolle (Konzept 13 §7) an einer Stelle im Code. „Heute" der Admins: „Meldungen: 2 offen" (dringende mit Warnsymbol + Text).
- Löschjobs: Absender an Meldungen nach 90 Tagen entfernen, abgeschlossene nach `retention_report_days` löschen; Benachrichtigungen nach `retention_notification_days`.

**Tabellen:** neu `shopping_item`, `shopping_favorite`, `issue_report`, `notification`; mitgezogen `handover_note` (Oberfläche M10); genutzt `shopping_list`, `task`, `task_template`.

**Akzeptanzkriterien**
- [ ] Szenario A4 am Team-Tablet: „Seifenspender auffüllen" → „Nicht möglich" → Vorschlag „Flüssigseife auf ‚Besorgt die Familie'" mit einem Tipp → Admin sieht Glocke und „Meldungen: 1 offen" → Eintrag abgehakt → Meldung automatisch erledigt.
- [ ] Szenario A5: „Melden" → kaputt → „Glühbirne Bad" ohne Aufgabe.
- [ ] Ähnlicher Titel bietet „Auch bemerkt" an; Zähler steigt, keine zweite Meldung.
- [ ] Pro „nicht möglich" genau eine Meldung (Test, auch bei doppeltem Absenden).
- [ ] Assistenz und Team-Tablet sehen offene Meldungen ohne Absender; „nur für Admins" unsichtbar (Tests).
- [ ] Druckansicht Einkaufsliste: A4, schwarz-weiß, Kästchen zum Abhaken.

---

### M8 – Kalender: Termine, externe Dienste, Besuche und Serien
**Ziel:** Alle sehen, wer heute kommt; Termine und Besuche gibt es einzeln und als Serie.

**Umfang**
- Verwaltung → Externe Dienste: Name ohne Klarnamen, Art, dienstliche Telefonnummer, Hinweis fürs Team, Farbe, aktiv.
- Schalter „Termine anlegen" je Person in Verwaltung → Personen (aus M3 hierher verschoben; nur Assistenz).
- Termine (Admins, Angehörige, Assistenz mit Schalter): Zeit oder ganztägig, Ort, Mitbringliste, Notiz, Zuständigkeit/Begleitung, Weitergeben, Sichtbarkeit.
- Besuche: externer Dienst, Zeit, Hinweis; Sichtbarkeit alle (Standard) oder nur Admins; **keine** Zuständigkeit und Begleitung.
- Serien für Termine und Besuche (Wochentage/Rhythmus, Uhrzeit, Dauer, Beginn, Ende, Pause) über den Generator aus M6; einzelne Vorkommen absagen („fällt aus" + Notiz) oder verschieben (neuer Tag per Tipp im Wochenkalender `WeekPicker` aus M5 statt Datumsfeld – Wunsch 03.11.) – bleibt bei Serienänderung erhalten; Serie beenden.
- „Heute": Block „Heute kommen" (für alle inkl. Team-Tablet; abgesagte als „fällt aus") und „Termine heute".
- Kalender `/kalender`: Agenda (Standard), Woche, Monat; Termine, Besuche, Aufgaben mit Datum, Wartungsfälligkeiten, Feiertage (yasumi, `holiday_region`), jeweils Symbol + Farbe + Text; Admins legen per Tipp in einen freien Bereich an; Hinweis bei Besuch an einem Feiertag.
- Druckansicht „Tages-ToDo" für einen wählbaren Tag (Standard morgen) inkl. „Es kommen …".

**Pakete:** azuyalabs/yasumi.
**Tabellen:** neu `visitor`, `appointment_series`, `appointment`, `appointment_participant`, `appointment_item`.

**Akzeptanzkriterien**
- [ ] Szenario A6: „Physio jeden Dienstag 10–11" und „Caritas alle 14 Tage donnerstags 13–15" erscheinen an den richtigen Tagen in „Heute kommen" (auch am Team-Tablet) mit Hinweis fürs Team.
- [ ] Einen Physio-Besuch absagen → „fällt aus"; bleibt so nach einer Änderung der Serie.
- [ ] Besuch „nur Admins" ist für Angehörige ohne Admin-Recht, Assistenz und Team-Tablet unsichtbar.
- [ ] Besuch mit Begleitung lässt sich weder im Formular noch in der Datenbank speichern.
- [ ] Assistenz ohne Schalter: Anlegen 403; mit Schalter erlaubt.
- [ ] Druck „Tages-ToDo für morgen": Aufgaben mit Kästchen + Zeile „Es kommen …".

---

### M9 – Dienstplan, Arbeitszeiten, Urlaub und Krankheit
**Ziel:** Admins planen Dienste und pflegen Arbeitszeiten per „Dienstplan übernehmen"; Assistenzkräfte mit eigenem Zugang sehen und erfassen ihre eigenen.

**Umfang**
- Verwaltung → Dienstplan: feste Tage je Person → geplante Dienste über den Generator; Einzeldienste; verschieben; absagen mit Grund und „trotzdem bezahlt?" (bei Absage durch den Manager Standard ja); Notiz am Dienst.
- Verwaltung → Arbeitszeiten: alle Einträge; „Dienstplan übernehmen" für Tag/Woche/Monat (optional eine Person) mit Vorschau – nur geplante, nicht abgesagte Dienste ohne Zeiteintrag, **nie überschreiben**, `start_source = 'dienstplan'`, Verlauf „aus Dienstplan übernommen"; korrigieren/nachtragen nur mit Begründung; Änderungsverlauf; Druckansicht Stundenübersicht je Person und Monat.
- Assistenz (persönlich angemeldet): eigene Dienste (Heute + Kalender), Dienst beginnen/beenden, eigene Zeiten der letzten `time_view_months` Monate, bearbeiten/nachtragen nur am selben Tag; Hinweise bei > 6 h ohne 30 min Pause und bei offenem Dienst über `max_shift_minutes` (an Admins).
- Urlaub & Krankheit: Assistenz beantragt Urlaub (auch aus dem Kalender) und meldet Krankheit (Zeitraum + AU ja/nein); Admins genehmigen bzw. tragen für Personen ohne Zugang ein; Dienste im Zeitraum werden „ausgefallen (Urlaub/Krank)".
- Kalender: Dienste (Assistenz nur eigene, Admins alle mit Ist-Zeiten), Urlaub/Krank, Team-Woche für Admins (Zeile je Person + Zeile Besuche); „Heute" der Admins: „Wer ist heute da / kommt als Nächstes" und am Monatsende „Dienstplan übernehmen".
- „Wer gerade da ist" richtet sich jetzt nach dem Dienstplan.
- Benachrichtigungen: Dienst neu/verschoben/abgesagt (Assistenz), Urlaub – Antrag (Admins) / Entscheidung (Assistenz).

**Tabellen:** neu `shift_pattern`, `planned_shift`, `time_entry`, `time_entry_change`, `absence`.

**Akzeptanzkriterien**
- [ ] „Dienstplan übernehmen" für einen Monat legt nur fehlende Einträge an, Vorschau zeigt dieselbe Liste; zweiter Lauf legt nichts an; abgesagte Dienste werden nicht übernommen.
- [ ] Assistenz bearbeitet eigenen Eintrag am selben Tag; am Folgetag gesperrt (Oberfläche + 403).
- [ ] Admin-Korrektur ohne Begründung wird abgelehnt (Formular + DB-CHECK); Verlauf zeigt alt → neu.
- [ ] Nur ein offener Dienst je Person; ein zweites „Dienst beginnen" wird verständlich abgelehnt.
- [ ] Team-Tablet und Angehörige ohne Admin-Recht sehen keine Dienste, Zeiten oder Urlaube (Tests).
- [ ] Zeiten in UTC gespeichert und über die Zeitumstellung (März/Oktober) richtig angezeigt (Test).

---

### M10 – Übergabebuch, Gesprächspunkte, Abwesenheit des Managers
**Ziel:** Infos fürs nächste Team, Gesprächspunkte für den nächsten Dienst einer Person und die Abwesenheit des Managers.

**Umfang**
- Übergabebuch: Info · Wichtig · Frage an Admins; anheften; Lesebestätigung (persönlich angemeldet; am Team-Tablet ohne); Absender Person bzw. „Team-Tablet"; „Heute": wichtige Übergaben; Benachrichtigungen „Wichtige Übergabe" und „Frage an Admins"; Hinweis „Fehlt etwas? Melden"; Löschjob nach `retention_handover_days`.
- Gesprächspunkte (Aufgabe mit Kategorie `gespraech`, Fälligkeit „beim nächsten Dienst von …", privat): Admins/Angehörige legen an; erscheinen zum nächsten Dienst der Person auf „Heute" der Person, die sie angelegt hat; Benachrichtigung „Gesprächspunkt fällig".
- Abwesenheit des Managers: Zeitraum + Notiz; Team sieht „nicht zu Hause" (Admins mit Notiz); danach Abfragen: Aufgaben pausieren? Dienste stattfinden lassen (mit Notiz) oder absagen („trotzdem bezahlt?", Standard ja)? Besuche im Zeitraum absagen (Telefonnummer des Dienstes dabei)? Betroffene Assistenzkräfte werden benachrichtigt; Abwesenheit im Kalender.

**Tabellen:** neu `handover_read`, `manager_absence`; genutzt `handover_note`, `task`, `planned_shift`, `appointment`.

**Akzeptanzkriterien**
- [ ] Übergabe am Team-Tablet → Absender „Team-Tablet"; wichtige Übergabe erscheint auf „Heute" aller.
- [ ] Lesebestätigung nur bei persönlicher Anmeldung; Admins sehen, wer gelesen hat.
- [ ] Szenario A9: Gesprächspunkt „mit Martin besprechen" erscheint zum nächsten Dienst von Martin nur bei der Person, die ihn angelegt hat.
- [ ] Szenario A8: Abwesenheit eintragen → Aufgaben pausiert, Dienste abgesagt bzw. mit Notiz, geplante Besuche zum Absagen angeboten; Team sieht „nicht zu Hause".
- [ ] Löschjob Übergaben nach 30 Tagen (Test mit fester Uhr).

---

### M11 – Fotos und Videos
**Ziel:** Fotos und kurze Videos lassen sich überall anhängen, wo das Konzept es vorsieht – datensparsam und nur für Berechtigte abrufbar.

**Umfang**
- Fotos im Browser verkleinern (max. 1600 px); auf dem Server Typ und Größe prüfen, neu kodieren (EXIF/GPS entfernt), außerhalb des Web-Verzeichnisses speichern (Pfad per Umgebungsvariable), Auslieferung über einen Controller mit Rechteprüfung am Besitzer.
- Videos bis `video_max_seconds` (90 s): Upload in Teilstücken, Umwandlung auf 720p im Hintergrund (Messenger async, ffmpeg), „Ton entfernen"; ohne ffmpeg ist der Video-Upload ausgeblendet.
- Anbinden an Meldungen, Übergaben, Erledigungen (Nachweis „Foto"), Aufgaben, Wartungspläne (Anleitungsschritte und Orte in M12).
- Hinweis „Möglichst nur Hände und Gegenstände fotografieren".
- Löschjob: abgebrochene Upload-Sitzungen und verwaiste Dateien.

**Tabellen:** neu `media`, `upload_session`; mitgezogen `chat_conversation`, `chat_message` (Chat ist Stufe 2, `media` verweist darauf); technisch `messenger_messages`.

**Akzeptanzkriterien**
- [ ] Team-Tablet: Foto zur Meldung „Glühbirne kaputt"; Admin sieht es im Meldungs-Log.
- [ ] Testbild mit GPS-Daten → gespeicherte Datei ohne EXIF (Test).
- [ ] Foto einer privaten Aufgabe ist für andere 404 (Test); keine Datei unter `public/`.
- [ ] Video über 90 s wird verständlich abgelehnt; ein 60-s-Video wird in Teilstücken hochgeladen und nach der Umwandlung abgespielt (Worker läuft).
- [ ] Aufgabe mit Nachweis „Foto" lässt sich nur mit Foto erledigen.

---

### M12 – Wissen: Anleitungen, „Wer kennt was", Orte, Profil
**Ziel:** Anleitungen mit Schritten, Fotos und Videos – lesen am Team-Tablet, schreiben mit einfachem Editor; dazu Orte und das Profil des Managers.

**Umfang**
- Wissen `/wissen`: Übersicht nach Typ/Kategorie, Volltextsuche; Typen Anleitung · Steckbrief · Notfallplan · Rezept (Zutaten mit Portionen); Schritte mit Tipp/Achtung und Medien; Vorlesen über die Sprachausgabe des Geräts.
- Editor: Schritt-Karten mit kleiner Werkzeugleiste (fett, hervorheben, Listen, Link), Hinweis-Kasten, Fotos/Videos je Schritt, Verschieben per Pfeil-Knöpfen, automatisch speichern, Vorschau „So sieht es die Assistenz"; Speichern als bereinigtes HTML (HtmlSanitizer); Editor-Bibliothek lokal über AssetMapper.
- Schalter „Anleitungen veröffentlichen" je Person in Verwaltung → Personen (aus M3 hierher verschoben; nur Assistenz).
- Versionen: eine veröffentlichte + höchstens ein Entwurf; Freigabe nach `guide_publish_mode` und Schalter „Anleitungen veröffentlichen"; Team-Tablet schlägt nur Entwürfe vor; „Stimmt nicht mehr"; Prüf-Hinweis nach 12 Monaten; Benachrichtigung „Anleitung vorgeschlagen / stimmt nicht mehr".
- Wer kennt was: „Gelesen" (mit Version), „Eingewiesen" (Admin), Matrix für Admins, „geändert seit deinem Lesen".
- Notfallpläne erscheinen auf der Notfall-Seite; Druckansichten Anleitung und Rezept.
- Orte: pflegen (Admins, Angehörige, Assistenz), Barrierefreiheit, Öffnungszeiten, „Route in Google Maps" als Link (keine Einbettung), Fotos, verknüpfte Anleitungen.
- Profil des Managers: Einträge je Bereich mit Sichtbarkeit Team/nur für mich; Team-Tablet zeigt nur „Team"; Hinweis „keine Medikamente"; Schlagworte warnen bei Listeneinträgen.

**Pakete:** Editor (Tiptap oder Trix – Entscheidung im Meilenstein-Plan, lokal ausgeliefert).
**Tabellen:** neu `guide_version`, `guide_step`, `guide_step_media`, `guide_tag`, `guide_feedback`, `guide_acknowledgement`, `recipe_ingredient`, `poi_guide`, `profile_item`, `profile_keyword`; genutzt `guide`, `poi`, `media`.

**Akzeptanzkriterien**
- [ ] Anleitung „Rollstuhl laden" mit 3 Schritten, Foto und Achtung-Hinweis anlegen, veröffentlichen, am Team-Tablet lesen und vorlesen lassen.
- [ ] Team-Tablet schlägt eine Anleitung vor → Entwurf → Admin gibt frei.
- [ ] `<script>` und `onclick` werden beim Speichern entfernt (Test).
- [ ] Neue Version → „geändert seit deinem Lesen" bei allen, die gelesen hatten; Matrix zeigt gelesen/eingewiesen.
- [ ] Profil-Eintrag „nur für mich" am Team-Tablet unsichtbar; Schlagwort „Nüsse" warnt beim Listeneintrag „Nussnougatcreme".
- [ ] Druck Anleitung und Rezept: A4, große Schrift.

---

### M13 – Erweiterungen, E-Mail und Benachrichtigungsregeln
**Ziel:** E-Mail und die KI-Seite werden im Admin-Menü eingerichtet; erst danach erscheinen E-Mail-Funktionen. Jede Person stellt ihre Benachrichtigungen ein.

**Umfang**
- Verwaltung → Erweiterungen: Übersicht (Kacheln E-Mail und KI mit Status nicht eingerichtet/ok/Fehler).
- E-Mail: SMTP-Server, Port, Verschlüsselung, Benutzer, Passwort (verschlüsselt, nie wieder angezeigt), Absender, **Testmail**, an/aus; Mailer-DSN zur Laufzeit aus `app_setting`; Umgebungsvariablen haben Vorrang (Felder dann gesperrt); Versand asynchron.
- KI-Seite (Funktionen selbst Stufe 2): Schlüssel (verschlüsselt, danach nur „••••a1b2"), Modell, Verbindung testen, Hauptschalter, Funktionsschalter, Monatsbudget.
- Geheimnisse ändern/löschen nur nach erneuter 2FA, Protokolleintrag ohne Inhalt; Gesundheitscheck per Scheduler; `FeatureAvailability` vollständig (Twig-Funktion + Voter).
- Mit eingerichteter E-Mail: Passwort vergessen, 2FA per E-Mail-Code, Einladung per E-Mail, E-Mail-Adresse bestätigen.
- „Mein Konto → Benachrichtigungen": je Ereignis App/E-Mail, E-Mail aus/sofort/täglich, „Titel in E-Mails zeigen", Ruhezeiten; gespeichert werden nur Abweichungen vom Rollen-Standard; dringende Meldungen sofort (Ruhezeiten gelten); Tageszusammenfassung um `daily_digest_time`. E-Mails standardmäßig ohne Details („Neue Meldung in HomeCrew" + Link).
- Verwaltung → Benachrichtigungen: Uhrzeit der Tageszusammenfassung, Standards je Rolle ansehen.
- „Prüfliste vor dem Start" für Admins (Konzept 11 §3).
- Löschjobs: Reset-Links und 2FA-Codes nach Ablauf.

**Pakete:** symfonycasts/reset-password-bundle, scheb/2fa-email.
**Tabellen:** neu `notification_preference`, `password_reset_request`; genutzt `app_setting`, `app_user`, `notification`.

**Akzeptanzkriterien**
- [ ] Ohne E-Mail-Einrichtung nirgends E-Mail-Optionen, kein „Passwort vergessen", keine E-Mail-2FA (Tests über die gerenderten Seiten).
- [ ] Testmail kommt an; falsches Passwort → Status „Fehler" mit verständlicher Meldung, E-Mail-Funktionen verschwinden.
- [ ] SMTP-Passwort und KI-Schlüssel erscheinen nie im HTML, im Log oder im Protokoll (Tests); Ändern verlangt erneute 2FA.
- [ ] KI-Test mit ungültigem Schlüssel → „Fehler"; ohne Schlüssel lässt sich KI nicht einschalten (Formular + DB-CHECK); sonst ist von der KI nichts zu sehen.
- [ ] Manager stellt „Neue Meldung: E-Mail sofort, mit Titel" ein → Meldung am Team-Tablet → E-Mail mit Titel; ohne Häkchen nur Hinweis + Link.
- [ ] Tageszusammenfassung zur eingestellten Uhrzeit (Test mit fester Uhr).
- [ ] Abschluss Stufe 1: „Empfohlener Start" aus kontext.md §4 einmal komplett im Browser durchgespielt; Prüfliste vor dem Start vollständig.

---

## Offene Punkte (vor dem jeweiligen Meilenstein klären)
1. ~~**M0 – Wert der Schriftgröße:** Skill `homecrew-ui` schreibt `sehr-gross`, das Schema `sehr_gross`.~~ Erledigt 01.11.: überall `sehr_gross` (Enum `App\Enum\FontSize`), Skill angeglichen.
2. ~~**M3 – Datenschutzhinweis** (`privacy_notice_version`, Konzept 5 §4.1): Vorschlag Bestätigung bei der ersten Anmeldung in M3, neue Version mit Einrichtung einer Erweiterung in M13.~~ Erledigt 02.11. (M3): Pflichtschritt nach dem Passwortwechsel, Text als Entwurf (vor dem Echtbetrieb prüfen); neue Version mit M13.
3. ~~**M4 – Schriftgröße am Gerät:** Konzept 11 sieht sie vor, `device` hat keine Spalte.~~ Erledigt 01.11. (M2): `device.font_size` in `schema.sql` + `smoke_test.sql` (B93) und in der Migration; Oberfläche in M4.
4. **M4 – echtes Tablet lokal:** WSL läuft im NAT-Modus; ein Tablet im WLAN erreicht den Rechner erst mit `networkingMode=mirrored` in `.wslconfig` oder per Portweiterleitung. Bis dahin Tablet im Windows-Browser prüfen (zweites Profil, Gerätesimulation). **Stand M4:** weiterhin offen; geprüft per Tests, `curl` und Screenshots.
5. **M12 – Rezepte:** kontext.md §4 nennt Rezepte in Stufe 2, Konzept 6 und das Entscheidungs-Log (Druckansicht Rezepte) in Stufe 1. Vorschlag: Typ Rezept mit Zutaten und Druck in M12, „aus Rezepten auf die Liste" in Stufe 2.
6. **Mitgezogene Tabellen ohne Oberfläche** (Folge der Strategie): `asset`, `asset_component` (Pflege Stufe 2), `chat_conversation`, `chat_message` (Chat Stufe 2).
7. **Pi (nach Stufe 1):** Hinter cloudflared dürfen Tunnel-Anfragen nie als Heimnetz gelten (Trusted Proxies, Tunnel-Header) – Regel und Tests in M4 (`HomeNetwork`, `HomeNetworkTest`: Cloudflare-Kennzeichen und jede weitergeleitete Adresse), Abnahme beim Pi-Aufbau (dort `HOME_NETWORKS` setzen). Live-Aktualisierung lokal per Neuladen, Mercure erst auf dem Pi. **Aus Review M4:** `HOME_NETWORKS` darf weder das Docker-Netz von cloudflared noch Loopback enthalten, wenn cloudflared über localhost verbindet (sonst schützen nur noch die Cloudflare-Kennzeichen). Der Kopplungs-QR nutzt `linkBase()` – bei Aufruf über eine IP-Adresse ist das `DEFAULT_URI`; zeigt das auf die Tunnel-Domain, scheitert die Kopplung per QR (Code abtippen geht). Tablets brauchen einen Rechnernamen im Heimnetz (UniFi-DNS).

8. **M7/M11 – Einkaufsliste mit Link und Foto** (Wunsch Andreas, 03.11.): je Eintrag ein Link (z. B. zum Produkt im Shop) und genau ein Foto, auch direkt mit der Kamera aufgenommen. Braucht eine Schemaänderung: Link-Spalte an `shopping_item` (ggf. auch an `shopping_favorite`), Besitzer `shopping_item_id` in `media` (inkl. `ck_media_owner`). Beim Planen von M7 klären: Link schon in M7, Foto mit der Medien-Verarbeitung aus M11 (Verkleinern, EXIF/GPS entfernen) – oder M11 vorziehen? Links nur `https://`, mit Hinweis „öffnet eine fremde Seite“.

## Später
Auffälligkeiten außerhalb eines Meilensteins (siehe Skill `meilenstein`).

- ~~**Ab M3/M4 – Tokens nicht ins Log:** In prod schreibt Monolog bei Fehlern den ganzen Puffer (`fingers_crossed`) inkl. Kanal `request` mit Routen-Parametern. Einladungs- und Kopplungs-Codes daher nicht im URL-Pfad/Query übergeben (oder Kanal `request` in prod ausschließen) – beim Planen von M3/M4 entscheiden. (Review M0) **M3:** Einladungen erledigt (Code nur im URL-Fragment, Formular per POST).~~ Erledigt (M4): auch der Kopplungscode steht nur im URL-Fragment und geht per POST.
- **Dev-Werkzeug Screenshots:** Headless-Chrome unter Windows erzwingt ≥ 500 px Breite; für 390 px die Gerätesimulation über das DevTools-Protokoll nutzen (Hilfsskript liegt nicht im Projekt).
- **DBAL 5:** Die TINYINT-Lösung (`MariaDbSchemaManager`) hängt am alten Introspektionsweg von DBAL 4; der neue `MetadataProvider` liest `TINYINT UNSIGNED` weiterhin als bool. Beim Umstieg auf DBAL 5 anpassen (`MappingTest::testMappingMatchesTheMigratedSchema` schlägt an). (M1)
- **Pi/Docker:** PHP muss in UTC laufen (`date.timezone = UTC`), damit `DATETIME`-Werte aus der Datenbank richtig gelesen werden – lokal ist das so; beim Bau des FrankenPHP-Images setzen. (M1)
- ~~**M2 – Schema-Abgleich mit Fremdschlüsseln:** in M2 einmal bewusst ein `ON DELETE` in der Migration ändern und prüfen, dass `composer schema:check` rot wird (Review M1).~~ Erledigt (M2): `fk_webauthn_user` auf RESTRICT → Abgleich meldet die Abweichung, danach zurück.
- **Vor dem Echtbetrieb – 2FA-Pflicht einschalten:** `ADMIN_2FA_REQUIRED=1` setzen (vorerst `0` auf Wunsch); in die „Prüfliste vor dem Start" (M13) aufnehmen. (M2)
- **Pi – Anmelde-Sperre und Proxy:** Die Sperre nach Fehlversuchen zählt auch je IP (global 25 / 15 min). Hinter cloudflared müssen Trusted Proxies stimmen, sonst teilen sich alle Anfragen eine IP und ein Angreifer sperrt alle aus. Zusammen mit Punkt 7 der offenen Punkte abnehmen. (M2)
- **Pi – Passkeys und Hostnamen:** Ein Passkey gilt nur für den Hostnamen, unter dem er angelegt wurde. Ist HomeCrew im WLAN und über den Tunnel unter verschiedenen Namen erreichbar, `WEBAUTHN_RP_ID` auf die gemeinsame Domain setzen (UniFi-DNS und Tunnel mit demselben Namen). (M2)
- **Browser-Prüfung automatisieren:** Das DevTools-Protokoll von Chrome unter Windows ist aus WSL nur über einen nach außen offenen Port erreichbar (gesperrt). Für Screenshots angemeldeter Seiten und Passkey-Tests mit virtuellem Authenticator ein lokales Werkzeug klären (z. B. Chromium in WSL). (M2)
- **Verwaltung (EasyAdmin):** Dunkelmodus ist abgeschaltet; Schriftgröße/Kontrast der Person wirken dort noch nicht (nur Schrift, Farben, Fokus, 48-px-Ziele). Bei Bedarf in M3 nachziehen. (M2)
- ~~**M3 – Laufende Sitzungen sofort beenden:** Sperre und Austritt prüft bisher nur der `UserChecker` bei der Anmeldung. Mit der Personenverwaltung (M3) gesperrte/ausgetretene Personen auch in laufenden Sitzungen abmelden (z. B. `EquatableInterface` oder Request-Listener). (Review M2)~~ Erledigt (M3): `AccountStatusSubscriber`.
- ~~**M3 – Frische Bestätigung:** Passwort ändern, neue Wiederherstellungscodes und weitere Passkeys nur nach erneuter Bestätigung (Passwort + zweiter Schritt, wenige Minuten gültig) – bis dahin gesperrt. (Review M2)~~ Erledigt (M3): `RecentConfirmation`, 5 Minuten.
- **M13 – Protokoll der Einstellungen:** Reine Statusspalten (`*_status`, `*_checked_at`, `ai_key_error`) nicht protokollieren, sonst füllt der Gesundheitscheck das Protokoll. (Review M2)
- **Löschjob-Zeit:** Der Scheduler startet nachts um 03:30 deutscher Zeit (fest, nicht aus `app_setting.timezone` – der Worker soll ohne Datenbank starten). Für die Fristen spielt die Uhrzeit keine Rolle. (Review M2)
- **Vor dem Echtbetrieb – Datenschutzhinweis prüfen:** Der Text unter `/konto/datenschutz` (`templates/account/privacy.html.twig`) ist ein Entwurf aus Konzept 5 §4 (u. a. Tunnel-Dienst, Aufsichtsbehörde BayLDA). In die „Prüfliste vor dem Start" (M13) aufnehmen; bei Textänderung `PrivacyNotice::VERSION` erhöhen. (M3)
- **Austritt mit Datum in der Zukunft** („zum 30.11."): bisher nur heute oder früher. Falls gebraucht: Zugang bleibt bis zum letzten Tag, ein täglicher Job entfernt ihn danach (`UserChecker` sperrt schon ab dem Folgetag). (M3)
- **Verwaltung – Bedienung nur per Tastatur/Screenreader:** Die Bestätigung riskanter Aktionen auf der Seite „Zugang" geht über `<details>`; bei der Abnahme mit Tastatur und Screenreader prüfen. (M3)
- **TOTP-Code bei der frischen Bestätigung:** Ein Code aus der App wird dort nicht gegen Wiederverwendung gesperrt (anders als beim Anmelden, `code_reuse_cache`). Risiko gering (Person ist schon angemeldet); bei Bedarf denselben Cache nutzen. (M3)
- **Passkey-Bestätigung nur mit eigenen Passkeys:** Die Optionen für `/konto/bestaetigen/passkey` werden ohne Benutzer angefordert; auf geteilten Geräten bietet der Browser auch fremde Passkeys an (der Handler lehnt sie ab). Schöner: `allowCredentials` mit den eigenen Passkeys. (Review M3)
- **Personenliste:** „offene Einladung" wird je Zeile einzeln abgefragt (wenige Personen, unkritisch); bei Bedarf gesammelt laden. (Review M3)
- ~~**M4 – Farbe der Namenskachel:** Das Farbfeld schickt ohne Auswahl `#000000`. Mit den Namenskacheln in M4 eine kleine, kontrastgeprüfte Farbauswahl statt freiem Farbwähler. (Review M3)~~ Erledigt (M4): Palette `PersonColor`.
- ~~**Ab M5 – Tablet per Freigabeliste:** Jede neue Seite mit nur `IS_AUTHENTICATED_FULLY` ist auch für das Team-Tablet erreichbar (auch im Modus „Persönlich"). Team-Funktionen ausdrücklich über `TEAM_VIEW` bzw. den Betrachter-Dienst freigeben, besser: Tablet-Anfragen standardmäßig ablehnen, außer an markierten Routen. (Review M4)~~ Erledigt (M5): `#[TeamTabletAllowed]` + `TeamTabletGuard`, Test mit fester Liste der freigegebenen Routen.
- **Bildschirmschoner – Barrierefreiheit:** Die Seite dahinter ist nicht `inert` (Tab erreicht verdeckte Elemente), nach dem Aufwecken kehrt der Fokus nicht zurück; die Ebene verdeckt den Notfall-Knopf der Kopfleiste – ein Link „Alle Notfall-Infos" auf der Ebene löst das. Feste Farben (Bildschirmschoner, Kachel-Palette) als Tokens in `tokens.css`. Bei der Browser-Abnahme M4 mit erledigen. (Review M4)
- **Namenskacheln und PIN-Fehler:** `AppUserRepository::findForPinLogin` filtert anders als `PinLogin::canUse()` (ohne PIN, heute ausgetreten) – eine Kachel kann auf eine 404 führen; Fehler im `PinAuthenticator` für unbekannte oder ausgeschaltete Personen landen ebenfalls auf der 404 statt mit Hinweis bei den Kacheln. (Review M4)
- **Gesperrte Person am Tablet:** Die Abmeldung durch `AccountStatusSubscriber` führt nach `/anmelden?grund=gesperrt` statt zur Startseite des Tablets (bei PIN-Anmeldungen je nach Reihenfolge der Listener). (Review M4)
- **Notfall im zweiten Anmeldeschritt am Tablet:** Dort fehlt der Notfall-Knopf (`EmergencyVoter`, `access_control ^/`); ließe sich über das gekoppelte Tablet im Heimnetz zulassen. (Review M4)
- **Entkoppelte Geräte:** Die Bearbeiten-Maske ist per URL weiter erreichbar (nur der Knopf fehlt) – harmlos. (Review M4)
- **Vor dem Echtbetrieb – Passwort-Speichern am Tablet aus:** In Chrome auf dem Tablet „Passwörter speichern anbieten" ausschalten – ein gespeichertes Admin-Passwort gäbe sonst allen am Tablet die Verwaltung (solange `ADMIN_2FA_REQUIRED=0`). Die Anmeldeseite am Tablet weist darauf hin. In die „Prüfliste vor dem Start" (M13) aufnehmen. (Review M4)
- **Wochenkalender am Handy:** Bei 390 px sind die sieben Tageknöpfe etwa 45 px breit (56 px hoch) – knapp unter den 48 px der Projektregel. Bei der Browser-Abnahme prüfen; sonst zwei Zeilen je Woche oder schmalerer Seitenrand. (Review M5)
- **Wackeliger Test:** `AccountTest::testConfirmationIsLimitedToFiveAttempts` ist in M5 einmal im Gesamtlauf gescheitert (einzeln und danach grün). Bei Wiederholung die Versuchsgrenze (Rate-Limiter, Uhr) untersuchen. (M5)
- **Text auf der Zugangsseite einer Person:** In `templates/admin/person/access.html.twig` erscheinen „Passkeys: %count%." und „Wiederherstellungscodes übrig: %count%." als „2." bzw. „8." – Symfony liest bei `%count%` das Wort vor dem Doppelpunkt als Plural-Beschriftung und schneidet es ab. Platzhalter umbenennen (z. B. `%passkeys%`) und mit einem Test absichern. (M6)
- **Monatliche Rhythmen „am ersten Montag"** u. Ä. bietet das Plan-Formular noch nicht an (nur Tag des Monats). Bei Bedarf in `App\Service\Series\Rhythm` ergänzen – auch für Termin- und Besuchsserien (M8). (M6)
- **Nachtlauf der Pläne robuster:** Wirft der Abgleich bei einem Plan eine Exception, bricht `PlanSync::syncAll()` ab und der EntityManager ist geschlossen – die übrigen Pläne bleiben bis zur nächsten Aktion liegen. Je Plan abfangen (ohne Inhalte loggen) und den EntityManager zurücksetzen, sobald es einen Fehlerfall gibt. (Review M6)
- **„Routinen & Wartung" bei unterschiedlich zuständigen Vorkommen:** Die Übersicht nimmt das früheste offene Vorkommen, auch wenn der Betrachter genau dieses nicht sieht (z. B. „nur wer zuständig ist" und an Ben weitergegeben) – dann fehlt „Jetzt erledigt", obwohl ein späteres sichtbar wäre. Randfall; bei Bedarf das früheste *sichtbare* Vorkommen nehmen. (Review M6)
- **Verlauf eines Plans begrenzen:** `/routinen/{id}` zeigt alle noch vorhandenen Erledigungen (bei Tagesplänen bis etwa 365 Zeilen). Bei Bedarf die letzten 20 zeigen und „Mehr" anbieten. (Review M6)
