# Konzept 12 – Team-Tablet (Gerätemodus ohne Personenanmeldung)

Stand: 01.11.2026 (nachmittags) · Status: Entwurf · **Ausbaustufe 1 – empfohlener Start**

## 1. Idee
Das Assistenz-Tablet wird einmal von einem Admin gekoppelt und läuft dann im **Team-Modus**: Es zeigt alles, was für die **Rolle Assistenz** bestimmt ist – Aufgaben, Wartungen, Termine, Besuche, Listen, Anleitungen, Notfall-Infos –, **ohne dass sich jemand anmeldet**. Das Tablet kennt keine Person.

Vorteile für den Start:
- Keine Einstiegshürde: neue Assistenz kommt, Tablet ist an, los geht's.
- Datensparsam: Es entsteht nicht, *wer* was erledigt hat.
- Arbeitszeiten pflegt der Admin (§4).

## 2. Modi pro Tablet (Admin-Menü → Geräte)
| Modus | Startbildschirm | Wann |
|---|---|---|
| **Team** (Standard) | direkt die Team-Ansicht „Heute" | Start, einfach |
| **Persönlich** | Namenskacheln + PIN | wenn Personen ihre Zeiten selbst erfassen sollen |
| **Beides** | Team-Ansicht + Knopf „Persönlich anmelden" (PIN) | später, gemischt |

Wechsel jederzeit; es geht nichts verloren.

## 3. Was das Team-Tablet zeigt und kann

| Bereich | Team-Tablet |
|---|---|
| **Heute** | Datum, wichtige Übergaben, „Neu seit gestern", **„Heute kommen"** (Physio, Caritas … mit Hinweis fürs Team), Termine für alle bzw. die Rolle Assistenz, Aufgaben für die Rolle Assistenz / „wer da ist" / alle |
| Aufgaben erledigen | ✅ inkl. Checkliste, Messwerten, Nachweisfoto; „nicht möglich" → **Meldung** mit Folgeaktion (Konzept 13) |
| **Melden** | ✅ großer Knopf: leer · kaputt · fehlt · sonstiges, mit Foto; „Auch bemerkt" bei bereits Gemeldetem |
| Offene Meldungen | ✅ Titel, Datum, Status (ohne Person) – damit nichts doppelt gemeldet wird |
| Routinen & Wartung | ✅ (zuletzt erledigt: nur Datum) |
| Listen | ✅ lesen, ergänzen, abhaken |
| Übergabebuch | ✅ lesen und schreiben (Absender: „Team-Tablet") |
| Wissen | ✅ lesen, „Stimmt nicht mehr" melden, **Anleitung vorschlagen** (immer als Entwurf → Admin gibt frei) |
| Notfall-Infos, Orte | ✅ |
| Profil | ✅ nur Einträge mit Sichtbarkeit „Team" (z. B. Unverträglichkeiten) |
| Kalender | ✅ Termine, **Besuche** und Aufgaben – **keine Dienste** |
| Druckansichten | ✅ Tages-ToDo (inkl. „Es kommen …"), Listen, Rezepte |
| KI-Assistent (ab Stufe 2) | ✅ mit Team-Werkzeugen: „Was steht heute an?", „Wann kommt die Physio?", „Setz Milch auf die Liste", „Die Seife ist leer", „Wie pumpe ich die Reifen auf?", „Bettwäsche ist gewechselt" |
| Dienstplan, Arbeitszeiten, Urlaub | – |
| Nachrichten | – (persönlich) |
| Private Termine/Aufgaben, Gesprächspunkte, Admin-Inhalte, Meldungen und Besuche „nur für Admins" | – |
| Verwaltung | – (nur nach voller Admin-Anmeldung, siehe §5) |

## 4. Arbeitszeiten im Team-Modus
Ohne persönliche Anmeldung erfasst niemand am Tablet seine Zeit – **der Admin pflegt die Arbeitszeiten**:
- **„Dienstplan übernehmen"**: Für einen Tag, eine Woche oder einen Monat werden alle geplanten Dienste mit einem Klick als Arbeitszeit übernommen; Abweichungen (später gekommen, länger geblieben, ausgefallen) passt der Admin an.
- Jeder Eintrag ist als „aus Dienstplan übernommen" markiert und steht im Änderungsverlauf.
- Danach Monatsabschluss wie gewohnt (Konzept 4).
- Für Assistenzkräfte ohne eigenen Zugang kann der Admin eine **Stundenübersicht** pro Person drucken bzw. als Excel weitergeben.

## 5. Sicherheit & Datenschutz
- Funktioniert nur auf **gekoppelten Tablets** und **nur im Heimnetz** – nie über das Internet.
- Zeigt nur, was für die Rolle Assistenz sichtbar ist; nie Admin-, private, Personal- oder Chat-Inhalte.
- Jeder in der Wohnung kann den Bildschirm sehen (auch Besuch, z. B. Physio oder Caritas) → Profil-Einträge bewusst auf „Team" oder „privat" stellen.
- **Bildschirmschoner** nach 5 Minuten (einstellbar). **Kein eigener Entsperr-Code in HomeCrew** (geklärt) – bei Bedarf schützt die Bildschirmsperre/PIN von Android.
- **Notfall-Button** immer sichtbar.
- Tablet verloren → im Admin-Menü **entkoppeln**, sofort wirkungslos.
- **Admin am Tablet**: Knopf „Admin-Anmeldung" → volle Anmeldung mit 2FA/Passkey → nach Abmelden oder 5 Minuten Inaktivität zurück in den Team-Modus.
- Erledigungen, Meldungen, Übergaben, Listeneinträge und Fotos tragen nur das **Gerät** als Absender, keine Person.

## 6. „Zuletzt erledigt" (geklärt)
- Für alle: **nur das Datum**.
- **Admins** sehen zusätzlich, wer es war (bei persönlicher Anmeldung, bis zur Anonymisierung nach 90 Tagen) bzw. „am Team-Tablet".

## 7. Später: persönliche Anmeldung dazunehmen
Bekommen Personen eine PIN, stellt der Admin das Tablet auf „Beides": Team-Ansicht bleibt, wer will, meldet sich zusätzlich an (eigene Dienste, Zeiten, Nachrichten). Die Arbeitszeit kann dann wieder selbst erfasst werden.

## 8. Datenbank
`device.mode` (team / persoenlich / beides), `device.dim_after_seconds`; `device_id` an Erledigungen, Meldungen (`issue_report.created_by_device_id`), Übergaben, Listeneinträgen, Medien und im Protokoll; `time_entry.start_source = 'dienstplan'` für übernommene Dienste. (`device.unlock_code_hash` entfallen.)
