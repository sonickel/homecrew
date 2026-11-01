# Konzept 7 – KI-Assistent mit Sprache und Text

Stand: 01.11.2026 (nachmittags) · Status: Entwurf · Ausbaustufe 2 (Einrichtungsseite „Erweiterungen" schon in Stufe 1)
Entscheidungen: **Claude Haiku 5.5** als Sprachmodell · **whisper.cpp lokal auf dem Pi** für die Spracherkennung (erst testen) · **KI ist eine Erweiterung: ohne hinterlegten, geprüften Schlüssel ist keine KI-Funktion sichtbar**

## 1. Ziel
Alle Nutzer bedienen die App per Sprache oder Text – nur mit den Rechten ihrer Rolle. Beispiele: „Trag mir Freitag 10 Uhr Sanitätshaus ein, nur für mich" · „Gib die Aufgabe Waschmittel an Anna weiter" · „Wann ist mein nächster Dienst?" · „Wann kommt die Physio?" · „Die Physio fällt nächsten Dienstag aus" · „Bettwäsche ist gewechselt" · „Setz Milch auf die Einkaufsliste" · „Die Seife ist leer" · „Wann wurden die Reifen zuletzt aufgepumpt?"

## 2. Einrichtung im Admin-Menü („Erweiterungen → KI")

### 2.1 Was der Admin einstellt
| Einstellung | Inhalt |
|---|---|
| **API-Schlüssel** | einfügen → wird sofort verschlüsselt gespeichert; danach nur noch „••••a1b2" sichtbar; ändern/löschen nur nach erneuter 2FA-Bestätigung; Eintrag im Protokoll |
| Anbieter / Modell | Anthropic · Claude Haiku 5.5 (vorbelegt; Modell änderbar, Anbieter später erweiterbar) |
| **Verbindung testen** | kleine Testanfrage → Status **ok** / **Fehler** (mit Meldung, z. B. „Schlüssel ungültig", „Guthaben aufgebraucht") |
| **Hauptschalter KI** | nur einschaltbar, wenn ein Schlüssel hinterlegt ist (Regel auch in der Datenbank) |
| Funktionen einzeln | KI-Assistent · KI-Schreibhilfe (Anleitungen) · Übersetzung (Stufe 5) · KI am Team-Tablet |
| Monatsbudget | z. B. 5 € – Hinweis an Admins bei 80 %, bei 100 % pausiert die KI bis Monatsende |
| Spracherkennung | lokal (whisper.cpp) / aus – Status des Dienstes wird angezeigt |
| Wer darf | pro Person Schalter „KI nutzen" (Standard an) und „per Sprache bestätigen" |

### 2.2 Sichtbarkeit: nichts einrichten = nichts sehen
Eine KI-Funktion erscheint **nur, wenn alles zutrifft**:
1. Hauptschalter an **und** Schlüssel hinterlegt **und** letzter Verbindungstest ok,
2. die einzelne Funktion ist eingeschaltet,
3. das Monatsbudget ist nicht aufgebraucht,
4. die Person darf KI nutzen (am Team-Tablet: „KI am Team-Tablet" an).

Sonst gibt es **keine** Assistent-Leiste, keinen Mikrofon-Knopf, keine KI-Kachel, keinen Schreibhilfe- oder Übersetzen-Knopf – die App sieht aus, als gäbe es die KI nicht. Technisch: ein zentraler Dienst `FeatureAvailability` (Twig-Funktion + Voter), damit keine Stelle die Prüfung vergisst.

- **Mikrofon** zusätzlich nur, wenn die lokale Spracherkennung erreichbar ist (Gesundheitscheck des whisper-Dienstes); sonst nur Textfeld.
- **Vorlesen** braucht keine KI (Sprachausgabe des Geräts) und bleibt immer verfügbar.
- Fällt der Schlüssel später aus (Fehler beim Aufruf, z. B. ungültig): Status → Fehler, KI-Funktionen verschwinden, Admins bekommen einen Hinweis in der Glocke.

### 2.3 Wo der Schlüssel liegt
- In der Datenbank nur als **libsodium-Chiffrat**; der Entschlüsselungs-Schlüssel liegt außerhalb der Datenbank (Symfony-Secret / Docker-Secret).
- Der API-Schlüssel geht **nie an den Browser**, nie ins Log, nie in Exporte.
- Alternative für Technik-Affine: Schlüssel als Umgebungsvariable vorgeben → das Feld zeigt „vom Server vorgegeben" und ist gesperrt.
- Backup: Ohne das Secret ist das Chiffrat nutzlos – nach einer Wiederherstellung ohne Secret den Schlüssel einfach neu eintragen.

### 2.4 Datenschutz beim Einschalten
Beim ersten Einschalten zeigt die App: „Anthropic wird Auftragsverarbeiter – Datenschutzinfo für das Team wird ergänzt". Die Version der Datenschutzinfo steigt; alle sehen beim nächsten Öffnen den Hinweis (`privacy_notice_version`).

## 3. Bedienung
- Assistent-Leiste auf jeder Seite (wenn verfügbar): Mikrofon-Knopf + Textfeld. Kein Dauerzuhören, kein Aktivierungswort.
- Erkannter Text wird angezeigt und kann korrigiert werden.
- Antworten als Text, optional vorgelesen (Sprachausgabe des Geräts).
- **Schreib-Aktionen nur über eine Bestätigungskarte** („Eintragen" / „Ändern" / „Verwerfen"); optional pro Person per gesprochenem „Ja". Ausgeführt wird vom Server.

## 4. Werkzeuge und Rechte
| Werkzeug | Admins | Angehörige | Assistenz | Team-Tablet |
|---|---|---|---|---|
| Kalender/Dienstplan lesen | alles | eigener Kalender | **nur eigene Dienste** | Termine, keine Dienste |
| **Besuche abfragen** („Wer kommt heute?", „Wann kommt die Physio?") | ✅ | ✅ | ✅ | ✅ |
| Aufgaben lesen, „zuletzt erledigt" | ✅ | sichtbare | sichtbare | Rolle Assistenz |
| Einkaufs-/Besorgen-Listen lesen + hinzufügen | ✅ | ✅ | ✅ | ✅ |
| Anleitung / Ort / Notfallnummer suchen (Titel + Link) | ✅ | ✅ | ✅ | ✅ |
| **Meldung anlegen** („Die Seife ist leer") | ✅ | ✅ | ✅ | ✅ |
| Offene Meldungen abfragen | ✅ | ✅ | ✅ | ✅ |
| Meldung weitergeben/schließen | ✅ | – | – | – |
| Termin/Besuch anlegen, verschieben, absagen (inkl. „nur für mich") | ✅ | ✅ | ⚙️ (Standard nein) | – |
| Aufgabe anlegen | ✅ | ✅ | ✅ | ✅ |
| Aufgabe weitergeben | ✅ | eigene | – | – |
| Aufgabe erledigen (inkl. Messwerte, „nicht möglich") | ✅ | ✅ | ✅ | ✅ |
| Gesprächspunkt anlegen | ✅ | – | – | – |
| Übergabe schreiben | ✅ | ✅ | ✅ | ✅ |
| Nachricht senden | ✅ | ✅ | ✅ (nicht an andere Assistenz) | – |
| Urlaub beantragen / krank melden (nur Zeitraum) | – | – | ✅ (eigene) | – |
| Dienst absagen, Urlaub genehmigen | ✅ (ab Stufe 3) | – | – | – |

**Nie über die KI**: Personal- und Lohndaten, Exporte, Personen/Rechte, Gesundheitsangaben im Profil, Arbeitszeiten ändern, Löschen, Serien anlegen oder löschen, Einstellungen/Erweiterungen.
Durchsetzung dreifach: nur erlaubte Werkzeuge ans Modell · erneute Prüfung mit denselben Votern bei der Ausführung · Lese-Werkzeuge liefern nur Sichtbares.

## 5. Sprachmodell: Claude Haiku 5.5 (geklärt)
- Preis laut Recherche: 0,10 $ / 0,50 $ je 1 Mio. Eingabe-/Ausgabe-Token (Anfragen bis 100.000 Token). Bei ca. 600 Anfragen im Monat **unter 1 € pro Monat**.
- **Wichtig: Das Claude-Pro-Abo deckt die API nicht ab.** Pro gilt für die Chat-Oberfläche; die API wird separat über die **Claude Console** abgerechnet (eigenes Konto, API-Schlüssel, Bezahlung nach Verbrauch, Vorauszahlung möglich). Nur Max- und Team-Pläne enthalten API-Guthaben.
- Für die App ohnehin richtig: Die API läuft unter den gewerblichen Bedingungen – Inhalte werden laut Dokumentation standardmäßig nicht gespeichert und nicht zum Training verwendet; der Auftragsverarbeitungsvertrag ist Teil der Bedingungen; Übermittlung in die USA über das Data Privacy Framework.
- In der Console ein **Ausgabenlimit** setzen; zusätzlich Monatsbudget in der App (§2.1).
- Anbindung über einen austauschbaren Adapter (offizielles PHP-SDK oder HTTP-Client); Wechsel z. B. zu einem EU-Anbieter bleibt möglich.

## 6. Datenschutz
- **Pseudonymisierung** vor dem Senden: Namen von Personen, Orten, Kontakten und externen Diensten werden auf dem Pi durch Platzhalter ersetzt und danach zurückübersetzt.
- Nur minimaler Kontext (Datum, Rolle, Platzhalterliste, Werkzeuge); keine Profil-, Gesundheits- oder Lohndaten, keine Anleitungs- oder Nachrichtentexte.
- **Audio verlässt den Pi nicht** (lokale Spracherkennung); es wird nicht gespeichert.
- Gesprächsverlauf 7 Tage; jede ausgeführte Aktion im Protokoll, als „per KI angelegt" erkennbar.
- Grenze: Frei gesprochene Sätze können Gesundheitsangaben enthalten („Termin beim Neurologen") – deshalb Hinweis in der Datenschutzinfo.

## 7. Spracherkennung: whisper.cpp lokal (geklärt: ausprobieren)
- Aufnahme im Browser → Upload an den Pi → whisper.cpp (deutsch, `-l de`) → Text → Audio löschen.
- Erwartung laut Recherche (Pi 5, CPU): Modell *base* etwa Echtzeit bis 2× schneller; *small* 0,4–0,6× Echtzeit (ein 5-Sekunden-Befehl ≈ 10 s). In der Proxmox-VM teilt sich whisper die CPU.
- **Test zu Beginn von Stufe 2**: 20–30 typische deutsche Befehle mit *base*, *small* und *small* quantisiert; Kriterien Wartezeit und Erkennungsqualität (Namen, Uhrzeiten).
- Rückfall, falls zu langsam: Diktat der Handy-Tastatur; als Option später ein EU-Dienst.

## 8. Datenbank
`app_setting`: `ai_enabled`, `ai_provider`, `ai_model`, `ai_api_key_enc`, `ai_api_key_hint`, `ai_key_status` (fehlt/ungeprueft/ok/fehler), `ai_key_checked_at`, `ai_key_error`, `ai_feature_assistant`, `ai_feature_writing`, `ai_feature_translation`, `ai_team_device`, `ai_monthly_budget_eur`, `speech_recognition`. CHECKs: KI nur mit Schlüssel einschaltbar; Status „fehlt" genau dann, wenn kein Schlüssel. Weiter `ai_conversation`, `ai_message`, `ai_action`, `ai_usage_month`; `app_user.can_use_ai`, `app_user.ai_voice_confirm`. Meldungen/Termine per KI: `created_via = 'ki'`.
