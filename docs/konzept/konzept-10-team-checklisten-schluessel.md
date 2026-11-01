# Konzept 10 – Team: Checklisten für Einstellung und Austritt

Stand: 01.11.2026 (nachmittags) · Status: Entwurf · Ausbaustufe 3
**Schlüssel & Zugänge: zurückgestellt** (geklärt – wird aktuell nicht genutzt; nicht im Schema, kann später ergänzt werden).

> Keine Rechtsberatung. Standardpunkte sind ein Startvorschlag und anpassbar.

## 1. Ablauf
- **Einstellung**: beim Anlegen einer Assistenz „Checkliste starten?"; Bezugsdatum = Eintritt.
- **Austritt**: beim Setzen des Austrittsdatums; Bezugsdatum = letzter Arbeitstag.
- Jeder Punkt: Zeitpunkt (vor Beginn · erster Tag · erste Woche · letzter Tag · nach Austritt) → Fälligkeitsdatum; erledigt oder „entfällt"; Notiz.
- Manche Punkte hakt die App automatisch ab (Person angelegt, Zugang eingerichtet, Datenschutzinfo bestätigt, Dienstplan und Lohn hinterlegt, Austritt eingetragen, Zugang gesperrt, letzte Abrechnung).

## 2. Zuständigkeit (geklärt)
- In der Vorlage legt man je Punkt fest, **wer ihn zuerst bekommt**: **Admins** (Standard), **Manager**, eine **Rolle** (z. B. Assistenz für „neue Person einweisen") oder in der konkreten Checkliste eine **bestimmte Person**.
- Admins können Punkte **weitergeben** (an Rolle oder Person) – wie bei Aufgaben (Konzept 2, §5.2).
- Zugewiesene Punkte erscheinen bei der Person auf „Heute".

## 3. Standardpunkte Einstellung
| Zeitpunkt | Punkt | Zuerst bei | Automatisch |
|---|---|---|---|
| vor Beginn | Arbeitsvertrag / Nachweis der wesentlichen Arbeitsbedingungen | Manager | – |
| vor Beginn | Abfragen: weitere Beschäftigungen, Krankenversicherung, RV-Befreiung gewünscht? | Admins | – |
| vor Beginn | Anmeldung bei der Minijob-Zentrale (Haushaltsscheck) | Manager | – |
| vor Beginn | Bezirk informieren (falls gefordert) | Admins | – |
| vor Beginn | Person anlegen, Stundenlohn und feste Tage hinterlegen | Admins | ✅ |
| vor Beginn | Zugang einrichten (Tablet-PIN und/oder Handy), Zugangs-Kärtchen übergeben | Admins | ✅ |
| erster Tag | Datenschutzinformation bestätigen | (Person selbst) | ✅ |
| erster Tag | Verschwiegenheit besprechen und unterschreiben lassen | Admins | – |
| erster Tag | Notfall-Infos und Notfall-Button zeigen | Admins | – |
| erster Tag | Wohnung und wichtigste Anleitungen zeigen | Rolle Assistenz oder Person | – |
| erste Woche | Urlaubsanspruch festlegen | Admins | – |

## 4. Standardpunkte Austritt
| Zeitpunkt | Punkt | Zuerst bei | Automatisch |
|---|---|---|---|
| sofort | Kündigung/Aufhebung schriftlich | Manager | – |
| sofort | Austrittsdatum eintragen | Admins | ✅ |
| vor dem letzten Tag | Resturlaub nehmen oder abgelten | Admins | – |
| letzter Tag | Schlüssel zurück, ggf. Codes ändern | Admins | – |
| letzter Tag | Zugang sperren | Admins | ✅ |
| nach Austritt | Abmeldung bei der Minijob-Zentrale | Manager | – |
| nach Austritt | Bezirk informieren (falls gefordert) | Admins | – |
| nach Austritt | Letzte Abrechnung, Halbjahresscheck berücksichtigen | Admins | ✅ |
| auf Wunsch | Arbeitszeugnis, Datenauskunft | Manager | – |

## 5. Datenbank
`checklist_template`, `checklist_template_item` (mit `assignee_role`), `checklist`, `checklist_item` (mit `assignee_user_id`/`assignee_role`).
