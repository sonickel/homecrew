> **Archiv – nicht maßgeblich.** Diskussionsstand vom Vormittag des 01.11.2026; maßgeblich ist `docs/konzept/kontext.md`.

# Review: Schwachstellen, Risiken und weitere Ideen

Stand: 01.11.2026 (aktualisiert nach Rückmeldung) · Grundlage: Konzepte 1–10, Schema

Legende: ✅ umgesetzt/entschieden · ❌ verworfen · ⚠️ akzeptiertes Risiko · 💡 offen

## 0. Die wichtigsten Punkte und Entscheidungen

| # | Schwachstelle | Entscheidung / Maßnahme | Status |
|---|---|---|---|
| 1 | Stufe 1 ist groß | **Stufe 1 bleibt so** (keine Aufteilung) | ❌ Aufteilung verworfen |
| 2 | Einstiegshürde für neue Assistenzkräfte | **PIN-Anmeldung am Tablet pro Person aktivierbar (Standard aus)**, Name antippen + PIN; „nur Tablet"-Personen ohne Handy-Konto; kombinierbar mit Handy-Konto | ✅ (Konzept 1, Schema) |
| 3 | Ausfall von Pi/Internet | Kein Notbetrieb vorgesehen; Backups über Proxmox; im WLAN unabhängig von Cloudflare | ⚠️ |
| 4 | Verschobene Aufgaben/Dienste wären doppelt erzeugt worden | Ursprungstag `occurrence_date` + „von Hand geändert" | ✅ |
| 5 | Keine Benachrichtigungen bis Stufe 4 | Benachrichtigungen **in der App** (Stufe 1), E-Mail optional (Stufe 2), Push optional (Stufe 4) | ✅ |
| 6 | Einarbeitung zu Hause = Arbeitszeit | Arbeitszeit-Art „Einarbeitung/Besprechung"; Einarbeitung im Dienst | ✅ |
| 7 | Klinikaufenthalt ≠ Dienste absagen | Option „Dienst findet statt – Ort: Klinik" | ✅ (Konzept 8) |
| 8 | Manager-PIN am Tablet ausspähbar | Manager-Zugang standardmäßig **ohne** PIN (PIN pro Person, Standard aus) | ✅ |
| 9 | Daten auf privaten Handys | **Reine Web-App** ohne Offline-Speicher → nur normale Anmeldung im Browser | ✅ |
| 10 | Medikamente/Behandlungspflege | **Bewusst nicht enthalten** | ✅ |

## 1. Akzeptanz im Team
- ✅ „Nur Tablet"-Personen und PIN-Anmeldung (siehe oben).
- 💡 „Erster Tag" in 10 Minuten machbar – vor dem Start mit einer neuen Person ausprobieren.
- 💡 Datenschutzinfo in einfacher Sprache; klar sagen, was die App nicht tut.
- ✅ **KI-Übersetzung von Anleitungen** als eigene **Stufe 5** (Konzept 3, §1.7).

## 2. Fachliche Punkte
- 💡 **Regeln für Intervall-Aufgaben**: *nicht möglich* → bleibt fällig; *übersprungen* → nächste Fälligkeit ab heute.
- 💡 **Gleichzeitiges Abhaken** (Tablet + Handy): „bereits erledigt von Anna".
- 💡 **Minijob-Grenze**: auch gelegentliches Überschreiten prüfen (max. 2 Monate/Jahr, bis zum Doppelten; 2026: 1.206 €).
- 💡 **Weitere Beschäftigung** der Assistenz bei Einstellung abfragen (Merker; Teil der Checkliste).
- 💡 **Abgleich mit dem Abgabenbescheid** und **Rücklage** für den halbjährlichen Einzug (Stufe 3).
- 💡 **Vertretung gesucht** („Dienst offen – wer kann?") in Stufe 2.
- 💡 **Bezirk früh fragen**: Nachweis-Format, Einarbeitung abrechenbar, Aufbewahrungsfristen.

## 3. Datenschutz & Sicherheit
- ✅ PIN nur pro Person und nur im Heimnetz; Manager ohne PIN.
- ✅ Reine Web-App, kein Offline-Speicher auf privaten Geräten.
- ✅ Stempelzeiten in UTC.
- ✅ 2FA wählbar: Passkey, Authenticator-App oder **Code per E-Mail**; Wiederherstellungscodes.
- 💡 Fotos aus der Wohnung: Hinweis beim Hochladen (Grundriss, Wertsachen).
- 💡 Betrieb für andere Haushalte: AV-Vertrag, Datenschutz-Folgenabschätzung, Haftung, Lizenz.

## 4. Technik
- ✅ Live-Aktualisierung über Mercure (in FrankenPHP).
- ✅ E-Mail über SMTP-Zugang (nicht direkt vom Pi), SPF/DKIM/DMARC.
- ✅ Editor für Anleitungen (schrittbasiert, kleine Werkzeugleiste, bereinigtes HTML).
- 💡 Speicher-Kontingent pro Haushalt; Videos nachts verarbeiten.

## 5. Ideen und wo sie gelandet sind

| Idee | Stufe | Status |
|---|---|---|
| Nachrichten (Chat) | 2 | ✅ Konzept 9 |
| Benachrichtigungen in der App | 1 | ✅ Konzept 9 |
| E-Mail (Einladung, Passwort, 2FA-Code) | 1 | ✅ Konzept 1/5 |
| E-Mail-Benachrichtigungen (optional) | 2 | ✅ Konzept 9 |
| Push (optional) | 4 | ✅ Konzept 9 |
| Schlüssel & Zugänge | 3 | ✅ Konzept 10 |
| Checklisten Einstellung/Austritt | 3 | ✅ Konzept 10 |
| KI-Übersetzung von Anleitungen | 5 | ✅ Konzept 3 |
| KI-Schreibhilfe im Editor | 2 | ✅ Konzept 3 |
| Druckansichten (Notfallblatt, Wochenplan, Anleitung) | – | 💡 |
| Vertretung gesucht | 2 | 💡 |
| Rücklage & Bescheid-Abgleich | 3 | 💡 |
| Auslagen erstatten | 3 | 💡 optional |
| Grund-Offline-Modus, externe Überwachung | – | ❌ |

**Bewusst nicht**: Medikamente/Behandlungspflege, Bewerbermanagement, Leistungsbewertung, Standortverfolgung, Pflegedokumentation.

## 6. Noch offen
1. Druckansichten gewünscht?
2. Vertretung gesucht in Stufe 2?
3. Chat: Sprachnachrichten, Löschfrist 180 Tage, Assistenz untereinander?
4. Bezirk: Nachweis-Format, Einarbeitung, Aufbewahrung.

## Quellen
- Assistenz im Krankenhaus (bvkm, Dez. 2021): https://bvkm.de/wp-content/uploads/2022/09/assistenz_im_krankenhaus.pdf
- Minijob-Grenze, gelegentliches Überschreiten: https://www.deutsche-handwerks-zeitung.de/sozialversicherung-steuer-und-arbeitsrecht-das-gilt-beim-minijob/150/3098/265446
