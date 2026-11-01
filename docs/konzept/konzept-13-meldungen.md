# Konzept 13 – Meldungen („nicht möglich", leer, kaputt) und Benachrichtigungsregeln

Stand: 01.11.2026 (nachmittags) · Status: Entwurf · **Ausbaustufe 1** (Messwert-Meldungen und KI ab Stufe 2, Push ab Stufe 4)

## 1. Ziel
Der Manager und die anderen Admins bekommen **sicher mit, wenn etwas nicht geht oder fehlt** – auch wenn es am Team-Tablet gemeldet wurde und niemand angemeldet war. Jede Meldung
1. landet im **Meldungs-Log**,
2. löst eine **Benachrichtigung** aus (Glocke immer, E-Mail optional, Push später),
3. hat einen **Status**, der sich oft von selbst schließt (Seife abgehakt → Meldung erledigt).

## 2. Wie entsteht eine Meldung?

| Weg | Beispiel | Stufe |
|---|---|---|
| „Nicht möglich" bei einer Aufgabe | „Seifenspender auffüllen" → „Seife leer" | 1 |
| Knopf **„Melden"** (groß auf „Heute", auch am Team-Tablet) | „Glühbirne Bad kaputt", „Ladegerät lädt nicht", „Waschmittel fast leer" | 1 |
| Messwert außerhalb des Sollwerts | Reifendruck 4,2 statt 6,0 bar | 2 (automatisch) |
| KI-Assistent | „Die Seife ist leer" → Bestätigungskarte | 2 |

**Melden-Dialog** (wenige Tipps, große Knöpfe):
1. **Was ist los?** leer · kaputt · fehlt · sonstiges
2. **Was genau?** kurzer Titel – Vorschläge aus Lieblingsartikeln, Hilfsmitteln und offenen Meldungen
3. optional: Foto, Notiz, **dringend**, Bezug zu Hilfsmittel/Ort, „nur für Admins sichtbar"
4. **Was soll passieren?** (optional) auf eine Liste … · Aufgabe für Person/Rolle … · nur melden

**Keine Doppelmeldungen**: Schon beim Tippen erscheinen offene Meldungen mit ähnlichem Titel („Seife leer – gemeldet gestern"). Ein Tipp auf **„Auch bemerkt"** zählt mit, statt eine zweite Meldung anzulegen.

## 3. Vorschlag aus dem Wartungsplan
Admins können an einem Wartungsplan hinterlegen: *„Wenn nicht möglich: auf Liste ‚Besorgt die Familie', Artikel ‚Flüssigseife'"*.
Die Assistenz tippt nur „nicht möglich" → Vorschlag ist vorausgefüllt → **ein Tipp zum Bestätigen**. Ändern geht trotzdem.

## 4. Status und automatischer Abschluss
**offen** → **in Arbeit** (Folgeaktion angelegt) → **erledigt** oder **verworfen**

| Folgeaktion | Wird erledigt, wenn … |
|---|---|
| Listeneintrag | der Eintrag abgehakt ist (automatisch) |
| Aufgabe für Person/Rolle | die Aufgabe erledigt ist (automatisch) |
| Übergabe / nur melden | ein Admin sie schließt (Notiz optional) |

Admins können jede Meldung jederzeit **weitergeben** (Liste, Aufgabe, Person/Rolle), **schließen** oder **verwerfen**. Offene Meldungen zeigen „seit 3 Tagen offen".

## 5. Wo sieht der Manager das?
- **Glocke** + „Neu seit deinem letzten Besuch" – live, sobald gemeldet wurde.
- **„Heute" der Admins**: Kachel ganz oben **„Meldungen: 2 offen"**; dringende mit Warnsymbol und Text (nie nur Farbe).
- **E-Mail** (optional, **schon Stufe 1**, weil der Mailversand für 2FA ohnehin da ist): sofort oder als **Tageszusammenfassung um 18 Uhr** (einstellbar). Dringende Meldungen gehen immer sofort raus (Ruhezeiten gelten trotzdem – echte Notfälle: anrufen bzw. 112). Inhalt standardmäßig nur „Neue Meldung in HomeCrew" + Link; pro Person einschaltbar: **mit Titel** („Seife leer").
- **Push** (Stufe 4, optional).
- Wer für die Ziel-Liste oder -Aufgabe zuständig ist (z. B. die Schwester für „Besorgt die Familie"), bekommt zusätzlich „Neuer Eintrag auf deiner Liste" bzw. „Aufgabe für dich".

## 6. Meldungs-Log
**Seite „Meldungen"** (Menü, für Admins vollständig):
- Liste mit Filter: offen / erledigt / alle · Art · Zeitraum · Hilfsmittel/Ort
- je Meldung: Datum/Uhrzeit, Titel, Art, Foto, Notiz, „auch bemerkt" (Anzahl), **wer** (Person oder „Team-Tablet" – nur für Admins), Folgeaktion mit Link, Statusverlauf, wer/was sie geschlossen hat
- kleine Auswertung „Was fehlt oft?" (z. B. Seife alle 3 Wochen → Vorrat anlegen oder Lieblingsartikel)
- Export als Excel (Stufe 3)

**Assistenz und Team-Tablet** sehen die **offenen Meldungen** (Titel, Datum, Status – ohne Person), damit niemand doppelt meldet und jeder weiß: „ist schon gemeldet". Meldungen mit „nur für Admins" sehen nur Admins.

## 7. Benachrichtigungsregeln (für alle Ereignisse)
Unter **„Mein Konto → Benachrichtigungen"** stellt jede Person pro Ereignis ein: **App · E-Mail · Push**. E-Mail wirkt nur, wenn die E-Mail-Benachrichtigung (aus/sofort/täglich) eingeschaltet ist; Push nur, wenn der Admin Push erlaubt und die Person es eingeschaltet hat.

Standards je Rolle (gespeichert werden nur Abweichungen):

| Ereignis | Admins | Angehörige | Assistenz |
|---|---|---|---|
| **Neue Meldung** | App | – | – |
| Meldung erledigt | aus | – | – |
| Frage an Admins (Übergabebuch) | App | – | – |
| Wichtige Übergabe | App | App | App |
| Neue Nachricht | App | App | App |
| Dienst neu/verschoben/abgesagt | – | – | App |
| Urlaub: Antrag / Entscheidung | App (Antrag) | – | App (Entscheidung) |
| Aufgabe zugewiesen/weitergegeben | App | App | App |
| Gesprächspunkt fällig | App | – | – |
| Termin neu/geändert (eigene) | App | App | App |
| Fristen | App | – | – |
| Checklisten | App | – | – |
| Anleitung vorgeschlagen / „stimmt nicht mehr" | App | – | – |
| Wartung überfällig | App | – | – |

Empfehlung für dich als Manager: **„Neue Meldung" zusätzlich per E-Mail, sofort, mit Titel**.

## 8. Datenschutz
- Am Team-Tablet entsteht keine Person, nur das Gerät als Absender.
- Wer gemeldet hat, sehen nur Admins; die Person wird nach 90 Tagen entfernt (wie bei Erledigungen).
- Abgeschlossene Meldungen werden nach **365 Tagen** gelöscht (einstellbar); offene bleiben, bis sie geschlossen sind.
- E-Mails standardmäßig ohne Inhalt; Hinweis im Dialog: „Bitte keine Gesundheitsangaben in Meldungen" (Titel landen sonst evtl. beim Mail-Anbieter).
- Fotos: Hinweis „keine Personen fotografieren".

## 9. Datenbank
- `issue_report` – Meldung: Art, Titel, Notiz, dringend, Sichtbarkeit (alle/admins), Bezug Aufgabe/Hilfsmittel/Ort, Absender Person **oder** Gerät, „auch bemerkt", Status, Folgeaktion (Listeneintrag/Aufgabe/Übergabe), Abschluss, Löschdatum.
- `media.issue_report_id` – Foto zur Meldung.
- `task_template.unable_list_id` / `unable_item_name` – Vorschlag bei „nicht möglich".
- `shopping_item.source = 'meldung'`, `notification.kind = 'meldung'`.
- `notification_preference` – Regeln je Person und Ereignis (App/E-Mail/Push).
- `app_user.notify_mail_details` (Titel in E-Mails), `app_setting.retention_report_days` (365), `app_setting.daily_digest_time` (18:00).
- Entfallen: `task_completion.follow_up` – die Folgeaktion steht jetzt in der Meldung.
