# Konzept 5 – Technik, Betrieb, Netzwerk, E-Mail, Datenschutz

Stand: 01.11.2026 (nachmittags) · Status: Entwurf zur Abstimmung

## 1. Architektur
- **Eine Installation = ein Haushalt** (geklärt). Weitere Haushalte bekommen eine eigene Instanz mit eigener Datenbank – sauber getrennt, keine Mandantenlogik im Code. Mehrere Instanzen können auf demselben Pi laufen (eigener Docker-Compose-Stack + eigene Datenbank je Haushalt).
- **Symfony-Monolith** mit Bereichen: Personen & Anmeldung · Alltag & Kalender · Meldungen · Nachrichten · Wissen · Verwaltung · Admin-Menü · Erweiterungen (KI, E-Mail, Spracherkennung, Push).
- **Laufzeit**: FrankenPHP (inkl. Mercure für Live-Aktualisierung), **MariaDB 11.8 LTS** (geklärt; lokal und auf dem Pi dieselbe Version – Docker-Image fest auf die lokale Version gepinnt), Messenger-Worker (Medien, Mails, Benachrichtigungen, Spracherkennung, KI), Scheduler (Wiederholungen und Serien, Fälligkeiten, Erinnerungen, Tageszusammenfassung, Löschfristen, Gesundheitschecks der Erweiterungen).
- **Oberfläche**: reine Web-App; Twig + Symfony UX (Turbo, Stimulus, Live Components), AssetMapper ohne Node-Build; responsiv für Tablet, Handy, PC. Kein Offline-Modus.
- **Admin-Menü / Pflegemasken**: Konzept 11. Für reine Stammdaten-Masken bietet sich EasyAdmin an (schnell gebaut, gleiche Rechteprüfung über Voter); tägliche Abläufe als eigene, barrierearme Oberfläche.
- **Einrichtung**: Befehl bzw. Einrichtungsassistent beim ersten Start legt den Manager an (mit Pflicht zur 2FA – Passkey oder Authenticator-App, da E-Mail anfangs noch nicht eingerichtet ist).
- **Erweiterungen** (geklärt): KI, E-Mail, Spracherkennung und Push werden **im Admin-Menü eingerichtet** (Schlüssel/Zugang, Test, an/aus). Ein zentraler Dienst `FeatureAvailability` entscheidet, ob eine Funktion sichtbar ist – **nicht eingerichtet = unsichtbar**. Status wird beim Test und regelmäßig per Scheduler geprüft; Fehler → Funktion verschwindet, Admins bekommen einen Hinweis.
- **Bibliotheken (Vorschlag)**: php-rrule, yasumi, PhpSpreadsheet, WebAuthn-Bundle, scheb/2fa-bundle, reset-password-bundle, Symfony Mailer (DSN zur Laufzeit aus den Einstellungen gebaut), HtmlSanitizer, Trix/Tiptap, endroid/qr-code, ffmpeg + libvips/Imagick, whisper.cpp; optional web-push.
- **KI**: Claude Haiku 5.5 über die Claude API (Konzept 7) – Adapter mit Pseudonymisierung.
- **Geheimnisse**: Feldverschlüsselung (Klarname, IBAN, TOTP-Geheimnis, **KI-Schlüssel, SMTP-Passwort**) mit libsodium; der Hauptschlüssel `APP_ENCRYPTION_KEY` (mindestens 32 zufällige Zeichen) liegt als Symfony-/Docker-Secret außerhalb der Datenbank (lokal in `.env.dev.local`, nie im Repository). Geht er verloren, sind TOTP-Geheimnisse nicht mehr lesbar – dann den zweiten Schritt per Server-Befehl zurücksetzen (Konzept 1 §5). Schlüssel und Passwörter gehen nie an den Browser, nie ins Log, nie in Exporte. Optional können KI-Schlüssel und SMTP-Zugang per Umgebungsvariable vorgegeben werden (die Felder im Admin-Menü sind dann gesperrt).
- Strikte Content-Security-Policy, keine externen Skripte/Schriften/Analysen.

## 2. Betrieb: Raspberry Pi 5 (8 GB, SSD) mit Proxmox
- VM oder LXC (Debian arm64) mit Docker Compose: `frankenphp`, `mariadb`, `worker`, `cloudflared`, `whisper` (optional – ohne ihn ist das Mikrofon einfach nicht sichtbar).
- Backups über Proxmox + nächtlicher `mariadb-dump --single-transaction`; vor jeder Migration eine Sicherung. **Das Secret (Hauptschlüssel) separat sichern** – ohne es sind verschlüsselte Felder nicht lesbar (KI-Schlüssel/SMTP-Passwort kann man neu eintragen, Klarnamen/IBAN nicht).
- **Datenträger unverschlüsselt** (geklärt, bewusste Entscheidung) → umso wichtiger: Feldverschlüsselung der sensibelsten Felder, Pi an einem sicheren Ort, Backups nicht offen herumliegen lassen.
- Pi-Ausfall: kein Notbetrieb vorgesehen (akzeptiertes Risiko).
- whisper.cpp braucht CPU: der VM genug Kerne geben, Pi aktiv kühlen.

## 3. Netzwerk & E-Mail
- **Cloudflare Tunnel** für den Zugriff von außen (Auftragsverarbeiter; Cache aus; Upload-Grenze 100 MB → Videos in Teilstücken; Herzschlag für Live-Verbindungen).
- **UniFi**: lokaler DNS-Eintrag → im WLAN direkt auf den Pi; Zertifikat per DNS-Challenge; **PIN-Anmeldung nur aus dem Heimnetz**.
- **E-Mail** über den SMTP-Zugang des eigenen Mail-Anbieters (geklärt), **eingerichtet im Admin-Menü** mit Testmail; SPF/DKIM/DMARC; Inhalte ohne Details (Titel nur, wenn die Person es einschaltet); asynchroner Versand; Tageszusammenfassung per Scheduler.

## 4. Datenschutz

### 4.1 Rollen nach DSGVO
- **Manager** = Verantwortlicher (Arbeitgeber).
- **Auftragsverarbeiter**: Cloudflare, Anthropic (KI – nur wenn eingerichtet), Mail-Anbieter.
- Betreibt jemand eine Instanz für einen anderen Haushalt, ist er dessen Auftragsverarbeiter (AV-Vertrag).
- Wird eine Erweiterung mit neuem Auftragsverarbeiter eingeschaltet (z. B. KI), steigt die Version der Datenschutzinfo; alle sehen beim nächsten Öffnen den Hinweis.
- Umsetzung (M3): Datenschutzhinweis unter „Mein Konto → Datenschutz", Pflichtschritt bei der ersten Anmeldung, druckbar für Personen ohne eigenen Zugang; Version als Konstante im Code (`PrivacyNotice::VERSION`). Der Text ist ein Entwurf und vor dem Echtbetrieb zu prüfen.

### 4.2 Datenkategorien (Auszug)
| Daten | Betroffene | Hinweis |
|---|---|---|
| Anzeigename, Benutzername, Zugang, Dienstplan, Zeiten, Urlaub, Lohn | Assistenz | minimal; keine Adressen/SV-Nummern |
| Krankheitszeiträume | Assistenz | nur Zeitraum + AU ja/nein |
| Erledigungen, Meldungen, „Wer kennt welche Anleitung" | Assistenz | Name an Erledigungen/Meldungen nach 90 Tagen entfernt, nur für Admins sichtbar; am Team-Tablet gar kein Name; keine Leistungsbewertung |
| Nachrichten, Sprachnachrichten | Teilnehmende | 365 Tage |
| KI-Anfragen | Nutzer der KI | pseudonymisiert; 7 Tage; Audio nur lokal |
| Profil, Notfall-Infos, Termine, Besuche externer Dienste | Manager, Kontaktpersonen, externe Dienste | Sichtbarkeit pro Eintrag; **keine Medikamente**; externe Dienste ohne Klarnamen |

### 4.3 Löschkonzept
| Daten | Frist |
|---|---|
| Übergabebuch | 30 Tage |
| Erledigungen | Person nach 90 Tagen entfernen; Aufgaben nach 365 Tagen löschen (außer letzte 5 je Wartungsplan) |
| Meldungen | Person nach 90 Tagen entfernen; abgeschlossene nach 365 Tagen löschen |
| Nachrichten | **365 Tage** (geklärt); Direktnachrichten mit Ausgetretenen nach 30 Tagen |
| Benachrichtigungen | 30 Tage |
| KI-Gespräche | 7 Tage; Audio sofort nach der Erkennung |
| Reset-Links, 2FA-Codes | nach Ablauf |
| Zugang (Passwort, PIN, Passkeys) | beim Austritt entfernt |
| KI-Schlüssel, SMTP-Passwort | beim Löschen in den Erweiterungen sofort entfernt |
| Zeiten, Abwesenheiten, Lohn, Checklisten | einstellbar – mit Bezirk/Steuerberater klären |
| Audit-Log | 12 Monate |

### 4.4 Maßnahmen
2FA/Passkey für Admins Pflicht (Schalter `ADMIN_2FA_REQUIRED`, vor dem Echtbetrieb einschalten) · Sperre nach Fehlversuchen · Abmeldung nach 8 h ohne Aktivität · PIN nur pro Person und nur im Heimnetz, nie mit Admin-Rechten · Auto-Sperre · Rollen + Voter (auch für KI-Werkzeuge) · Erweiterungen nur nach bewusster Einrichtung · Geheimnisse verschlüsselt, erneute 2FA zum Ändern · Pseudonymisierung vor KI-Aufrufen · lokale Spracherkennung · TLS · Feldverschlüsselung · Backups · Audit-Log · kein GPS, keine Leistungsstatistiken, kein Dauerzuhören.
