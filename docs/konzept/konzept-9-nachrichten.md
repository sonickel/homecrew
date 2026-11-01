# Konzept 9 – Nachrichten (Chat) und Benachrichtigungen

Stand: 01.11.2026 (nachmittags) · Stufen: Benachrichtigungen in der App und **per E-Mail 1** · Chat **2** · Push **4** (optional) · Regeln je Ereignis und Meldungen: Konzept 13

## 1. Ziel
Direkt schreiben ohne private Telefonnummern und WhatsApp; Daten bleiben auf dem Pi.

## 2. Abgrenzung
- **Übergabebuch** = dienstbezogene Infos für alle, die als Nächstes kommen.
- **Meldung** = etwas fehlt/ist kaputt/war nicht möglich → geht an die Admins, hat einen Status (Konzept 13).
- **Nachrichten** = Gespräch zwischen bestimmten Personen. Aus einer Nachricht wird per Tipp: Übergabe · Aufgabe · Termin · Listeneintrag · Meldung.

## 3. Unterhaltungen (geklärt)
- **Direkt** zwischen zwei Personen – **Assistenzkräfte untereinander standardmäßig nicht** (globale Einstellung, Standard aus).
- **Team** – alle aktiven Personen.
- **Gruppen** – nur von Admins angelegt (z. B. „Familie").
- Admins können Direktnachrichten anderer nicht mitlesen.
- Am Team-Tablet gibt es keine Nachrichten (persönlich).

## 4. Inhalte
- Text, Fotos, **Sprachnachrichten** (max. 2 Minuten) – **im Admin-Menü global ein-/ausschaltbar** (geklärt, Standard an).
- Antworten mit Zitat; eigene Nachrichten 15 Minuten bearbeiten, löschen; „dringend"; „gelesen" nur in Direktnachrichten (pro Person abschaltbar); Vorlesen.

## 5. Benachrichtigungen
- **In der App** (Stufe 1, immer): Glocke + „Seit deinem letzten Besuch" beim Anmelden; Live-Aktualisierung.
- **E-Mail** (jetzt **Stufe 1**, weil SMTP für 2FA ohnehin eingerichtet ist): pro Person aus/sofort/täglich (Tageszusammenfassung um 18 Uhr, einstellbar), Standard aus; Inhalt standardmäßig ohne Details, pro Person „mit Titel" einschaltbar.
- **Push** (Stufe 4): pro Person einschaltbar, Standard aus, vom Admin global sperrbar.
- **Regeln je Ereignis** (App · E-Mail · Push) unter „Mein Konto → Benachrichtigungen", Standards je Rolle – Tabelle in Konzept 13 §7.
- Ereignisse: **neue Meldung** · Meldung erledigt · Frage an Admins · wichtige Übergabe · neue Nachricht · Dienst neu/verschoben/abgesagt · Urlaub (Antrag/Entscheidung) · Aufgabe zugewiesen/weitergegeben · Gesprächspunkt fällig · Termin · Fristen · Checklisten · Anleitung vorgeschlagen/„stimmt nicht mehr" · Wartung überfällig.

## 6. Erreichbarkeit
Nachrichten außerhalb des Dienstes müssen nicht beantwortet werden; Ruhezeiten pro Person (gelten für E-Mail/Push, auch bei dringenden Meldungen); Hinweis „Im Notfall anrufen oder 112".

## 7. Austritt & Löschung
Austritt: raus aus Team und Gruppen; Direktnachrichten schreibgeschützt, nach 30 Tagen gelöscht. **Löschfrist 365 Tage** (geklärt); Benachrichtigungen 30 Tage.

## 8. KI
`nachricht_senden` mit Bestätigungskarte; Vorlesen lokal – Nachrichteninhalte gehen nicht an den KI-Anbieter.

## 9. Technik
Mercure (in FrankenPHP) für Live-Aktualisierung; E-Mail über SMTP (Messenger-Warteschlange, Tageszusammenfassung per Scheduler); optional Web Push; keine Ende-zu-Ende-Verschlüsselung (offen benannt).

## 10. Datenbank
`chat_conversation`, `chat_message`, `chat_participant`, Medien mit `chat_message_id` (Foto, Sprachnachricht), `notification`, `notification_preference`, `push_subscription`; Einstellungen in `app_setting` (`voice_messages_enabled`, `assistant_direct_chat`, `push_allowed`, `daily_digest_time`, `retention_chat_days`) und `app_user` (`notify_email`, `notify_push`, `notify_mail_details`, Ruhezeit, Lesebestätigung).
