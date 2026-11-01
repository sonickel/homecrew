# Konzept 8 – Kalender: persönlicher Zeitplan, Besuche externer Dienste, Übersicht für Admins

Stand: 01.11.2026 (nachmittags) · Status: Entwurf · Ausbaustufe 1 (Grundfunktionen inkl. Besuche und Serien), Erweiterungen 3 und 4

## 1. Ziel
- **Persönlicher Kalender** für jede Person: eigene Dienste, Urlaub, eigene Aufgaben und Termine.
- **Besuche externer Dienste** (Physio, Caritas, Pflegedienst, Handwerker …) für das ganze Team sichtbar – reine Info, niemand muss etwas tun.
- **Übersicht für Admins**: wer kommt wann, wer fällt aus, welche Termine brauchen Begleitung, was ist fällig.
- Keine doppelten Daten – der Kalender zeigt, was es in der App schon gibt.

## 2. Einträge
Geplante Dienste (abgesagt durchgestrichen, Notiz z. B. „in der Klinik") · erfasste Zeiten · Urlaub/Krank · „Manager nicht zu Hause" · Termine · **Besuche** · Aufgaben mit Datum/Zeitfenster · Gesprächspunkte · Wartungsfälligkeiten · Feiertage (Bayern) · Fristen (nur Admins, Stufe 3). Jeweils Symbol + Farbe + Text.

## 3. Besuche externer Dienste (neu)

### 3.1 Idee
Fast jeden Tag kommt jemand von außen. Das Team soll wissen, **wer heute kommt** – ohne dass jemand den Eintrag bearbeiten, bestätigen oder begleiten muss.

### 3.2 Externe Dienste (Stammdaten, Admins)
| Feld | Beispiel |
|---|---|
| Name (Organisation/Funktion, **keine Klarnamen**) | „Physiotherapie", „Caritas Haushaltshilfe", „Pflegedienst" |
| Art | Therapie · Pflege · Haushalt · Arzt (Hausbesuch) · Lieferung · Handwerk · Sonstiges |
| Telefon (dienstlich, optional) | Praxis/Büro – z. B. „falls niemand kommt" |
| **Hinweis fürs Team** | „Caritas putzt Bad und Küche – an dem Tag dort nicht putzen" · „Physio braucht Platz im Wohnzimmer, Assistenz muss nicht dabei sein" |
| Farbe | eigene Kalenderfarbe (immer zusätzlich Symbol + Text) |

### 3.3 Wiederkehrende Besuche (Serien)
- Beispiel: **Physio jeden Dienstag 10:00–11:00**, **Caritas alle 14 Tage donnerstags 13:00–15:00**.
- Regel wie bei Wartungsplänen (wöchentlich, alle 2 Wochen, bestimmte Wochentage …), Beginn, optional Ende, Pausieren (z. B. Praxis-Urlaub).
- Der Scheduler erzeugt die Einzelbesuche einige Wochen im Voraus. Einzelne Besuche lassen sich **absagen** („fällt aus – Praxis geschlossen", bleibt durchgestrichen sichtbar) oder **verschieben**; solche Änderungen bleiben erhalten, wenn die Serie später geändert wird.
- Auch einzelne Besuche ohne Serie (Handwerker, Lieferung Hilfsmittel).
- Dasselbe Serien-Prinzip gibt es für **normale Termine** (z. B. Ergotherapie freitags in der Praxis, mit Begleitung durch die Rolle Assistenz).

### 3.4 Wo erscheinen Besuche?
- **„Heute"** – eigener Block **„Heute kommen"**: „10:00 Physio · 13:00 Caritas (Putzen)" mit Hinweis fürs Team; abgesagte als „fällt aus".
- **Team-Tablet** ✅, **Kalender** aller Personen (Agenda, Woche, Monat), **Druck** „Tages-ToDo für morgen" (Zeile „Es kommen …").
- **Admins**: in der Team-Woche als eigene Zeile „Besuche"; im Block „Wer ist heute da / kommt als Nächstes".
- Keine Benachrichtigungen bei Besuchen (reine Info); wer möchte, aktiviert das Ereignis „Termin neu/geändert".

### 3.5 Unterschied Besuch ↔ Termin
| | Besuch | Termin |
|---|---|---|
| Wer handelt? | niemand – nur Info | zuständige Person/Rolle, ggf. Begleitung |
| Zuständigkeit, Begleitung, Mitbringliste | – (per Regel ausgeschlossen) | ✅ |
| Sichtbarkeit | Standard **alle**; „nur Admins" möglich (z. B. Hausbesuch Arzt) | alle/zuständig/admins/privat |
| Typisch | Physio, Caritas, Pflegedienst, Handwerker | Arzttermin außer Haus, Teambesprechung |

### 3.6 Hinweise für Admins
- Manager trägt Abwesenheit ein (Urlaub/Klinik) → „In dieser Zeit sind 3 Besuche geplant – in HomeCrew absagen?" (die Praxis bzw. die Caritas informiert man weiterhin selbst; die Telefonnummer steht direkt dabei).
- Besuch an einem Feiertag → Hinweis.

### 3.7 Rechte
- **Sehen**: alle (nach Sichtbarkeit), inkl. Team-Tablet.
- **Anlegen/Ändern/Absagen** (einzeln und als Serie): wie Termine – Admins und Angehörige; Assistenz nur mit Schalter „Termine anlegen" (Standard aus).
- **Externe Dienste pflegen**: Admins (Admin-Menü → Alltag → Externe Dienste).

### 3.8 Datenschutz
- Externe Dienste ohne Klarnamen (Organisation/Funktion), nur dienstliche Telefonnummern.
- Therapie- oder Pflegebesuche können auf Gesundheit schließen lassen – Titel bewusst neutral wählbar („Therapie") und Sichtbarkeit „nur Admins" möglich; im Kalender-Abo (Stufe 4) neutrale Titel.

## 4. Ansichten
- **Mein Kalender**: Agenda (Standard bei großer Schrift und auf dem Handy), Woche, Monat.
- **Team (nur Admins)**: Team-Woche (Zeile pro Person + Zeile „Besuche") und Team-Monat (Matrix mit Kürzeln).
- Interaktion: Admins tippen in freie Bereiche → Dienst/Termin/Besuch/Aufgabe anlegen; Assistenz markiert einen Zeitraum → Urlaub beantragen.

## 5. Sichtbarkeit (geklärt)
| Eintrag | Admins | Angehörige | Assistenz | Team-Tablet |
|---|---|---|---|---|
| Dienste | alle, mit Ist-Zeiten | – | **nur eigene** | – |
| Urlaub/Krank | alle, mit Art | – | nur eigene | – |
| Manager nicht zu Hause | mit Notiz | „nicht zu Hause" | „nicht zu Hause" | „nicht zu Hause" |
| **Besuche** | ✅ | ✅ (Sichtbarkeit alle) | ✅ (Sichtbarkeit alle) | ✅ (Sichtbarkeit alle) |
| Termine | nach Sichtbarkeit (alle/zuständig/admins/privat) | nach Sichtbarkeit | nach Sichtbarkeit | alle + Rolle Assistenz |
| Aufgaben | alle sichtbaren | eigene/zugewiesene | eigene/zugewiesene | Rolle Assistenz |
| Fristen | ✅ | – | – | – |

## 6. Hinweise für Admins
Begleitung fehlt · Dienst fällt aus (Vertretung nötig) · Urlaubsanträge überschneiden sich · Dienst an Feiertag · Besuche während Abwesenheit des Managers · ab Stufe 3: geplante Kosten vs. Monatsbudget, Minijob-Grenze. (Keine Soll-Abdeckung je Wochentag – geklärt.)

## 7. Abwesenheit des Managers
Eintragen → Team sieht „nicht zu Hause" → Aufgaben pausieren? → Dienste: finden statt (ggf. mit Notiz, z. B. „in der Klinik") oder werden abgesagt („trotzdem bezahlt?", Standard ja) → **Besuche in dem Zeitraum absagen?** Betroffene Assistenzkräfte werden benachrichtigt.

## 8. Druck
Aus dem Kalender: **Tages-ToDo für morgen** (oder einen gewählten Tag) inkl. „Es kommen …" – siehe Konzept 2, §10.

## 9. Kalender-Abo (Stufe 4)
Persönlicher, nur lesbarer ICS-Link, widerrufbar, standardmäßig neutrale Titel.

## 10. Datenbank
`visitor` (externe Dienste), `appointment_series` (Serien für Termine und Besuche, RRULE, Uhrzeit/Dauer, Pause, `generated_until`), `appointment` mit `kind` (termin/besuch), `visitor_id`, `series_id` + `occurrence_date` (eindeutig je Serie und Tag), `is_modified`, `status` (geplant/abgesagt), `cancel_note`. Regel: Besuche haben weder Begleitung noch Zuständigkeits-Rolle (CHECK).
