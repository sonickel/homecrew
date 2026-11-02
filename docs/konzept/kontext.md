# Projektkontext: HomeCrew – App für Persönliche Assistenz (Übersicht)

Stand: 01.11.2026 (nachmittags) · **Dieses Dokument ist maßgeblich**; Details in den Konzepten (siehe Index). Das Review-Dokument (`docs/archiv/`) zeigt den Diskussionsstand vom Vormittag.
**Phase: Umsetzung Stufe 1 startet** – lokal in WSL (VS Code + Claude Code), Plan in `docs/plan-stufe-1.md`. Konzept bleibt die fachliche Vorgabe.

## 1. Kurzbeschreibung
**HomeCrew** ist eine **reine Web-App** für einen Haushalt, in dem eine Person im **Arbeitgebermodell** persönliche Assistenz beschäftigt (Bayern, Bezirk mit **Monatsbudget**, Minijobs im **Haushaltsscheckverfahren**). Die App macht den Alltag selbsterklärend (auch wenn niemand zu Hause ist) – am einfachsten über ein **Team-Tablet ohne Anmeldung** –, zeigt allen, **wer heute kommt** (Physio, Caritas …), sorgt mit **Meldungen** dafür, dass der Manager mitbekommt, was fehlt oder nicht ging, bietet Kalender, Listen, Notfall-Infos, Anleitungen mit Fotos/Videos, Nachrichten, einen optionalen KI-Assistenten mit Sprache und Text und vereinfacht die Verwaltung. **Eine Installation = ein Haushalt**; Self-Hosting auf dem Raspberry Pi.

## 2. Entscheidungen (Log)

| Datum | Entscheidung |
|---|---|
| 31.10. | Manager + 3–4 Assistenzkräfte im täglichen Wechsel; Lohn selbst; Bezirk zahlt automatisch, Überweisung manuell |
| 31.10. | Wiederkehrende Aufgaben/Wartungen, Orte mit Maps-Link, Medien mit automatischer Verkleinerung |
| 01.11. | Haushaltsscheck; Excel zuerst; Cloudflare Tunnel; UniFi; Pi 5 (8 GB, SSD, Proxmox); Symfony + **MariaDB 11.4 LTS** |
| 01.11. | Stundenlohn pro Person; Assistenz nicht RV-befreit; keine Haushaltskasse; große Schrift; Budget je Monat; keine Unterschriften |
| 01.11. | Dienstplan in Stufe 1 (feste Tage); Kalender; Übersicht „zuletzt erledigt"; Fotos/Videos an Wartungsplänen |
| 01.11. | Benachrichtigungen in der App, Push/E-Mail optional; reine Web-App ohne Offline; Pi-Ausfall akzeptiert; keine Medikamente |
| 01.11. | E-Mail über SMTP des eigenen Mail-Anbieters (Einladung, Passwort, 2FA-Code); Editor für Anleitungen; KI-Übersetzung als Stufe 5 |
| 01.11. nachm. | **Keine Mandanten** – weitere Haushalte = eigene Instanz/Datenbank |
| 01.11. nachm. | **Rollen** Manager · Angehörige · Assistenz; **Admin = eigenes Recht** (Manager immer, Angehörige optional, Assistenz nie); **2FA/Passkey Pflicht für Admins** |
| 01.11. nachm. | **Personen anlegt der Admin** inkl. Benutzername; Tablet-PIN funktioniert **ohne E-Mail und Handy** (Zugangs-Kärtchen); PIN pro Person, Standard aus |
| 01.11. nachm. | Assistenz legt standardmäßig **keine Termine** an; sieht **nur eigene Dienste**; „Wer kommt" nur für Admins |
| 01.11. nachm. | **Arbeitszeiten**: Assistenz sieht 3 Monate, bearbeitet nur am selben Tag; danach nur Admins (mit Begründung) |
| 01.11. nachm. | **„Nicht möglich" → Ziel wählen**: mehrere Listen (Einkauf, Besorgt die Familie …) oder Aufgabe für Person/Rolle oder Übergabe |
| 01.11. nachm. | **Zuständigkeit & Weitergeben** für Aufgaben, Termine, Checklisten (zuerst oft Admins, dann Rolle Assistenz oder Person); Sichtbarkeit alle/zuständig/admins/**privat** |
| 01.11. nachm. | **Notfall-Infos** als eigener Bereich (Kontakte, Nummern, Notfallpläne); Notfall-Button ohne PIN ok |
| 01.11. nachm. | **Lernpfade gestrichen** → „Wer kennt welche Anleitung" (gelesen / eingewiesen) |
| 01.11. nachm. | Videos 90 s; Proxmox unverschlüsselt (ok); **whisper.cpp lokal** testen; **KI = Claude Haiku 5.5** über die Claude API (separates Console-Konto, nicht im Pro-Abo enthalten) |
| 01.11. nachm. | Kein Dienstort-Feld, keine Soll-Abdeckung; Chat: **Sprachnachrichten** (global schaltbar), **Assistenz nicht untereinander**, **Löschfrist 365 Tage** |
| 01.11. nachm. | **Schlüsselliste zurückgestellt**; **Druckansichten**: Tages-ToDo für morgen, Einkaufsliste, Rezepte |
| 01.11. nachm. | **Admin-Menü mit Pflegemasken** für alles (Konzept 11) |
| 01.11. nachm. | **Name: HomeCrew** (vor Veröffentlichung Marke/Domain prüfen) |
| 01.11. nachm. | **Team-Tablet**: Gerätemodus ohne Personenanmeldung, zeigt alles für die Rolle Assistenz; **Arbeitszeiten pflegt dann der Admin** („Dienstplan übernehmen"); Modi Team (Standard) · Persönlich · Beides (Konzept 12) |
| 01.11. nachm. | **„Zuletzt erledigt": nur Datum**; Admins sehen zusätzlich Person bzw. „Team-Tablet" |
| 01.11. nachm. | Team-Tablet: **nur Bildschirmschoner, kein eigener Entsperr-Code** (Android-PIN als Rückfall) |
| 01.11. nachm. | **Meldungen** (Konzept 13): „nicht möglich" und Knopf „Melden" (leer · kaputt · fehlt) → **Meldungs-Log** mit Status, automatischer Abschluss, Benachrichtigung der Admins; Vorschlag bei „nicht möglich" am Wartungsplan |
| 01.11. nachm. | **Benachrichtigungsregeln je Ereignis** (App · E-Mail · Push); **E-Mail-Benachrichtigungen nach Stufe 1 vorgezogen** (sofort oder Tageszusammenfassung, Titel optional) |
| 01.11. nachm. | **Besuche externer Dienste** (Physio, Caritas, Pflegedienst …): Kalendereintrag als reine Info, **für das ganze Team sichtbar**, niemand muss etwas tun; als Serie (z. B. jeden Dienstag) mit Absagen einzelner Termine; Block „Heute kommen"; externe Dienste ohne Klarnamen (Konzept 8 §3) |
| 01.11. nachm. | **Lokale Entwicklung**: WSL2 Debian (`/var/www/homecrew`), PHP 8.4, **Symfony 7.4 LTS**, Symfony CLI als Webserver, vorhandene MariaDB – **ohne Docker/FrankenPHP/Mercure/whisper**. Pi später per Docker Compose mit derselben MariaDB-Version wie lokal |
| 01.11. nachm. | **Erweiterungen im Admin-Menü**: KI-Schlüssel, Modell, Verbindungstest, Funktionsschalter, Budget; ebenso **E-Mail (SMTP)** mit Testmail. **Nicht eingerichtet = Funktion unsichtbar**; Geheimnisse verschlüsselt, Ändern nur mit erneuter 2FA (Konzept 7 §2, Konzept 11) |
| 01.11. abends | **MariaDB 11.8 LTS** statt 11.4 LTS (lokal in WSL vorhanden: 11.8.6); der Pi nutzt im Docker-Image dieselbe Version; Migrationsstrategie in Konzept 6 §6 |
| 01.11. abends (M2) | **Anmeldung**: Passkey ist eine vollständige Anmeldung (Fingerabdruck/Gesicht/Geräte-PIN, ohne Benutzername); wer Passkey als zweiten Schritt hat, kommt mit Passwort nur plus Wiederherstellungscode weiter. **2FA-Pflicht für Admins per Schalter** (`ADMIN_2FA_REQUIRED`) – auf Wunsch vorerst aus, **vor dem Echtbetrieb einschalten**. Persönliche Anmeldungen (Handy/PC) enden nach **8 Stunden** ohne Aktivität. Löschfristen-Maske zeigt nur Fristen vorhandener Funktionen. **Schriftgröße am Gerät** (`device.font_size`) im Schema |
| 02.11. (M3) | **Personen und Zugänge**: Schalter je Person kommen mit ihrer Funktion (M6/M8/M12, KI Stufe 2). **Datenschutzhinweis** als Schritt bei der ersten Anmeldung (Text als Entwurf). **Manager-Schutz**: den Manager bearbeitet nur der Manager selbst; niemand ändert eigene Rolle/Admin-Recht oder sperrt sich selbst. Start-Passwort/-PIN **erzeugt die App**. **Austritt** nur heute oder früher, Zugang sofort weg, zurücknehmbar. Einladungscode nur im URL-Fragment. Änderungen an der eigenen Anmeldung nur nach **frischer Bestätigung** (5 Minuten) |

## 3. Rollen (Kurzfassung)
- **Manager** – assistierte Person, genau einer, immer Admin.
- **Angehörige** – eigener Zugang, optional Admin.
- **Assistenz** – Alltag, Wissen, Melden; mit eigenem Zugang zusätzlich eigene Dienste/Zeiten und Nachrichten; nie Admin.
- **Admins** – Manager + Angehörige mit Admin-Recht: Verwaltung, Personen, Geräte, Einstellungen, Erweiterungen, Meldungs-Log; 2FA/Passkey Pflicht (Schalter `ADMIN_2FA_REQUIRED`, vorerst aus – vor dem Echtbetrieb einschalten).
- **Team-Tablet** – keine Person, sondern ein Gerät: sieht und erledigt, was für die Rolle Assistenz bestimmt ist, und kann melden.
- **Externe Dienste** (Physio, Caritas …) – keine Nutzer, nur Besuche im Kalender.

## 4. Ausbaustufen

| Stufe | Inhalt | Konzepte |
|---|---|---|
| **1 – Alltag & Kalender** | Einrichtung, Admin-Menü (Personen, Geräte, Einstellungen, **Erweiterungen**: E-Mail, KI-Seite vorbereitet), **Team-Tablet**, Anmeldung (Passwort/Passkey/2FA, Tablet-PIN), E-Mail für Einladung/Passwort/2FA und Benachrichtigungen, „Heute", Dienstplan, Kalender mit **Besuchen externer Dienste und Serien**, Arbeitszeiten (**Dienstplan übernehmen** durch Admin, optional Dienst beginnen/beenden), eigene Zeiten, Urlaubsantrag/Krankmeldung, Aufgaben + Wartungspläne mit Zuständigkeit/Weitergeben, Routinen & Wartung, **Meldungen + Meldungs-Log**, Gesprächspunkte, Übergabebuch, Termine, **Listen**, Wissensbasis mit Editor + Fotos/Videos, **Wer kennt was**, **Notfall-Infos**, Orte, Profil, Abwesenheit des Managers, Benachrichtigungen (App + E-Mail, Regeln je Ereignis), **Druckansichten** | 1, 2, 3, 8, 9, 11, 12, 13 |
| **2 – Wartung, KI & Nachrichten** | Hilfsmittel mit Bauteilen/Sollwerten, Messwerte (inkl. automatischer Meldung bei Abweichung), QR-Aufkleber, Rezepte, **KI-Assistent** (Claude Haiku 5.5, whisper.cpp; nur sichtbar, wenn eingerichtet; auch am Team-Tablet), KI-Schreibhilfe, **Chat** inkl. Sprachnachrichten | 3, 7, 9, 13 |
| **3 – Verwaltung** | Monatsabschluss, Lohn, Urlaubskonto, Lohnfortzahlung, Feiertage, bezahlte Ausfälle, Bewilligung + Ampel, Excel-Exporte, Fristen, U1, **Checklisten Einstellung/Austritt** | 4, 10 |
| **4 – Komfort** | Push (optional), Dienst-Tausch, Kalender-Abo, GiroCode, PDF für den Bezirk, Schlüsselliste (falls gebraucht) | – |
| **5 – Mehrsprachigkeit** | KI-Übersetzung von Anleitungen, später Oberfläche mehrsprachig | 3 |

**Empfohlener Start**: Tablet im Team-Modus, Assistenzkräfte zunächst ohne eigenen Zugang, Admin übernimmt monatlich den Dienstplan als Arbeitszeit; Physio/Caritas als Besuchs-Serien anlegen; E-Mail einrichten, Manager bekommt neue Meldungen per E-Mail. KI-Schlüssel erst, wenn gewünscht – vorher ist von der KI nichts zu sehen. PIN/Handy-Zugang später pro Person dazuschalten (Tablet auf „Beides").

## 5. Dokument-Index
- `konzept-1-rollen-zugang.md` – Rollen, Admin-Recht, Rechte-Matrix (inkl. Team-Tablet, Meldungen, Besuche), Personen anlegen, Anmeldung, E-Mail
- `konzept-2-alltag.md` – „Heute" (inkl. „Heute kommen"), Dienstplan, Dienst, Aufgaben/Wartung, „nicht möglich", Zuständigkeit & Weitergeben, Sichtbarkeit, Termine & Besuche, Listen, Druckansichten
- `konzept-3-wissen-hilfsmittel-orte.md` – Wissensbasis, Editor, **Notfall-Infos**, **Wer kennt was**, Übersetzung, Medien, Hilfsmittel, Orte, Profil
- `konzept-4-verwaltung.md` – Zeiten (zwei Wege, „Dienstplan übernehmen"), Abwesenheiten, Lohn, Bezirk, Excel, Fristen
- `konzept-5-technik-datenschutz.md` – Eine Instanz je Haushalt, Architektur, **Erweiterungen & Geheimnisse**, Pi, Netzwerk, E-Mail, Datenschutz
- `konzept-6-datenbank.md` – Schema (78 Tabellen), Entscheidungen, Test
- `konzept-7-ki-assistent.md` – **Einrichtung im Admin-Menü, Sichtbarkeit nur wenn eingerichtet**, Claude Haiku 5.5, whisper.cpp, Werkzeuge je Rolle
- `konzept-8-kalender.md` – Kalender, **Besuche externer Dienste & Serien**, Sichtbarkeit, Abwesenheit des Managers
- `konzept-9-nachrichten.md` – Chat, Benachrichtigungen (App, E-Mail, Push)
- `konzept-10-team-checklisten-schluessel.md` – Checklisten Einstellung/Austritt (Schlüssel zurückgestellt)
- `konzept-11-admin-menue.md` – **Admin-Menü, alle Pflegemasken, Erweiterungen**
- `konzept-12-team-tablet.md` – Team-Tablet: Modi, Inhalte, Arbeitszeiten durch den Admin, Sicherheit
- `konzept-13-meldungen.md` – Meldungen, Meldungs-Log, automatischer Abschluss, Benachrichtigungsregeln je Ereignis
- `../archiv/review-schwachstellen-ideen.md` – Review (Stand Vormittag, nicht maßgeblich)
- `../plan-stufe-1.md` – Umsetzungsplan Stufe 1 (legt Claude Code an)
- `/CLAUDE.md` – Anweisungen für Claude Code · `../datenbank/schema.sql` – Referenzschema · `../datenbank/smoke_test.sql` – Test (93 Regeln) · `/.claude/skills/` – Projekt-Skills

## 6. Offene Fragen
1. Arbeitsverträge: feste Dienste oder Arbeit auf Abruf?
2. Bezirk: Nachweis-Format, Einarbeitung abrechenbar, Aufbewahrungsfristen; Zuschläge?
3. whisper.cpp-Test: Qualität/Wartezeit auf dem Pi.
4. Übersetzung: welche Sprachen (später).
5. Name HomeCrew: Marke (DPMA/EUIPO) und Domain vor einer Veröffentlichung prüfen; für den privaten Einsatz unkritisch.
6. Vergangene Termine/Besuche: nach welcher Frist löschen? (Vorschlag: 12 Monate)

## 7. Quellen
- Claude Pro vs. API: https://support.claude.com/en/articles/9876003-i-have-a-paid-claude-subscription-pro-max-team-or-enterprise-plans-why-do-i-have-to-pay-separately-to-use-the-claude-api-and-console
- Claude Haiku 5.5 Preis: https://www.vktr.com/ai-platforms/anthropic-cuts-smallmodel-pricing-90-with-claude-haiku-55/ · API-Datenhaltung: https://platform.claude.com/docs/en/build-with-claude/api-and-data-retention
- whisper.cpp auf dem Pi 5: https://www.promptquorum.com/power-local-llm/local-whisper-stt-comparison-2026
- Namens-Check (ähnlicher Name im Schicht-Umfeld): https://www.crewapp.com/
- Haushaltsscheck, Abgaben, Minijob-Grenze, Krankheit, Annahmeverzug, Klinik, EuGH, Cloudflare, MariaDB: siehe Quellen in den Konzepten 4, 5, 7 und im Review
