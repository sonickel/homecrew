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

---

### M3 – Personen und persönliche Zugänge
**Ziel:** Admins legen Personen mit Rolle, Admin-Recht und passendem Zugang an; jede Person verwaltet ihr Konto.

**Umfang**
- Verwaltung → Personen: anlegen/bearbeiten (Rolle, Admin-Recht, Benutzername, Anzeigename, Farbe, Eintritt); Regeln genau ein Manager, Manager immer Admin, Assistenz nie Admin (Formular + Datenbank).
- Zugang: keiner (nur Team-Tablet) · Tablet-PIN (Start-PIN, PIN-Anmeldung an/aus) · Handy/PC (Start-Passwort mit Änderungspflicht oder Einladung per QR-Code, einmalig, 72 h).
- Schalter je Person: wiederkehrende Aufgaben, Anleitungen veröffentlichen, Termine anlegen, KI.
- Admins setzen Passwort, PIN und 2FA zurück; sperren; Austritt (Zugang entfernt, Person bleibt als Anker).
- Druckansicht Zugangs-Kärtchen; Start-Passwort/-PIN nur direkt nach dem Setzen sichtbar.
- „Mein Konto": Passwort ändern, 2FA und Passkeys verwalten, neue Wiederherstellungscodes, Schriftgröße (normal/groß/sehr groß), hoher Kontrast.
- Angehörige mit Admin-Recht richten bei der ersten Anmeldung 2FA ein. (PIN-Anmeldung selbst: M4.)

**Tabellen:** neu `invitation`; genutzt `app_user`, `webauthn_credential`, `audit_log`.

**Akzeptanzkriterien**
- [ ] Assistenz „Ben" ohne Zugang anlegen: steht in der Liste, kann sich nirgends anmelden.
- [ ] Angehörige mit Admin-Recht + Start-Passwort: erste Anmeldung erzwingt Passwortwechsel und 2FA.
- [ ] Einladungs-QR einlösen → Passwort setzen → angemeldet; zweites Einlösen und abgelaufene Einladung werden verständlich abgelehnt.
- [ ] Assistenz nicht zum Admin machbar, kein zweiter Manager (Formular und Datenbank).
- [ ] Austritt: Anmeldung gesperrt, Person bleibt als „ausgetreten" sichtbar.
- [ ] Schriftgröße „sehr groß" und hoher Kontrast wirken sofort und bleiben gespeichert.
- [ ] Zugangs-Kärtchen druckt schwarz-weiß auf A4.
- [ ] Änderungen an Personen/Rechten im Protokoll (ohne Passwörter/PINs); Tests für Voter, Formularregeln, Einladungen.

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
- [ ] Tablet (zweites Browserprofil) koppeln → Team-Ansicht ohne Anmeldung; nach Entkoppeln sofort „Dieses Gerät ist nicht mehr gekoppelt".
- [ ] Geräte-Token von außerhalb des Heimnetzes (andere IP bzw. Tunnel-Header) wird abgelehnt.
- [ ] Team-Tablet erreicht weder `/verwaltung` noch „Mein Konto" (Tests).
- [ ] Falsche PIN → „Die PIN stimmt nicht. Noch 2 Versuche, dann 5 Minuten Pause."; ein Admin hat nach PIN-Anmeldung keine Admin-Rechte.
- [ ] Admin-Anmeldung am Tablet → nach Abmelden bzw. 5 Minuten zurück im Team-Modus.
- [ ] Bildschirmschoner nach eingestellter Zeit; Antippen weckt; Notfall ohne PIN erreichbar.
- [ ] Notfallkontakt „nur Admins" am Team-Tablet unsichtbar.
- [ ] Tests: Heimnetz-Prüfung (Unit, IPv4/IPv6/Proxy), Geräte-Authenticator, Modi, Notfall-Sichtbarkeit.

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
- [ ] Szenario A1: Team-Tablet zeigt sofort die Aufgaben für alle, Rolle Assistenz und „wer da ist" – keine privaten oder „nur Admins".
- [ ] Private Aufgabe sieht nur die Person, die sie angelegt hat; „zuständig" sieht die zuständige Person + Admins.
- [ ] Erledigt am Team-Tablet → Gerät gespeichert, keine Person; „Rückgängig" innerhalb 10 s stellt den alten Zustand her.
- [ ] Szenario A10: Admin gibt eine Aufgabe an die Rolle Assistenz weiter; Angehörige nur eigene; Assistenz und Team-Tablet gar nicht (403).
- [ ] Sichtbarkeits-Matrix als Tests (Sichtbarkeit × Rolle × Team-Tablet); Löschjobs mit fester Uhr getestet.

---

### M6 – Wartungspläne, Serien-Generator, „Routinen & Wartung"
**Ziel:** Wiederkehrende Aufgaben entstehen zuverlässig aus Plänen; die Übersicht zeigt, was fällig ist und wann es zuletzt erledigt wurde.

**Umfang**
- Verwaltung → Wartungspläne / wiederkehrende Aufgaben (Admins; Personen mit Schalter): Rhythmus nach Kalender (täglich, wöchentlich, Wochentage, monatlich …) oder Intervall nach letzter Erledigung, Zeitfenster, Checkliste, Zuständigkeit, Sichtbarkeit, Pausieren.
- Gemeinsamer Serien-Generator (`Series/OccurrenceGenerator`, php-rrule): Vorkommen mit Ursprungstag, eindeutig je Vorlage und Tag, einzeln geänderte Vorkommen bleiben unberührt; läuft täglich per Scheduler und beim Speichern. Wird in M8 und M9 wiederverwendet.
- Checkliste beim Erledigen abhaken (Sollwerte und Messwerte erst Stufe 2).
- „Routinen & Wartung" `/routinen`: Pläne nach Dringlichkeit – Status (Symbol + Wort), zuständig, Rhythmus, zuletzt erledigt (**nur Datum**; Admins zusätzlich Person bzw. „Team-Tablet"), nächste Fälligkeit; Detail mit Verlauf und „Jetzt erledigt".
- „Heute" der Admins: „Routinen & Wartung: 2 überfällig".
- Löschjob: alte Erledigungen löschen, die letzten `completion_keep_last` je Plan bleiben.

**Pakete:** rlanvin/php-rrule.
**Tabellen:** neu `task_template_item`, `task_item`, `task_item_result`; mitgezogen `asset_component`; genutzt `task_template`, `task`, `task_completion`.

**Akzeptanzkriterien**
- [ ] „Bettwäsche wechseln jeden Montag" erzeugt die Vorkommen für den Vorlauf; ein zweiter Generatorlauf erzeugt keine Doppelungen.
- [ ] Einzeln verschobenes Vorkommen bleibt bei Planänderung erhalten.
- [ ] Intervall „alle 14 Tage nach Erledigung" rechnet ab der Erledigung; pausierter Plan erzeugt nichts.
- [ ] Assistenz und Team-Tablet sehen „zuletzt erledigt" nur als Datum; Admins sehen Person bzw. „Team-Tablet".
- [ ] Assistenz ohne Schalter kann keine Pläne anlegen (403), mit Schalter schon.
- [ ] Unit-Tests Generator: Monatsende, Schaltjahr, Zeitumstellung (Ortszeit), Pause, Ende.

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
- Termine (Admins, Angehörige, Assistenz mit Schalter): Zeit oder ganztägig, Ort, Mitbringliste, Notiz, Zuständigkeit/Begleitung, Weitergeben, Sichtbarkeit.
- Besuche: externer Dienst, Zeit, Hinweis; Sichtbarkeit alle (Standard) oder nur Admins; **keine** Zuständigkeit und Begleitung.
- Serien für Termine und Besuche (Wochentage/Rhythmus, Uhrzeit, Dauer, Beginn, Ende, Pause) über den Generator aus M6; einzelne Vorkommen absagen („fällt aus" + Notiz) oder verschieben – bleibt bei Serienänderung erhalten; Serie beenden.
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
2. **M3 – Datenschutzhinweis** (`privacy_notice_version`, Konzept 5 §4.1): Vorschlag Bestätigung bei der ersten Anmeldung in M3, neue Version mit Einrichtung einer Erweiterung in M13.
3. ~~**M4 – Schriftgröße am Gerät:** Konzept 11 sieht sie vor, `device` hat keine Spalte.~~ Erledigt 01.11. (M2): `device.font_size` in `schema.sql` + `smoke_test.sql` (B93) und in der Migration; Oberfläche in M4.
4. **M4 – echtes Tablet lokal:** WSL läuft im NAT-Modus; ein Tablet im WLAN erreicht den Rechner erst mit `networkingMode=mirrored` in `.wslconfig` oder per Portweiterleitung. Bis dahin Tablet im Windows-Browser prüfen (zweites Profil, Gerätesimulation).
5. **M12 – Rezepte:** kontext.md §4 nennt Rezepte in Stufe 2, Konzept 6 und das Entscheidungs-Log (Druckansicht Rezepte) in Stufe 1. Vorschlag: Typ Rezept mit Zutaten und Druck in M12, „aus Rezepten auf die Liste" in Stufe 2.
6. **Mitgezogene Tabellen ohne Oberfläche** (Folge der Strategie): `asset`, `asset_component` (Pflege Stufe 2), `chat_conversation`, `chat_message` (Chat Stufe 2).
7. **Pi (nach Stufe 1):** Hinter cloudflared dürfen Tunnel-Anfragen nie als Heimnetz gelten (Trusted Proxies, Tunnel-Header) – Test in M4, Abnahme beim Pi-Aufbau. Live-Aktualisierung lokal per Neuladen, Mercure erst auf dem Pi.

## Später
Auffälligkeiten außerhalb eines Meilensteins (siehe Skill `meilenstein`).

- **Ab M3/M4 – Tokens nicht ins Log:** In prod schreibt Monolog bei Fehlern den ganzen Puffer (`fingers_crossed`) inkl. Kanal `request` mit Routen-Parametern. Einladungs- und Kopplungs-Codes daher nicht im URL-Pfad/Query übergeben (oder Kanal `request` in prod ausschließen) – beim Planen von M3/M4 entscheiden. (Review M0)
- **Dev-Werkzeug Screenshots:** Headless-Chrome unter Windows erzwingt ≥ 500 px Breite; für 390 px die Gerätesimulation über das DevTools-Protokoll nutzen (Hilfsskript liegt nicht im Projekt).
- **DBAL 5:** Die TINYINT-Lösung (`MariaDbSchemaManager`) hängt am alten Introspektionsweg von DBAL 4; der neue `MetadataProvider` liest `TINYINT UNSIGNED` weiterhin als bool. Beim Umstieg auf DBAL 5 anpassen (`MappingTest::testMappingMatchesTheMigratedSchema` schlägt an). (M1)
- **Pi/Docker:** PHP muss in UTC laufen (`date.timezone = UTC`), damit `DATETIME`-Werte aus der Datenbank richtig gelesen werden – lokal ist das so; beim Bau des FrankenPHP-Images setzen. (M1)
- ~~**M2 – Schema-Abgleich mit Fremdschlüsseln:** in M2 einmal bewusst ein `ON DELETE` in der Migration ändern und prüfen, dass `composer schema:check` rot wird (Review M1).~~ Erledigt (M2): `fk_webauthn_user` auf RESTRICT → Abgleich meldet die Abweichung, danach zurück.
- **Vor dem Echtbetrieb – 2FA-Pflicht einschalten:** `ADMIN_2FA_REQUIRED=1` setzen (vorerst `0` auf Wunsch); in die „Prüfliste vor dem Start" (M13) aufnehmen. (M2)
- **Pi – Anmelde-Sperre und Proxy:** Die Sperre nach Fehlversuchen zählt auch je IP (global 25 / 15 min). Hinter cloudflared müssen Trusted Proxies stimmen, sonst teilen sich alle Anfragen eine IP und ein Angreifer sperrt alle aus. Zusammen mit Punkt 7 der offenen Punkte abnehmen. (M2)
- **Pi – Passkeys und Hostnamen:** Ein Passkey gilt nur für den Hostnamen, unter dem er angelegt wurde. Ist HomeCrew im WLAN und über den Tunnel unter verschiedenen Namen erreichbar, `WEBAUTHN_RP_ID` auf die gemeinsame Domain setzen (UniFi-DNS und Tunnel mit demselben Namen). (M2)
- **Browser-Prüfung automatisieren:** Das DevTools-Protokoll von Chrome unter Windows ist aus WSL nur über einen nach außen offenen Port erreichbar (gesperrt). Für Screenshots angemeldeter Seiten und Passkey-Tests mit virtuellem Authenticator ein lokales Werkzeug klären (z. B. Chromium in WSL). (M2)
- **Verwaltung (EasyAdmin):** Dunkelmodus ist abgeschaltet; Schriftgröße/Kontrast der Person wirken dort noch nicht (nur Schrift, Farben, Fokus, 48-px-Ziele). Bei Bedarf in M3 nachziehen. (M2)
- **M3 – Laufende Sitzungen sofort beenden:** Sperre und Austritt prüft bisher nur der `UserChecker` bei der Anmeldung. Mit der Personenverwaltung (M3) gesperrte/ausgetretene Personen auch in laufenden Sitzungen abmelden (z. B. `EquatableInterface` oder Request-Listener). (Review M2)
- **M3 – Frische Bestätigung:** Passwort ändern, neue Wiederherstellungscodes und weitere Passkeys nur nach erneuter Bestätigung (Passwort + zweiter Schritt, wenige Minuten gültig) – bis dahin gesperrt. (Review M2)
- **M13 – Protokoll der Einstellungen:** Reine Statusspalten (`*_status`, `*_checked_at`, `ai_key_error`) nicht protokollieren, sonst füllt der Gesundheitscheck das Protokoll. (Review M2)
- **Löschjob-Zeit:** Der Scheduler startet nachts um 03:30 deutscher Zeit (fest, nicht aus `app_setting.timezone` – der Worker soll ohne Datenbank starten). Für die Fristen spielt die Uhrzeit keine Rolle. (Review M2)
