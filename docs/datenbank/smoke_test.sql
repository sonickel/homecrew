-- =====================================================================
--  Smoke-Test für schema.sql (MariaDB 11.8 LTS)
--
--  Auf einer LEEREN Datenbank ausführen – nie auf homecrew oder homecrew_test
--  (Befehl auch in docs/konzept/konzept-6-datenbank.md §8):
--    mariadb -e "DROP DATABASE IF EXISTS homecrew_smoke; CREATE DATABASE homecrew_smoke CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--    mariadb homecrew_smoke < docs/datenbank/schema.sql
--    mariadb homecrew_smoke < docs/datenbank/smoke_test.sql
--
--  Erwartung: Die Ergebnisliste enthält nur Zeilen mit „OK", die letzte
--  Zeile lautet „ALLE PRÜFUNGEN OK". Bricht das Skript vorher mit einem
--  Fehler ab, ist ein gültiger Testdatensatz abgelehnt worden → melden.
-- =====================================================================

SET time_zone = '+00:00';

CREATE TEMPORARY TABLE _smoke (
  nr        INT AUTO_INCREMENT PRIMARY KEY,
  pruefung  VARCHAR(120),
  ergebnis  VARCHAR(255)
);

DELIMITER //

-- Erwartet, dass p_sql mit Fehlernummer p_errno abgelehnt wird
-- (1062 = doppelt, 4025 = CHECK verletzt, 1451 = noch referenziert)
CREATE OR REPLACE PROCEDURE _expect_fail(IN p_label VARCHAR(120), IN p_sql TEXT, IN p_errno INT)
BEGIN
  DECLARE v_errno INT DEFAULT NULL;
  DECLARE v_msg   VARCHAR(255) DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR SQLEXCEPTION
  BEGIN
    GET DIAGNOSTICS CONDITION 1 v_errno = MYSQL_ERRNO, v_msg = MESSAGE_TEXT;
  END;
  SET @_sql = p_sql;
  PREPARE _st FROM @_sql;
  IF v_errno IS NULL THEN
    EXECUTE _st;
    DEALLOCATE PREPARE _st;
  END IF;
  INSERT INTO _smoke (pruefung, ergebnis) VALUES (p_label,
    CASE
      WHEN v_errno IS NULL    THEN 'FEHLER – wurde angenommen'
      WHEN v_errno = p_errno  THEN CONCAT('OK – abgelehnt (', v_errno, ')')
      ELSE CONCAT('FEHLER – anderer Fehler ', v_errno, ': ', LEFT(v_msg, 150))
    END);
END//

CREATE OR REPLACE PROCEDURE _expect(IN p_label VARCHAR(120), IN p_ok BOOLEAN)
BEGIN
  INSERT INTO _smoke (pruefung, ergebnis) VALUES (p_label, IF(p_ok, 'OK', 'FEHLER'));
END//

-- Zählt alle Zeilen in allen Tabellen, die per Fremdschlüssel auf diese Person zeigen
CREATE OR REPLACE PROCEDURE _count_refs(IN p_user BIGINT, OUT p_total BIGINT)
BEGIN
  DECLARE v_done INT DEFAULT 0;
  DECLARE v_table VARCHAR(64);
  DECLARE v_column VARCHAR(64);
  DECLARE cur CURSOR FOR
    SELECT table_name, column_name FROM information_schema.key_column_usage
     WHERE table_schema = DATABASE() AND referenced_table_name = 'app_user';
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_done = 1;
  SET p_total = 0;
  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO v_table, v_column;
    IF v_done = 1 THEN LEAVE read_loop; END IF;
    SET @_cnt_sql = CONCAT('SELECT COUNT(*) INTO @_cnt FROM `', v_table, '` WHERE `', v_column, '` = ', p_user);
    PREPARE _c FROM @_cnt_sql;
    EXECUTE _c;
    DEALLOCATE PREPARE _c;
    SET p_total = p_total + @_cnt;
  END LOOP;
  CLOSE cur;
END//

DELIMITER ;

-- ---------------------------------------------------------------------
--  A. Gültige Testdaten – müssen fehlerfrei durchlaufen
-- ---------------------------------------------------------------------

-- Erweiterungen: KI-Schlüssel hinterlegt und geprüft → KI einschaltbar; SMTP-Zugang eingerichtet → E-Mail-Funktionen sichtbar
UPDATE app_setting SET household_name = 'Test-Haushalt',
       ai_api_key_enc = 'chiffrat', ai_api_key_hint = 'a1b2', ai_key_status = 'ok', ai_key_checked_at = NOW(), ai_enabled = 1,
       mail_enabled = 1, smtp_host = 'smtp.example.org', smtp_port = 587, smtp_username = 'homecrew@example.org',
       smtp_password_enc = 'chiffrat', mail_from_address = 'homecrew@example.org', mail_from_name = 'HomeCrew', mail_status = 'ok'
 WHERE id = 1;

-- Personen: Manager (immer Admin), Schwester (Angehörige mit Admin-Recht), zwei Assistenzkräfte
INSERT INTO app_user (public_id, role, is_admin, username, display_name, active_from, font_size, password_hash, twofa_method)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'manager', 1, 'manager', 'Ich', '2026-01-01', 'gross', 'pw-hash', 'passkey');
SET @m_mgr = LAST_INSERT_ID();
INSERT INTO app_user (public_id, role, username, display_name, active_from, pin_hash)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'assistenz', 'martin', 'Martin', '2026-01-01', 'argon2-hash');
SET @m_martin = LAST_INSERT_ID();
INSERT INTO app_user (public_id, role, username, display_name, active_from, password_hash)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'assistenz', 'anna', 'Anna', '2026-10-01', 'pw-hash');
SET @m_anna = LAST_INSERT_ID();
INSERT INTO app_user (public_id, role, is_admin, username, display_name, active_from)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'angehoerige', 1, 'schwester', 'Schwester', '2026-01-01');
SET @m_fam = LAST_INSERT_ID();

INSERT INTO device (name, token_hash, paired_by_user_id)
VALUES ('Tablet Flur', SHA2('geraet', 256), @m_mgr);
SET @dev = LAST_INSERT_ID();

INSERT INTO employee_record (user_id, entry_date, workdays_per_week) VALUES (@m_martin, '2026-01-01', 1.0);
INSERT INTO employee_record (user_id, entry_date, workdays_per_week) VALUES (@m_anna,   '2026-10-01', 2.0);
INSERT INTO wage_rate (user_id, valid_from, hourly_rate) VALUES (@m_martin, '2026-01-01', 14.50);
INSERT INTO wage_rate (user_id, valid_from, hourly_rate) VALUES (@m_anna,   '2026-10-01', 15.00);
INSERT INTO vacation_entitlement (user_id, year, days) VALUES (@m_martin, 2026, 4.0);
INSERT INTO funding_grant (valid_from, valid_until, monthly_budget) VALUES ('2026-01-01', '2027-12-31', 1450.00);

-- Orte, Hilfsmittel, Wissen
INSERT INTO poi (public_id, name, category, city, latitude, longitude, a11y_step_free)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'Sanitätshaus', 'sanitaetshaus', 'Würzburg', 49.791300, 9.953500, 'ja');
SET @poi = LAST_INSERT_ID();

INSERT INTO asset (public_id, name, asset_type, supplier_poi_id)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'Aktivrollstuhl', 'Rollstuhl', @poi);
SET @asset = LAST_INSERT_ID();
INSERT INTO asset_component (asset_id, name, component_type) VALUES (@asset, 'Reifen hinten links', 'reifen');
SET @comp = LAST_INSERT_ID();
INSERT INTO asset_component_property (component_id, prop_key, label, value_num, unit, is_target, tolerance_pct)
VALUES (@comp, 'solldruck', 'Solldruck', 6.00, 'bar', 1, 10.00);
INSERT INTO asset_component_property (component_id, prop_key, label, value_text)
VALUES (@comp, 'ventil', 'Ventil', 'Sclaverand');

INSERT INTO guide (public_id, guide_type, created_by_user_id)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'anleitung', @m_martin);
SET @guide = LAST_INSERT_ID();
INSERT INTO guide_version (guide_id, version_no, status, title, author_user_id, published_at)
VALUES (@guide, 1, 'veroeffentlicht', 'Reifen aufpumpen', @m_martin, NOW());
SET @gv1 = LAST_INSERT_ID();
INSERT INTO guide_step (version_id, sort, body, hint_type, hint_text)
VALUES (@gv1, 1, 'Ventilkappe abschrauben und die Pumpe mit dem Sclaverand-Adapter aufsetzen.', 'achtung', 'Nicht über den Solldruck pumpen.');
SET @step = LAST_INSERT_ID();
INSERT INTO guide_version (guide_id, version_no, status, title, author_user_id)
VALUES (@guide, 2, 'entwurf', 'Reifen aufpumpen (neu)', @m_anna);
INSERT INTO guide_tag (guide_id, tag) VALUES (@guide, 'rollstuhl');
INSERT INTO asset_guide (asset_id, guide_id) VALUES (@asset, @guide);

-- Wer kennt welche Anleitung: Anna hat Version 1 gelesen, Martin wurde vom Manager eingewiesen
INSERT INTO guide_acknowledgement (guide_id, user_id, read_at, read_version_no) VALUES (@guide, @m_anna, NOW(), 1);
INSERT INTO guide_acknowledgement (guide_id, user_id, instructed_at, instructed_by_user_id) VALUES (@guide, @m_martin, NOW(), @m_mgr);

INSERT INTO profile_item (section, kind, allergen_code, title, severity, visibility)
VALUES ('unvertraeglichkeit', 'unvertraeglichkeit', 'milch', 'Laktose', 2, 'team');
SET @prof = LAST_INSERT_ID();
INSERT INTO profile_keyword (profile_item_id, keyword) VALUES (@prof, 'Milch');
INSERT INTO profile_item (section, title, visibility) VALUES ('essen', 'Kaffee schwarz', 'team');
INSERT INTO emergency_contact (name, relation, phone, when_to_call) VALUES ('Schwester', 'Familie', '+49 931 0000000', 'immer zuerst');
INSERT INTO emergency_contact (category, name, phone, poi_id, show_on_lockscreen) VALUES ('arzt', 'Hausarztpraxis', '+49 931 1111111', @poi, 0);

-- Dienstplan & Arbeitszeit
INSERT INTO shift_pattern (user_id, weekday, start_time, end_time, valid_from)
VALUES (@m_martin, 2, '09:00:00', '13:00:00', '2026-11-03');
SET @pattern = LAST_INSERT_ID();
INSERT INTO planned_shift (user_id, shift_pattern_id, occurrence_date, starts_at, ends_at)
VALUES (@m_martin, @pattern, '2026-11-03', '2026-11-03 09:00:00', '2026-11-03 13:00:00');
SET @ps1 = LAST_INSERT_ID();
INSERT INTO planned_shift (user_id, shift_pattern_id, occurrence_date, starts_at, ends_at)
VALUES (@m_martin, @pattern, '2026-11-10', '2026-11-10 09:00:00', '2026-11-10 13:00:00');
SET @ps2 = LAST_INSERT_ID();
-- Manager verschiebt den Dienst vom 10.11. auf 11–15 Uhr (ursprünglicher Muster-Tag bleibt)
UPDATE planned_shift SET starts_at = '2026-11-10 11:00:00', ends_at = '2026-11-10 15:00:00', is_modified = 1 WHERE id = @ps2;
INSERT INTO planned_shift (user_id, starts_at, ends_at, status, cancel_reason)
VALUES (@m_anna, '2026-11-04 14:00:00', '2026-11-04 17:00:00', 'ausgefallen', 'krank');

INSERT INTO time_entry (user_id, planned_shift_id, started_at, start_source, device_id)
VALUES (@m_martin, @ps1, '2026-11-03 09:02:00', 'tablet', @dev);
SET @te1 = LAST_INSERT_ID();
UPDATE time_entry SET ended_at = '2026-11-03 13:05:00', duration_minutes = 243, end_source = 'handy' WHERE id = @te1;
INSERT INTO time_entry (user_id, started_at, start_source)
VALUES (@m_martin, '2026-11-10 09:00:00', 'handy');     -- neuer offener Dienst ist erlaubt, der alte ist beendet
INSERT INTO time_entry (user_id, entry_kind, started_at, ended_at, duration_minutes, start_source, end_source)
VALUES (@m_anna, 'einarbeitung', '2026-10-01 08:00:00', '2026-10-01 10:00:00', 120, 'tablet', 'tablet');
-- Martin ändert am selben Tag selbst (ohne Begründung), später korrigiert ein Admin (mit Begründung)
INSERT INTO time_entry_change (time_entry_id, kind, changed_by_user_id, old_ended_at, new_ended_at)
VALUES (@te1, 'selbst', @m_martin, '2026-11-03 13:00:00', '2026-11-03 13:05:00');
INSERT INTO time_entry_change (time_entry_id, kind, changed_by_user_id, old_ended_at, new_ended_at, reason)
VALUES (@te1, 'admin', @m_fam, '2026-11-03 13:05:00', '2026-11-03 13:15:00', 'Einkauf dauerte länger');

INSERT INTO absence (user_id, absence_type, date_from, date_to, status, sick_note_presented)
VALUES (@m_anna, 'krank', '2026-11-04', '2026-11-05', 'erfasst', 1);
SET @abs = LAST_INSERT_ID();
INSERT INTO sick_pay_refund (absence_id, continued_pay) VALUES (@abs, 45.00);
INSERT INTO timesheet_confirmation (user_id, year, month) VALUES (@m_martin, 2026, 11);

-- Lohnzeile: 243 min × 14,50 € = 58,73 € · RV-Eigenanteil = 175 × 18,6 % − 58,73 × 5 % = 29,61 €
INSERT INTO monthly_closing (year, month, status, closed_by_user_id, closed_at)
VALUES (2026, 11, 'abgeschlossen', @m_mgr, NOW());
SET @closing = LAST_INSERT_ID();
INSERT INTO payroll_line (monthly_closing_id, user_id, minutes_worked, gross_work, gross_total,
                          rv_employee_share, payout, employer_contributions, total_cost, minijob_limit)
VALUES (@closing, @m_martin, 243, 58.73, 58.73, 29.61, 29.12, 8.59, 67.32, 603.00);

-- Aufgaben & Wartungsplan
INSERT INTO task_template (title, category, proof_type, guide_id, asset_id, recurrence_type, interval_days,
                           earliest_after_days, window_days, starts_on, created_by_user_id)
VALUES ('Reifen prüfen und aufpumpen', 'hilfsmittel', 'messwert', @guide, @asset, 'intervall', 14, 12, 3, '2026-11-01', @m_mgr);
SET @tpl = LAST_INSERT_ID();
INSERT INTO task_template (title, category, recurrence_type, rrule, window_days, starts_on)
VALUES ('Seifenspender prüfen', 'haushalt', 'kalender', 'FREQ=WEEKLY;BYDAY=MO', 6, '2026-11-02');

INSERT INTO task (template_id, occurrence_date, title, category, proof_type, guide_id, asset_id, window_start, due_date)
VALUES (@tpl, '2026-11-05', 'Reifen prüfen und aufpumpen', 'hilfsmittel', 'messwert', @guide, @asset, '2026-11-02', '2026-11-05');
SET @task = LAST_INSERT_ID();
INSERT INTO task_item (task_id, label, target_value, unit, asset_component_id)
VALUES (@task, 'Reifen hinten links', 6.00, 'bar', @comp);
SET @titem = LAST_INSERT_ID();
INSERT INTO task_completion (task_id, action, user_id, done_at, comment)
VALUES (@task, 'erledigt', @m_anna, '2026-11-04 10:15:00', 'leicht zu wenig Luft');
SET @compl = LAST_INSERT_ID();
UPDATE task SET status = 'erledigt' WHERE id = @task;
INSERT INTO task_item_result (completion_id, task_item_id, is_checked, measured_value)
VALUES (@compl, @titem, 1, 5.40);
SET @tres = LAST_INSERT_ID();
INSERT INTO component_measurement (component_id, measured_on, measured_value, target_value, unit, task_item_result_id)
VALUES (@comp, '2026-11-04', 5.40, 6.00, 'bar', @tres);

-- Gesprächspunkt per KI: „beim nächsten Mal mit Martin besprechen"
INSERT INTO task (title, category, assignee_user_id, visibility, due_rule, due_date, created_via, created_by_user_id)
VALUES ('Mit Martin über Urlaubsplanung sprechen', 'gespraech', @m_martin, 'privat', 'naechster_dienst', '2026-11-10', 'ki', @m_mgr);
-- Aufgabe zunächst an die Admins, dann von der Schwester an die Rolle Assistenz weitergegeben
INSERT INTO task (title, category, assignee_role, due_date, created_by_user_id)
VALUES ('Waschmittel besorgen', 'einkauf', 'admins', '2026-11-12', @m_mgr);
UPDATE task SET assignee_role = 'assistenz', assigned_by_user_id = @m_fam, assigned_at = NOW() WHERE id = LAST_INSERT_ID();

INSERT INTO handover_note (author_user_id, note_type, body, is_pinned, delete_after)
VALUES (@m_anna, 'wichtig', 'Nachfüllseife ist alle.', 1, '2026-12-04');
SET @note = LAST_INSERT_ID();
INSERT INTO handover_note (parent_id, author_user_id, body, delete_after)
VALUES (@note, @m_mgr, 'Danke, steht auf der Einkaufsliste.', '2026-12-04');
INSERT INTO handover_read (handover_note_id, user_id) VALUES (@note, @m_martin);

INSERT INTO appointment (title, starts_at, poi_id, needs_escort, assignee_role, visibility, created_via, created_by_user_id)
VALUES ('Termin Sanitätshaus', '2026-11-06 10:00:00', @poi, 1, 'assistenz', 'zustaendig', 'ki', @m_mgr);
SET @appt = LAST_INSERT_ID();
INSERT INTO appointment_item (appointment_id, label) VALUES (@appt, 'Versichertenkarte');
INSERT INTO appointment_participant (appointment_id, user_id, role) VALUES (@appt, @m_martin, 'begleitung');
INSERT INTO appointment (title, starts_at, all_day, visibility) VALUES ('Privat', '2026-11-07 00:00:00', 1, 'privat');

-- Besuche externer Dienste: Physio jeden Dienstag 10 Uhr, Caritas alle 14 Tage donnerstags 13 Uhr – nur Info, für alle sichtbar
INSERT INTO visitor (name, kind, phone, team_note, color)
VALUES ('Physiotherapie', 'therapie', '0931 000000', 'Braucht Platz im Wohnzimmer – Assistenz muss nicht dabei sein', '#2E7D32');
SET @vis_physio = LAST_INSERT_ID();
INSERT INTO visitor (name, kind, team_note) VALUES ('Caritas Haushaltshilfe', 'haushalt', 'Putzt Bad und Küche – an dem Tag dort nicht putzen');
SET @vis_caritas = LAST_INSERT_ID();
INSERT INTO appointment_series (kind, title, visitor_id, rrule, start_time, duration_minutes, starts_on, generated_until, created_by_user_id)
VALUES ('besuch', 'Physio', @vis_physio, 'FREQ=WEEKLY;BYDAY=TU', '10:00:00', 60, '2026-11-03', '2026-11-10', @m_mgr);
SET @series_physio = LAST_INSERT_ID();
INSERT INTO appointment (kind, title, visitor_id, series_id, occurrence_date, starts_at, ends_at)
VALUES ('besuch', 'Physio', @vis_physio, @series_physio, '2026-11-03', '2026-11-03 10:00:00', '2026-11-03 11:00:00');
INSERT INTO appointment (kind, title, visitor_id, series_id, occurrence_date, starts_at, ends_at)
VALUES ('besuch', 'Physio', @vis_physio, @series_physio, '2026-11-10', '2026-11-10 10:00:00', '2026-11-10 11:00:00');
SET @visit_physio2 = LAST_INSERT_ID();
-- Physio sagt den 10.11. ab → bleibt sichtbar, durchgestrichen
UPDATE appointment SET status = 'abgesagt', cancel_note = 'Praxis geschlossen', is_modified = 1 WHERE id = @visit_physio2;
INSERT INTO appointment_series (kind, title, visitor_id, rrule, start_time, duration_minutes, starts_on)
VALUES ('besuch', 'Caritas (Putzen)', @vis_caritas, 'FREQ=WEEKLY;INTERVAL=2;BYDAY=TH', '13:00:00', 120, '2026-11-05');
SET @series_caritas = LAST_INSERT_ID();
INSERT INTO appointment (kind, title, visitor_id, series_id, occurrence_date, starts_at, ends_at)
VALUES ('besuch', 'Caritas (Putzen)', @vis_caritas, @series_caritas, '2026-11-05', '2026-11-05 13:00:00', '2026-11-05 15:00:00');
-- Einzelner Besuch ohne Serie (Handwerker), sowie eine Termin-Serie mit Begleitung (Ergotherapie in der Praxis)
INSERT INTO appointment (kind, title, starts_at, note) VALUES ('besuch', 'Heizungswartung', '2026-11-12 08:00:00', 'Zugang zum Heizungsraum');
INSERT INTO appointment_series (title, poi_id, rrule, start_time, duration_minutes, starts_on, needs_escort, assignee_role)
VALUES ('Ergotherapie', @poi, 'FREQ=WEEKLY;BYDAY=FR', '09:00:00', 45, '2026-11-06', 1, 'assistenz');

-- Wartungsplan einer bestimmten Rolle zugeordnet, „zuletzt erledigt" gepflegt
INSERT INTO task_template (title, category, assignee_role, recurrence_type, interval_days, starts_on)
VALUES ('Bettwäsche wechseln', 'haushalt', 'assistenz', 'intervall', 14, '2026-11-01');
UPDATE task_template SET last_done_at = '2026-11-04 10:15:00', next_due_on = '2026-11-18' WHERE id = @tpl;
-- Persönliche Routine einer Assistenz (alle 2 Jahre, sichtbar nur für sie und den Manager)
INSERT INTO task_template (title, category, assignee_user_id, visibility, recurrence_type, rrule, starts_on)
VALUES ('Erste-Hilfe-Kurs auffrischen', 'sonstiges', @m_martin, 'zustaendig', 'kalender', 'FREQ=YEARLY;INTERVAL=2', '2026-11-01');

-- Abgesagter Dienst wegen Abwesenheit des Managers, trotzdem bezahlt
INSERT INTO planned_shift (user_id, starts_at, ends_at, status, cancel_reason, cancel_paid)
VALUES (@m_martin, '2026-12-22 09:00:00', '2026-12-22 13:00:00', 'ausgefallen', 'manager_abwesend', 1);

INSERT INTO calendar_feed (user_id, token_hash) VALUES (@m_martin, SHA2('feed', 256));

-- Team-Tablet: Gerät ohne Personenanmeldung erledigt eine Aufgabe, schreibt eine Übergabe, ergänzt die Liste
UPDATE device SET mode = 'team', dim_after_seconds = 600, font_size = 'sehr_gross' WHERE id = @dev;
INSERT INTO task (title, category, assignee_role, due_date) VALUES ('Blumen gießen', 'haushalt', 'assistenz', '2026-11-05');
SET @task_team = LAST_INSERT_ID();
INSERT INTO task_completion (task_id, action, device_id, done_at) VALUES (@task_team, 'erledigt', @dev, '2026-11-05 11:00:00');
UPDATE task SET status = 'erledigt' WHERE id = @task_team;
INSERT INTO handover_note (device_id, body, delete_after) VALUES (@dev, 'Post liegt auf dem Küchentisch.', '2026-12-05');

-- Nachrichten: Team-Chat, Direktchat Manager ↔ Martin, Foto + Sprachnachricht
INSERT INTO chat_conversation (kind) VALUES ('team');
SET @conv_team = LAST_INSERT_ID();
INSERT INTO chat_conversation (kind, direct_key, created_by_user_id)
VALUES ('direkt', CONCAT(LEAST(@m_mgr, @m_martin), ':', GREATEST(@m_mgr, @m_martin)), @m_mgr);
SET @conv_direct = LAST_INSERT_ID();
INSERT INTO chat_conversation (kind, title) VALUES ('gruppe', 'Familie');
INSERT INTO chat_message (conversation_id, author_user_id, body, delete_after)
VALUES (@conv_direct, @m_mgr, 'Kannst du morgen eine Stunde später kommen?', '2027-05-01');
SET @msg1 = LAST_INSERT_ID();
INSERT INTO chat_message (conversation_id, author_user_id, reply_to_id, delete_after)
VALUES (@conv_direct, @m_martin, @msg1, '2027-05-01');     -- nur Sprachnachricht, kein Text
SET @msg2 = LAST_INSERT_ID();
INSERT INTO media (public_id, kind, status, chat_message_id, duration_seconds)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'audio', 'bereit', @msg2, 8);
SET @media_voice = LAST_INSERT_ID();
INSERT INTO chat_message (conversation_id, kind, body, created_via, delete_after)
VALUES (@conv_team, 'system', 'Anna ist dem Team beigetreten.', 'system', '2027-05-01');
INSERT INTO chat_participant (conversation_id, user_id, last_read_message_id) VALUES (@conv_direct, @m_mgr, @msg2);
INSERT INTO chat_participant (conversation_id, user_id, last_read_message_id) VALUES (@conv_direct, @m_martin, @msg1);
INSERT INTO chat_participant (conversation_id, user_id) VALUES (@conv_team, @m_anna);

-- Benachrichtigungen in der App; Push-Abo nur, weil der Manager es selbst eingeschaltet hat
INSERT INTO notification (user_id, kind, title, link, delete_after)
VALUES (@m_martin, 'nachricht', 'Neue Nachricht', '/nachrichten/1', '2026-12-01');
UPDATE app_user SET notify_push = 1, quiet_from = '20:00:00', quiet_until = '08:00:00' WHERE id = @m_mgr;
INSERT INTO push_subscription (user_id, endpoint_hash, endpoint, key_p256dh, key_auth, device_label)
VALUES (@m_mgr, SHA2('https://push.example/abc', 256), 'https://push.example/abc', 'p256dh-key', 'auth-key', 'Handy');

-- E-Mail: 2FA-Code per E-Mail für den Manager, Passwort-Reset, tägliche Zusammenfassung
UPDATE app_user SET email = 'manager@example.org', email_verified_at = NOW(), twofa_method = 'email',
       email_code_hash = SHA2('123456', 256), email_code_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE) WHERE id = @m_mgr;
INSERT INTO password_reset_request (user_id, selector, hashed_token, expires_at)
VALUES (@m_martin, 'sel123', SHA2('token', 256), DATE_ADD(NOW(), INTERVAL 1 HOUR));
UPDATE app_user SET notify_email = 'taeglich' WHERE id = @m_anna;
UPDATE notification SET mailed_at = NOW() WHERE user_id = @m_martin;

-- PIN-Anmeldung: für Martin aktiviert; „nur Tablet"-Person Ben (Benutzername + Start-PIN vom Admin, ohne Passwort/E-Mail)
UPDATE app_user SET pin_login_enabled = 1 WHERE id = @m_martin;
INSERT INTO app_user (public_id, role, username, display_name, active_from, pin_hash, pin_login_enabled, pin_must_change)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'assistenz', 'ben', 'Ben', '2026-11-01', 'argon2-hash-ben', 1, 1);
SET @m_ben = LAST_INSERT_ID();

-- Übersetzung einer Anleitung (Stufe 5)
INSERT INTO guide_translation (version_id, language, title, provider_model)
VALUES (@gv1, 'pl', 'Pompowanie opon', 'test-model');
SET @tr = LAST_INSERT_ID();
INSERT INTO guide_step_translation (translation_id, step_id, body, hint_text)
VALUES (@tr, @step, 'Odkręć nasadkę wentyla …', 'Nie pompuj powyżej ciśnienia zadanego.');

-- Checkliste Einstellung für Ben (Punkte zunächst bei den Admins oder direkt bei einer Rolle)
INSERT INTO checklist_template (kind, title) VALUES ('einstellung', 'Neue Assistenz');
SET @cl_tpl = LAST_INSERT_ID();
INSERT INTO checklist_template_item (template_id, sort, label, due_rule, assignee_role, auto_key)
VALUES (@cl_tpl, 1, 'Person in der App anlegen', 'vor_beginn', 'admins', 'person_angelegt'),
       (@cl_tpl, 2, 'Anmeldung bei der Minijob-Zentrale (Haushaltsscheck)', 'vor_beginn', 'manager', NULL),
       (@cl_tpl, 3, 'Neue Person in die Wohnung einweisen', 'erster_tag', 'assistenz', NULL);
INSERT INTO checklist (user_id, template_id, kind, reference_date) VALUES (@m_ben, @cl_tpl, 'einstellung', '2026-11-01');
SET @cl = LAST_INSERT_ID();
INSERT INTO checklist_item (checklist_id, sort, label, assignee_role, auto_key, done_at) VALUES (@cl, 1, 'Person in der App anlegen', 'admins', 'person_angelegt', NOW());
INSERT INTO checklist_item (checklist_id, sort, label, is_skipped, note) VALUES (@cl, 2, 'Erweitertes Führungszeugnis', 1, 'nicht verlangt');
INSERT INTO checklist_item (checklist_id, sort, label, assignee_user_id) VALUES (@cl, 3, 'Neue Person in die Wohnung einweisen', @m_martin);

-- Mehrere Listen: Einkauf durch die Assistenz, Besorgen durch die Familie
INSERT INTO shopping_list (name, assignee_role) VALUES ('Einkauf', 'assistenz');
SET @list_shop = LAST_INSERT_ID();
INSERT INTO shopping_list (name, assignee_user_id) VALUES ('Besorgt die Familie', @m_fam);
SET @list_family = LAST_INSERT_ID();
INSERT INTO shopping_item (list_id, name, source, source_task_id) VALUES (@list_family, 'Nachfüllseife (Großpackung)', 'aufgabe', @task);
INSERT INTO shopping_item (list_id, name, is_done, done_at) VALUES (@list_shop, 'Brot', 1, NOW());
INSERT INTO shopping_favorite (list_id, name, poi_id) VALUES (@list_shop, 'Dinkelbrot', @poi);
INSERT INTO shopping_item (list_id, name, created_by_device_id) VALUES (@list_shop, 'Spülmittel', @dev);

-- Meldungen: „Seifenspender auffüllen" ist am Team-Tablet nicht möglich → Vorschlag aus dem Wartungsplan
-- („Flüssigseife" auf „Besorgt die Familie"); Meldung geht an die Admins und schließt sich, sobald abgehakt
INSERT INTO task_template (title, category, assignee_role, recurrence_type, interval_days, starts_on, unable_list_id, unable_item_name)
VALUES ('Seifenspender auffüllen', 'haushalt', 'assistenz', 'intervall', 7, '2026-11-01', @list_family, 'Flüssigseife');
SET @tpl_soap = LAST_INSERT_ID();
INSERT INTO task (template_id, occurrence_date, title, category, assignee_role, due_date)
VALUES (@tpl_soap, '2026-11-05', 'Seifenspender auffüllen', 'haushalt', 'assistenz', '2026-11-05');
SET @task_soap = LAST_INSERT_ID();
INSERT INTO task_completion (task_id, action, device_id, done_at, comment)
VALUES (@task_soap, 'nicht_moeglich', @dev, '2026-11-05 12:00:00', 'Keine Nachfüllseife mehr da');
UPDATE task SET status = 'nicht_moeglich' WHERE id = @task_soap;
INSERT INTO shopping_item (list_id, name, source, source_task_id, created_by_device_id)
VALUES (@list_family, 'Flüssigseife', 'meldung', @task_soap, @dev);
SET @item_soap = LAST_INSERT_ID();
INSERT INTO issue_report (public_id, kind, title, note, task_id, created_by_device_id, status, follow_up, follow_up_shopping_item_id)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'nicht_moeglich', 'Seife leer', 'Keine Nachfüllseife mehr da', @task_soap, @dev, 'in_arbeit', 'liste', @item_soap);
SET @rep_soap = LAST_INSERT_ID();
INSERT INTO notification (user_id, kind, title, link, delete_after)
SELECT id, 'meldung', 'Neue Meldung', CONCAT('/meldungen/', @rep_soap), '2026-12-05' FROM app_user WHERE is_admin = 1;
-- Nächste Assistenz bemerkt es auch → „auch bemerkt" statt doppelter Meldung
UPDATE issue_report SET confirm_count = 1, last_confirmed_at = '2026-11-06 09:00:00' WHERE id = @rep_soap;
-- Schwester hakt die Seife ab → Meldung schließt sich automatisch (ohne Person)
UPDATE shopping_item SET is_done = 1, done_at = '2026-11-07 17:00:00' WHERE id = @item_soap;
UPDATE issue_report SET status = 'erledigt', closed_at = '2026-11-07 17:00:00', close_note = 'automatisch: Listeneintrag abgehakt',
       delete_after = '2027-11-07' WHERE id = @rep_soap;
-- Meldung über den Knopf „Melden" (ohne Aufgabe): Martin, dringend, mit Foto und Bezug zum Hilfsmittel
INSERT INTO issue_report (public_id, kind, title, is_urgent, asset_id, created_by_user_id)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'kaputt', 'Ladegerät Rollstuhl lädt nicht', 1, @asset, @m_martin);
SET @rep_charger = LAST_INSERT_ID();
INSERT INTO media (public_id, kind, status, issue_report_id) VALUES (UNHEX(REPLACE(UUID(),'-','')), 'foto', 'bereit', @rep_charger);
SET @media_report = LAST_INSERT_ID();
-- Benachrichtigungsregeln: Manager will Meldungen sofort per E-Mail (mit Titel) und „erledigt" in der App; Schwester nur in der App
UPDATE app_user SET notify_email = 'sofort', notify_mail_details = 1 WHERE id = @m_mgr;
INSERT INTO notification_preference (user_id, event_type, via_app, via_email) VALUES (@m_mgr, 'meldung', 1, 1);
INSERT INTO notification_preference (user_id, event_type, via_app) VALUES (@m_mgr, 'meldung_erledigt', 1);
INSERT INTO notification_preference (user_id, event_type, via_app, via_email) VALUES (@m_fam, 'meldung', 1, 0);
INSERT INTO notification_preference (user_id, event_type, via_app) VALUES (@m_martin, 'nachricht', 1);

-- Arbeitszeit ohne persönliche Anmeldung: Admin übernimmt Bens geplanten Dienst als Arbeitszeit
INSERT INTO planned_shift (user_id, starts_at, ends_at) VALUES (@m_ben, '2026-11-06 14:00:00', '2026-11-06 18:00:00');
SET @ps_ben = LAST_INSERT_ID();
INSERT INTO time_entry (user_id, planned_shift_id, started_at, ended_at, duration_minutes, start_source, end_source)
VALUES (@m_ben, @ps_ben, '2026-11-06 13:00:00', '2026-11-06 17:00:00', 240, 'dienstplan', 'dienstplan');
INSERT INTO time_entry_change (time_entry_id, kind, changed_by_user_id, new_started_at, new_ended_at, reason)
VALUES (LAST_INSERT_ID(), 'nachtrag', @m_fam, '2026-11-06 13:00:00', '2026-11-06 17:00:00', 'Aus Dienstplan übernommen');

-- Medien: Wartungsplan-Foto, Video am Bauteil, Foto in der Anleitung, Nachweisfoto
INSERT INTO media (public_id, kind, status, task_template_id, caption)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'foto', 'bereit', @tpl, 'So sieht das Ventil aus');
INSERT INTO media (public_id, kind, status, asset_component_id, duration_seconds, has_audio)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'video', 'bereit', @comp, 75, 0);
INSERT INTO media (public_id, kind, status, guide_id)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'foto', 'bereit', @guide);
SET @media_guide = LAST_INSERT_ID();
INSERT INTO guide_step_media (step_id, media_id) VALUES (@step, @media_guide);
INSERT INTO media (public_id, kind, status, task_completion_id)
VALUES (UNHEX(REPLACE(UUID(),'-','')), 'foto', 'bereit', @compl);
SET @media_proof = LAST_INSERT_ID();
INSERT INTO upload_session (media_id, token_hash, total_bytes, expires_at)
VALUES (@media_proof, SHA2('upload', 256), 1000000, '2026-11-05 00:00:00');

-- KI-Assistent
INSERT INTO ai_conversation (user_id, delete_after) VALUES (@m_mgr, '2026-11-08');
SET @conv = LAST_INSERT_ID();
INSERT INTO ai_message (conversation_id, role, content, input_mode)
VALUES (@conv, 'nutzer', 'Trag mir Freitag 10 Uhr Sanitätshaus ein', 'sprache');
INSERT INTO ai_action (conversation_id, user_id, tool_name, arguments, status, result_entity_type, result_entity_id)
VALUES (@conv, @m_mgr, 'termin_anlegen', '{"titel":"Termin Sanitätshaus","beginn":"2026-11-06T10:00"}', 'ausgefuehrt', 'appointment', @appt);
INSERT INTO ai_usage_month (year, month, requests) VALUES (2026, 11, 1);

INSERT INTO reminder (kind, due_on, title, absence_id) VALUES ('u1_erstattung', '2026-11-15', 'U1-Erstattung beantragen', @abs);
INSERT INTO manager_absence (date_from, date_to, label) VALUES ('2026-12-20', '2027-01-03', 'Urlaub');
INSERT INTO audit_log (actor_user_id, via, action, entity_type, entity_id, details)
VALUES (@m_mgr, 'ki', 'termin.anlegen', 'appointment', @appt, '{"ok":true}');

CALL _expect('A: alle gültigen Testdaten eingefügt', TRUE);
SET @ok = (SELECT open_marker IS NULL FROM time_entry WHERE id = @te1);
CALL _expect('A: beendeter Dienst hat keinen Offen-Marker', @ok);
SET @ok = ((SELECT COUNT(*) FROM guide_step WHERE MATCH(body, hint_text) AGAINST('Pumpe' IN BOOLEAN MODE)) = 1);
CALL _expect('A: Volltextsuche findet „Pumpe" in Anleitungsschritten', @ok);
SET @ok = ((SELECT COUNT(*) FROM notification WHERE kind = 'meldung') = 2);
CALL _expect('A: Meldung „Seife leer" erreicht beide Admins', @ok);
SET @ok = ((SELECT status = 'erledigt' AND closed_by_user_id IS NULL AND created_by_user_id IS NULL AND created_by_device_id = @dev
            FROM issue_report WHERE id = @rep_soap) = 1);
CALL _expect('A: Meldung vom Team-Tablet automatisch erledigt (ohne Person)', @ok);
SET @ok = ((SELECT COUNT(*) FROM appointment WHERE kind = 'besuch' AND visibility = 'alle') = 4
           AND (SELECT COUNT(*) FROM appointment WHERE kind = 'besuch' AND status = 'abgesagt') = 1);
CALL _expect('A: Besuche für alle sichtbar, abgesagter Physio-Termin bleibt als „fällt aus" stehen', @ok);
SET @ok = ((SELECT ai_enabled = 1 AND ai_key_status = 'ok' AND mail_enabled = 1 FROM app_setting WHERE id = 1) = 1);
CALL _expect('A: KI und E-Mail eingerichtet (Schlüssel/Zugang hinterlegt)', @ok);

-- ---------------------------------------------------------------------
--  B. Regeln – jede Zeile muss abgelehnt werden
-- ---------------------------------------------------------------------

CALL _expect_fail('B01 Zweiter offener Dienst derselben Person',
  'INSERT INTO time_entry (user_id, started_at, start_source) VALUES (@m_martin, ''2026-11-11 09:00:00'', ''tablet'')', 1062);
CALL _expect_fail('B02 Dienstende vor Beginn',
  'INSERT INTO time_entry (user_id, started_at, ended_at, duration_minutes, start_source, end_source) VALUES (@m_anna, ''2026-11-03 10:00:00'', ''2026-11-03 09:00:00'', 0, ''tablet'', ''tablet'')', 4025);
CALL _expect_fail('B03 Beendeter Dienst ohne Dauer',
  'INSERT INTO time_entry (user_id, started_at, ended_at, start_source, end_source) VALUES (@m_anna, ''2026-11-03 09:00:00'', ''2026-11-03 10:00:00'', ''tablet'', ''tablet'')', 4025);
CALL _expect_fail('B04 Medium ohne Besitzer',
  'INSERT INTO media (public_id, kind) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''foto'')', 4025);
CALL _expect_fail('B05 Medium mit zwei Besitzern',
  'INSERT INTO media (public_id, kind, guide_id, poi_id) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''foto'', @guide, @poi)', 4025);
CALL _expect_fail('B06 Person erkennbar ohne Einwilligung',
  'INSERT INTO media (public_id, kind, poi_id, shows_person) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''foto'', @poi, 1)', 4025);
CALL _expect_fail('B07 Kalender-Wiederholung ohne RRULE',
  'INSERT INTO task_template (title, category, recurrence_type, starts_on) VALUES (''x'', ''haushalt'', ''kalender'', ''2026-11-01'')', 4025);
CALL _expect_fail('B08 Intervall-Wiederholung mit RRULE',
  'INSERT INTO task_template (title, category, recurrence_type, rrule, interval_days, starts_on) VALUES (''x'', ''haushalt'', ''intervall'', ''FREQ=DAILY'', 7, ''2026-11-01'')', 4025);
CALL _expect_fail('B09 Zweite veröffentlichte Version einer Anleitung',
  'INSERT INTO guide_version (guide_id, version_no, status, title) VALUES (@guide, 3, ''veroeffentlicht'', ''x'')', 1062);
CALL _expect_fail('B10 Zweiter offener Entwurf einer Anleitung',
  'INSERT INTO guide_version (guide_id, version_no, status, title) VALUES (@guide, 3, ''zur_freigabe'', ''x'')', 1062);
CALL _expect_fail('B11 Ungültige Rolle',
  'INSERT INTO app_user (public_id, role, username, display_name, active_from) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''chef'', ''neu'', ''Neu'', ''2026-01-01'')', 4025);
CALL _expect_fail('B12 Anzeigename doppelt',
  'INSERT INTO app_user (public_id, role, username, display_name, active_from) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''assistenz'', ''martin2'', ''Martin'', ''2026-01-01'')', 1062);
CALL _expect_fail('B13 Abwesenheit endet vor Beginn',
  'INSERT INTO absence (user_id, absence_type, date_from, date_to, status) VALUES (@m_anna, ''urlaub'', ''2026-12-10'', ''2026-12-01'', ''beantragt'')', 4025);
CALL _expect_fail('B14 Urlaub mit AU-Vermerk',
  'INSERT INTO absence (user_id, absence_type, date_from, date_to, status, sick_note_presented) VALUES (@m_anna, ''urlaub'', ''2026-12-01'', ''2026-12-02'', ''beantragt'', 1)', 4025);
CALL _expect_fail('B15 Lohnzeile: Auszahlung passt nicht zu Brutto − RV',
  'INSERT INTO payroll_line (monthly_closing_id, user_id, gross_work, gross_total, rv_employee_share, payout, employer_contributions, total_cost, minijob_limit) VALUES (@closing, @m_anna, 100, 100, 10, 95, 14.62, 114.62, 603)', 4025);
CALL _expect_fail('B16 Dienst ausgefallen ohne Grund',
  'INSERT INTO planned_shift (user_id, starts_at, ends_at, status) VALUES (@m_anna, ''2026-11-11 09:00:00'', ''2026-11-11 12:00:00'', ''ausgefallen'')', 4025);
CALL _expect_fail('B17 Profil: Art bei Abschnitt „essen"',
  'INSERT INTO profile_item (section, kind, title) VALUES (''essen'', ''allergie'', ''x'')', 4025);
CALL _expect_fail('B18 Kenntnis einer Anleitung doppelt erfasst',
  'INSERT INTO guide_acknowledgement (guide_id, user_id, read_at, read_version_no) VALUES (@guide, @m_anna, NOW(), 1)', 1062);
CALL _expect_fail('B19 Ort: Breitengrad ohne Längengrad',
  'INSERT INTO poi (public_id, name, category, latitude) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''x'', ''arzt'', 49.79)', 4025);
CALL _expect_fail('B20 KI-Aktion mit ungültigem JSON',
  'INSERT INTO ai_action (tool_name, arguments) VALUES (''termin_anlegen'', ''{kaputt'')', 4025);
CALL _expect_fail('B21 Person mit Arbeitszeiten löschen',
  'DELETE FROM app_user WHERE id = @m_martin', 1451);
CALL _expect_fail('B22 Einkauf erledigt ohne Zeitpunkt',
  'INSERT INTO shopping_item (list_id, name, is_done) VALUES (@list_shop, ''Milch'', 1)', 4025);
CALL _expect_fail('B23 Monat wiedergeöffnet ohne Begründung',
  'UPDATE monthly_closing SET status = ''wiedergeoeffnet'' WHERE id = @closing', 4025);
CALL _expect_fail('B24 Stundenlohn 0 €',
  'INSERT INTO wage_rate (user_id, valid_from, hourly_rate) VALUES (@m_anna, ''2027-01-01'', 0)', 4025);
CALL _expect_fail('B25 Verschobene Aufgabe wird von der Vorlage nicht erneut erzeugt',
  'INSERT INTO task (template_id, occurrence_date, title, category, due_date) VALUES (@tpl, ''2026-11-05'', ''x'', ''hilfsmittel'', ''2026-11-08'')', 1062);
CALL _expect_fail('B36 Verschobener Dienst wird vom Muster nicht erneut erzeugt',
  'INSERT INTO planned_shift (user_id, shift_pattern_id, occurrence_date, starts_at, ends_at) VALUES (@m_martin, @pattern, ''2026-11-10'', ''2026-11-10 09:00:00'', ''2026-11-10 13:00:00'')', 1062);
CALL _expect_fail('B37 Zweiter Team-Chat',
  'INSERT INTO chat_conversation (kind) VALUES (''team'')', 1062);
CALL _expect_fail('B38 Direktchat ohne Personenpaar',
  'INSERT INTO chat_conversation (kind) VALUES (''direkt'')', 4025);
CALL _expect_fail('B39 Zweiter Direktchat für dasselbe Paar',
  'INSERT INTO chat_conversation (kind, direct_key) VALUES (''direkt'', CONCAT(LEAST(@m_mgr, @m_martin), '':'', GREATEST(@m_mgr, @m_martin)))', 1062);
CALL _expect_fail('B40 Unbekannte Art der Arbeitszeit',
  'INSERT INTO time_entry (user_id, entry_kind, started_at, start_source) VALUES (@m_anna, ''freizeit'', ''2026-11-20 09:00:00'', ''tablet'')', 4025);
CALL _expect_fail('B41 Medium gehört Chat-Nachricht und Ort zugleich',
  'INSERT INTO media (public_id, kind, chat_message_id, poi_id) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''foto'', @msg1, @poi)', 4025);
CALL _expect_fail('B42 Benachrichtigung mit unbekannter Art',
  'INSERT INTO notification (user_id, kind, title, delete_after) VALUES (@m_anna, ''werbung'', ''x'', ''2026-12-01'')', 4025);
CALL _expect_fail('B43 PIN-Anmeldung aktiviert, aber keine PIN gesetzt',
  'INSERT INTO app_user (public_id, role, username, display_name, active_from, pin_login_enabled) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''assistenz'', ''neu'', ''Neu'', ''2026-11-01'', 1)', 4025);
CALL _expect_fail('B44 2FA per E-Mail ohne E-Mail-Adresse',
  'UPDATE app_user SET twofa_method = ''email'' WHERE id = @m_fam', 4025);
CALL _expect_fail('B45 Zweite Person mit Rolle „Manager"',
  'INSERT INTO app_user (public_id, role, is_admin, username, display_name, active_from) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''manager'', 1, ''mgr2'', ''Mgr2'', ''2026-11-01'')', 1062);
CALL _expect_fail('B46 Manager ohne Admin-Recht',
  'UPDATE app_user SET is_admin = 0 WHERE id = @m_mgr', 4025);
CALL _expect_fail('B51 Assistenz mit Admin-Recht',
  'UPDATE app_user SET is_admin = 1 WHERE id = @m_martin', 4025);
CALL _expect_fail('B52 Benutzername doppelt',
  'INSERT INTO app_user (public_id, role, username, display_name, active_from) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''assistenz'', ''anna'', ''Anna B.'', ''2026-11-01'')', 1062);
CALL _expect_fail('B53 Aufgabe mit alter Sichtbarkeit „team"',
  'INSERT INTO task (title, category, visibility, due_date) VALUES (''x'', ''haushalt'', ''team'', ''2026-11-05'')', 4025);
CALL _expect_fail('B54 Termin mit unbekannter Zuständigkeits-Rolle',
  'INSERT INTO appointment (title, starts_at, assignee_role) VALUES (''x'', ''2026-11-20 10:00:00'', ''chef'')', 4025);
CALL _expect_fail('B55 Notfallkontakt mit unbekannter Kategorie',
  'INSERT INTO emergency_contact (category, name, phone) VALUES (''nachbar'', ''x'', ''1'')', 4025);
CALL _expect_fail('B56 Admin-Korrektur der Arbeitszeit ohne Begründung',
  'INSERT INTO time_entry_change (time_entry_id, kind, changed_by_user_id) VALUES (@te1, ''admin'', @m_mgr)', 4025);
CALL _expect_fail('B57 Zweite Liste mit gleichem Namen',
  'INSERT INTO shopping_list (name) VALUES (''Einkauf'')', 1062);
CALL _expect_fail('B58 Zweite Zeile in den globalen Einstellungen',
  'INSERT INTO app_setting (id) VALUES (2)', 4025);
CALL _expect_fail('B59 Tablet mit unbekanntem Modus',
  'UPDATE device SET mode = ''kiosk'' WHERE id = @dev', 4025);
CALL _expect_fail('B60 Arbeitszeit mit unbekannter Quelle',
  'INSERT INTO time_entry (user_id, started_at, ended_at, duration_minutes, start_source, end_source) VALUES (@m_ben, ''2026-11-07 09:00:00'', ''2026-11-07 10:00:00'', 60, ''telefon'', ''telefon'')', 4025);
CALL _expect_fail('B61 Meldung mit unbekannter Art',
  'INSERT INTO issue_report (public_id, kind, title) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''dreckig'', ''x'')', 4025);
CALL _expect_fail('B62 Meldung mit unbekanntem Status',
  'INSERT INTO issue_report (public_id, kind, title, status) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''leer'', ''x'', ''wartend'')', 4025);
CALL _expect_fail('B63 Erledigte Meldung ohne Abschlusszeit',
  'INSERT INTO issue_report (public_id, kind, title, status) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''leer'', ''x'', ''erledigt'')', 4025);
CALL _expect_fail('B64 Offene Meldung mit Abschlusszeit',
  'INSERT INTO issue_report (public_id, kind, title, status, closed_at, delete_after) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''leer'', ''x'', ''offen'', NOW(), ''2027-11-01'')', 4025);
CALL _expect_fail('B65 Verworfene Meldung ohne Löschdatum',
  'INSERT INTO issue_report (public_id, kind, title, status, closed_at) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''leer'', ''x'', ''verworfen'', NOW())', 4025);
CALL _expect_fail('B66 Meldung mit unbekannter Folgeaktion',
  'INSERT INTO issue_report (public_id, kind, title, follow_up) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''leer'', ''x'', ''mail'')', 4025);
CALL _expect_fail('B67 Zweite Meldung zur selben Aufgabe',
  'INSERT INTO issue_report (public_id, kind, title, task_id) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''nicht_moeglich'', ''x'', @task_soap)', 1062);
CALL _expect_fail('B68 „Auch bemerkt" ohne Zeitpunkt',
  'INSERT INTO issue_report (public_id, kind, title, confirm_count) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''leer'', ''x'', 2)', 4025);
CALL _expect_fail('B69 Meldung mit unbekannter Sichtbarkeit',
  'INSERT INTO issue_report (public_id, kind, title, visibility) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''leer'', ''x'', ''privat'')', 4025);
CALL _expect_fail('B70 Foto gehört zugleich zu Meldung und Ort',
  'INSERT INTO media (public_id, kind, issue_report_id, poi_id) VALUES (UNHEX(REPLACE(UUID(),''-'','''')), ''foto'', @rep_charger, @poi)', 4025);
CALL _expect_fail('B71 Benachrichtigungsregel für unbekanntes Ereignis',
  'INSERT INTO notification_preference (user_id, event_type) VALUES (@m_anna, ''werbung'')', 4025);
CALL _expect_fail('B72 Zweite Regel für dieselbe Person und dasselbe Ereignis',
  'INSERT INTO notification_preference (user_id, event_type) VALUES (@m_fam, ''meldung'')', 1062);
CALL _expect_fail('B73 Besuch mit Begleitung',
  'INSERT INTO appointment (kind, title, starts_at, needs_escort) VALUES (''besuch'', ''x'', ''2026-11-20 10:00:00'', 1)', 4025);
CALL _expect_fail('B74 Besuch mit zuständiger Rolle',
  'INSERT INTO appointment (kind, title, starts_at, assignee_role) VALUES (''besuch'', ''x'', ''2026-11-20 10:00:00'', ''assistenz'')', 4025);
CALL _expect_fail('B75 Kalendereintrag mit unbekannter Art',
  'INSERT INTO appointment (kind, title, starts_at) VALUES (''party'', ''x'', ''2026-11-20 10:00:00'')', 4025);
CALL _expect_fail('B76 Serien-Vorkommen ohne Ursprungstag',
  'INSERT INTO appointment (kind, title, series_id, starts_at) VALUES (''besuch'', ''x'', @series_physio, ''2026-11-17 10:00:00'')', 4025);
CALL _expect_fail('B77 Ursprungstag ohne Serie',
  'INSERT INTO appointment (title, occurrence_date, starts_at) VALUES (''x'', ''2026-11-17'', ''2026-11-17 10:00:00'')', 4025);
CALL _expect_fail('B78 Zweites Vorkommen derselben Serie am selben Tag',
  'INSERT INTO appointment (kind, title, series_id, occurrence_date, starts_at) VALUES (''besuch'', ''x'', @series_physio, ''2026-11-03'', ''2026-11-03 12:00:00'')', 1062);
CALL _expect_fail('B79 Absage-Notiz an geplantem Termin',
  'INSERT INTO appointment (title, starts_at, cancel_note) VALUES (''x'', ''2026-11-20 10:00:00'', ''y'')', 4025);
CALL _expect_fail('B80 Termin mit unbekanntem Status',
  'INSERT INTO appointment (title, starts_at, status) VALUES (''x'', ''2026-11-20 10:00:00'', ''vielleicht'')', 4025);
CALL _expect_fail('B81 Externer Dienst mit unbekannter Art',
  'INSERT INTO visitor (name, kind) VALUES (''x'', ''spion'')', 4025);
CALL _expect_fail('B82 Zweiter externer Dienst mit gleichem Namen',
  'INSERT INTO visitor (name) VALUES (''Physiotherapie'')', 1062);
CALL _expect_fail('B83 Serie mit Uhrzeit-Pflicht ohne Uhrzeit',
  'INSERT INTO appointment_series (title, rrule, duration_minutes, starts_on) VALUES (''x'', ''FREQ=WEEKLY'', 60, ''2026-11-01'')', 4025);
CALL _expect_fail('B84 Besuchs-Serie mit Begleitung',
  'INSERT INTO appointment_series (kind, title, rrule, start_time, duration_minutes, starts_on, needs_escort) VALUES (''besuch'', ''x'', ''FREQ=WEEKLY'', ''10:00:00'', 60, ''2026-11-01'', 1)', 4025);
CALL _expect_fail('B85 Serie endet vor Beginn',
  'INSERT INTO appointment_series (title, rrule, start_time, duration_minutes, starts_on, ends_on) VALUES (''x'', ''FREQ=WEEKLY'', ''10:00:00'', 60, ''2026-11-10'', ''2026-11-01'')', 4025);
CALL _expect_fail('B86 Kalenderfarbe im falschen Format',
  'INSERT INTO visitor (name, color) VALUES (''y'', ''gruen'')', 4025);
CALL _expect_fail('B87 KI einschalten ohne Schlüssel',
  'UPDATE app_setting SET ai_api_key_enc = NULL, ai_api_key_hint = NULL, ai_key_status = ''fehlt'' WHERE id = 1', 4025);
CALL _expect_fail('B88 Schlüssel-Status „ok" ohne Schlüssel',
  'UPDATE app_setting SET ai_enabled = 0, ai_api_key_enc = NULL, ai_api_key_hint = NULL WHERE id = 1', 4025);
CALL _expect_fail('B89 Unbekannter Schlüssel-Status',
  'UPDATE app_setting SET ai_key_status = ''super'' WHERE id = 1', 4025);
CALL _expect_fail('B90 Unbekannter KI-Anbieter',
  'UPDATE app_setting SET ai_provider = ''xyz'' WHERE id = 1', 4025);
CALL _expect_fail('B91 E-Mail eingeschaltet ohne Absenderadresse',
  'UPDATE app_setting SET mail_from_address = NULL WHERE id = 1', 4025);
CALL _expect_fail('B92 SMTP mit unbekannter Verschlüsselung',
  'UPDATE app_setting SET smtp_encryption = ''rot13'' WHERE id = 1', 4025);
CALL _expect_fail('B93 Tablet mit unbekannter Schriftgröße',
  'UPDATE device SET font_size = ''riesig'' WHERE id = @dev', 4025);
CALL _expect_fail('B47 Zweite Übersetzung derselben Version in dieselbe Sprache',
  'INSERT INTO guide_translation (version_id, language, title) VALUES (@gv1, ''pl'', ''x'')', 1062);
CALL _expect_fail('B48 „Übersetzung" nach Deutsch',
  'INSERT INTO guide_translation (version_id, language, title) VALUES (@gv1, ''de'', ''x'')', 4025);
CALL _expect_fail('B49 Checklisten-Punkt zugleich erledigt und „entfällt"',
  'INSERT INTO checklist_item (checklist_id, label, done_at, is_skipped) VALUES (@cl, ''x'', NOW(), 1)', 4025);
CALL _expect_fail('B50 Unbekannte E-Mail-Einstellung',
  'UPDATE app_user SET notify_email = ''stuendlich'' WHERE id = @m_anna', 4025);
CALL _expect_fail('B29 Unbekannte Rolle als Zuständigkeit',
  'INSERT INTO task (title, category, assignee_role, due_date) VALUES (''x'', ''haushalt'', ''chef'', ''2026-11-05'')', 4025);
CALL _expect_fail('B30 Abwesenheit des Managers endet vor Beginn',
  'INSERT INTO manager_absence (date_from, date_to) VALUES (''2026-12-10'', ''2026-12-01'')', 4025);
CALL _expect_fail('B31 Absage durch Manager ohne Entscheidung „bezahlt?"',
  'INSERT INTO planned_shift (user_id, starts_at, ends_at, status, cancel_reason) VALUES (@m_anna, ''2026-12-23 09:00:00'', ''2026-12-23 12:00:00'', ''ausgefallen'', ''manager_abwesend'')', 4025);
CALL _expect_fail('B32 Geplanter Dienst mit „bezahlt"-Vermerk',
  'INSERT INTO planned_shift (user_id, starts_at, ends_at, cancel_paid) VALUES (@m_anna, ''2026-12-23 09:00:00'', ''2026-12-23 12:00:00'', 1)', 4025);
CALL _expect_fail('B33 Lohnzeile: bezahlter Ausfall fehlt im Brutto',
  'INSERT INTO payroll_line (monthly_closing_id, user_id, gross_work, gross_cancelled_paid, gross_total, payout, employer_contributions, total_cost, minijob_limit) VALUES (@closing, @m_anna, 50, 20, 50, 50, 7.31, 57.31, 603)', 4025);
CALL _expect_fail('B34 Termin mit unbekannter Sichtbarkeit',
  'INSERT INTO appointment (title, starts_at, visibility) VALUES (''x'', ''2026-11-20 10:00:00'', ''geheim'')', 4025);
CALL _expect_fail('B35 Teilnehmer mit unbekannter Rolle',
  'INSERT INTO appointment_participant (appointment_id, user_id, role) VALUES (@appt, @m_anna, ''chef'')', 4025);
CALL _expect_fail('B26 Unverträglichkeit ohne Art',
  'INSERT INTO profile_item (section, title) VALUES (''unvertraeglichkeit'', ''x'')', 4025);
CALL _expect_fail('B27 Intervall-Wiederholung ohne Tageszahl',
  'INSERT INTO task_template (title, category, recurrence_type, starts_on) VALUES (''x'', ''haushalt'', ''intervall'', ''2026-11-01'')', 4025);
CALL _expect_fail('B28 Hinweistext ohne Hinweisart',
  'INSERT INTO guide_step (version_id, sort, body, hint_text) VALUES (@gv1, 9, ''x'', ''y'')', 4025);

-- ---------------------------------------------------------------------
--  C. Löschverhalten
-- ---------------------------------------------------------------------

-- Austritt: Zugang entfernen (Passwort, PIN, Passkeys), Person bleibt für Arbeitszeiten erhalten
UPDATE app_user SET active_until = '2026-11-30', password_hash = NULL, pin_hash = NULL, pin_login_enabled = 0,
       twofa_method = 'keine', email = NULL, disabled_at = NOW() WHERE id = @m_anna;
DELETE FROM webauthn_credential WHERE user_id = @m_anna;
SET @ok = ((SELECT COUNT(*) FROM time_entry WHERE user_id = @m_anna) > 0);
CALL _expect('C1 Zugang entfernt → Arbeitszeiten der Person bleiben', @ok);

-- Anleitung löschen: Versionen, Schritte, Medien weg; Verweise genullt
DELETE FROM guide WHERE id = @guide;
SET @ok = ((SELECT COUNT(*) FROM guide_version WHERE guide_id = @guide) = 0
           AND (SELECT COUNT(*) FROM guide_step WHERE id = @step) = 0);
CALL _expect('C2 Anleitung gelöscht → Versionen/Schritte weg', @ok);
SET @ok = ((SELECT COUNT(*) FROM media WHERE id = @media_guide) = 0);
CALL _expect('C3 Anleitung gelöscht → ihre Medien weg', @ok);
SET @ok = ((SELECT guide_id IS NULL FROM task_template WHERE id = @tpl)
           AND (SELECT guide_id IS NULL FROM task WHERE id = @task));
CALL _expect('C4 Anleitung gelöscht → Wartungsplan/Aufgabe bleiben, Verweis genullt', @ok);

-- Direktchat löschen: Nachrichten und Sprachnachricht verschwinden mit
DELETE FROM chat_conversation WHERE id = @conv_direct;
SET @ok = ((SELECT COUNT(*) FROM chat_message WHERE conversation_id = @conv_direct) = 0
           AND (SELECT COUNT(*) FROM media WHERE id = @media_voice) = 0);
CALL _expect('C5a Unterhaltung gelöscht → Nachrichten und Medien weg', @ok);

-- Person nach Ablauf aller Fristen endgültig löschen – in der dokumentierten Reihenfolge (App-Dienst)
DELETE FROM payroll_line           WHERE user_id = @m_martin;
DELETE FROM sick_pay_refund        WHERE absence_id IN (SELECT id FROM absence WHERE user_id = @m_martin);
DELETE FROM time_entry             WHERE user_id = @m_martin;
DELETE FROM absence                WHERE user_id = @m_martin;
DELETE FROM planned_shift          WHERE user_id = @m_martin;
DELETE FROM timesheet_confirmation WHERE user_id = @m_martin;
DELETE FROM vacation_entitlement   WHERE user_id = @m_martin;
DELETE FROM wage_rate              WHERE user_id = @m_martin;
DELETE FROM employee_record        WHERE user_id = @m_martin;
DELETE FROM app_user               WHERE id = @m_martin;

CALL _count_refs(@m_martin, @rest);
CALL _expect('C5 Person gelöscht → keine Zeile verweist mehr auf sie', @rest = 0);
SET @ok = ((SELECT COUNT(*) FROM task WHERE title = 'Mit Martin über Urlaubsplanung sprechen' AND assignee_user_id IS NULL) = 1);
CALL _expect('C5b Person gelöscht → ihre Aufgaben bleiben, Zuständigkeit geleert', @ok);
SET @ok = ((SELECT created_by_user_id IS NULL FROM issue_report WHERE id = @rep_charger) = 1);
CALL _expect('C5c Person gelöscht → ihre Meldung bleibt (ohne Person)', @ok);
SET @ok = ((SELECT COUNT(*) FROM legal_limit) >= 5 AND (SELECT COUNT(*) FROM contribution_rate) >= 8);
CALL _expect('C6 Globale Sätze/Grenzen bleiben erhalten', @ok);

-- Meldung löschen (Löschjob): Foto verschwindet mit
DELETE FROM issue_report WHERE id = @rep_charger;
SET @ok = ((SELECT COUNT(*) FROM media WHERE id = @media_report) = 0);
CALL _expect('C7 Meldung gelöscht → ihr Foto weg', @ok);
-- Aufgabe löschen (Löschjob erledigter Aufgaben): Meldung und Listeneintrag bleiben
DELETE FROM task WHERE id = @task_soap;
SET @ok = ((SELECT task_id IS NULL FROM issue_report WHERE id = @rep_soap) = 1
           AND (SELECT COUNT(*) FROM shopping_item WHERE id = @item_soap AND source_task_id IS NULL) = 1);
CALL _expect('C8 Aufgabe gelöscht → Meldung und Listeneintrag bleiben, Verweis genullt', @ok);

-- Externen Dienst entfernen: Serie und Besuche bleiben (ohne Verweis); Serie löschen: ihre Vorkommen verschwinden
DELETE FROM visitor WHERE id = @vis_caritas;
SET @ok = ((SELECT visitor_id IS NULL FROM appointment_series WHERE id = @series_caritas) = 1
           AND (SELECT COUNT(*) FROM appointment WHERE series_id = @series_caritas AND visitor_id IS NULL) = 1);
CALL _expect('C9 Externer Dienst gelöscht → Serie und Besuche bleiben, Verweis genullt', @ok);
DELETE FROM appointment_series WHERE id = @series_physio;
SET @ok = ((SELECT COUNT(*) FROM appointment WHERE series_id = @series_physio) = 0
           AND (SELECT COUNT(*) FROM visitor WHERE id = @vis_physio) = 1);
CALL _expect('C10 Serie gelöscht → ihre Vorkommen weg, externer Dienst bleibt', @ok);

-- ---------------------------------------------------------------------
--  Ergebnis
-- ---------------------------------------------------------------------

SELECT nr, pruefung, ergebnis FROM _smoke ORDER BY nr;
SELECT IF(SUM(ergebnis NOT LIKE 'OK%') = 0, 'ALLE PRÜFUNGEN OK',
          CONCAT(SUM(ergebnis NOT LIKE 'OK%'), ' PRÜFUNG(EN) FEHLGESCHLAGEN')) AS zusammenfassung
FROM _smoke;

DROP PROCEDURE _expect_fail;
DROP PROCEDURE _expect;
DROP PROCEDURE _count_refs;
