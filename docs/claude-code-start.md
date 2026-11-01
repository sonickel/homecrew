# Start mit Claude Code – Ablauf und Prompts

Diese Datei ist für dich (Andreas) zum Kopieren der Prompts. Claude Code liest ohnehin `CLAUDE.md` und die Skills unter `.claude/skills/`.

## Einmalig vorbereiten (WSL-Terminal)
1. Dateien aus `homecrew-claude-code.zip` nach `/var/www/homecrew` entpacken (die Ordner `docs/`, `.claude/` und `CLAUDE.md` liegen dann direkt im Projekt) und committen:
   ```bash
   cd /var/www/homecrew
   git add CLAUDE.md docs .claude
   git commit -m "docs: Konzept, Referenzschema und Claude-Code-Einstellungen"
   ```
2. Datenbank-Benutzer anlegen (Passwort selbst wählen):
   ```bash
   sudo mariadb -e "CREATE USER 'homecrew'@'localhost' IDENTIFIED BY 'DEIN-PASSWORT';
   GRANT ALL PRIVILEGES ON \`homecrew%\`.* TO 'homecrew'@'localhost'; FLUSH PRIVILEGES;"
   mariadb --version
   ```
3. VS Code **im WSL-Modus** öffnen: `cd /var/www/homecrew && code .` – unten links muss „WSL: Debian" stehen. Claude Code dort öffnen.

## Prompt 1 – Umgebung prüfen und Plan erstellen (Plan-Modus)
> Lies CLAUDE.md und docs/konzept/kontext.md, die übrigen Konzepte nur überfliegen. Prüfe die Umgebung (Debian-Version, `php -v`, `php -m`, composer, symfony CLI, `mariadb --version`), installiere noch nichts und sag mir, was fehlt und mit welchen Befehlen ich es installiere. Erstelle dann `docs/plan-stufe-1.md`: Stufe 1 in 10–14 kleinen Meilensteinen, jeder für sich lauffähig und im Browser prüfbar, mit Akzeptanzkriterien und den betroffenen Tabellen. Meilenstein 0 = Symfony-7.4-Grundgerüst in diesem bestehenden Repository + Qualitätswerkzeuge (`composer qa`), Meilenstein 1 = Referenzschema und Smoke-Test lokal ausführen + Migrationsstrategie festlegen. Schreib noch keinen Anwendungscode.

## Prompt 2 – Meilenstein 0
> Setze Meilenstein 0 nach Skill `meilenstein` um. Wenn du die Datenbank-Zugangsdaten brauchst, sag mir, was ich in `.env.local` eintragen soll – ich trage sie selbst ein.

## Prompt 3 ff. – nächste Meilensteine
> Setze Meilenstein N aus docs/plan-stufe-1.md nach Skill `meilenstein` um. Erst Plan, dann auf mein Ok warten.

Tipps:
- Zwischen Meilensteinen `/clear` – der Kontext steht in CLAUDE.md, Plan und Konzept.
- Bei Fehlern, die sich wiederholen: „Trag das unter ‚Gelernt' in CLAUDE.md ein."
- Vor dem Commit: „Lass den reviewer-Agenten über den Diff laufen."

## Empfohlene Reihenfolge Stufe 1 (Vorschlag für den Plan)
0. Grundgerüst, Qualitätswerkzeuge, Layout/Design-Tokens
1. Referenzschema lokal testen, Migrationsstrategie
2. Einrichtung (Manager anlegen) + Anmeldung mit Passkey/Authenticator-App + Admin-Menü-Gerüst
3. Personen und Geräte, Team-Tablet-Modus (Koppeln, Heimnetz, Bildschirmschoner, Notfall-Knopf)
4. „Heute" + einmalige Aufgaben + Erledigen
5. Wartungspläne/wiederkehrende Aufgaben + Serien-Generator + „Routinen & Wartung"
6. Meldungen + Listen
7. Kalender: Termine, externe Dienste, Besuchsserien, „Heute kommen"
8. Dienstplan + Arbeitszeiten („Dienstplan übernehmen"), Urlaub/Krank
9. Übergabebuch, Gesprächspunkte, Abwesenheit des Managers
10. Wissen: Anleitungen mit Editor + Medien-Upload, Notfall-Infos, Orte, Profil, „Wer kennt was"
11. Benachrichtigungen (App) + Erweiterung E-Mail + Regeln je Ereignis
12. Druckansichten, Feinschliff Barrierefreiheit, Löschjobs
