---
name: datenschutz-sicherheit
description: Prüfliste für Rechte, Sichtbarkeit, Team-Tablet, Geheimnisse und DSGVO in HomeCrew. Verwenden bei Anmeldung, Rechten, Personen-/Zeitdaten, Erweiterungen, Uploads, Löschjobs und vor jedem Review.
---

# Datenschutz & Sicherheit – Prüfliste

## Rechte und Sichtbarkeit
- [ ] Jede Aktion/Seite hat einen Voter-Check im Controller; Template-`is_granted` nur zur Anzeige.
- [ ] Sichtbarkeit `alle`/`zustaendig`/`admins`/`privat` (Meldungen/Besuche: `alle`/`admins`) wird in Listen **und** Detailseiten **und** KI-/Export-Lesewegen gleich angewendet.
- [ ] Rechte-Matrix aus `docs/konzept/konzept-1-rollen-zugang.md` eingehalten (Assistenz: nur eigene Dienste/Zeiten; Zeiten nur am selben Tag bearbeitbar).
- [ ] Funktionale Tests decken erlaubt/verboten für jede Rolle **und** das Team-Tablet ab.

## Team-Tablet
- [ ] Nur mit gültigem Geräte-Token, nicht entkoppelt, nur aus dem Heimnetz (IP-Prüfung konfigurierbar).
- [ ] Sicht = Rolle Assistenz ohne persönliche Daten: keine Dienste, Zeiten, Nachrichten, privaten/Admin-Inhalte, keine Absender von Meldungen.
- [ ] Einträge speichern `device_id`, nie eine Person.
- [ ] Admin-Anmeldung am Tablet: volle Anmeldung mit 2FA, automatische Rückkehr in den Team-Modus.

## Anmeldung
- [ ] Admins: 2FA/Passkey erzwungen; PIN-Anmeldung und Team-Modus geben nie Admin-Rechte.
- [ ] PIN: nur wenn für die Person aktiviert, nur gekoppeltes Gerät im Heimnetz, Sperre nach Fehlversuchen.
- [ ] Rate-Limiting (Symfony RateLimiter) für Login, PIN, Passwort vergessen, 2FA-Code.
- [ ] Sessions: sichere Cookies, Abmeldung nach Inaktivität (Tablet kürzer).

## Geheimnisse & Erweiterungen
- [ ] `*_enc`-Felder (KI-Schlüssel, SMTP-Passwort, TOTP, Klarname, IBAN) nur verschlüsselt; Hauptschlüssel als Symfony-Secret, nicht im Repository.
- [ ] Geheimnisse nie im Formular zurückgeben, nie in Logs, Exceptions, Exporten oder an den Browser.
- [ ] Ändern/Löschen von Geheimnissen nur nach erneuter 2FA; Eintrag im Audit-Log.
- [ ] Funktionen einer Erweiterung nur sichtbar über `FeatureAvailability` (nicht eingerichtet = unsichtbar).

## Daten sparsam
- [ ] Nur Anzeigenamen in der Oberfläche; keine Diagnosen, Medikamente, Adressen, SV-/Steuernummern, Geburtsdaten.
- [ ] Externe Dienste ohne Klarnamen; Freitextfelder mit Hinweis „keine Gesundheitsangaben", wo sinnvoll.
- [ ] Keine Leistungsstatistiken über Personen, kein Standort, kein Dauerzuhören.
- [ ] Löschfristen aus `app_setting` umgesetzt (Scheduler-Job + Test), Personenbezug an Erledigungen/Meldungen nach Frist entfernt.

## Web-Sicherheit
- [ ] CSRF an allen Formularen/Aktionen; Turbo-Streams nur für berechtigte Betrachter.
- [ ] Ausgabe immer escaped; HTML aus dem Editor nur über HtmlSanitizer.
- [ ] Uploads: Typ/Größe prüfen, außerhalb des Web-Verzeichnisses speichern, über Controller mit Rechteprüfung ausliefern, Metadaten (EXIF/GPS) entfernen.
- [ ] Strikte Content-Security-Policy, keine externen Ressourcen.
- [ ] Sensible Vorgänge (Rechte, Personen, Zeiten-Korrekturen, Geräte, Erweiterungen) ins Audit-Log – ohne Inhalte/Geheimnisse.
