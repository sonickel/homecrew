---
name: homecrew-ui
description: Gestaltung und Oberflächen-Regeln für HomeCrew – Design-Tokens, Typografie, Layout für Team-Tablet und Handy, Komponenten, Texte, Barrierefreiheit und Druckansichten. Verwenden bei jedem Template, CSS, Twig-Component oder Oberflächentext.
---

# HomeCrew – Oberfläche & Design

## Worum es geht
Ein Tablet hängt im Flur oder steht in der Küche. Fast jeden Tag kommt jemand anderes – oft zum ersten Mal. Wer davorsteht, soll in **fünf Sekunden** wissen: Was ist heute zu tun, wer kommt, was ist wichtig, wo ist der Notfall-Knopf. Vorbild sind gut gemachte **Emaille-Schilder und Wandkalender**: klar, robust, freundlich, ohne Schnickschnack. Die Oberfläche ist ruhig; der einzige „laute" Moment ist das große Datum und die Liste für heute.

## Design-Tokens (in `assets/styles/tokens.css` als CSS-Variablen)
| Token | Wert | Verwendung |
|---|---|---|
| `--hc-ink` | `#18222B` | Text, Symbole |
| `--hc-paper` | `#F4F6F7` | Seitenhintergrund (kühles Hellgrau, kein Creme) |
| `--hc-surface` | `#FFFFFF` | Zeilen, Formulare |
| `--hc-enamel` | `#1E4D8C` | Kopfleiste, Hauptaktionen (Emaille-Blau, weißer Text) |
| `--hc-signal` | `#F4C430` | „Melden", Aufmerksamkeit (immer mit dunkler Schrift) |
| `--hc-line` | `#D3D9DE` | Trennlinien zwischen Zeilen (rein gestalterisch) |
| `--hc-border` | `#6B7782` | Rahmen von Eingabefeldern und Bedienelementen (≥ 3 : 1 nötig) |
| `--hc-ok` / `--hc-due` / `--hc-late` | `#2E7D4F` / `#8A5300` / `#B42318` | erledigt / fällig / überfällig – **immer mit Symbol und Wort** |
| `--hc-emergency` | `#B42318` | ausschließlich Notfall-Knopf |

- Kontraste vor Verwendung prüfen (Text ≥ 4,5 : 1, große Schrift/Symbole ≥ 3 : 1); bei Abweichung Ton nachdunkeln, nicht Größe erhöhen.
- Eigener Token-Satz „hoher Kontrast" (`[data-contrast="hoch"]`): Schwarz auf Weiß, dickere Linien, keine Flächenfarben hinter Text.
- Abstände in einer Skala (`--hc-space-1` … `-6` = 4, 8, 12, 16, 24, 32 px); Radius: 6 px für Bedienelemente, 0 für Listenzeilen – nicht überall derselbe Radius.

## Typografie
- **Eine Schrift: Atkinson Hyperlegible Next** (vom Braille Institute für Lesbarkeit entwickelt, SIL OFL), **lokal als woff2** ausgeliefert, Schnitte 400 und 700; Fallback `system-ui, sans-serif`. Ziffern tabellarisch (`font-variant-numeric: tabular-nums`) für Uhrzeiten.
- Grundgröße: Handy 13,5 px, Tablet 15 px (75 % des ersten Entwurfs, Entscheidung 01.11.); Stufen ×1,25 (Tablet: 15 · 18,75 · 23,4 · 29,3 · 36,6). Schriftgröße pro Person/Gerät über `html[data-font="normal|gross|sehr_gross"]` (Werte wie `app_user.font_size`, +25 % / +50 %), Layout muss bei „sehr groß" funktionieren (umbrechen statt abschneiden). Eingabefelder später mindestens 16 px (sonst zoomt iOS beim Antippen).
- Satzanfang groß (keine GROSSBUCHSTABEN-Labels), Zeilenlänge ≤ 70 Zeichen, Zeilenabstand 1,5 für Fließtext.

## Layout
Team-Tablet quer (Hauptgerät):
```
┌───────────────────────────────────────────────────────────┐
│ Dienstag, 3. November                     [ Notfall ]     │  Kopfleiste Emaille-Blau, Datum groß
├──────────────────────────────────┬────────────────────────┤
│ Heute                            │ Heute kommen           │
│ ○ 09:00 Bettwäsche wechseln  [✓] │ 10:00 Physio           │
│ ○ Seifenspender auffüllen    [✓] │ 13:00 Caritas (Putzen) │
│ ! Reifendruck – überfällig   [✓] │  Bad/Küche macht Caritas│
│ ● 14:00 Termin Sanitätshaus      │ Wichtig                │
│ ...                              │  Post liegt in der Küche│
│                                  │ [  Melden  ] [ Listen ]│
└──────────────────────────────────┴────────────────────────┘
```
- Links eine **Zeitleiste für heute** (Aufgaben, Termine, Besuche gemischt nach Uhrzeit, „ohne Uhrzeit" danach), rechts Kontext und große Aktionen.
- Handy hochkant: eine Spalte, unten feste Leiste mit höchstens 4 Zielen (Heute · Listen · Melden · Mehr); Notfall bleibt oben sichtbar.
- Inhalte linksbündig; Listen als **Zeilen mit Trennlinien**, Karten nur für wirklich eigenständige Kacheln (z. B. Wissen-Übersicht). Kein Raster aus identischen Karten mit Schatten.
- Admin-Bereich darf dichter sein (Tabellen), bleibt aber in denselben Tokens.

## Komponenten (Twig Components in `src/Twig/Components/` + `templates/components/`)
- `Button` (Varianten: primär, sekundär, gefährlich, signal), `StatusBadge` (Symbol + Text), `TaskRow`, `VisitRow`, `ReportButton`, `EmergencyButton`, `PageHeader`, `EmptyState`, `Toast`, `FormRow`.
- Trefferflächen: mindestens 48 × 48 px, auf dem Team-Tablet 56 px; ganze Zeile antippbar, Haupt-Aktion („Erledigt") rechts als eigener großer Knopf.
- **Rückgängig statt Nachfragen**: „Erledigt" sofort ausführen, 10 Sekunden „Rückgängig" anbieten. Bestätigungsdialoge nur für Löschen/Unumkehrbares.
- Symbole lokal als SVG (z. B. symfony/ux-icons mit lokal importierten Icons), immer mit sichtbarem Text oder `aria-label`.

## Bewegung
Nur als Antwort auf eine Handlung (Zeile wird erledigt, Bereich klappt auf). Keine Einblend-Animationen beim Laden, keine Hover-Effekte auf jeder Zeile. `prefers-reduced-motion` respektieren.

## Texte (deutsch, Du-Form)
- Knöpfe sagen, was passiert: „Erledigt", „Nicht möglich", „Melden", „Auf die Liste", „Speichern" – nie „OK"/„Absenden". Dieselbe Handlung heißt überall gleich (Knopf „Melden" → Hinweis „Gemeldet").
- Feste Begriffe (nicht variieren): Aufgabe · Wartung · Meldung · Besuch · Termin · Dienst · Übergabe · Liste · Anleitung · Notfall · Team-Tablet. Keine Technikwörter (Ticket, Task, Issue, Feed).
- Fehlertexte: was ist passiert + was tun („Die PIN stimmt nicht. Noch 2 Versuche, dann 5 Minuten Pause.") – ohne Entschuldigung, ohne Fachjargon.
- Leere Zustände laden zum Handeln ein („Für heute ist alles erledigt. Etwas fehlt? Melden.").
- Keine Schmuck-Labels über Überschriften, keine „→" an Links, keine Mittelpunkt-Ketten als Metazeile.

## Barrierefreiheit (WCAG 2.2 AA)
Sichtbarer Fokus (3 px Rahmen in `--hc-enamel`, auf Blau in Weiß) · alle Funktionen per Tastatur · echte Labels an Feldern (kein Platzhalter als Label) · Status nie nur über Farbe · Live-Region für Hinweise/Toasts · semantisches HTML (Überschriften-Hierarchie, Listen, `button` vs. `a`) · Vorlesen-Knopf für Anleitungen nutzt die Sprachausgabe des Geräts.

## Druckansichten (`assets/styles/print.css`)
A4 hochkant, schwarz-weiß, ohne Navigation; oben Haushalt + Datum; Kästchen ☐ zum Abhaken; mindestens 12 pt (große Schrift 14 pt); Seitenumbruch zwischen Abschnitten; Besuche als eigene Zeile „Es kommen: …".

## Technik
- Reines CSS mit Cascade Layers: `@layer tokens, base, components, utilities;` – Dateien unter `assets/styles/`, über AssetMapper eingebunden, **kein Build-Schritt, kein CSS-Framework nötig**.
- Keine externen Schriften, Skripte oder CDNs (Content-Security-Policy).
- **Kein Inline-`<script>`/`<style>` und keine `style=""`-Attribute**: Seiten, die Turbo nachlädt, behalten die CSP der ersten Seite (deren Nonce) – Inline-Code würde blockiert. Verhalten nur über Stimulus-Controller, Aussehen nur über CSS-Dateien.
- CSS wird in `templates/base.html.twig` per `<link>` eingebunden, **nicht** aus JavaScript importiert (CSS-Importe werden in der Importmap zu `data:`-Modulen, die die CSP blockiert).
- Turbo für schnelle Seitenwechsel, Stimulus nur für echte Interaktion (Rückgängig-Timer, Bildschirmschoner, Upload).

## Vorgehen bei einer neuen Seite
1. Im Plan: Zweck der Seite in einem Satz, ASCII-Skizze für Tablet und Handy, verwendete Komponenten.
2. Bauen mit vorhandenen Komponenten; neue Komponente nur, wenn sie mindestens zweimal gebraucht wird.
3. Prüfen bei 1024 × 768 und 390 × 844, mit Schriftgröße „sehr groß" und nur per Tastatur; wenn möglich Screenshot ansehen und selbstkritisch nachbessern (eine Sache weglassen, statt eine hinzuzufügen).
