---
name: meilenstein
description: Ablauf für jeden Umsetzungsschritt in HomeCrew – von Plan über Umsetzung, Qualitätsprüfung und Review bis zum Commit. Verwenden, wenn ein Meilenstein aus docs/plan-stufe-1.md oder eine neue Funktion umgesetzt werden soll.
---

# Meilenstein umsetzen

## 1. Verstehen (Plan-Modus, noch keine Änderungen)
1. `docs/plan-stufe-1.md` öffnen, den Meilenstein und seine Akzeptanzkriterien lesen.
2. Die betroffenen Konzepte in `docs/konzept/` lesen (Index in `kontext.md`) und die passenden Tabellen in `docs/datenbank/schema.sql`.
3. Unklarheiten oder Widersprüche zwischen Konzept, Schema und Plan **vor** dem Planen als Fragen auflisten – nicht raten.
4. Plan schreiben:
   - Ziel in 1–2 Sätzen, Abgrenzung (was ausdrücklich nicht).
   - Betroffene Tabellen/Entities, Migrationen, Services, Voter, Controller, Templates, Tests.
   - Reihenfolge in kleinen Schritten, jeder einzeln lauffähig.
   - Wie geprüft wird (Tests, manuelle Prüfung im Browser mit konkreter URL).
5. Plan zur Bestätigung vorlegen. Erst nach „ok" umsetzen.

## 2. Umsetzen
- Schritt für Schritt nach Plan; nach jedem Schritt kurz prüfen (Test oder Aufruf), nicht alles am Ende.
- Skills beachten: `symfony-qualitaet` (Code), `homecrew-ui` (Oberfläche), `datenbank-migrationen` (Schema), `datenschutz-sicherheit` (Rechte, Daten).
- Keine Nebenbaustellen: Auffälligkeiten außerhalb des Meilensteins notieren (Abschnitt „Später" in der Plan-Datei), nicht nebenbei umbauen.
- Läuft etwas grundsätzlich anders als geplant: anhalten, neu planen, Rückfrage – nicht im Code improvisieren.

## 3. Prüfen
1. `composer qa` muss grün sein (CS-Fixer, PHPStan, Lint, PHPUnit). Bei neuen oder geänderten Tabellen zusätzlich `composer schema:check` (alle migrierten Tabellen identisch zur Referenz).
2. Neue Logik hat Tests: Voter und Services als Unit-Tests, Seiten als funktionale Tests (mindestens: erlaubte Rolle sieht die Seite, nicht erlaubte bekommt 403/404, Team-Tablet sieht nur Erlaubtes).
3. Im Browser prüfen (Tablet-Breite ~1024 px und Handy ~390 px, große Schrift) – bei Oberflächen Screenshot ansehen, wenn möglich.
4. Agent `reviewer` auf den Diff ansetzen; Befunde beheben oder begründet zurückweisen.

## 4. Abschließen
1. Akzeptanzkriterien in `docs/plan-stufe-1.md` abhaken, Abweichungen dort vermerken.
2. Hat sich fachlich etwas geändert: betroffenes Konzept-Dokument aktualisieren (und in `kontext.md` im Entscheidungs-Log eintragen).
3. Fehler, die passiert sind und sich nicht wiederholen sollen: kurz unter „Gelernt" in `CLAUDE.md`.
4. Commit (deutsch, Conventional Commits), z. B. `feat(meldungen): Knopf „Melden" am Team-Tablet`. Ein Meilenstein = ein oder wenige thematische Commits; nie `.env.local` oder Geheimnisse committen.
5. Kurze Zusammenfassung für den Nutzer: was ist fertig, wie testet man es (URL, Testzugang), was ist offen.

## Definition of Done
- Akzeptanzkriterien erfüllt, `composer qa` grün, Review ohne offene kritische Befunde
- Rechte über Voter geprüft (inkl. Team-Tablet), Sichtbarkeitsregeln eingehalten
- Migration passt zu `schema.sql`; Daten lassen sich mit Fixtures/Testdaten nachvollziehen
- Oberfläche bedienbar mit Touch, Tastatur und großer Schrift; Texte deutsch und verständlich
- Plan-Datei und ggf. Konzept aktualisiert, committet
