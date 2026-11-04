# Konzept 1 – Rollen, Rechte, Personen anlegen, Anmeldung, E-Mail

Stand: 03.11.2026 (M6: Schalter „wiederkehrende Aufgaben") · Status: Entwurf · App-Name: **HomeCrew**

## 1. Grundsätze
- **Eine Installation = ein Haushalt** (keine Mandanten; weitere Haushalte = eigene Instanz).
- **Rolle** = wer jemand ist (wichtig für Zuständigkeit und Sichtbarkeit). **Admin** = zusätzliches Recht zum Verwalten.
- **Team-Tablet**: Das Assistenz-Tablet kann ohne Personenanmeldung laufen und zeigt alles für die Rolle Assistenz (Konzept 12) – empfohlener Start.
- **Externe Dienste** (Physio, Caritas …) sind **keine Personen in der App** – sie haben keinen Zugang und erscheinen nur als Besuche im Kalender (Konzept 8).
- Grundhaltung: Der Manager entscheidet, die Assistenz assistiert.

## 2. Rollen und Admin-Recht
| Rolle | Wer | Admin-Recht |
|---|---|---|
| **Manager** | die assistierte Person, genau eine | immer |
| **Angehörige** | Familie, Vertrauenspersonen | optional |
| **Assistenz** | Assistenzkräfte | nie (keine Bearbeitung eigener Zeiten) |

„Admins" = Manager + Angehörige mit Admin-Recht. **2FA oder Passkey ist für alle Admins Pflicht** – technisch über den Schalter `ADMIN_2FA_REQUIRED`, auf Wunsch vorerst aus (Admins sehen dann einen Hinweis zum Einrichten); vor dem Echtbetrieb einschalten (Entscheidung 01.11. abends).

## 3. Rechte-Matrix
Legende: ✅ ja · ⚙️ per Schalter (Standard aus) · 👁 nur eigene · – nein

| Funktion | Admins | Angehörige | Assistenz (angemeldet) | Team-Tablet |
|---|---|---|---|---|
| „Heute" | ✅ | ✅ | ✅ | ✅ (Sicht Rolle Assistenz) |
| „Wer ist heute da / kommt als Nächstes" | ✅ | – | – | – |
| Kalender | ✅ alles | eigener | eigener | Termine, Besuche, Aufgaben – keine Dienste |
| **Besuche externer Dienste sehen** („Heute kommen") | ✅ | ✅ | ✅ | ✅ |
| Dienstplan pflegen | ✅ | – | – | – |
| Eigene Dienste sehen | – | – | 👁 | – |
| Dienst beginnen/beenden | – | – | ✅ | – (Admin pflegt Zeiten) |
| Eigene Zeiten sehen (3 Monate) / am selben Tag bearbeiten | – | – | 👁 | – |
| Alle Zeiten pflegen (mit Begründung), „Dienstplan übernehmen" | ✅ | – | – | – |
| Aufgaben erledigen | ✅ | ✅ | ✅ | ✅ |
| Einmalige Aufgaben anlegen | ✅ | ✅ | ✅ | ✅ (ohne „privat") |
| Aufgaben bearbeiten | ✅ alle sichtbaren | 👁 selbst angelegte | 👁 selbst angelegte | – |
| Aufgaben weitergeben | ✅ alle sichtbaren | 👁 wofür zuständig | 👁 wofür zuständig | 👁 wofür zuständig (Rolle Assistenz, „wer da ist", einzelne Assistenz) |
| Wiederkehrende Aufgaben / Wartungspläne anlegen und ändern („Routinen & Wartung" sehen: alle) | ✅ | ⚙️ | ⚙️ | – |
| **Melden** (leer · kaputt · fehlt, „nicht möglich") | ✅ | ✅ | ✅ | ✅ |
| **Offene Meldungen sehen** | ✅ alle, mit Absender | ✅ (ohne Absender) | ✅ (ohne Absender) | ✅ (ohne Absender) |
| **Meldungs-Log, Meldungen weitergeben/schließen** | ✅ | – | – | – |
| Gesprächspunkte, private Inhalte | ✅ | ✅ (private) | – | – |
| Termine und Besuche anlegen/absagen (einzeln und als Serie) | ✅ | ✅ | ⚙️ | – |
| Externe Dienste pflegen (Physio, Caritas …) | ✅ | – | – | – |
| Listen nutzen / verwalten | ✅ / ✅ | ✅ / – | ✅ / – | ✅ / – |
| Anleitungen lesen | ✅ | ✅ | ✅ | ✅ |
| Anleitungen erstellen/bearbeiten | ✅ | – | ✅ | Vorschlag (Entwurf → Freigabe) |
| Anleitungen veröffentlichen | ✅ | – | ⚙️ | – |
| „Wer kennt welche Anleitung" / Einweisung bestätigen | ✅ | – | 👁 „gelesen" | – |
| Notfall-Infos lesen / pflegen | ✅ / ✅ | ✅ / – | ✅ / – | ✅ / – |
| Orte pflegen | ✅ | ✅ | ✅ | – |
| Hilfsmittel pflegen | ✅ | – | ✅ | – |
| Profil lesen (sichtbare) / bearbeiten | ✅ / ✅ | ✅ / – | ✅ / – | ✅ („Team") / – |
| Übergabebuch | ✅ | ✅ | ✅ | ✅ |
| Nachrichten | ✅ | ✅ | ✅ (nicht untereinander) | – |
| Eigene Benachrichtigungsregeln | ✅ | ✅ | ✅ | – |
| KI-Assistent (nach Rechten) | ✅ | ✅ | ✅ | ✅ (Team-Werkzeuge) |
| Urlaub beantragen / genehmigen | – / ✅ | – | 👁 / – | – |
| Krankmeldung | ✅ | – | 👁 | – |
| Druckansichten | ✅ | ✅ | ✅ | ✅ |
| Verwaltung, Personen, Geräte, Einstellungen | ✅ | – | – | – |

**Schalter pro Person**: wiederkehrende Aufgaben (anlegen und jeden sichtbaren Plan ändern wie Admins, auch nach PIN-Anmeldung – Entscheidung 03.11., M6) · Anleitungen veröffentlichen · Termine anlegen · KI (Standard an) · KI per Sprache bestätigen · PIN-Anmeldung (Standard aus).

## 4. Personen anlegen
1. Admin legt an: **Rolle**, ggf. **Admin-Recht**, **Benutzername** (vom Admin), **Anzeigename**, Eintritt.
2. Zugang – nichts, einzeln oder kombiniert:
   - **kein eigener Zugang** – arbeitet nur am Team-Tablet (Start-Empfehlung; Zeiten pflegt der Admin),
   - **Tablet-PIN** – ohne E-Mail und Handy; Zugangs-Kärtchen drucken,
   - **Handy/PC** – Start-Passwort (Pflicht zum Ändern) oder Einladung per QR/E-Mail.
   - Start-Passwort (12 Zeichen) und Start-PIN (6 Ziffern) **erzeugt die App**; sie stehen nur einmal auf dem Zugangs-Kärtchen. Die **Einladung** gilt 72 Stunden und nur einmal, eine neue ersetzt die offene; der Code steht im QR-Code nur nach dem „#" der Adresse (erreicht den Server und dessen Log nicht). Hat die Person schon einen zweiten Schritt, meldet sie sich nach dem Einlösen normal an (Entscheidung 02.11.).
3. Optional Checkliste „Einstellung" (Konzept 10).

## 5. Anmeldung
- **Web-App** im Browser (Handy, Tablet, PC), kein App-Store, kein Offline.
- **Handy/PC**: Benutzername + Passwort oder Passkey; 2FA (Passkey · Authenticator-App · E-Mail-Code) + Wiederherstellungscodes; Pflicht für Admins (Schalter, s. §2).
  - **Passkey = vollständige Anmeldung** (Fingerabdruck, Gesicht oder Geräte-PIN, ohne Benutzername). Wer einen Passkey als zweiten Schritt hat, kommt mit dem Passwort nur zusammen mit einem Wiederherstellungscode weiter.
  - 10 Wiederherstellungscodes, jeder einmal gültig, nur einmal angezeigt; zweiter Schritt und Passwort je 5 Versuche in 15 Minuten, dann Wartezeit.
  - Abmeldung nach **8 Stunden** ohne Aktivität (Tablet kürzer, Konzept 12).
- **Tablet**: Modus pro Gerät – **Team** (ohne Anmeldung, Standard) · **Persönlich** (Name + PIN) · **Beides** (Konzept 12). Im Team-Modus kein eigener Entsperr-Code (Bildschirmschoner + ggf. Android-Sperre). PIN pro Person aktivierbar (Standard aus), nur gekoppelte Tablets im Heimnetz (mit Schalter „PIN nur im Heimnetz" aus auch unterwegs – die Team-Ansicht nie), nie Admin-Rechte über PIN. PIN: 6 Ziffern, nichts Einfaches; 3 Fehlversuche → 5 Minuten Pause; die Start-PIN wird bei der ersten PIN-Anmeldung ersetzt. Admin-Anmeldung am Tablet nur voll mit 2FA, nach 5 Minuten ohne Bedienung bzw. Abmelden automatisch zurück; PIN-Anmeldung endet nach der „Auto-Sperre" (Entscheidung 02.11., Konzept 12 §5.1).
- **Zurücksetzen**: Admins setzen Passwort, PIN, 2FA anderer zurück – **nicht beim Manager**: Den Manager bearbeitet nur der Manager selbst, niemand ändert die eigene Rolle oder das eigene Admin-Recht, den eigenen Zugang verwaltet jede Person unter „Mein Konto" (Entscheidung 02.11.); Manager: Wiederherstellungscodes, notfalls Server-Befehl `php bin/console app:manager:reset-2fa [--passwort]` (entfernt App, Codes und Passkeys, optional neues Start-Passwort). Erste Einrichtung: `php bin/console app:setup`.
- **Austritt**: Zugang entfernt (Passwort, PIN, zweiter Schritt, Passkeys, Einladungen – sofort), Person bleibt als „ausgetreten" Anker für Arbeitszeiten; Datum heute oder früher, „Austritt zurücknehmen" bei Irrtum; Checkliste „Austritt". **Sperren** beendet auch laufende Sitzungen.
- **„Mein Konto"**: Schriftgröße, hoher Kontrast, Passwort, zweiter Schritt, Passkeys, Wiederherstellungscodes, Datenschutzhinweis. Nach einer PIN-Anmeldung am Tablet nur PIN ändern (mit der aktuellen PIN), Darstellung und Datenschutzhinweis (Entscheidung 02.11.). Wer die eigene Anmeldung ändert – auch beim ersten zweiten Schritt oder Passkey –, bestätigt vorher wie beim Anmelden (Passkey oder Passwort + Code; 5 Minuten gültig, eine frische Anmeldung zählt). Admins mit 2FA-Pflicht behalten immer einen zweiten Schritt.
- **Datenschutzhinweis**: bei der ersten Anmeldung (nach dem Passwortwechsel) einmal bestätigen; neue Version → erneut (Konzept 5 §4.1).

## 6. E-Mail
Einladung, Passwort vergessen, 2FA-Code und **Benachrichtigungen** (pro Person und Ereignis, aus/sofort/täglich – v. a. neue Meldungen) – alles **Stufe 1**; über den SMTP-Zugang des eigenen Mail-Anbieters; Inhalte standardmäßig ohne Details.

## 7. Bedienung & Barrierefreiheit
Schriftgröße pro Person (am Team-Tablet einstellbar pro Gerät), hoher Kontrast, Bedienelemente ≥ 48 px, Status nie nur über Farbe, Vorlesen, Sprach- und Textsteuerung, Ziel WCAG 2.2 AA.
