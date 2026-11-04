# Konzept 11 – Admin-Menü und Pflegemasken

Stand: 04.11.2026 (M7: Listen, Lieblingsartikel, Meldungen) · Status: Entwurf · App: **HomeCrew**
Antwort auf die Frage „Sind für alles Pflegemasken vorgesehen?": **Ja.** Alles, was in der Datenbank steht, kann ein Admin in der App pflegen – ohne Datenbank-Werkzeug.

## 1. Aufbau
- Menüpunkt **„Verwaltung"** nur für Admins; nur nach voller Anmeldung mit 2FA/Passkey, **nie am Tablet per PIN und nie im Team-Modus**. Am Team-Tablet gibt es den Knopf „Admin-Anmeldung" (volle Anmeldung, danach automatisch zurück in den Team-Modus).
- Jede Maske: Liste mit Suche/Filter → Bearbeiten-Formular → Speichern; Änderungen an Personen, Rechten, Geräten, Erweiterungen und Zeiten landen im Protokoll.
- Technik: Stammdaten-Masken mit EasyAdmin (schnell, einheitlich), Abläufe (Dienstplan, Zeiten, „Dienstplan übernehmen", Meldungen, Serien, Monatsabschluss) als eigene Oberfläche. Rechte immer über dieselben Voter.

## 2. Masken

| Bereich | Maske | Inhalt / Aktionen | Stufe |
|---|---|---|---|
| **Personen** | Personen | anlegen (Rolle, Admin-Recht, Benutzername, Anzeigename, Farbe, Eintritt), Zugang: **keiner (nur Team-Tablet)**, Start-PIN setzen, **PIN-Anmeldung an/aus**, Start-Passwort, Einladung (QR, per E-Mail nur wenn E-Mail eingerichtet), Zugangs-Kärtchen drucken; 2FA/PIN/Passwort zurücksetzen; Schalter (wiederkehrende Aufgaben, Anleitungen veröffentlichen, Termine anlegen, KI); sperren/Austritt; **den Manager bearbeitet nur der Manager selbst**, den eigenen Zugang jede Person unter „Mein Konto"; Schalter erscheinen mit ihrer Funktion; Personen werden nicht gelöscht | 1 |
| | Geräte | Tablet koppeln (Name → Kopplungscode als QR oder zum Abtippen, 10 Minuten, einmal), umbenennen, **Modus (Team · Persönlich · Beides)**, **Bildschirmschoner** (Minuten), Schriftgröße am Gerät, zuletzt aktiv, entkoppeln (sofort wirkungslos, Geräte werden nicht gelöscht) | 1 |
| **Einstellungen** | Allgemein | Haushaltsname, Zeitzone, Feiertagsregion, Auto-Sperre Tablet, PIN nur im Heimnetz, Ansichtszeitraum eigener Zeiten (3 Monate), max. Dienstdauer, Videolänge, Freigabe-Modus für Anleitungen | 1 |
| | Benachrichtigungen | Uhrzeit der Tageszusammenfassung (18:00), Push erlauben (Stufe 4); Standards je Rolle ansehen | 1 |
| | Nachrichten | **Sprachnachrichten an/aus**, Assistenz untereinander an/aus | 2 |
| | Löschfristen | Übergaben, Erledigungen, **Meldungen (365 Tage)**, Nachrichten (365 Tage), Benachrichtigungen, KI, Protokoll, Personaldaten – jede Frist erscheint erst mit ihrer Funktion (Nachrichten/KI Stufe 2, KI nur wenn eingerichtet; Personaldaten Stufe 3) | 1 |
| **Erweiterungen** | **Übersicht** | Kacheln mit Status: KI · Spracherkennung · E-Mail · Push (Stufe 4) – jeweils „nicht eingerichtet" / „ok" / „Fehler" | 1 |
| | **KI** | API-Schlüssel hinterlegen (danach nur „••••a1b2"), Modell, **Verbindung testen**, Hauptschalter, Funktionen einzeln (Assistent · Schreibhilfe · Übersetzung · am Team-Tablet), Monatsbudget, Verbrauch dieses Monats – Details Konzept 7 §2 | 1 (Seite), 2 (Funktionen) |
| | **Spracherkennung** | lokal (whisper.cpp) / aus, Status des Dienstes, Testaufnahme | 2 |
| | **E-Mail** | SMTP-Server, Port, Verschlüsselung (STARTTLS/SMTPS), Benutzer, Passwort (verschlüsselt, danach nicht mehr sichtbar), Absenderadresse/-name, **Testmail senden**, an/aus | 1 |
| | Push | an/aus; Schlüssel erzeugt die App selbst | 4 |
| **Alltag** | **Meldungen** | Log aller Meldungen (Filter offen/abgeschlossen/alle, Art, Zeitraum; Hilfsmittel ab Stufe 2), weitergeben (Liste/Aufgabe für Person/Rolle), schließen, verwerfen, Verlauf; wer gemeldet hat (Person/„Team-Tablet"); „Was fehlt oft?" – eigene Seite `/meldungen`, der Menüeintrag führt dorthin (M7) | 1 |
| | Dienstplan | feste Tage je Person, Einzeldienste, Absagen („trotzdem bezahlt?") | 1 |
| | **Externe Dienste** | Physio, Caritas, Pflegedienst …: Name (ohne Klarnamen), Art, dienstliche Telefonnummer, **Hinweis fürs Team**, Farbe, aktiv | 1 |
| | **Termine & Besuche (Serien)** | Serie anlegen (Besuch oder Termin, Wochentage/Rhythmus, Uhrzeit, Dauer, Beginn/Ende, Pause, Sichtbarkeit; bei Terminen Zuständigkeit/Begleitung), einzelne Vorkommen absagen/verschieben, Serie beenden | 1 |
| | Wartungspläne / wiederkehrende Aufgaben | anlegen, Zuständigkeit, Sichtbarkeit, Pausieren, Fotos/Videos, **Vorschlag bei „nicht möglich"** (Liste + Artikel); Verlauf mit Person bzw. „Team-Tablet" – als eigene Seiten unter `/routinen` (auch für Personen mit Schalter, die keine Verwaltung haben; Menüeintrag führt dorthin, M6) | 1 |
| | Listen | Listen anlegen (Name, zuständige Rolle/Person – nicht beides), aktiv/ausgeschaltet (nie löschen), Reihenfolge; **Lieblingsartikel** (Name, Liste oder alle, Menge, Link nur `https://`, Reihenfolge) – zwei EasyAdmin-Masken (M7) | 1 |
| | Abwesenheit des Managers | Zeitraum, Aufgaben pausieren, Dienste, Besuche absagen | 1 |
| **Wissen** | Anleitungen | Freigaben (auch Vorschläge vom Team-Tablet), Archiv, „nicht übersetzen" | 1 |
| | Wer kennt was | Übersicht, Einweisung bestätigen | 1 |
| | Notfall-Infos | Kontakte & Nummern (Kategorie, Name, Beziehung, Telefon + zweite Nummer, wann anrufen, Sichtbarkeit alle/nur Admins, Sperrbildschirm – wirkt nur bei „alle", Reihenfolge); Ort-Verknüpfung mit den Orten (M12) | 1 |
| | Orte | inkl. Barrierefreiheit | 1 |
| | Profil des Managers | Einträge und Sichtbarkeit (Team · privat) – am Team-Tablet erscheint nur „Team" | 1 |
| | Hilfsmittel | Bauteile, Sollwerte, Dokumente | 2 |
| **Verwaltung** | Arbeitszeiten | alle Einträge, **„Dienstplan übernehmen"** (Tag/Woche/Monat, Vorschau), korrigieren/nachtragen (mit Begründung), Verlauf, **Stundenübersicht pro Person drucken/Excel** | 1 (Erfassung, Übernahme), 3 (Monatsabschluss) |
| | Urlaub & Krankheit | Anträge genehmigen, Urlaub/Krankheit für Personen ohne Zugang eintragen | 1 |
| | Personaldaten | Stundenlohn, RV/KV, Arbeitstage, Urlaubsanspruch | 3 |
| | Bewilligung | Zeitraum, Monatsbudget | 3 |
| | Monatsabschluss & Exporte | prüfen, abschließen, Excel | 3 |
| | Sätze & Grenzen | Minijob-Grenze, Mindestlohn, Abgabensätze | 3 |
| | Checklisten | Vorlagen pflegen, laufende Checklisten | 3 |
| **System** | Protokoll | sensible Vorgänge ansehen (Person oder Gerät) | 1 |
| | Export | alle Daten als ZIP (Auskunft, Umzug) – ohne Schlüssel und Passwörter | 3 |

**Grundsatz für alle Erweiterungen**: nicht eingerichtet oder Fehler → die zugehörigen Funktionen sind **unsichtbar** (nicht nur ausgegraut). Beispiele:
- ohne KI-Schlüssel: keine Assistent-Leiste, kein Mikrofon, keine Schreibhilfe, kein Übersetzen;
- ohne E-Mail: keine Einladung per E-Mail, kein „Passwort vergessen" per E-Mail (Admins setzen zurück), kein E-Mail-Code als 2FA-Methode, keine E-Mail-Benachrichtigungen;
- ohne Spracherkennung: kein Mikrofon, Textfeld bleibt.

Schlüssel und Passwörter werden verschlüsselt gespeichert, nie wieder angezeigt; Ändern/Löschen nur nach erneuter 2FA-Bestätigung; optional per Umgebungsvariable vorgegeben (dann gesperrt).

**Persönlich (für alle, nicht im Admin-Menü):** „Mein Konto → Benachrichtigungen" – pro Ereignis App · E-Mail · Push, E-Mail aus/sofort/täglich, „Titel in E-Mails zeigen", Ruhezeiten (Konzept 13 §7). E-Mail-Optionen nur sichtbar, wenn E-Mail eingerichtet ist.

Einzelne Termine und Besuche legen Admins direkt im Kalender an (Tipp in einen freien Bereich).

## 3. Kleine Helfer
- **Zugangs-Kärtchen** drucken (Name, Benutzername, Start-PIN/-Passwort, Hinweis zur ersten Anmeldung).
- **Prüfliste vor dem Start**: 2FA-Pflicht für Admins eingeschaltet (`ADMIN_2FA_REQUIRED=1`), Manager mit 2FA (Passkey oder Authenticator-App – geht ohne E-Mail), E-Mail eingerichtet und Testmail ok, E-Mail-Benachrichtigung für Meldungen eingerichtet, Tablet gekoppelt (Modus Team), Notfall-Infos gepflegt, Profil-Einträge auf richtige Sichtbarkeit geprüft, Dienstplan angelegt, regelmäßige Besuche (Physio, Caritas …) als Serien angelegt; optional KI-Schlüssel hinterlegt.
- **Erinnerung Monatsende**: „Dienstplan für Oktober übernehmen und prüfen" auf „Heute" der Admins.
