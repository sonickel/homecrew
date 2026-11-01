# Konzept 4 – Verwaltung: Zeiten, Abwesenheiten, Lohn, Haushaltsscheck, Bezirk, Exporte

Stand: 01.11.2026 (nachmittags) · Status: Entwurf zur Abstimmung · Ausbaustufe 3 (Dienstplan, Zeiterfassung, „Dienstplan übernehmen" und Urlaubsantrag schon in Stufe 1)

> Keine Rechts- oder Steuerberatung. Sätze, Grenzen und Regeln sind konfigurierbar und mit dem Haushaltsscheck-Rechner bzw. dem Bezirk gegenzuprüfen.

## 1. Rechtlicher Rahmen (Stand 2026)
- **Haushaltsscheckverfahren**; Halbjahresscheck bei schwankendem Entgelt (fällig 30.06./31.12.); Abgaben-Einzug 31.07./31.01. vom Konto des Managers.
- **Arbeitgeberabgaben** höchstens 14,62 % (2026).
- **Rentenversicherung**: Assistenzkräfte nicht befreit → Eigenanteil (13,6 %, Mindestbemessung 175 €) wird vom Lohn einbehalten, weil die Minijob-Zentrale ihn vom Konto des Managers einzieht.
- **Minijob-Grenze** 603 € (2026), 633 € ab 2027; gelegentliches Überschreiten max. 2 Monate/Jahr bis zum Doppelten.
- **Krankheit**: Entgeltfortzahlung nach 4 Wochen Beschäftigung, bis 6 Wochen; U1-Erstattung 80 %.
- **Urlaub**, **Feiertagsvergütung**, **Annahmeverzug** bei vom Arbeitgeber abgesagten festen Diensten (§ 615 BGB).
- Bezirk verlangt keine unterschriebenen Stundenzettel.

## 2. Stammdaten je Assistenz (nur Admins)
Anzeigename · Klarname (optional, verschlüsselt) · Eintritt/Austritt · **Stundenlohn pro Person** mit „gültig ab" · RV-befreit (Standard nein) · gesetzlich krankenversichert · Arbeitstage/Woche · IBAN (optional, verschlüsselt, Stufe 4). Nicht gespeichert: Adresse, Geburtsdatum, SV-Nummer, Steuer-ID, Diagnosen.

## 3. Zeiterfassung

### 3.1 Zwei Wege (geklärt)
| Weg | Wer erfasst | Wann |
|---|---|---|
| **A – Admin pflegt** (Start mit Team-Tablet, Konzept 12) | Admin übernimmt den Dienstplan und passt Abweichungen an | Assistenz hat keinen eigenen Zugang oder das Tablet läuft im Team-Modus |
| **B – Selbst erfassen** | Assistenz: Dienst beginnen/beenden per Tablet (PIN) oder Handy | Tablet im Modus „Persönlich"/„Beides" oder eigener Handy-Zugang |

Beide Wege können gemischt vorkommen (z. B. Anna erfasst selbst, Ben hat nur das Team-Tablet). Pause in Minuten; kein Standort; Art: Dienst · Einarbeitung · Besprechung.

### 3.2 „Dienstplan übernehmen" (Weg A)
- Admin wählt Tag, Woche oder Monat (optional nur eine Person) → alle **geplanten, nicht abgesagten Dienste ohne Zeiteintrag** werden als Arbeitszeit angelegt (Beginn/Ende/Pause aus dem Plan).
- Vorschau vor dem Übernehmen; bereits vorhandene Einträge werden nie überschrieben.
- Abweichungen (später gekommen, länger geblieben, ausgefallen) korrigiert der Admin im Anschluss – mit Begründung, wie jede Admin-Änderung.
- Markierung „aus Dienstplan übernommen" (`start_source = 'dienstplan'`) + Eintrag im Änderungsverlauf (Art `nachtrag`, Begründung „aus Dienstplan übernommen").
- **Stundenübersicht** pro Person und Monat zum Drucken bzw. als Excel – für Assistenzkräfte ohne eigenen Zugang.

### 3.3 Wer darf was (Weg B)
| | Assistenz | Admins |
|---|---|---|
| Eigene Zeiten sehen | **letzte 3 Monate** (Einstellung) | alle, unbegrenzt |
| Bearbeiten | **nur am selben Tag** (bis Mitternacht des Tages, an dem der Dienst endete) | jederzeit, **mit Begründung** |
| Nachtragen (vergessener Dienst) | am selben Tag | jederzeit, mit Begründung |

- Danach ist der Eintrag für die Assistenz **gesperrt**; Änderungswünsche schickt sie per Nachricht an einen Admin.
- Jede Änderung steht im **Änderungsverlauf** (wer, wann, alt → neu, Begründung).
- Pro Person nur ein offener Dienst; mehr als 6 h ohne 30 Min. Pause → Hinweis; vergessenes Ende → Hinweis an die Admins.

### 3.4 Monatsabschluss
1. Optional: Assistenz bestätigt ihre Monatsstunden („stimmt so") – nur mit eigenem Zugang.
2. Admin prüft (Markierungen: aus Dienstplan, nachgetragen, korrigiert, Pausenhinweis; Hinweis „geplante Dienste ohne Zeiteintrag") und schließt ab.
3. Lohnberechnung wird festgeschrieben; Monat gesperrt; Wiederöffnen nur mit Begründung.

## 4. Abwesenheiten & Ausfälle
- **Urlaub**: Anspruch nach Arbeitstagen/Woche; Antrag (Handy, Kalender, KI) oder vom Admin eingetragen → Genehmigung durch Admin → geplante Dienste „ausgefallen (Urlaub)"; Urlaubsentgelt nach 13-Wochen-Durchschnitt (änderbar).
- **Krankheit**: nur Zeitraum + „AU vorgelegt"; Lohnfortzahlung aus geplanten Diensten; U1-Erstattung erfassen.
- **Feiertage** Bayern automatisch.
- **Abgesagte Dienste**: Urlaub/Krank wie oben; „Manager abwesend", „Assistenz verhindert", „sonstiges" → Entscheidung „trotzdem bezahlt?" (bei Absage durch den Manager Standard ja). Abgesagte Dienste werden von „Dienstplan übernehmen" nicht übernommen.

## 5. Lohnberechnung pro Monat
```
Arbeit + Urlaubsentgelt + Lohnfortzahlung + Feiertage + bezahlte Ausfälle = Brutto
RV-Eigenanteil = max(Brutto; 175 €) × RV-Gesamtsatz − Brutto × RV-AG-Satz   (falls nicht befreit)
Auszahlung     = Brutto − RV-Eigenanteil
AG-Abgaben     = Brutto × Summe AG-Sätze
Gesamtkosten   = Brutto + AG-Abgaben   → Vergleich mit Monatsbudget des Bezirks
```
Warnungen bei Minijob-Grenze; Sätze und Grenzen als Tabellen mit „gültig ab".

## 6. Bezirk
Bewilligung als **Monatsbudget**; Ampel Gesamtkosten vs. Budget (auch im Kalender); Erinnerung 3 Monate vor Ablauf; Nachweis zunächst als Excel.

## 7. Excel-Exporte
Monatsexport (Zeiten, Summen, Überweisungen mit Auszahlungsbeträgen, Bezirk, Parameter – Summen als Formeln), Stundenübersicht pro Person, Halbjahresexport für den Halbjahresscheck (volle Euro), Jahresexport.

## 8. Fristen & Erinnerungen
Monatsende (inkl. „Dienstplan übernehmen" für den Monat) · Halbjahresscheck · Abgaben-Einzug · Anmeldung/Abmeldung bei der Minijob-Zentrale · U1-Erstattung · Jahreswechsel (Sätze prüfen) · Bewilligung läuft ab. Erscheinen bei den Admins auf „Heute" und im Kalender.

## 9. Offene Punkte
- Inhalt des Bezirks-Nachweises; Zuschläge; Tätigkeitskategorien.
- Arbeitsverträge: feste Dienste oder Arbeit auf Abruf?
