-- =====================================================================
--  HomeCrew – Datenbankschema (fachliche Referenz)
--  Ziel:   MariaDB 11.8 LTS, InnoDB, utf8mb4
--  Stand:  02.11.2026 · Umsetzung Stufe 1 (M4: emergency_contact.category VARCHAR(15))
--          Doctrine-Migrations erzeugen das Schema; diese Datei ist die
--          Referenz, gegen die Entities und Migrations geprüft werden
--          (composer schema:check, docs/konzept/konzept-6-datenbank.md §6).
--
--  Eine Datenbank = ein Haushalt. Weitere Haushalte bekommen eine eigene
--  Instanz mit eigener Datenbank (keine Mandantenfähigkeit im Schema).
--
--  Konventionen
--  * Tabellen/Spalten englisch, snake_case; Wertelisten deutsch (Fachsprache)
--  * PK: BIGINT UNSIGNED AUTO_INCREMENT. Was per Link/QR-Code nach außen geht,
--    hat zusätzlich public_id BINARY(16) (UUID v7, von der App erzeugt).
--  * Zeitpunkte in UTC (Verbindung mit time_zone '+00:00'): technische Zeitstempel UND
--    Stempelzeiten (time_entry) – so bleiben sie auch bei der Zeitumstellung eindeutig.
--    Geplantes (Dienstplan, Termine, Fälligkeiten) in Ortszeit des Haushalts.
--  * Geld DECIMAL(10,2) · Prozentsätze DECIMAL(6,3) · Dauern in Minuten
--  * Wertelisten als VARCHAR + CHECK (lesbar, Doctrine-freundlich)
--  * *_enc = libsodium-Chiffrat; Schlüssel liegt außerhalb der Datenbank
--  * Löschregeln:
--      - Person endgültig löschen (nach Ablauf der Fristen): App-Dienst löscht
--        zuerst die Personaldaten (RESTRICT schützt sie vor versehentlichem
--        Mitlöschen), danach die Person → Übriges folgt per CASCADE / SET NULL.
--      - Medien hängen per CASCADE an genau einem Besitzer.
--  * KEINE Diagnosen, Adressen, SV-/Steuernummern der Assistenzkräfte.
--  * CHECKs: Ein NULL-Ergebnis gilt in SQL als „bestanden" → optionale Spalten
--    werden in Bedingungen immer ausdrücklich mit IS [NOT] NULL geprüft.
-- =====================================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
--  1. Einstellungen, Personen, Anmeldung
-- ---------------------------------------------------------------------

CREATE TABLE app_setting (
  id                           TINYINT UNSIGNED  NOT NULL DEFAULT 1,
  household_name               VARCHAR(100)      NOT NULL DEFAULT 'Mein Haushalt',
  timezone                     VARCHAR(40)       NOT NULL DEFAULT 'Europe/Berlin',
  holiday_region               VARCHAR(20)       NOT NULL DEFAULT 'BY',     -- BY, BY-KATH (Mariä Himmelfahrt), BY-AUGSBURG …
  minijob_procedure            VARCHAR(20)       NOT NULL DEFAULT 'haushaltsscheck',
  guide_publish_mode           VARCHAR(20)       NOT NULL DEFAULT 'freigabe',
  tablet_lock_seconds          SMALLINT UNSIGNED NOT NULL DEFAULT 180,
  pin_lan_only                 TINYINT(1)        NOT NULL DEFAULT 1,        -- PIN-Anmeldung nur aus dem Heimnetz
  max_shift_minutes            SMALLINT UNSIGNED NOT NULL DEFAULT 600,      -- Hinweis „Dienst noch offen?"
  time_view_months             TINYINT UNSIGNED  NOT NULL DEFAULT 3,        -- so weit sieht die Assistenz eigene Zeiten zurück
  video_max_seconds            SMALLINT UNSIGNED NOT NULL DEFAULT 90,
  voice_messages_enabled       TINYINT(1)        NOT NULL DEFAULT 1,        -- Sprachnachrichten im Chat
  assistant_direct_chat        TINYINT(1)        NOT NULL DEFAULT 0,        -- Assistenzkräfte untereinander schreiben
  push_allowed                 TINYINT(1)        NOT NULL DEFAULT 0,
  daily_digest_time            TIME              NOT NULL DEFAULT '18:00:00', -- Tageszusammenfassung per E-Mail (notify_email = taeglich)
  -- Erweiterung KI (Admin-Menü → Erweiterungen). Ohne gültigen Schlüssel ist keine KI-Funktion sichtbar.
  ai_enabled                   TINYINT(1)        NOT NULL DEFAULT 0,        -- Hauptschalter, nur mit Schlüssel möglich
  ai_provider                  VARCHAR(20)       NOT NULL DEFAULT 'anthropic',
  ai_model                     VARCHAR(60)       NOT NULL DEFAULT 'claude-haiku-5-5',
  ai_api_key_enc               VARBINARY(512)    NULL,                      -- libsodium-Chiffrat; nie an den Browser, nie ins Log
  ai_api_key_hint              CHAR(4)           NULL,                      -- letzte 4 Zeichen zur Anzeige („••••a1b2")
  ai_key_status                VARCHAR(12)       NOT NULL DEFAULT 'fehlt',  -- fehlt · ungeprueft · ok · fehler
  ai_key_checked_at            DATETIME          NULL,
  ai_key_error                 VARCHAR(255)      NULL,                      -- letzte Fehlermeldung des Verbindungstests
  ai_feature_assistant         TINYINT(1)        NOT NULL DEFAULT 1,        -- KI-Assistent (Text/Sprache)
  ai_feature_writing           TINYINT(1)        NOT NULL DEFAULT 1,        -- KI-Schreibhilfe im Anleitungs-Editor
  ai_feature_translation       TINYINT(1)        NOT NULL DEFAULT 0,        -- Übersetzung von Anleitungen (Stufe 5)
  ai_team_device               TINYINT(1)        NOT NULL DEFAULT 1,        -- KI-Assistent auch am Team-Tablet
  ai_monthly_budget_eur        DECIMAL(8,2)      NULL,
  speech_recognition           VARCHAR(10)       NOT NULL DEFAULT 'lokal',  -- lokal (whisper.cpp auf dem Pi) / aus – Mikrofon nur, wenn der Dienst erreichbar ist
  -- Erweiterung E-Mail (SMTP des eigenen Mail-Anbieters). Ohne funktionierenden Zugang sind alle E-Mail-Funktionen ausgeblendet.
  mail_enabled                 TINYINT(1)        NOT NULL DEFAULT 0,
  smtp_host                    VARCHAR(190)      NULL,
  smtp_port                    SMALLINT UNSIGNED NULL,
  smtp_encryption              VARCHAR(10)       NOT NULL DEFAULT 'starttls',  -- starttls (587) · smtps (465)
  smtp_username                VARCHAR(190)      NULL,
  smtp_password_enc            VARBINARY(512)    NULL,
  mail_from_address            VARCHAR(190)      NULL,
  mail_from_name               VARCHAR(100)      NULL,                      -- Absendername, neutral halten (z. B. „HomeCrew")
  mail_status                  VARCHAR(12)       NOT NULL DEFAULT 'fehlt',  -- fehlt · ungeprueft · ok · fehler
  mail_checked_at              DATETIME          NULL,
  retention_handover_days      SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  retention_completion_days    SMALLINT UNSIGNED NOT NULL DEFAULT 90,       -- Personenbezug an Erledigungen
  completion_keep_last         TINYINT UNSIGNED  NOT NULL DEFAULT 5,        -- letzte n Erledigungen je Wartungsplan bleiben
  retention_done_task_days     SMALLINT UNSIGNED NOT NULL DEFAULT 365,
  retention_chat_days          SMALLINT UNSIGNED NOT NULL DEFAULT 365,
  retention_notification_days  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  retention_report_days        SMALLINT UNSIGNED NOT NULL DEFAULT 365,      -- abgeschlossene Meldungen
  retention_audit_days         SMALLINT UNSIGNED NOT NULL DEFAULT 365,
  retention_ai_days            SMALLINT UNSIGNED NOT NULL DEFAULT 7,
  retention_hr_years           TINYINT UNSIGNED  NULL,                      -- NULL = noch nicht festgelegt → nichts automatisch löschen
  updated_at                   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT ck_setting_single   CHECK (id = 1),
  CONSTRAINT ck_setting_minijob  CHECK (minijob_procedure IN ('haushaltsscheck','gewerblich')),
  CONSTRAINT ck_setting_publish  CHECK (guide_publish_mode IN ('freigabe','direkt')),
  CONSTRAINT ck_setting_speech   CHECK (speech_recognition IN ('lokal','aus')),
  CONSTRAINT ck_setting_months   CHECK (time_view_months BETWEEN 1 AND 24),
  CONSTRAINT ck_setting_budget   CHECK (ai_monthly_budget_eur IS NULL OR ai_monthly_budget_eur >= 0),
  CONSTRAINT ck_setting_ai_prov  CHECK (ai_provider IN ('anthropic')),     -- weitere Anbieter später per Migration
  CONSTRAINT ck_setting_ai_stat  CHECK (ai_key_status IN ('fehlt','ungeprueft','ok','fehler')),
  CONSTRAINT ck_setting_ai_key   CHECK ((ai_api_key_enc IS NULL AND ai_api_key_hint IS NULL AND ai_key_status = 'fehlt')
                                     OR (ai_api_key_enc IS NOT NULL AND ai_key_status <> 'fehlt')),
  CONSTRAINT ck_setting_ai_on    CHECK (ai_enabled = 0 OR ai_api_key_enc IS NOT NULL),
  CONSTRAINT ck_setting_mail_enc CHECK (smtp_encryption IN ('starttls','smtps')),
  CONSTRAINT ck_setting_mail_st  CHECK (mail_status IN ('fehlt','ungeprueft','ok','fehler')),
  CONSTRAINT ck_setting_mail_on  CHECK (mail_enabled = 0 OR (smtp_host IS NOT NULL AND smtp_port IS NOT NULL AND smtp_port > 0
                                                            AND mail_from_address IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Globale Einstellungen des Haushalts (genau eine Zeile, Admin-Menü)';

CREATE TABLE app_user (
  id                            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  public_id                     BINARY(16)       NOT NULL,
  -- Person & Rechte
  role                          VARCHAR(12)      NOT NULL,           -- was die Person ist
  is_admin                      TINYINT(1)       NOT NULL DEFAULT 0, -- was sie darf (Manager immer Admin; Admins brauchen 2FA/Passkey – erzwingt die App)
  username                      VARCHAR(50)      NOT NULL,           -- vom Admin festgelegt
  display_name                  VARCHAR(50)      NOT NULL,           -- Vorname/Kürzel, überall sichtbar
  color                         CHAR(7)          NULL,               -- Kachelfarbe am Tablet (#RRGGBB)
  active_from                   DATE             NOT NULL,
  active_until                  DATE             NULL,               -- Austritt = Zugang endet
  disabled_at                   DATETIME         NULL,
  anonymized_at                 DATETIME         NULL,
  -- Anmeldung Handy/PC (optional)
  email                         VARCHAR(190)     NULL,               -- optional: Passwort-Reset, Einladung, 2FA-Code, Benachrichtigungen
  email_verified_at             DATETIME         NULL,
  password_hash                 VARCHAR(255)     NULL,               -- NULL: kein Login am Handy/PC (nur Tablet-PIN oder Passkey)
  password_must_change          TINYINT(1)       NOT NULL DEFAULT 0, -- Start-Passwort vom Admin
  twofa_method                  VARCHAR(10)      NOT NULL DEFAULT 'keine',
  totp_secret_enc               VARBINARY(255)   NULL,
  email_code_hash               CHAR(64)         NULL,               -- 2FA-Code per E-Mail (gehasht, kurzlebig)
  email_code_expires_at         DATETIME         NULL,
  email_code_attempts           TINYINT UNSIGNED NOT NULL DEFAULT 0,
  twofa_backup_codes            JSON             NULL,               -- Hashes der Wiederherstellungscodes
  last_login_at                 DATETIME         NULL,
  -- Anmeldung Tablet (PIN, optional)
  pin_hash                      VARCHAR(255)     NULL,
  pin_login_enabled             TINYINT(1)       NOT NULL DEFAULT 0, -- nur wenn ausdrücklich aktiviert; gibt nie Admin-Rechte
  pin_must_change               TINYINT(1)       NOT NULL DEFAULT 1, -- Start-PIN vom Admin bei erster Anmeldung ändern
  pin_failed_count              TINYINT UNSIGNED NOT NULL DEFAULT 0,
  pin_locked_until              DATETIME         NULL,
  -- Rechte-Schalter (vom Admin vergeben)
  can_publish_guides            TINYINT(1)       NOT NULL DEFAULT 0,
  can_manage_recurring_tasks    TINYINT(1)       NOT NULL DEFAULT 0,
  can_create_team_appointments  TINYINT(1)      NOT NULL DEFAULT 0, -- Assistenz: standardmäßig nein
  can_use_ai                    TINYINT(1)       NOT NULL DEFAULT 1, -- wirkt nur, wenn KI global aktiviert
  ai_voice_confirm              TINYINT(1)       NOT NULL DEFAULT 0, -- KI-Aktionen per „Ja" bestätigen
  -- Persönliche Einstellungen
  font_size                     VARCHAR(12)      NOT NULL DEFAULT 'normal',
  high_contrast                 TINYINT(1)       NOT NULL DEFAULT 0,
  speech_output                 TINYINT(1)       NOT NULL DEFAULT 0,
  calendar_view                 VARCHAR(10)      NOT NULL DEFAULT 'agenda',
  preferred_language            VARCHAR(10)      NOT NULL DEFAULT 'de',  -- für Übersetzungen (Stufe 5)
  notify_email                  VARCHAR(10)      NOT NULL DEFAULT 'aus', -- aus / sofort / täglich
  notify_push                   TINYINT(1)       NOT NULL DEFAULT 0,
  notify_mail_details           TINYINT(1)       NOT NULL DEFAULT 0,     -- 1 = Titel (z. B. „Seife leer") in der E-Mail, sonst nur Hinweis + Link
  quiet_from                    TIME             NULL,
  quiet_until                   TIME             NULL,
  show_read_receipts            TINYINT(1)       NOT NULL DEFAULT 1,
  privacy_notice_version        VARCHAR(20)      NULL,
  privacy_notice_ack_at         DATETIME         NULL,
  created_at                    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- Genau eine Person ist „Manager" (die assistierte Person):
  manager_marker                TINYINT AS (IF(role = 'manager', 1, NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_user_public (public_id),
  UNIQUE KEY uq_user_username (username),
  UNIQUE KEY uq_user_display (display_name),
  UNIQUE KEY uq_user_email (email),
  UNIQUE KEY uq_user_manager (manager_marker),
  CONSTRAINT ck_user_role         CHECK (role IN ('manager','assistenz','angehoerige')),
  CONSTRAINT ck_user_manager      CHECK (role <> 'manager' OR is_admin = 1),
  CONSTRAINT ck_user_assist_admin CHECK (role <> 'assistenz' OR is_admin = 0),   -- keine Bearbeitung eigener Zeiten
  CONSTRAINT ck_user_dates        CHECK (active_until IS NULL OR active_until >= active_from),
  CONSTRAINT ck_user_pin          CHECK (pin_login_enabled = 0 OR pin_hash IS NOT NULL),
  CONSTRAINT ck_user_2fa          CHECK (twofa_method IN ('keine','passkey','totp','email')),
  CONSTRAINT ck_user_2fa_mail     CHECK (twofa_method <> 'email' OR email IS NOT NULL),
  CONSTRAINT ck_user_2fa_totp     CHECK (twofa_method <> 'totp' OR totp_secret_enc IS NOT NULL),
  CONSTRAINT ck_user_font         CHECK (font_size IN ('normal','gross','sehr_gross')),
  CONSTRAINT ck_user_calview      CHECK (calendar_view IN ('agenda','woche','monat')),
  CONSTRAINT ck_user_mail         CHECK (notify_email IN ('aus','sofort','taeglich'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Person im Haushalt: Rolle, Admin-Recht, Anmeldung (Passwort/Passkey/2FA und/oder Tablet-PIN), Einstellungen';

CREATE TABLE webauthn_credential (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED NOT NULL,
  credential_id  VARBINARY(255)  NOT NULL,
  public_key     BLOB            NOT NULL,
  sign_count     INT UNSIGNED    NOT NULL DEFAULT 0,
  aaguid         BINARY(16)      NULL,
  transports     VARCHAR(100)    NULL,
  label          VARCHAR(100)    NULL,               -- z. B. „Handy"
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at   DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_webauthn_cred (credential_id),
  CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Passkeys';

CREATE TABLE password_reset_request (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  selector      VARCHAR(20)     NOT NULL,
  hashed_token  VARCHAR(100)    NOT NULL,
  requested_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  expires_at    DATETIME        NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reset_selector (selector),
  KEY ix_reset_user (user_id),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Passwort-Reset per E-Mail-Link (einmalig, kurzlebig)';

CREATE TABLE invitation (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id             BIGINT UNSIGNED NOT NULL,
  token_hash          CHAR(64)        NOT NULL,       -- SHA-256 des Einladungscodes
  expires_at          DATETIME        NOT NULL,
  used_at             DATETIME        NULL,
  created_by_user_id  BIGINT UNSIGNED NULL,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_invitation_token (token_hash),
  CONSTRAINT fk_invitation_member  FOREIGN KEY (user_id)            REFERENCES app_user (id) ON DELETE CASCADE,
  CONSTRAINT fk_invitation_creator FOREIGN KEY (created_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Einladungscode/QR (einmalig, 72 h)';

CREATE TABLE device (
  id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name               VARCHAR(50)     NOT NULL,
  token_hash         CHAR(64)        NOT NULL,
  paired_by_user_id  BIGINT UNSIGNED NULL,
  mode               VARCHAR(12)     NOT NULL DEFAULT 'team',   -- team: Team-Tablet ohne Personenanmeldung · persoenlich: nur PIN · beides
  dim_after_seconds  SMALLINT UNSIGNED NOT NULL DEFAULT 300,    -- Bildschirmschoner im Team-Modus
  font_size          VARCHAR(12)     NOT NULL DEFAULT 'normal', -- Schriftgröße am Gerät (Werte wie app_user.font_size)
  paired_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_seen_at       DATETIME        NULL,
  revoked_at         DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_device_token (token_hash),
  CONSTRAINT fk_device_paired FOREIGN KEY (paired_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_device_mode   CHECK (mode IN ('team','persoenlich','beides')),
  CONSTRAINT ck_device_font   CHECK (font_size IN ('normal','gross','sehr_gross'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Gekoppeltes Tablet: Team-Tablet (sieht alles für die Rolle Assistenz, keine Person) und/oder persönliche PIN-Anmeldung';

-- ---------------------------------------------------------------------
--  2. Personalbereich & Rechengrundlagen (nur Manager)
-- ---------------------------------------------------------------------

CREATE TABLE employee_record (
  id                        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id                   BIGINT UNSIGNED NOT NULL,
  real_name_enc             VARBINARY(512)  NULL,          -- optional, nur für Exporte
  iban_enc                  VARBINARY(256)  NULL,          -- optional, GiroCode (Stufe 4)
  entry_date                DATE            NOT NULL,
  exit_date                 DATE            NULL,
  rv_exempt                 TINYINT(1)      NOT NULL DEFAULT 0,
  rv_exempt_since           DATE            NULL,
  statutory_health_insured  TINYINT(1)      NOT NULL DEFAULT 1,  -- steuert 5 % KV-Pauschale
  workdays_per_week         DECIMAL(3,1)    NULL,               -- Basis Urlaubsanspruch
  created_at                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at                DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_employee_user (user_id),
  CONSTRAINT fk_employee_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE RESTRICT,
  CONSTRAINT ck_employee_dates      CHECK (exit_date IS NULL OR exit_date >= entry_date),
  CONSTRAINT ck_employee_rv         CHECK (rv_exempt = 0 OR rv_exempt_since IS NOT NULL),
  CONSTRAINT ck_employee_days       CHECK (workdays_per_week IS NULL OR (workdays_per_week > 0 AND workdays_per_week <= 7))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Personalbereich je Assistenz (minimal)';

CREATE TABLE wage_rate (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id      BIGINT UNSIGNED NOT NULL,
  valid_from   DATE            NOT NULL,
  hourly_rate  DECIMAL(6,2)    NOT NULL,
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_wage_from (user_id, valid_from),
  CONSTRAINT fk_wage_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE RESTRICT,
  CONSTRAINT ck_wage_positive   CHECK (hourly_rate > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Stundenlohn pro Person mit Gültigkeit ab';

CREATE TABLE vacation_entitlement (
  id                 BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id            BIGINT UNSIGNED  NOT NULL,
  year               SMALLINT UNSIGNED NOT NULL,
  days               DECIMAL(4,1)     NOT NULL,
  carried_over_days  DECIMAL(4,1)     NOT NULL DEFAULT 0,
  note               VARCHAR(255)     NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vacation_year (user_id, year),
  CONSTRAINT fk_vacation_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE RESTRICT,
  CONSTRAINT ck_vacation_days       CHECK (days >= 0 AND carried_over_days >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Urlaubsanspruch je Person und Jahr';

CREATE TABLE legal_limit (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(30)     NOT NULL,
  valid_from   DATE            NOT NULL,
  amount       DECIMAL(10,2)   NOT NULL,
  source_note  VARCHAR(255)    NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_limit_code_from (code, valid_from),
  CONSTRAINT ck_limit_code CHECK (code IN ('minijob_grenze','mindestlohn','rv_mindestbemessung'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Global: gesetzliche Grenzen mit Gültigkeit ab';

CREATE TABLE contribution_rate (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  code         VARCHAR(30)     NOT NULL,
  valid_from   DATE            NOT NULL,
  rate_pct     DECIMAL(6,3)    NOT NULL,
  source_note  VARCHAR(255)    NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_rate_code_from (code, valid_from),
  CONSTRAINT ck_rate_code CHECK (code IN ('kv_pauschal','rv_arbeitgeber','rv_gesamt','pauschsteuer','u1','u2','unfall','u1_erstattung')),
  CONSTRAINT ck_rate_range CHECK (rate_pct >= 0 AND rate_pct <= 100)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Global: Abgabensätze Haushaltsscheck mit Gültigkeit ab';

CREATE TABLE funding_grant (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  file_reference  VARCHAR(50)     NULL,            -- Aktenzeichen
  valid_from      DATE            NOT NULL,
  valid_until     DATE            NOT NULL,
  monthly_budget  DECIMAL(10,2)   NOT NULL,
  monthly_hours   DECIMAL(6,2)    NULL,
  note            VARCHAR(255)    NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_grant_period (valid_from),
  CONSTRAINT ck_grant_dates   CHECK (valid_until >= valid_from),
  CONSTRAINT ck_grant_budget  CHECK (monthly_budget > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bewilligung des Bezirks (Budget je Monat)';

-- ---------------------------------------------------------------------
--  2b. Team: Schlüssel & Zugänge, Checklisten für Einstellung und Austritt
-- ---------------------------------------------------------------------

CREATE TABLE checklist_template (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind        VARCHAR(12)     NOT NULL,
  title       VARCHAR(100)    NOT NULL,
  is_active   TINYINT(1)      NOT NULL DEFAULT 1,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_cl_template (kind),
  CONSTRAINT ck_cl_template_kind   CHECK (kind IN ('einstellung','austritt'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vorlage Checkliste (wird beim Anlegen des Haushalts mit Standardpunkten befüllt, anpassbar)';

CREATE TABLE checklist_template_item (
  id             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  template_id    BIGINT UNSIGNED   NOT NULL,
  sort           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  label          VARCHAR(200)      NOT NULL,
  hint           TEXT              NULL,
  due_rule       VARCHAR(16)       NULL,
  assignee_role  VARCHAR(12)      NOT NULL DEFAULT 'admins',  -- wer den Punkt zunächst bekommt
  auto_key       VARCHAR(30)       NULL,          -- wird von der App automatisch abgehakt
  PRIMARY KEY (id),
  KEY ix_cl_titem_template (template_id, sort),
  CONSTRAINT fk_cl_titem_template FOREIGN KEY (template_id) REFERENCES checklist_template (id) ON DELETE CASCADE,
  CONSTRAINT ck_cl_titem_due      CHECK (due_rule IS NULL OR due_rule IN ('vor_beginn','erster_tag','erste_woche','letzter_tag','nach_austritt')),
  CONSTRAINT ck_cl_titem_role     CHECK (assignee_role IN ('admins','manager','assistenz','angehoerige')),
  CONSTRAINT ck_cl_titem_auto     CHECK (auto_key IS NULL OR auto_key IN (
      'person_angelegt','zugang_eingerichtet','datenschutz_bestaetigt','dienstplan_angelegt','lohn_hinterlegt',
      'austritt_eingetragen','zugang_gesperrt','letzte_abrechnung'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Punkte einer Checklisten-Vorlage';

CREATE TABLE checklist (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id         BIGINT UNSIGNED NOT NULL,
  template_id     BIGINT UNSIGNED NULL,
  kind            VARCHAR(12)     NOT NULL,
  reference_date  DATE            NOT NULL,     -- Eintritts- bzw. Austrittsdatum
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at    DATETIME        NULL,
  PRIMARY KEY (id),
  KEY ix_checklist_member (user_id, kind),
  KEY ix_checklist (completed_at),
  KEY ix_checklist_template (template_id),
  CONSTRAINT fk_checklist_member   FOREIGN KEY (user_id) REFERENCES app_user (id)         ON DELETE CASCADE,
  CONSTRAINT fk_checklist_template FOREIGN KEY (template_id)   REFERENCES checklist_template (id) ON DELETE SET NULL,
  CONSTRAINT ck_checklist_kind     CHECK (kind IN ('einstellung','austritt'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Checkliste für eine konkrete Einstellung bzw. einen Austritt';

CREATE TABLE checklist_item (
  id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  checklist_id      BIGINT UNSIGNED   NOT NULL,
  sort              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  label             VARCHAR(200)      NOT NULL,
  hint              TEXT              NULL,
  due_date          DATE              NULL,
  assignee_user_id  BIGINT UNSIGNED   NULL,     -- weitergegeben an eine Person …
  assignee_role     VARCHAR(12)       NULL,     -- … oder an eine Rolle (inkl. „admins")
  auto_key          VARCHAR(30)       NULL,
  done_at           DATETIME          NULL,
  done_by_user_id   BIGINT UNSIGNED   NULL,
  is_skipped        TINYINT(1)        NOT NULL DEFAULT 0,   -- „entfällt"
  note              VARCHAR(255)      NULL,
  PRIMARY KEY (id),
  KEY ix_cl_item_checklist (checklist_id, sort),
  KEY ix_cl_item_doneby (done_by_user_id),
  KEY ix_cl_item_assignee (assignee_user_id),
  CONSTRAINT fk_cl_item_checklist FOREIGN KEY (checklist_id)     REFERENCES checklist (id) ON DELETE CASCADE,
  CONSTRAINT fk_cl_item_doneby    FOREIGN KEY (done_by_user_id)  REFERENCES app_user (id)  ON DELETE SET NULL,
  CONSTRAINT fk_cl_item_assignee  FOREIGN KEY (assignee_user_id) REFERENCES app_user (id)  ON DELETE SET NULL,
  CONSTRAINT ck_cl_item_role      CHECK (assignee_role IS NULL OR assignee_role IN ('admins','manager','assistenz','angehoerige')),
  CONSTRAINT ck_cl_item_state     CHECK (NOT (is_skipped = 1 AND done_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Punkt einer konkreten Checkliste (erledigt / entfällt)';

-- ---------------------------------------------------------------------
--  3. Orte, Hilfsmittel, Wissen
-- ---------------------------------------------------------------------

CREATE TABLE poi (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id       BINARY(16)      NOT NULL,
  name            VARCHAR(100)    NOT NULL,
  category        VARCHAR(30)     NOT NULL,     -- arzt, apotheke, baecker, supermarkt, sanitaetshaus, behoerde, spazierweg …
  street          VARCHAR(150)    NULL,
  postal_code     VARCHAR(10)     NULL,
  city            VARCHAR(100)    NULL,
  latitude        DECIMAL(9,6)    NULL,
  longitude       DECIMAL(9,6)    NULL,
  phone           VARCHAR(30)     NULL,
  website         VARCHAR(255)    NULL,
  opening_hours   TEXT            NULL,
  note            TEXT            NULL,
  preferences     TEXT            NULL,          -- „hier das Dinkelbrot"
  a11y_step_free  VARCHAR(10)     NULL,
  a11y_entrance   VARCHAR(500)    NULL,
  a11y_elevator   TINYINT(1)      NULL,
  a11y_toilet     TINYINT(1)      NULL,
  a11y_parking    TINYINT(1)      NULL,
  a11y_auto_door  TINYINT(1)      NULL,
  created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_poi_public (public_id),
  KEY ix_poi_cat (category),
  CONSTRAINT ck_poi_stepfree CHECK (a11y_step_free IS NULL OR a11y_step_free IN ('ja','nein','teilweise')),
  CONSTRAINT ck_poi_coords   CHECK ((latitude IS NULL AND longitude IS NULL)
                                 OR (latitude IS NOT NULL AND longitude IS NOT NULL
                                     AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Orte mit Barrierefreiheit und Navigations-Link';

CREATE TABLE asset (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id        BINARY(16)      NOT NULL,
  name             VARCHAR(100)    NOT NULL,
  asset_type       VARCHAR(50)     NULL,          -- E-Rollstuhl, Aktivrollstuhl, Rollator, Lifter …
  manufacturer     VARCHAR(100)    NULL,
  model            VARCHAR(100)    NULL,
  serial_number    VARCHAR(100)    NULL,
  location_text    VARCHAR(200)    NULL,          -- Standort in der Wohnung
  supplier_poi_id  BIGINT UNSIGNED NULL,          -- Sanitätshaus
  status           VARCHAR(15)     NOT NULL DEFAULT 'in_betrieb',
  note             TEXT            NULL,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_asset_public (public_id),
  CONSTRAINT fk_asset_supplier FOREIGN KEY (supplier_poi_id) REFERENCES poi (id)    ON DELETE SET NULL,
  CONSTRAINT ck_asset_status   CHECK (status IN ('in_betrieb','defekt','in_reparatur','ausgemustert'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Hilfsmittel';

CREATE TABLE asset_component (
  id              BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  asset_id        BIGINT UNSIGNED   NOT NULL,
  name            VARCHAR(100)      NOT NULL,     -- „Reifen hinten links"
  component_type  VARCHAR(30)       NULL,         -- reifen, akku, polster …
  sort            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_component_asset (asset_id, sort),
  CONSTRAINT fk_component_asset  FOREIGN KEY (asset_id)  REFERENCES asset (id)  ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Bauteile eines Hilfsmittels';

CREATE TABLE asset_component_property (
  id             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  component_id   BIGINT UNSIGNED   NOT NULL,
  prop_key       VARCHAR(50)       NOT NULL,      -- solldruck, ventil, groesse …
  label          VARCHAR(50)       NOT NULL,
  value_num      DECIMAL(10,2)     NULL,
  value_text     VARCHAR(100)      NULL,
  unit           VARCHAR(12)       NULL,          -- bar, psi, Zoll …
  is_target      TINYINT(1)        NOT NULL DEFAULT 0,   -- Sollwert → Checkliste
  tolerance_pct  DECIMAL(5,2)      NULL,
  sort           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_property_key (component_id, prop_key),
  CONSTRAINT fk_property_component FOREIGN KEY (component_id) REFERENCES asset_component (id) ON DELETE CASCADE,
  CONSTRAINT ck_property_value     CHECK (value_num IS NOT NULL OR value_text IS NOT NULL),
  CONSTRAINT ck_property_target    CHECK (is_target = 0 OR value_num IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Eigenschaften/Sollwerte von Bauteilen (z. B. Solldruck 6,0 bar)';

CREATE TABLE guide (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id           BINARY(16)      NOT NULL,
  guide_type          VARCHAR(15)     NOT NULL,
  category            VARCHAR(50)     NULL,
  is_archived         TINYINT(1)      NOT NULL DEFAULT 0,
  translatable        TINYINT(1)      NOT NULL DEFAULT 1,      -- darf per KI übersetzt werden (Stufe 5)
  locked_by_user_id   BIGINT UNSIGNED NULL,      -- „Ben bearbeitet gerade"
  locked_at           DATETIME        NULL,
  last_reviewed_on    DATE            NULL,
  created_by_user_id  BIGINT UNSIGNED NULL,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_guide_public (public_id),
  KEY ix_guide (guide_type),
  CONSTRAINT fk_guide_locker  FOREIGN KEY (locked_by_user_id)  REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_guide_creator FOREIGN KEY (created_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_guide_type    CHECK (guide_type IN ('anleitung','rezept','steckbrief','notfallplan'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Wissensbasis: stabile Identität (für QR/Links), Inhalt in guide_version';

CREATE TABLE guide_version (
  id                   BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  guide_id             BIGINT UNSIGNED   NOT NULL,
  version_no           INT UNSIGNED      NOT NULL,
  status               VARCHAR(15)       NOT NULL DEFAULT 'entwurf',
  title                VARCHAR(150)      NOT NULL,
  summary              VARCHAR(500)      NULL,
  duration_minutes     SMALLINT UNSIGNED NULL,
  materials            TEXT              NULL,
  servings             TINYINT UNSIGNED  NULL,        -- Rezept
  personal_note        TEXT              NULL,        -- „so mag ich es"
  change_note          VARCHAR(255)      NULL,
  author_user_id       BIGINT UNSIGNED   NULL,
  approved_by_user_id  BIGINT UNSIGNED   NULL,
  created_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at         DATETIME          NULL,
  published_at         DATETIME          NULL,
  -- Je Anleitung höchstens eine veröffentlichte und eine offene Version:
  published_marker     TINYINT AS (IF(status = 'veroeffentlicht', 1, NULL)) STORED,
  open_marker          TINYINT AS (IF(status IN ('entwurf','zur_freigabe'), 1, NULL)) STORED,
  PRIMARY KEY (id),
  UNIQUE KEY uq_version_no        (guide_id, version_no),
  UNIQUE KEY uq_version_published (guide_id, published_marker),
  UNIQUE KEY uq_version_open      (guide_id, open_marker),
  FULLTEXT KEY ft_version_text (title, summary, materials),
  CONSTRAINT fk_version_guide    FOREIGN KEY (guide_id)                  REFERENCES guide (id)      ON DELETE CASCADE,
  CONSTRAINT fk_version_author   FOREIGN KEY (author_user_id)      REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_version_approver FOREIGN KEY (approved_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_version_status   CHECK (status IN ('entwurf','zur_freigabe','veroeffentlicht','ersetzt','verworfen'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Versionen einer Anleitung (Freigabe-Workflow, Verlauf)';

CREATE TABLE guide_step (
  id          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  version_id  BIGINT UNSIGNED   NOT NULL,
  sort        SMALLINT UNSIGNED NOT NULL,
  body        TEXT              NOT NULL,         -- bereinigtes HTML aus dem Editor (fett, Listen, Links)
  hint_type   VARCHAR(10)       NULL,
  hint_text   VARCHAR(500)      NULL,
  PRIMARY KEY (id),
  KEY ix_step_version (version_id, sort),
  FULLTEXT KEY ft_step_text (body, hint_text),
  CONSTRAINT fk_step_version FOREIGN KEY (version_id) REFERENCES guide_version (id) ON DELETE CASCADE,
  CONSTRAINT ck_step_hint    CHECK ((hint_type IS NULL AND hint_text IS NULL)
                                 OR (hint_type IS NOT NULL AND hint_type IN ('tipp','achtung') AND hint_text IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Schritte einer Anleitungs-Version';

CREATE TABLE recipe_ingredient (
  id          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  version_id  BIGINT UNSIGNED   NOT NULL,
  sort        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  amount      DECIMAL(8,2)      NULL,
  unit        VARCHAR(12)       NULL,
  name        VARCHAR(100)      NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ingredient_version (version_id, sort),
  CONSTRAINT fk_ingredient_version FOREIGN KEY (version_id) REFERENCES guide_version (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Zutaten (nur Typ rezept)';

CREATE TABLE guide_translation (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  version_id           BIGINT UNSIGNED NOT NULL,     -- gehört zu genau einer Version → neue Version = neu übersetzen
  language             VARCHAR(10)     NOT NULL,     -- z. B. en, pl, ro, uk, tr, ar
  title                VARCHAR(150)    NOT NULL,
  summary              VARCHAR(500)    NULL,
  provider_model       VARCHAR(80)     NULL,
  reviewed_by_user_id  BIGINT UNSIGNED NULL,         -- optional von einer Person geprüft
  reviewed_at          DATETIME        NULL,
  created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_translation_lang (version_id, language),
  KEY ix_translation_reviewer (reviewed_by_user_id),
  CONSTRAINT fk_translation_version  FOREIGN KEY (version_id)                REFERENCES guide_version (id) ON DELETE CASCADE,
  CONSTRAINT fk_translation_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES app_user (id)    ON DELETE SET NULL,
  CONSTRAINT ck_translation_lang     CHECK (language <> 'de')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='KI-Übersetzung einer Anleitungs-Version (Stufe 5)';

CREATE TABLE guide_step_translation (
  translation_id  BIGINT UNSIGNED NOT NULL,
  step_id         BIGINT UNSIGNED NOT NULL,
  body            TEXT            NOT NULL,
  hint_text       VARCHAR(500)    NULL,
  PRIMARY KEY (translation_id, step_id),
  KEY ix_step_translation_step (step_id),
  CONSTRAINT fk_step_tr_translation FOREIGN KEY (translation_id) REFERENCES guide_translation (id) ON DELETE CASCADE,
  CONSTRAINT fk_step_tr_step        FOREIGN KEY (step_id)        REFERENCES guide_step (id)        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Übersetzter Text je Schritt';

CREATE TABLE guide_tag (
  guide_id  BIGINT UNSIGNED NOT NULL,
  tag       VARCHAR(50)     NOT NULL,
  PRIMARY KEY (guide_id, tag),
  KEY ix_tag (tag),
  CONSTRAINT fk_tag_guide FOREIGN KEY (guide_id) REFERENCES guide (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Schlagworte';

CREATE TABLE guide_acknowledgement (
  id                     BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  guide_id               BIGINT UNSIGNED  NOT NULL,
  user_id                BIGINT UNSIGNED  NOT NULL,
  read_at                DATETIME         NULL,          -- „gelesen" (bestätigt die Person selbst)
  read_version_no        INT UNSIGNED     NULL,          -- welche Version → Hinweis „seitdem geändert"
  instructed_at          DATETIME         NULL,          -- „eingewiesen" (bestätigt ein Admin)
  instructed_by_user_id  BIGINT UNSIGNED  NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ack (guide_id, user_id),
  KEY ix_ack_user (user_id),
  KEY ix_ack_instructor (instructed_by_user_id),
  CONSTRAINT fk_ack_guide      FOREIGN KEY (guide_id)              REFERENCES guide (id)    ON DELETE CASCADE,
  CONSTRAINT fk_ack_user       FOREIGN KEY (user_id)               REFERENCES app_user (id) ON DELETE CASCADE,
  CONSTRAINT fk_ack_instructor FOREIGN KEY (instructed_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_ack_state      CHECK (read_at IS NOT NULL OR instructed_at IS NOT NULL),
  CONSTRAINT ck_ack_version    CHECK (read_at IS NULL OR read_version_no IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Wer kennt welche Anleitung: gelesen (selbst) / eingewiesen (Admin) – ersetzt Lernpfade';

CREATE TABLE guide_feedback (
  id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  guide_id     BIGINT UNSIGNED NOT NULL,
  user_id      BIGINT UNSIGNED NULL,
  body         VARCHAR(500)    NOT NULL,
  status       VARCHAR(10)     NOT NULL DEFAULT 'offen',
  created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  resolved_at  DATETIME        NULL,
  PRIMARY KEY (id),
  KEY ix_feedback_guide (guide_id, status),
  CONSTRAINT fk_feedback_guide  FOREIGN KEY (guide_id)      REFERENCES guide (id)      ON DELETE CASCADE,
  CONSTRAINT fk_feedback_member FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_feedback_status CHECK (status IN ('offen','erledigt'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='„Stimmt nicht mehr"-Hinweise';

CREATE TABLE asset_guide (
  asset_id  BIGINT UNSIGNED NOT NULL,
  guide_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (asset_id, guide_id),
  KEY ix_asset_guide_guide (guide_id),
  CONSTRAINT fk_asset_guide_asset FOREIGN KEY (asset_id) REFERENCES asset (id) ON DELETE CASCADE,
  CONSTRAINT fk_asset_guide_guide FOREIGN KEY (guide_id) REFERENCES guide (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE poi_guide (
  poi_id    BIGINT UNSIGNED NOT NULL,
  guide_id  BIGINT UNSIGNED NOT NULL,
  PRIMARY KEY (poi_id, guide_id),
  KEY ix_poi_guide_guide (guide_id),
  CONSTRAINT fk_poi_guide_poi   FOREIGN KEY (poi_id)   REFERENCES poi (id)   ON DELETE CASCADE,
  CONSTRAINT fk_poi_guide_guide FOREIGN KEY (guide_id) REFERENCES guide (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
--  4. Profil des Managers
-- ---------------------------------------------------------------------

CREATE TABLE profile_item (
  id             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  section        VARCHAR(20)       NOT NULL,
  kind           VARCHAR(20)       NULL,          -- nur bei Unverträglichkeiten
  allergen_code  VARCHAR(20)       NULL,          -- EU-14-Hauptallergene
  title          VARCHAR(100)      NOT NULL,
  body           TEXT              NULL,
  severity       TINYINT UNSIGNED  NULL,          -- 1 leicht · 2 mittel · 3 Notfall
  action_text    TEXT              NULL,          -- „Was tun bei Kontakt"
  visibility     VARCHAR(10)       NOT NULL DEFAULT 'team',
  sort           SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_profile_section (section, sort),
  CONSTRAINT ck_profile_section    CHECK (section IN ('unvertraeglichkeit','essen','alltag','zusammenarbeit','notfall')),
  CONSTRAINT ck_profile_kind       CHECK ((section = 'unvertraeglichkeit' AND kind IS NOT NULL AND kind IN ('abneigung','unvertraeglichkeit','allergie'))
                                       OR (section <> 'unvertraeglichkeit' AND kind IS NULL)),
  CONSTRAINT ck_profile_severity   CHECK (severity IS NULL OR severity BETWEEN 1 AND 3),
  CONSTRAINT ck_profile_visibility CHECK (visibility IN ('team','privat'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Profil: Unverträglichkeiten, Vorlieben, Routinen, Zusammenarbeit, Notfall';

CREATE TABLE profile_keyword (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  profile_item_id  BIGINT UNSIGNED NOT NULL,
  keyword          VARCHAR(50)     NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_keyword (profile_item_id, keyword),
  KEY ix_keyword (keyword),
  CONSTRAINT fk_keyword_item   FOREIGN KEY (profile_item_id) REFERENCES profile_item (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Schlagworte für Warnungen in Einkaufsliste/Rezepten';

CREATE TABLE emergency_contact (
  id                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  category            VARCHAR(15)       NOT NULL DEFAULT 'angehoerige',   -- 15: 'dienstleister' hat 13 Zeichen (M4)
  name                VARCHAR(100)      NOT NULL,
  relation            VARCHAR(50)       NULL,          -- „Schwester", „Hausarztpraxis"
  phone               VARCHAR(30)       NOT NULL,
  phone_alt           VARCHAR(30)       NULL,
  when_to_call        VARCHAR(255)      NULL,          -- „immer zuerst", „nur werktags 8–18 Uhr"
  poi_id              BIGINT UNSIGNED   NULL,          -- z. B. Praxis aus den Orten
  visibility          VARCHAR(10)       NOT NULL DEFAULT 'alle',
  show_on_lockscreen  TINYINT(1)        NOT NULL DEFAULT 1,
  sort                SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_emergency (category, sort),
  KEY ix_emergency_poi (poi_id),
  CONSTRAINT fk_emergency_poi      FOREIGN KEY (poi_id) REFERENCES poi (id) ON DELETE SET NULL,
  CONSTRAINT ck_emergency_category CHECK (category IN ('angehoerige','arzt','notdienst','dienstleister','sonstiges')),
  CONSTRAINT ck_emergency_visible  CHECK (visibility IN ('alle','admins'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Notfall-Infos: Kontakte und wichtige Nummern (Sperrbildschirm ohne PIN, wenn show_on_lockscreen)';

-- ---------------------------------------------------------------------
--  5. Dienstplan, Arbeitszeit, Abwesenheit, Abrechnung
-- ---------------------------------------------------------------------

CREATE TABLE shift_pattern (
  id             BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id        BIGINT UNSIGNED  NOT NULL,
  weekday        TINYINT UNSIGNED NOT NULL,      -- 1 = Montag … 7 = Sonntag
  start_time     TIME             NOT NULL,
  end_time       TIME             NOT NULL,
  every_n_weeks  TINYINT UNSIGNED NOT NULL DEFAULT 1,
  valid_from     DATE             NOT NULL,      -- legt bei 14-tägig auch den Rhythmus fest
  valid_until    DATE             NULL,
  note           VARCHAR(255)     NULL,
  created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_pattern_member (user_id, weekday),
  CONSTRAINT fk_pattern_member FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE,
  CONSTRAINT ck_pattern_weekday CHECK (weekday BETWEEN 1 AND 7),
  CONSTRAINT ck_pattern_times   CHECK (end_time > start_time),
  CONSTRAINT ck_pattern_weeks   CHECK (every_n_weeks BETWEEN 1 AND 4),
  CONSTRAINT ck_pattern_dates   CHECK (valid_until IS NULL OR valid_until >= valid_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Feste Tage: „Anna jeden Dienstag 9–13"';

CREATE TABLE planned_shift (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           BIGINT UNSIGNED NOT NULL,
  shift_pattern_id  BIGINT UNSIGNED NULL,        -- NULL = einzeln eingetragen
  occurrence_date   DATE            NULL,        -- ursprünglicher Tag laut Muster (bleibt beim Verschieben gleich)
  is_modified       TINYINT(1)      NOT NULL DEFAULT 0,  -- von Hand geändert → bei Musteränderung nicht neu erzeugen
  starts_at         DATETIME        NOT NULL,
  ends_at           DATETIME        NOT NULL,
  status            VARCHAR(12)     NOT NULL DEFAULT 'geplant',
  cancel_reason     VARCHAR(20)     NULL,        -- nur Manager + Person sehen den Grund
  cancel_paid       TINYINT(1)      NULL,        -- ausgefallen, aber bezahlt (Entscheidung Manager)
  note              VARCHAR(255)    NULL,
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_planned_from_pattern (shift_pattern_id, occurrence_date),
  KEY ix_planned_time (starts_at),
  KEY ix_planned_member_time (user_id, starts_at),
  CONSTRAINT fk_planned_member  FOREIGN KEY (user_id)    REFERENCES app_user (id)    ON DELETE RESTRICT,
  CONSTRAINT fk_planned_pattern FOREIGN KEY (shift_pattern_id) REFERENCES shift_pattern (id) ON DELETE SET NULL,
  CONSTRAINT ck_planned_times   CHECK (ends_at > starts_at),
  CONSTRAINT ck_planned_status  CHECK (status IN ('geplant','ausgefallen')),
  -- Urlaub/Krank werden über absence vergütet; bei allen anderen Ausfällen entscheidet der Manager,
  -- ob der Dienst trotzdem bezahlt wird (z. B. Annahmeverzug, wenn der Arbeitgeber absagt).
  CONSTRAINT ck_planned_reason  CHECK ((status = 'geplant' AND cancel_reason IS NULL AND cancel_paid IS NULL)
                                    OR (status = 'ausgefallen' AND cancel_reason IS NOT NULL
                                        AND cancel_reason IN ('urlaub','krank') AND cancel_paid IS NULL)
                                    OR (status = 'ausgefallen' AND cancel_reason IS NOT NULL
                                        AND cancel_reason IN ('manager_abwesend','assistenz_verhindert','sonstiges')
                                        AND cancel_paid IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Konkreter geplanter Dienst (Grundlage Lohnfortzahlung/Feiertag)';

CREATE TABLE time_entry (
  id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  user_id           BIGINT UNSIGNED   NOT NULL,
  planned_shift_id  BIGINT UNSIGNED   NULL,
  entry_kind        VARCHAR(12)       NOT NULL DEFAULT 'dienst',   -- Einarbeitung/Besprechung ist auch Arbeitszeit
  started_at        DATETIME          NOT NULL,      -- UTC
  ended_at          DATETIME          NULL,          -- UTC; NULL = Dienst läuft
  break_minutes     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  duration_minutes  INT UNSIGNED      NULL,          -- beim Beenden berechnet (Ende − Beginn − Pause)
  start_source      VARCHAR(10)       NOT NULL,
  end_source        VARCHAR(10)       NULL,
  device_id         BIGINT UNSIGNED   NULL,
  is_backfilled     TINYINT(1)        NOT NULL DEFAULT 0,  -- nachgetragen
  is_corrected      TINYINT(1)        NOT NULL DEFAULT 0,
  note              VARCHAR(255)      NULL,
  created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  -- Pro Person höchstens ein offener Dienst:
  open_marker       TINYINT AS (IF(ended_at IS NULL, 1, NULL)) STORED,
  PRIMARY KEY (id),
  KEY ix_time_member_start (user_id, started_at),     -- muss vor uq_time_one_open stehen (FK-Index ohne generierte Spalte)
  KEY ix_time_start (started_at),
  UNIQUE KEY uq_time_one_open (user_id, open_marker),
  CONSTRAINT fk_time_member  FOREIGN KEY (user_id)    REFERENCES app_user (id)    ON DELETE RESTRICT,
  CONSTRAINT fk_time_planned FOREIGN KEY (planned_shift_id) REFERENCES planned_shift (id) ON DELETE SET NULL,
  CONSTRAINT fk_time_device  FOREIGN KEY (device_id)        REFERENCES device (id)        ON DELETE SET NULL,
  CONSTRAINT ck_time_order   CHECK (ended_at IS NULL OR ended_at > started_at),
  CONSTRAINT ck_time_closed  CHECK ((ended_at IS NULL AND duration_minutes IS NULL AND end_source IS NULL)
                                 OR (ended_at IS NOT NULL AND duration_minutes IS NOT NULL AND end_source IS NOT NULL)),
  CONSTRAINT ck_time_kind    CHECK (entry_kind IN ('dienst','einarbeitung','besprechung')),
  CONSTRAINT ck_time_source  CHECK (start_source IN ('tablet','handy','manuell','dienstplan')
                                 AND (end_source IS NULL OR end_source IN ('tablet','handy','manuell','dienstplan')))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Arbeitszeit: Dienst beginnen/beenden (kein Standort)';

CREATE TABLE time_entry_change (
  id                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  time_entry_id       BIGINT UNSIGNED   NOT NULL,
  kind                VARCHAR(12)       NOT NULL,   -- selbst: Assistenz am selben Tag · admin: Korrektur danach · nachtrag: vom Admin neu erfasst
  changed_by_user_id  BIGINT UNSIGNED   NULL,
  old_started_at      DATETIME          NULL,
  old_ended_at        DATETIME          NULL,
  old_break_minutes   SMALLINT UNSIGNED NULL,
  new_started_at      DATETIME          NULL,
  new_ended_at        DATETIME          NULL,
  new_break_minutes   SMALLINT UNSIGNED NULL,
  reason              VARCHAR(255)      NULL,
  created_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_change_entry (time_entry_id),
  KEY ix_change_by (changed_by_user_id),
  CONSTRAINT fk_change_entry  FOREIGN KEY (time_entry_id)      REFERENCES time_entry (id) ON DELETE CASCADE,
  CONSTRAINT fk_change_by     FOREIGN KEY (changed_by_user_id) REFERENCES app_user (id)   ON DELETE SET NULL,
  CONSTRAINT ck_change_kind   CHECK (kind IN ('selbst','admin','nachtrag')),
  CONSTRAINT ck_change_reason CHECK (kind = 'selbst' OR reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Änderungsverlauf der Arbeitszeit (Assistenz nur am selben Tag, danach nur Admins mit Begründung)';

CREATE TABLE absence (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id               BIGINT UNSIGNED NOT NULL,
  absence_type          VARCHAR(12)     NOT NULL,
  date_from             DATE            NOT NULL,
  date_to               DATE            NOT NULL,
  status                VARCHAR(12)     NOT NULL,
  sick_note_presented   TINYINT(1)      NULL,          -- nur bei krank; KEINE Diagnose, kein Dokument
  note                  VARCHAR(255)    NULL,
  requested_by_user_id  BIGINT UNSIGNED NULL,
  decided_by_user_id    BIGINT UNSIGNED NULL,
  decided_at            DATETIME        NULL,
  created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_absence_member (user_id, date_from),
  KEY ix_absence (date_from),
  CONSTRAINT fk_absence_member    FOREIGN KEY (user_id)              REFERENCES app_user (id) ON DELETE RESTRICT,
  CONSTRAINT fk_absence_requester FOREIGN KEY (requested_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_absence_decider   FOREIGN KEY (decided_by_user_id)   REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_absence_type      CHECK (absence_type IN ('urlaub','krank','unbezahlt','sonstiges')),
  CONSTRAINT ck_absence_status    CHECK (status IN ('beantragt','genehmigt','abgelehnt','storniert','erfasst')),
  CONSTRAINT ck_absence_dates     CHECK (date_to >= date_from),
  CONSTRAINT ck_absence_sicknote  CHECK (absence_type = 'krank' OR sick_note_presented IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Urlaub, Krankheit (nur Zeitraum), sonstige Abwesenheit';

CREATE TABLE sick_pay_refund (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  absence_id       BIGINT UNSIGNED NOT NULL,
  continued_pay    DECIMAL(10,2)   NOT NULL,        -- gezahlte Lohnfortzahlung
  refund_rate_pct  DECIMAL(6,3)    NOT NULL DEFAULT 80.000,
  applied_on       DATE            NULL,
  refunded_on      DATE            NULL,
  refunded_amount  DECIMAL(10,2)   NULL,
  note             VARCHAR(255)    NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_refund_absence (absence_id),
  CONSTRAINT fk_refund_absence FOREIGN KEY (absence_id) REFERENCES absence (id) ON DELETE RESTRICT,
  CONSTRAINT ck_refund_amounts CHECK (continued_pay >= 0 AND (refunded_amount IS NULL OR refunded_amount >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='U1-Erstattung der Lohnfortzahlung (Minijob-Zentrale)';

CREATE TABLE monthly_closing (
  id                   BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  year                 SMALLINT UNSIGNED NOT NULL,
  month                TINYINT UNSIGNED  NOT NULL,
  status               VARCHAR(15)       NOT NULL DEFAULT 'offen',
  closed_by_user_id    BIGINT UNSIGNED   NULL,
  closed_at            DATETIME          NULL,
  reopened_by_user_id  BIGINT UNSIGNED   NULL,
  reopened_at          DATETIME          NULL,
  reopen_reason        VARCHAR(255)      NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_closing_month (year, month),
  CONSTRAINT fk_closing_closer   FOREIGN KEY (closed_by_user_id)   REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_closing_reopener FOREIGN KEY (reopened_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_closing_month    CHECK (month BETWEEN 1 AND 12),
  CONSTRAINT ck_closing_status   CHECK (status IN ('offen','abgeschlossen','wiedergeoeffnet')),
  CONSTRAINT ck_closing_reason   CHECK (status <> 'wiedergeoeffnet' OR reopen_reason IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Monatsabschluss (sperrt Zeiten des Monats)';

CREATE TABLE timesheet_confirmation (
  id            BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED   NOT NULL,
  year          SMALLINT UNSIGNED NOT NULL,
  month         TINYINT UNSIGNED  NOT NULL,
  confirmed_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_confirmation (user_id, year, month),
  KEY ix_confirmation (year, month),
  CONSTRAINT fk_confirm_member FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE RESTRICT,
  CONSTRAINT ck_confirm_month  CHECK (month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Assistenz bestätigt ihre Monatsstunden (interne Kontrolle, keine Unterschrift)';

CREATE TABLE payroll_line (
  id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  monthly_closing_id      BIGINT UNSIGNED NOT NULL,
  user_id                 BIGINT UNSIGNED NOT NULL,
  minutes_worked          INT UNSIGNED    NOT NULL DEFAULT 0,
  minutes_vacation        INT UNSIGNED    NOT NULL DEFAULT 0,
  minutes_sick            INT UNSIGNED    NOT NULL DEFAULT 0,
  minutes_holiday         INT UNSIGNED    NOT NULL DEFAULT 0,
  minutes_cancelled_paid  INT UNSIGNED    NOT NULL DEFAULT 0,   -- abgesagt, aber bezahlt
  gross_work              DECIMAL(10,2)   NOT NULL DEFAULT 0,
  gross_vacation          DECIMAL(10,2)   NOT NULL DEFAULT 0,
  gross_sick              DECIMAL(10,2)   NOT NULL DEFAULT 0,
  gross_holiday           DECIMAL(10,2)   NOT NULL DEFAULT 0,
  gross_cancelled_paid    DECIMAL(10,2)   NOT NULL DEFAULT 0,
  gross_total             DECIMAL(10,2)   NOT NULL,
  rv_employee_share       DECIMAL(10,2)   NOT NULL DEFAULT 0,
  payout                  DECIMAL(10,2)   NOT NULL,
  employer_contributions  DECIMAL(10,2)   NOT NULL,
  total_cost              DECIMAL(10,2)   NOT NULL,
  minijob_limit           DECIMAL(10,2)   NOT NULL,
  over_limit              TINYINT(1)      NOT NULL DEFAULT 0,
  calculated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_payroll_person (monthly_closing_id, user_id),
  KEY ix_payroll_member (user_id),
  CONSTRAINT fk_payroll_closing FOREIGN KEY (monthly_closing_id) REFERENCES monthly_closing (id) ON DELETE CASCADE,
  CONSTRAINT fk_payroll_member  FOREIGN KEY (user_id)      REFERENCES app_user (id)      ON DELETE RESTRICT,
  CONSTRAINT ck_payroll_gross   CHECK (gross_total = gross_work + gross_vacation + gross_sick + gross_holiday + gross_cancelled_paid),
  CONSTRAINT ck_payroll_payout  CHECK (payout = gross_total - rv_employee_share),
  CONSTRAINT ck_payroll_cost    CHECK (total_cost = gross_total + employer_contributions)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Festgeschriebene Lohnberechnung je Person und Monat';

-- ---------------------------------------------------------------------
--  6. Alltag: Aufgaben, Übergabe, Termine, Einkauf
-- ---------------------------------------------------------------------

CREATE TABLE manager_absence (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  date_from           DATE            NOT NULL,
  date_to             DATE            NOT NULL,
  label               VARCHAR(100)    NULL,          -- privat, z. B. „Urlaub Ostsee"; Team sieht nur „nicht zu Hause"
  pause_tasks         TINYINT(1)      NOT NULL DEFAULT 1,
  show_to_team        TINYINT(1)      NOT NULL DEFAULT 1,
  created_by_user_id  BIGINT UNSIGNED NULL,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_mgr_absence (date_from),
  CONSTRAINT fk_mgr_absence_creator FOREIGN KEY (created_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_mgr_absence_dates   CHECK (date_to >= date_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Manager nicht zu Hause (Urlaub, Klinik …): Kalender, Aufgaben pausieren';

CREATE TABLE shopping_list (
  id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  name              VARCHAR(100)      NOT NULL,          -- „Einkauf (Assistenz)", „Besorgt die Familie", „Drogerie"
  assignee_user_id  BIGINT UNSIGNED   NULL,              -- wer sich kümmert: Person …
  assignee_role     VARCHAR(12)       NULL,              -- … oder Rolle
  is_active         TINYINT(1)        NOT NULL DEFAULT 1,
  sort              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_shopping_list_name (name),
  KEY ix_shopping_list_assignee (assignee_user_id),
  CONSTRAINT fk_shopping_list_assignee FOREIGN KEY (assignee_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_shopping_list_role     CHECK (assignee_role IS NULL OR assignee_role IN ('admins','manager','assistenz','angehoerige'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mehrere Listen (Einkauf, Besorgen durch Familie …) – Ziel u. a. bei „nicht möglich"';

CREATE TABLE task_template (
  id                   BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  title                VARCHAR(150)      NOT NULL,
  description          TEXT              NULL,
  category             VARCHAR(15)       NOT NULL,
  estimated_minutes    SMALLINT UNSIGNED NULL,
  priority             VARCHAR(10)       NOT NULL DEFAULT 'normal',
  requires_manager     TINYINT(1)        NOT NULL DEFAULT 0,
  proof_type           VARCHAR(10)       NOT NULL DEFAULT 'keiner',
  assignee_user_id     BIGINT UNSIGNED   NULL,          -- bestimmte Person (auch Manager/Angehörige) – hat Vorrang
  assignee_role        VARCHAR(12)       NULL,          -- sonst Rolle (admins/manager/assistenz/angehoerige); beides NULL = wer gerade da ist
  visibility           VARCHAR(12)       NOT NULL DEFAULT 'alle',   -- alle / zustaendig (+Admins) / admins / privat (nur Ersteller)
  guide_id             BIGINT UNSIGNED   NULL,
  asset_id             BIGINT UNSIGNED   NULL,
  poi_id               BIGINT UNSIGNED   NULL,
  recurrence_type      VARCHAR(10)       NOT NULL,
  rrule                VARCHAR(255)      NULL,          -- RFC 5545, z. B. FREQ=WEEKLY;BYDAY=MO
  interval_days        SMALLINT UNSIGNED NULL,          -- „alle n Tage nach letzter Erledigung"
  earliest_after_days  SMALLINT UNSIGNED NULL,
  window_days          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  due_time             TIME              NULL,
  starts_on            DATE              NOT NULL,
  ends_on              DATE              NULL,
  paused_from          DATE              NULL,
  paused_until         DATE              NULL,
  is_active            TINYINT(1)        NOT NULL DEFAULT 1,
  last_done_at         DATETIME          NULL,          -- „zuletzt erledigt" – bleibt, auch wenn alte Aufgaben gelöscht sind
  unable_list_id       BIGINT UNSIGNED   NULL,          -- Vorschlag bei „nicht möglich": auf diese Liste …
  unable_item_name     VARCHAR(100)      NULL,          -- … mit diesem Artikel (z. B. „Flüssigseife")
  next_due_on          DATE              NULL,          -- für die Übersicht (vom Scheduler gepflegt)
  created_by_user_id   BIGINT UNSIGNED   NULL,
  created_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_template (is_active),
  KEY ix_template_overview (next_due_on),
  KEY ix_template_unable_list (unable_list_id),
  CONSTRAINT fk_template_assignee FOREIGN KEY (assignee_user_id)   REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_template_guide    FOREIGN KEY (guide_id)                 REFERENCES guide (id)      ON DELETE SET NULL,
  CONSTRAINT fk_template_asset    FOREIGN KEY (asset_id)                 REFERENCES asset (id)      ON DELETE SET NULL,
  CONSTRAINT fk_template_poi      FOREIGN KEY (poi_id)                   REFERENCES poi (id)        ON DELETE SET NULL,
  CONSTRAINT fk_template_creator  FOREIGN KEY (created_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_template_unable   FOREIGN KEY (unable_list_id)     REFERENCES shopping_list (id) ON DELETE SET NULL,
  CONSTRAINT ck_template_category CHECK (category IN ('haushalt','waesche','kueche','hilfsmittel','einkauf','termin','gespraech','sonstiges')),
  CONSTRAINT ck_template_priority CHECK (priority IN ('normal','wichtig')),
  CONSTRAINT ck_template_proof    CHECK (proof_type IN ('keiner','foto','kommentar','messwert')),
  CONSTRAINT ck_template_visible  CHECK (visibility IN ('alle','zustaendig','admins','privat')),
  CONSTRAINT ck_template_role     CHECK (assignee_role IS NULL OR assignee_role IN ('admins','manager','assistenz','angehoerige')),
  CONSTRAINT ck_template_recur    CHECK ((recurrence_type = 'kalender'  AND rrule IS NOT NULL AND interval_days IS NULL)
                                      OR (recurrence_type = 'intervall' AND rrule IS NULL AND interval_days IS NOT NULL AND interval_days > 0)),
  CONSTRAINT ck_template_earliest CHECK (earliest_after_days IS NULL
                                      OR (recurrence_type = 'intervall' AND interval_days IS NOT NULL AND earliest_after_days <= interval_days)),
  CONSTRAINT ck_template_dates    CHECK (ends_on IS NULL OR ends_on >= starts_on),
  CONSTRAINT ck_template_pause    CHECK ((paused_from IS NULL AND paused_until IS NULL)
                                      OR (paused_from IS NOT NULL AND (paused_until IS NULL OR paused_until >= paused_from)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Wiederkehrende Aufgabe / Wartungsplan';

CREATE TABLE task_template_item (
  id                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  template_id         BIGINT UNSIGNED   NOT NULL,
  sort                SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  label               VARCHAR(150)      NOT NULL,
  target_value        DECIMAL(10,2)     NULL,
  unit                VARCHAR(12)       NULL,
  asset_component_id  BIGINT UNSIGNED   NULL,
  PRIMARY KEY (id),
  KEY ix_template_item (template_id, sort),
  CONSTRAINT fk_template_item_template  FOREIGN KEY (template_id)        REFERENCES task_template (id)   ON DELETE CASCADE,
  CONSTRAINT fk_template_item_component FOREIGN KEY (asset_component_id) REFERENCES asset_component (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Checklistenpunkte einer Vorlage (zusätzlich zu den automatisch erzeugten Sollwert-Punkten)';

CREATE TABLE task (
  id                   BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  template_id          BIGINT UNSIGNED   NULL,          -- NULL = einmalige Aufgabe
  occurrence_date      DATE              NULL,          -- ursprünglicher Termin laut Vorlage (bleibt beim Verschieben gleich)
  title                VARCHAR(150)      NOT NULL,
  description          TEXT              NULL,
  category             VARCHAR(15)       NOT NULL,
  estimated_minutes    SMALLINT UNSIGNED NULL,
  priority             VARCHAR(10)       NOT NULL DEFAULT 'normal',
  requires_manager     TINYINT(1)        NOT NULL DEFAULT 0,
  proof_type           VARCHAR(10)       NOT NULL DEFAULT 'keiner',
  assignee_user_id     BIGINT UNSIGNED   NULL,          -- Person (bei Gesprächspunkt: mit wem) – hat Vorrang
  assignee_role        VARCHAR(12)       NULL,          -- sonst Rolle (admins/manager/assistenz/angehoerige); beides NULL = wer gerade da ist
  assigned_by_user_id  BIGINT UNSIGNED   NULL,          -- wer zuletzt weitergegeben hat
  assigned_at          DATETIME          NULL,
  visibility           VARCHAR(12)       NOT NULL DEFAULT 'alle',   -- alle / zustaendig (+Admins) / admins / privat (nur Ersteller)
  guide_id             BIGINT UNSIGNED   NULL,
  asset_id             BIGINT UNSIGNED   NULL,
  poi_id               BIGINT UNSIGNED   NULL,
  due_rule             VARCHAR(16)       NOT NULL DEFAULT 'datum',   -- naechster_dienst: Fälligkeit folgt dem Dienstplan
  visible_from         DATE              NULL,
  window_start         DATE              NULL,
  due_date             DATE              NOT NULL,
  due_time             TIME              NULL,
  status               VARCHAR(15)       NOT NULL DEFAULT 'offen',
  postponed_count      TINYINT UNSIGNED  NOT NULL DEFAULT 0,
  created_via          VARCHAR(10)       NOT NULL DEFAULT 'app',
  created_by_user_id   BIGINT UNSIGNED   NULL,
  created_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_task_occurrence (template_id, occurrence_date),
  KEY ix_task_today (status, due_date),
  KEY ix_task_assignee (assignee_user_id, status),
  KEY ix_task_assigned_by (assigned_by_user_id),
  CONSTRAINT fk_task_template FOREIGN KEY (template_id)              REFERENCES task_template (id) ON DELETE SET NULL,
  CONSTRAINT fk_task_assignee FOREIGN KEY (assignee_user_id)   REFERENCES app_user (id)    ON DELETE SET NULL,
  CONSTRAINT fk_task_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES app_user (id)   ON DELETE SET NULL,
  CONSTRAINT fk_task_guide    FOREIGN KEY (guide_id)                 REFERENCES guide (id)         ON DELETE SET NULL,
  CONSTRAINT fk_task_asset    FOREIGN KEY (asset_id)                 REFERENCES asset (id)         ON DELETE SET NULL,
  CONSTRAINT fk_task_poi      FOREIGN KEY (poi_id)                   REFERENCES poi (id)           ON DELETE SET NULL,
  CONSTRAINT fk_task_creator  FOREIGN KEY (created_by_user_id) REFERENCES app_user (id)    ON DELETE SET NULL,
  CONSTRAINT ck_task_category CHECK (category IN ('haushalt','waesche','kueche','hilfsmittel','einkauf','termin','gespraech','sonstiges')),
  CONSTRAINT ck_task_priority CHECK (priority IN ('normal','wichtig')),
  CONSTRAINT ck_task_proof    CHECK (proof_type IN ('keiner','foto','kommentar','messwert')),
  CONSTRAINT ck_task_visible  CHECK (visibility IN ('alle','zustaendig','admins','privat')),
  CONSTRAINT ck_task_role     CHECK (assignee_role IS NULL OR assignee_role IN ('admins','manager','assistenz','angehoerige')),
  CONSTRAINT ck_task_duerule  CHECK (due_rule IN ('datum','naechster_dienst')),
  CONSTRAINT ck_task_status   CHECK (status IN ('offen','erledigt','uebersprungen','nicht_moeglich')),
  CONSTRAINT ck_task_via      CHECK (created_via IN ('app','ki','system')),
  CONSTRAINT ck_task_window   CHECK (window_start IS NULL OR window_start <= due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Aufgabe: einmalig oder Vorkommen einer Vorlage, auch Gesprächspunkte';

CREATE TABLE task_item (
  id                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  task_id             BIGINT UNSIGNED   NOT NULL,
  sort                SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  label               VARCHAR(150)      NOT NULL,
  target_value        DECIMAL(10,2)     NULL,
  unit                VARCHAR(12)       NULL,
  asset_component_id  BIGINT UNSIGNED   NULL,
  PRIMARY KEY (id),
  KEY ix_task_item (task_id, sort),
  CONSTRAINT fk_task_item_task      FOREIGN KEY (task_id)            REFERENCES task (id)            ON DELETE CASCADE,
  CONSTRAINT fk_task_item_component FOREIGN KEY (asset_component_id) REFERENCES asset_component (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Checkliste eines Vorkommens (inkl. Sollwerte aus Hilfsmittel-Bauteilen)';

CREATE TABLE task_completion (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  task_id        BIGINT UNSIGNED NOT NULL,
  action         VARCHAR(15)     NOT NULL,
  user_id        BIGINT UNSIGNED NULL,           -- nach retention_completion_days entfernt; NULL am Team-Tablet
  device_id      BIGINT UNSIGNED NULL,           -- erledigt am Team-Tablet
  done_at        DATETIME        NOT NULL,
  comment        VARCHAR(500)    NULL,
  anonymized_at  DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_completion_task (task_id),
  KEY ix_completion_anon (anonymized_at, done_at),
  KEY ix_completion_user (user_id),
  KEY ix_completion_device (device_id),
  CONSTRAINT fk_completion_task   FOREIGN KEY (task_id)   REFERENCES task (id)     ON DELETE CASCADE,
  CONSTRAINT fk_completion_member FOREIGN KEY (user_id)   REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_completion_device FOREIGN KEY (device_id) REFERENCES device (id)   ON DELETE SET NULL,
  CONSTRAINT ck_completion_action CHECK (action IN ('erledigt','uebersprungen','nicht_moeglich'))   -- „nicht möglich" erzeugt eine Meldung (issue_report)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Abschluss eines Vorkommens (wer/wann/Kommentar)';

CREATE TABLE task_item_result (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  completion_id   BIGINT UNSIGNED NOT NULL,
  task_item_id    BIGINT UNSIGNED NOT NULL,
  is_checked      TINYINT(1)      NOT NULL DEFAULT 0,
  measured_value  DECIMAL(10,2)   NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_item_result (completion_id, task_item_id),
  KEY ix_item_result_item (task_item_id),
  CONSTRAINT fk_item_result_completion FOREIGN KEY (completion_id) REFERENCES task_completion (id) ON DELETE CASCADE,
  CONSTRAINT fk_item_result_item       FOREIGN KEY (task_item_id)  REFERENCES task_item (id)       ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Ergebnis je Checklistenpunkt (abgehakt, Messwert)';

CREATE TABLE component_measurement (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  component_id         BIGINT UNSIGNED NOT NULL,
  measured_on          DATE            NOT NULL,
  measured_value       DECIMAL(10,2)   NOT NULL,
  target_value         DECIMAL(10,2)   NULL,
  unit                 VARCHAR(12)     NULL,
  task_item_result_id  BIGINT UNSIGNED NULL,
  PRIMARY KEY (id),
  KEY ix_measurement_component (component_id, measured_on),
  CONSTRAINT fk_measurement_component FOREIGN KEY (component_id)        REFERENCES asset_component (id)  ON DELETE CASCADE,
  CONSTRAINT fk_measurement_result    FOREIGN KEY (task_item_result_id) REFERENCES task_item_result (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Messwert-Historie je Bauteil (ohne Personenbezug)';

CREATE TABLE handover_note (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  parent_id         BIGINT UNSIGNED NULL,     -- Antwort (eine Ebene)
  author_user_id    BIGINT UNSIGNED NULL,
  device_id         BIGINT UNSIGNED NULL,     -- geschrieben am Team-Tablet
  note_type         VARCHAR(10)     NOT NULL DEFAULT 'info',
  body              VARCHAR(1000)   NOT NULL,
  is_pinned         TINYINT(1)      NOT NULL DEFAULT 0,
  pinned_until      DATE            NULL,
  resolved_at       DATETIME        NULL,
  related_task_id   BIGINT UNSIGNED NULL,
  related_asset_id  BIGINT UNSIGNED NULL,
  created_via       VARCHAR(10)     NOT NULL DEFAULT 'app',
  created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delete_after      DATE            NOT NULL,
  PRIMARY KEY (id),
  KEY ix_handover_feed (created_at),
  KEY ix_handover_cleanup (delete_after),
  KEY ix_handover_device (device_id),
  CONSTRAINT fk_handover_device FOREIGN KEY (device_id)            REFERENCES device (id)        ON DELETE SET NULL,
  CONSTRAINT fk_handover_parent FOREIGN KEY (parent_id)            REFERENCES handover_note (id) ON DELETE CASCADE,
  CONSTRAINT fk_handover_author FOREIGN KEY (author_user_id) REFERENCES app_user (id)    ON DELETE SET NULL,
  CONSTRAINT fk_handover_task   FOREIGN KEY (related_task_id)      REFERENCES task (id)          ON DELETE SET NULL,
  CONSTRAINT fk_handover_asset  FOREIGN KEY (related_asset_id)     REFERENCES asset (id)         ON DELETE SET NULL,
  CONSTRAINT ck_handover_type   CHECK (note_type IN ('info','wichtig','frage')),
  CONSTRAINT ck_handover_via    CHECK (created_via IN ('app','ki','system'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Übergabebuch';

CREATE TABLE handover_read (
  handover_note_id  BIGINT UNSIGNED NOT NULL,
  user_id           BIGINT UNSIGNED NOT NULL,
  read_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (handover_note_id, user_id),
  KEY ix_handover_read_member (user_id),
  CONSTRAINT fk_handover_read_note   FOREIGN KEY (handover_note_id) REFERENCES handover_note (id) ON DELETE CASCADE,
  CONSTRAINT fk_handover_read_member FOREIGN KEY (user_id)    REFERENCES app_user (id)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lesebestätigung (nur bei „wichtig")';

CREATE TABLE visitor (
  id          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  name        VARCHAR(100)      NOT NULL,                 -- Organisation/Funktion, keine Klarnamen: „Physiotherapie", „Caritas Haushaltshilfe"
  kind        VARCHAR(12)       NOT NULL DEFAULT 'sonstiges',
  phone       VARCHAR(30)       NULL,                     -- dienstliche Nummer (Praxis, Büro) – z. B. bei Verspätung
  team_note   VARCHAR(500)      NULL,                     -- was das Team wissen muss: „Caritas putzt Bad und Küche"
  color       CHAR(7)           NULL,                     -- Kalenderfarbe (#RRGGBB), immer zusätzlich Symbol + Text
  is_active   TINYINT(1)        NOT NULL DEFAULT 1,
  sort        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_visitor_name (name),
  CONSTRAINT ck_visitor_kind  CHECK (kind IN ('therapie','pflege','haushalt','arzt','lieferung','handwerk','sonstiges')),
  CONSTRAINT ck_visitor_color CHECK (color IS NULL OR color REGEXP '^#[0-9A-Fa-f]{6}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Externe Dienste, die ins Haus kommen (Physio, Caritas, Pflegedienst …) – reine Info für das Team';

CREATE TABLE appointment_series (
  id                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  kind                VARCHAR(10)       NOT NULL DEFAULT 'termin',   -- termin · besuch (nur Info, niemand muss etwas tun)
  title               VARCHAR(150)      NOT NULL,
  visitor_id          BIGINT UNSIGNED   NULL,
  poi_id              BIGINT UNSIGNED   NULL,
  rrule               VARCHAR(255)      NOT NULL,                   -- RFC 5545, z. B. FREQ=WEEKLY;BYDAY=TU oder FREQ=WEEKLY;INTERVAL=2;BYDAY=TH
  all_day             TINYINT(1)        NOT NULL DEFAULT 0,
  start_time          TIME              NULL,                       -- Ortszeit
  duration_minutes    SMALLINT UNSIGNED NULL,
  starts_on           DATE              NOT NULL,
  ends_on             DATE              NULL,
  paused_from         DATE              NULL,
  paused_until        DATE              NULL,
  needs_escort        TINYINT(1)        NOT NULL DEFAULT 0,
  assignee_user_id    BIGINT UNSIGNED   NULL,
  assignee_role       VARCHAR(12)       NULL,
  visibility          VARCHAR(12)       NOT NULL DEFAULT 'alle',
  note                VARCHAR(500)      NULL,
  is_active           TINYINT(1)        NOT NULL DEFAULT 1,
  generated_until     DATE              NULL,                       -- bis hierhin hat der Scheduler Vorkommen erzeugt
  created_by_user_id  BIGINT UNSIGNED   NULL,
  created_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_series_active (is_active, generated_until),
  KEY ix_series_visitor (visitor_id),
  KEY ix_series_poi (poi_id),
  KEY ix_series_assignee (assignee_user_id),
  KEY ix_series_creator (created_by_user_id),
  CONSTRAINT fk_series_visitor  FOREIGN KEY (visitor_id)         REFERENCES visitor (id)  ON DELETE SET NULL,
  CONSTRAINT fk_series_poi      FOREIGN KEY (poi_id)             REFERENCES poi (id)      ON DELETE SET NULL,
  CONSTRAINT fk_series_assignee FOREIGN KEY (assignee_user_id)   REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_series_creator  FOREIGN KEY (created_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_series_kind     CHECK (kind IN ('termin','besuch')),
  CONSTRAINT ck_series_info     CHECK (kind = 'termin' OR (needs_escort = 0 AND assignee_role IS NULL)),
  CONSTRAINT ck_series_time     CHECK ((all_day = 1 AND start_time IS NULL AND duration_minutes IS NULL)
                                    OR (all_day = 0 AND start_time IS NOT NULL AND duration_minutes IS NOT NULL AND duration_minutes > 0)),
  CONSTRAINT ck_series_dates    CHECK (ends_on IS NULL OR ends_on >= starts_on),
  CONSTRAINT ck_series_pause    CHECK ((paused_from IS NULL AND paused_until IS NULL)
                                    OR (paused_from IS NOT NULL AND (paused_until IS NULL OR paused_until >= paused_from))),
  CONSTRAINT ck_series_role     CHECK (assignee_role IS NULL OR assignee_role IN ('admins','manager','assistenz','angehoerige')),
  CONSTRAINT ck_series_visible  CHECK (visibility IN ('alle','zustaendig','admins','privat'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Wiederkehrende Termine und Besuche (z. B. Physio dienstags 10 Uhr) – erzeugt Einträge in appointment';

CREATE TABLE appointment (
  id                   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind                 VARCHAR(10)     NOT NULL DEFAULT 'termin',   -- termin · besuch (externer Dienst, nur Info)
  title                VARCHAR(150)    NOT NULL,
  visitor_id           BIGINT UNSIGNED NULL,
  series_id            BIGINT UNSIGNED NULL,
  occurrence_date      DATE            NULL,      -- Ursprungstag in der Serie (bleibt beim Verschieben)
  is_modified          TINYINT(1)      NOT NULL DEFAULT 0,   -- einzeln geändert → wird bei Serienänderung nicht neu erzeugt
  status               VARCHAR(10)     NOT NULL DEFAULT 'geplant',  -- geplant · abgesagt (durchgestrichen „fällt aus")
  cancel_note          VARCHAR(255)    NULL,
  starts_at            DATETIME        NOT NULL,
  ends_at              DATETIME        NULL,
  all_day              TINYINT(1)      NOT NULL DEFAULT 0,
  poi_id               BIGINT UNSIGNED NULL,
  needs_escort         TINYINT(1)      NOT NULL DEFAULT 0,
  assignee_user_id     BIGINT UNSIGNED NULL,      -- zuständig/begleitet: bestimmte Person …
  assignee_role        VARCHAR(12)     NULL,      -- … oder Rolle (z. B. assistenz = wer an dem Tag Dienst hat)
  assigned_by_user_id  BIGINT UNSIGNED NULL,
  assigned_at          DATETIME        NULL,
  visibility           VARCHAR(12)     NOT NULL DEFAULT 'alle',   -- alle / zustaendig (+Admins) / admins / privat (nur Ersteller)
  note                 TEXT            NULL,
  created_via          VARCHAR(10)     NOT NULL DEFAULT 'app',
  created_by_user_id   BIGINT UNSIGNED NULL,
  created_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_appointment_time (starts_at),
  UNIQUE KEY uq_appointment_occurrence (series_id, occurrence_date),
  KEY ix_appointment_visitor (visitor_id),
  KEY ix_appointment_assignee (assignee_user_id),
  KEY ix_appointment_assigner (assigned_by_user_id),
  CONSTRAINT fk_appointment_poi      FOREIGN KEY (poi_id)              REFERENCES poi (id)      ON DELETE SET NULL,
  CONSTRAINT fk_appointment_visitor  FOREIGN KEY (visitor_id)          REFERENCES visitor (id)  ON DELETE SET NULL,
  CONSTRAINT fk_appointment_series   FOREIGN KEY (series_id)           REFERENCES appointment_series (id) ON DELETE CASCADE,
  CONSTRAINT fk_appointment_creator  FOREIGN KEY (created_by_user_id)  REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_appointment_assignee FOREIGN KEY (assignee_user_id)    REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_appointment_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_appointment_times    CHECK (ends_at IS NULL OR ends_at > starts_at),
  CONSTRAINT ck_appointment_via      CHECK (created_via IN ('app','ki','system')),
  CONSTRAINT ck_appointment_role     CHECK (assignee_role IS NULL OR assignee_role IN ('admins','manager','assistenz','angehoerige')),
  CONSTRAINT ck_appointment_visible  CHECK (visibility IN ('alle','zustaendig','admins','privat')),
  CONSTRAINT ck_appointment_kind     CHECK (kind IN ('termin','besuch')),
  CONSTRAINT ck_appointment_info     CHECK (kind = 'termin' OR (needs_escort = 0 AND assignee_role IS NULL)),
  CONSTRAINT ck_appointment_status   CHECK (status IN ('geplant','abgesagt')),
  CONSTRAINT ck_appointment_cancel   CHECK (status = 'abgesagt' OR cancel_note IS NULL),
  CONSTRAINT ck_appointment_occur    CHECK ((series_id IS NULL AND occurrence_date IS NULL AND is_modified = 0)
                                         OR (series_id IS NOT NULL AND occurrence_date IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Termine (Arzt, Teambesprechung …) und Besuche externer Dienste (Physio, Caritas …), einzeln oder aus einer Serie';

CREATE TABLE appointment_participant (
  appointment_id  BIGINT UNSIGNED NOT NULL,
  user_id         BIGINT UNSIGNED NOT NULL,
  role            VARCHAR(12)     NOT NULL DEFAULT 'teilnahme',
  PRIMARY KEY (appointment_id, user_id),
  KEY ix_participant_member (user_id),
  CONSTRAINT fk_participant_appt   FOREIGN KEY (appointment_id) REFERENCES appointment (id) ON DELETE CASCADE,
  CONSTRAINT fk_participant_member FOREIGN KEY (user_id)  REFERENCES app_user (id)  ON DELETE CASCADE,
  CONSTRAINT ck_participant_role   CHECK (role IN ('begleitung','teilnahme'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Wer begleitet/nimmt teil → erscheint im persönlichen Kalender';

CREATE TABLE appointment_item (
  id              BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  appointment_id  BIGINT UNSIGNED   NOT NULL,
  sort            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  label           VARCHAR(150)      NOT NULL,
  is_checked      TINYINT(1)        NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_appointment_item (appointment_id, sort),
  CONSTRAINT fk_appt_item_appt   FOREIGN KEY (appointment_id) REFERENCES appointment (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mitbringen-Checkliste (Versichertenkarte …)';

CREATE TABLE shopping_favorite (
  id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  list_id           BIGINT UNSIGNED   NULL,
  name              VARCHAR(100)      NOT NULL,
  default_quantity  VARCHAR(30)       NULL,
  poi_id            BIGINT UNSIGNED   NULL,
  category          VARCHAR(30)       NULL,
  sort              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_favorite (sort),
  KEY ix_favorite_list (list_id),
  CONSTRAINT fk_favorite_poi    FOREIGN KEY (poi_id)    REFERENCES poi (id)    ON DELETE SET NULL,
  CONSTRAINT fk_favorite_list   FOREIGN KEY (list_id)   REFERENCES shopping_list (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Lieblingsartikel zum schnellen Hinzufügen';

CREATE TABLE shopping_item (
  id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  list_id               BIGINT UNSIGNED NOT NULL,
  name                  VARCHAR(100)    NOT NULL,
  quantity              VARCHAR(30)     NULL,
  poi_id                BIGINT UNSIGNED NULL,
  category              VARCHAR(30)     NULL,
  note                  VARCHAR(255)    NULL,
  is_done               TINYINT(1)      NOT NULL DEFAULT 0,
  done_at               DATETIME        NULL,
  source                VARCHAR(10)     NOT NULL DEFAULT 'manuell',
  source_task_id        BIGINT UNSIGNED NULL,
  created_by_user_id    BIGINT UNSIGNED NULL,
  created_by_device_id  BIGINT UNSIGNED NULL,
  created_at            DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_shopping_open (list_id, is_done, category),
  KEY ix_shopping_device (created_by_device_id),
  CONSTRAINT fk_shopping_device  FOREIGN KEY (created_by_device_id)     REFERENCES device (id)     ON DELETE SET NULL,
  CONSTRAINT fk_shopping_list    FOREIGN KEY (list_id)                  REFERENCES shopping_list (id) ON DELETE CASCADE,
  CONSTRAINT fk_shopping_poi     FOREIGN KEY (poi_id)                   REFERENCES poi (id)        ON DELETE SET NULL,
  CONSTRAINT fk_shopping_task    FOREIGN KEY (source_task_id)           REFERENCES task (id)       ON DELETE SET NULL,
  CONSTRAINT fk_shopping_creator FOREIGN KEY (created_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_shopping_source  CHECK (source IN ('manuell','aufgabe','rezept','ki','meldung')),
  CONSTRAINT ck_shopping_done    CHECK ((is_done = 0 AND done_at IS NULL) OR (is_done = 1 AND done_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Einkaufsliste (Warnungen über profile_keyword)';

-- ---------------------------------------------------------------------
--  6a2. Meldungen (nicht möglich · leer · kaputt · fehlt) – Log + Benachrichtigung
-- ---------------------------------------------------------------------

CREATE TABLE issue_report (
  id                          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  public_id                   BINARY(16)        NOT NULL,
  kind                        VARCHAR(15)       NOT NULL,                 -- nicht_moeglich · leer · kaputt · fehlt · messwert · sonstiges
  title                       VARCHAR(150)      NOT NULL,                 -- „Seife leer"
  note                        VARCHAR(1000)     NULL,
  is_urgent                   TINYINT(1)        NOT NULL DEFAULT 0,       -- dringend: sofort benachrichtigen, auch bei „täglich"
  visibility                  VARCHAR(10)       NOT NULL DEFAULT 'alle',  -- alle (Titel für alle sichtbar) · admins
  task_id                     BIGINT UNSIGNED   NULL,                     -- Aufgabe, die nicht möglich war
  asset_id                    BIGINT UNSIGNED   NULL,
  poi_id                      BIGINT UNSIGNED   NULL,
  created_by_user_id          BIGINT UNSIGNED   NULL,                     -- NULL am Team-Tablet bzw. nach Anonymisierung
  created_by_device_id        BIGINT UNSIGNED   NULL,
  created_via                 VARCHAR(10)       NOT NULL DEFAULT 'app',   -- app · ki · system (Messwert außerhalb Sollwert)
  created_at                  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  confirm_count               SMALLINT UNSIGNED NOT NULL DEFAULT 0,       -- „auch bemerkt" statt doppelter Meldung
  last_confirmed_at           DATETIME          NULL,
  status                      VARCHAR(12)       NOT NULL DEFAULT 'offen', -- offen · in_arbeit · erledigt · verworfen
  follow_up                   VARCHAR(10)       NULL,                     -- liste · aufgabe · uebergabe
  follow_up_shopping_item_id  BIGINT UNSIGNED   NULL,
  follow_up_task_id           BIGINT UNSIGNED   NULL,
  follow_up_handover_id       BIGINT UNSIGNED   NULL,
  closed_at                   DATETIME          NULL,
  closed_by_user_id           BIGINT UNSIGNED   NULL,                     -- NULL = automatisch (Listeneintrag abgehakt / Aufgabe erledigt)
  close_note                  VARCHAR(255)      NULL,
  anonymized_at               DATETIME          NULL,                     -- Person entfernt (retention_completion_days)
  delete_after                DATE              NULL,                     -- beim Abschluss gesetzt (retention_report_days)
  PRIMARY KEY (id),
  UNIQUE KEY uq_report_public (public_id),
  UNIQUE KEY uq_report_task (task_id),
  KEY ix_report_status (status, created_at),
  KEY ix_report_cleanup (delete_after),
  KEY ix_report_anon (anonymized_at, created_at),
  KEY ix_report_asset (asset_id),
  KEY ix_report_poi (poi_id),
  KEY ix_report_creator (created_by_user_id),
  KEY ix_report_device (created_by_device_id),
  KEY ix_report_item (follow_up_shopping_item_id),
  KEY ix_report_follow_task (follow_up_task_id),
  KEY ix_report_handover (follow_up_handover_id),
  KEY ix_report_closer (closed_by_user_id),
  CONSTRAINT fk_report_task         FOREIGN KEY (task_id)                    REFERENCES task (id)          ON DELETE SET NULL,
  CONSTRAINT fk_report_asset        FOREIGN KEY (asset_id)                   REFERENCES asset (id)         ON DELETE SET NULL,
  CONSTRAINT fk_report_poi          FOREIGN KEY (poi_id)                     REFERENCES poi (id)           ON DELETE SET NULL,
  CONSTRAINT fk_report_creator      FOREIGN KEY (created_by_user_id)         REFERENCES app_user (id)      ON DELETE SET NULL,
  CONSTRAINT fk_report_device       FOREIGN KEY (created_by_device_id)       REFERENCES device (id)        ON DELETE SET NULL,
  CONSTRAINT fk_report_item         FOREIGN KEY (follow_up_shopping_item_id) REFERENCES shopping_item (id) ON DELETE SET NULL,
  CONSTRAINT fk_report_follow_task  FOREIGN KEY (follow_up_task_id)          REFERENCES task (id)          ON DELETE SET NULL,
  CONSTRAINT fk_report_handover     FOREIGN KEY (follow_up_handover_id)      REFERENCES handover_note (id) ON DELETE SET NULL,
  CONSTRAINT fk_report_closer       FOREIGN KEY (closed_by_user_id)          REFERENCES app_user (id)      ON DELETE SET NULL,
  CONSTRAINT ck_report_kind         CHECK (kind IN ('nicht_moeglich','leer','kaputt','fehlt','messwert','sonstiges')),
  CONSTRAINT ck_report_visible      CHECK (visibility IN ('alle','admins')),
  CONSTRAINT ck_report_via          CHECK (created_via IN ('app','ki','system')),
  CONSTRAINT ck_report_status       CHECK (status IN ('offen','in_arbeit','erledigt','verworfen')),
  CONSTRAINT ck_report_follow       CHECK (follow_up IS NULL OR follow_up IN ('liste','aufgabe','uebergabe')),
  CONSTRAINT ck_report_closed       CHECK ((status IN ('offen','in_arbeit') AND closed_at IS NULL AND delete_after IS NULL)
                                        OR (status IN ('erledigt','verworfen') AND closed_at IS NOT NULL AND delete_after IS NOT NULL)),
  CONSTRAINT ck_report_confirm      CHECK ((confirm_count = 0 AND last_confirmed_at IS NULL)
                                        OR (confirm_count > 0 AND last_confirmed_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Meldungen – erscheinen bei den Admins (Heute, Glocke, optional E-Mail) und im Meldungs-Log';

-- ---------------------------------------------------------------------
--  6b. Nachrichten (Chat)
-- ---------------------------------------------------------------------

CREATE TABLE chat_conversation (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind                VARCHAR(10)     NOT NULL,
  title               VARCHAR(100)    NULL,          -- nur bei Gruppen
  direct_key          VARCHAR(41)     NULL,          -- Direktchat: „kleinere-ID:größere-ID" → je Paar nur einmal
  created_by_user_id  BIGINT UNSIGNED NULL,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_message_at     DATETIME        NULL,
  -- Genau ein Team-Chat:
  team_marker         TINYINT AS (IF(kind = 'team', 1, NULL)) STORED,
  PRIMARY KEY (id),
  KEY ix_chat_conv (last_message_at),      -- vor den eindeutigen Indizes (FK-Index ohne generierte Spalte)
  UNIQUE KEY uq_chat_direct (direct_key),
  UNIQUE KEY uq_chat_team (team_marker),
  CONSTRAINT fk_chat_conv_creator FOREIGN KEY (created_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_chat_conv_kind    CHECK (kind IN ('direkt','gruppe','team')),
  CONSTRAINT ck_chat_conv_direct  CHECK ((kind = 'direkt' AND direct_key IS NOT NULL)
                                      OR (kind <> 'direkt' AND direct_key IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Unterhaltung: Direkt (2 Personen), Gruppe, Team (alle aktiven Mitglieder)';

CREATE TABLE chat_message (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  author_user_id   BIGINT UNSIGNED NULL,          -- NULL bei Systemnachricht oder nach Anonymisierung
  kind             VARCHAR(10)     NOT NULL DEFAULT 'text',
  body             TEXT            NULL,          -- leer erlaubt, wenn nur Foto/Sprachnachricht
  reply_to_id      BIGINT UNSIGNED NULL,
  is_urgent        TINYINT(1)      NOT NULL DEFAULT 0,
  created_via      VARCHAR(10)     NOT NULL DEFAULT 'app',
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  edited_at        DATETIME        NULL,
  deleted_at       DATETIME        NULL,          -- „Nachricht gelöscht" (Inhalt wird geleert)
  delete_after     DATE            NOT NULL,
  PRIMARY KEY (id),
  KEY ix_chat_msg_conv (conversation_id, id),
  KEY ix_chat_msg_cleanup (delete_after),
  KEY ix_chat_msg_author (author_user_id),
  KEY ix_chat_msg_reply (reply_to_id),
  CONSTRAINT fk_chat_msg_conv   FOREIGN KEY (conversation_id)      REFERENCES chat_conversation (id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_msg_author FOREIGN KEY (author_user_id) REFERENCES app_user (id)        ON DELETE SET NULL,
  CONSTRAINT fk_chat_msg_reply  FOREIGN KEY (reply_to_id)          REFERENCES chat_message (id)      ON DELETE SET NULL,
  CONSTRAINT ck_chat_msg_kind   CHECK (kind IN ('text','system')),
  CONSTRAINT ck_chat_msg_via    CHECK (created_via IN ('app','ki','system'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Chat-Nachricht (Text, optional mit Foto/Sprachnachricht über media)';

CREATE TABLE chat_participant (
  conversation_id       BIGINT UNSIGNED NOT NULL,
  user_id               BIGINT UNSIGNED NOT NULL,
  joined_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  left_at               DATETIME        NULL,          -- z. B. Austritt: Verlauf schreibgeschützt
  last_read_message_id  BIGINT UNSIGNED NULL,          -- „gelesen bis"
  muted                 TINYINT(1)      NOT NULL DEFAULT 0,
  PRIMARY KEY (conversation_id, user_id),
  KEY ix_chat_part_member (user_id),
  KEY ix_chat_part_read (last_read_message_id),
  CONSTRAINT fk_chat_part_conv   FOREIGN KEY (conversation_id)      REFERENCES chat_conversation (id) ON DELETE CASCADE,
  CONSTRAINT fk_chat_part_member FOREIGN KEY (user_id)        REFERENCES app_user (id)        ON DELETE CASCADE,
  CONSTRAINT fk_chat_part_read   FOREIGN KEY (last_read_message_id) REFERENCES chat_message (id)      ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Mitglieder einer Unterhaltung und Lesestand';

-- ---------------------------------------------------------------------
--  7. Medien (Fotos, Videos, Dokumente)
-- ---------------------------------------------------------------------

CREATE TABLE media (
  id                     BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  public_id              BINARY(16)        NOT NULL,
  kind                   VARCHAR(10)       NOT NULL,
  status                 VARCHAR(12)       NOT NULL DEFAULT 'hochgeladen',
  storage_path           VARCHAR(255)      NULL,      -- relativ, außerhalb des Web-Verzeichnisses
  poster_path            VARCHAR(255)      NULL,      -- Vorschaubild bei Video/PDF
  mime_type              VARCHAR(80)       NULL,
  size_bytes             BIGINT UNSIGNED   NULL,
  width                  SMALLINT UNSIGNED NULL,
  height                 SMALLINT UNSIGNED NULL,
  duration_seconds       SMALLINT UNSIGNED NULL,
  has_audio              TINYINT(1)        NULL,
  caption                VARCHAR(255)      NULL,
  sort                   SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  shows_person           TINYINT(1)        NOT NULL DEFAULT 0,
  person_consent_at      DATETIME          NULL,
  -- genau EIN Besitzer:
  guide_id               BIGINT UNSIGNED   NULL,      -- Anleitung (Platzierung je Schritt über guide_step_media)
  task_template_id       BIGINT UNSIGNED   NULL,      -- Wartungsplan: „So soll es aussehen" / Video
  task_id                BIGINT UNSIGNED   NULL,      -- einzelne Aufgabe
  task_completion_id     BIGINT UNSIGNED   NULL,      -- Nachweisfoto
  handover_note_id       BIGINT UNSIGNED   NULL,
  asset_id               BIGINT UNSIGNED   NULL,      -- Hilfsmittel, inkl. Bedienungsanleitung (PDF)
  asset_component_id     BIGINT UNSIGNED   NULL,      -- z. B. Foto vom Ventil
  poi_id                 BIGINT UNSIGNED   NULL,      -- z. B. Foto vom Eingang
  chat_message_id        BIGINT UNSIGNED   NULL,      -- Foto oder Sprachnachricht im Chat
  issue_report_id        BIGINT UNSIGNED   NULL,      -- Foto zur Meldung („so sieht es aus")
  uploaded_by_user_id    BIGINT UNSIGNED   NULL,
  uploaded_by_device_id  BIGINT UNSIGNED   NULL,
  created_at             DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_media_public (public_id),
  KEY ix_media (status),
  KEY ix_media_guide (guide_id),
  KEY ix_media_template (task_template_id),
  KEY ix_media_task (task_id),
  KEY ix_media_completion (task_completion_id),
  KEY ix_media_handover (handover_note_id),
  KEY ix_media_asset (asset_id),
  KEY ix_media_component (asset_component_id),
  KEY ix_media_poi (poi_id),
  KEY ix_media_chat (chat_message_id),
  KEY ix_media_report (issue_report_id),
  KEY ix_media_device (uploaded_by_device_id),
  CONSTRAINT fk_media_guide      FOREIGN KEY (guide_id)                  REFERENCES guide (id)           ON DELETE CASCADE,
  CONSTRAINT fk_media_template   FOREIGN KEY (task_template_id)          REFERENCES task_template (id)   ON DELETE CASCADE,
  CONSTRAINT fk_media_task       FOREIGN KEY (task_id)                   REFERENCES task (id)            ON DELETE CASCADE,
  CONSTRAINT fk_media_completion FOREIGN KEY (task_completion_id)        REFERENCES task_completion (id) ON DELETE CASCADE,
  CONSTRAINT fk_media_handover   FOREIGN KEY (handover_note_id)          REFERENCES handover_note (id)   ON DELETE CASCADE,
  CONSTRAINT fk_media_asset      FOREIGN KEY (asset_id)                  REFERENCES asset (id)           ON DELETE CASCADE,
  CONSTRAINT fk_media_component  FOREIGN KEY (asset_component_id)        REFERENCES asset_component (id) ON DELETE CASCADE,
  CONSTRAINT fk_media_poi        FOREIGN KEY (poi_id)                    REFERENCES poi (id)             ON DELETE CASCADE,
  CONSTRAINT fk_media_chat       FOREIGN KEY (chat_message_id)           REFERENCES chat_message (id)    ON DELETE CASCADE,
  CONSTRAINT fk_media_report     FOREIGN KEY (issue_report_id)           REFERENCES issue_report (id)    ON DELETE CASCADE,
  CONSTRAINT fk_media_uploader   FOREIGN KEY (uploaded_by_user_id) REFERENCES app_user (id)      ON DELETE SET NULL,
  CONSTRAINT fk_media_device     FOREIGN KEY (uploaded_by_device_id) REFERENCES device (id)      ON DELETE SET NULL,
  CONSTRAINT ck_media_kind       CHECK (kind IN ('foto','video','audio','dokument')),
  CONSTRAINT ck_media_status     CHECK (status IN ('hochgeladen','verarbeitung','bereit','fehler')),
  CONSTRAINT ck_media_owner      CHECK ((guide_id IS NOT NULL) + (task_template_id IS NOT NULL) + (task_id IS NOT NULL)
                                      + (task_completion_id IS NOT NULL) + (handover_note_id IS NOT NULL)
                                      + (asset_id IS NOT NULL) + (asset_component_id IS NOT NULL) + (poi_id IS NOT NULL)
                                      + (chat_message_id IS NOT NULL) + (issue_report_id IS NOT NULL) = 1),
  CONSTRAINT ck_media_consent    CHECK (shows_person = 0 OR person_consent_at IS NOT NULL),
  CONSTRAINT ck_media_duration   CHECK (kind IN ('video','audio') OR duration_seconds IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fotos/Videos/Dokumente – Datei auf der Platte, Metadaten hier';

CREATE TABLE guide_step_media (
  step_id   BIGINT UNSIGNED   NOT NULL,
  media_id  BIGINT UNSIGNED   NOT NULL,
  sort      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (step_id, media_id),
  KEY ix_step_media_media (media_id),
  CONSTRAINT fk_step_media_step  FOREIGN KEY (step_id)  REFERENCES guide_step (id) ON DELETE CASCADE,
  CONSTRAINT fk_step_media_media FOREIGN KEY (media_id) REFERENCES media (id)      ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Platzierung von Medien in Schritten (Medien bleiben über Versionen erhalten)';

CREATE TABLE upload_session (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  media_id            BIGINT UNSIGNED NOT NULL,
  token_hash          CHAR(64)        NOT NULL,
  total_bytes         BIGINT UNSIGNED NOT NULL,
  received_bytes      BIGINT UNSIGNED NOT NULL DEFAULT 0,
  expires_at          DATETIME        NOT NULL,
  created_by_user_id  BIGINT UNSIGNED NULL,
  created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_upload_token (token_hash),
  KEY ix_upload_media (media_id),
  KEY ix_upload_expiry (expires_at),
  CONSTRAINT fk_upload_media   FOREIGN KEY (media_id)                 REFERENCES media (id)      ON DELETE CASCADE,
  CONSTRAINT fk_upload_creator FOREIGN KEY (created_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT ck_upload_bytes   CHECK (received_bytes <= total_bytes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Upload in Teilstücken (wegen 100-MB-Grenze im Tunnel)';

-- ---------------------------------------------------------------------
--  8. Erinnerungen, KI-Assistent, Protokoll
-- ---------------------------------------------------------------------

CREATE TABLE reminder (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  kind          VARCHAR(20)     NOT NULL,
  due_on        DATE            NOT NULL,
  title         VARCHAR(150)    NOT NULL,
  user_id       BIGINT UNSIGNED NULL,
  absence_id    BIGINT UNSIGNED NULL,
  done_at       DATETIME        NULL,
  dismissed_at  DATETIME        NULL,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_reminder_due (due_on),
  CONSTRAINT fk_reminder_member  FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL,
  CONSTRAINT fk_reminder_absence FOREIGN KEY (absence_id)    REFERENCES absence (id)    ON DELETE SET NULL,
  CONSTRAINT ck_reminder_kind    CHECK (kind IN ('monatsabschluss','stunden_bestaetigen','halbjahresscheck','abgabeneinzug',
                                                 'anmeldung','abmeldung','u1_erstattung','jahreswechsel','bewilligung','pruefung_anleitung',
                                                 'checkliste'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Fristen-Erinnerungen';

CREATE TABLE ai_conversation (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id           BIGINT UNSIGNED NOT NULL,
  started_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_activity_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  delete_after      DATE            NOT NULL,
  PRIMARY KEY (id),
  KEY ix_ai_conv_member (user_id, last_activity_at),
  KEY ix_ai_conv_cleanup (delete_after),
  CONSTRAINT fk_ai_conv_member FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='KI-Gespräch (kurzlebig, für Rückfragen)';

CREATE TABLE ai_message (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id  BIGINT UNSIGNED NOT NULL,
  role             VARCHAR(12)     NOT NULL,
  content          MEDIUMTEXT      NOT NULL,
  input_mode       VARCHAR(10)     NULL,
  tokens_in        INT UNSIGNED    NULL,
  tokens_out       INT UNSIGNED    NULL,
  created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_ai_msg_conv (conversation_id, created_at),
  CONSTRAINT fk_ai_msg_conv   FOREIGN KEY (conversation_id) REFERENCES ai_conversation (id) ON DELETE CASCADE,
  CONSTRAINT ck_ai_msg_role   CHECK (role IN ('nutzer','assistent','werkzeug')),
  CONSTRAINT ck_ai_msg_mode   CHECK (input_mode IS NULL OR input_mode IN ('text','sprache'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Nachrichten im KI-Gespräch (kein Audio gespeichert)';

CREATE TABLE ai_action (
  id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  conversation_id     BIGINT UNSIGNED NULL,
  user_id             BIGINT UNSIGNED NULL,
  tool_name           VARCHAR(50)     NOT NULL,
  arguments           JSON            NOT NULL,
  status              VARCHAR(15)     NOT NULL DEFAULT 'vorgeschlagen',
  result_entity_type  VARCHAR(50)     NULL,
  result_entity_id    BIGINT UNSIGNED NULL,
  error_text          VARCHAR(255)    NULL,
  proposed_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  decided_at          DATETIME        NULL,
  executed_at         DATETIME        NULL,
  PRIMARY KEY (id),
  KEY ix_ai_action (proposed_at),
  KEY ix_ai_action_conv (conversation_id),
  CONSTRAINT fk_ai_action_conv   FOREIGN KEY (conversation_id) REFERENCES ai_conversation (id) ON DELETE SET NULL,
  CONSTRAINT fk_ai_action_member FOREIGN KEY (user_id)   REFERENCES app_user (id)      ON DELETE SET NULL,
  CONSTRAINT ck_ai_action_status CHECK (status IN ('vorgeschlagen','bestaetigt','abgelehnt','ausgefuehrt','fehler'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vom KI-Assistenten vorgeschlagene Aktionen (Schreiben nur nach Bestätigung)';

CREATE TABLE ai_usage_month (
  id             BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
  year           SMALLINT UNSIGNED NOT NULL,
  month          TINYINT UNSIGNED  NOT NULL,
  requests       INT UNSIGNED      NOT NULL DEFAULT 0,
  tokens_in      BIGINT UNSIGNED   NOT NULL DEFAULT 0,
  tokens_out     BIGINT UNSIGNED   NOT NULL DEFAULT 0,
  audio_seconds  INT UNSIGNED      NOT NULL DEFAULT 0,
  cost_eur       DECIMAL(8,2)      NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ai_usage (year, month),
  CONSTRAINT ck_ai_usage_month  CHECK (month BETWEEN 1 AND 12)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='KI-Verbrauch je Monat (Kostendeckel)';

CREATE TABLE notification (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,          -- Empfänger
  kind          VARCHAR(12)     NOT NULL,
  title         VARCHAR(150)    NOT NULL,
  body          VARCHAR(500)    NULL,
  link          VARCHAR(255)    NULL,              -- App-interner Pfad
  is_urgent     TINYINT(1)      NOT NULL DEFAULT 0,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  read_at       DATETIME        NULL,
  pushed_at     DATETIME        NULL,              -- nur wenn Push für die Person eingeschaltet ist
  mailed_at     DATETIME        NULL,              -- nur wenn E-Mail-Benachrichtigung eingeschaltet ist
  delete_after  DATE            NOT NULL,
  PRIMARY KEY (id),
  KEY ix_notification_inbox (user_id, read_at, created_at),
  KEY ix_notification_cleanup (delete_after),
  CONSTRAINT fk_notification_member FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE,
  CONSTRAINT ck_notification_kind   CHECK (kind IN ('nachricht','meldung','dienst','urlaub','aufgabe','termin','uebergabe','erinnerung','checkliste','system'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Benachrichtigungen in der App (Glocke, „seit deinem letzten Besuch") – Push nur optional';

CREATE TABLE notification_preference (
  user_id     BIGINT UNSIGNED NOT NULL,
  event_type  VARCHAR(20)     NOT NULL,
  via_app     TINYINT(1)      NOT NULL DEFAULT 1,
  via_email   TINYINT(1)      NOT NULL DEFAULT 0,     -- wirkt nur, wenn app_user.notify_email nicht 'aus' ist
  via_push    TINYINT(1)      NOT NULL DEFAULT 0,     -- wirkt nur mit app_user.notify_push und app_setting.push_allowed
  updated_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, event_type),
  CONSTRAINT fk_notify_pref_user  FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE,
  CONSTRAINT ck_notify_pref_event CHECK (event_type IN ('meldung','meldung_erledigt','frage','uebergabe_wichtig','nachricht',
                                         'dienst','urlaub','aufgabe','gespraech','termin','frist','checkliste','anleitung','wartung'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Wer bekommt welche Benachrichtigung auf welchem Weg – gespeichert werden nur Abweichungen von den Standards je Rolle';

CREATE TABLE push_subscription (
  id               BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id          BIGINT UNSIGNED  NOT NULL,
  endpoint_hash    CHAR(64)         NOT NULL,
  endpoint         TEXT             NOT NULL,
  key_p256dh       VARCHAR(255)     NOT NULL,
  key_auth         VARCHAR(64)      NOT NULL,
  device_label     VARCHAR(100)     NULL,
  created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_success_at  DATETIME         NULL,
  failed_count     TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_push_endpoint (endpoint_hash),
  KEY ix_push_user (user_id),
  CONSTRAINT fk_push_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Web-Push-Abo eines Geräts (nur wenn die Person Push selbst einschaltet, Push-Inhalt ohne Details)';

CREATE TABLE calendar_feed (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id       BIGINT UNSIGNED NOT NULL,
  token_hash    CHAR(64)        NOT NULL,
  detail_level  VARCHAR(10)     NOT NULL DEFAULT 'neutral',   -- neutral: „Dienst", „Termin" ohne Details
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_used_at  DATETIME        NULL,
  revoked_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_feed_token (token_hash),
  KEY ix_feed_member (user_id),
  CONSTRAINT fk_feed_member FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE,
  CONSTRAINT ck_feed_detail CHECK (detail_level IN ('neutral','details'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Persönliches Kalender-Abo (ICS, nur lesen, widerrufbar) – Stufe 4';

CREATE TABLE audit_log (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  actor_user_id  BIGINT UNSIGNED NULL,
  device_id      BIGINT UNSIGNED NULL,
  via            VARCHAR(10)     NOT NULL DEFAULT 'app',
  action         VARCHAR(50)     NOT NULL,      -- z. B. personal.ansehen, zeit.korrigieren, export.monat
  entity_type    VARCHAR(50)     NULL,
  entity_id      BIGINT UNSIGNED NULL,
  details        JSON            NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_time (created_at),
  KEY ix_audit_entity (entity_type, entity_id),
  KEY ix_audit_device (device_id),
  CONSTRAINT fk_audit_member FOREIGN KEY (actor_user_id) REFERENCES app_user (id)   ON DELETE SET NULL,
  CONSTRAINT fk_audit_device FOREIGN KEY (device_id)     REFERENCES device (id)     ON DELETE SET NULL,
  CONSTRAINT ck_audit_via    CHECK (via IN ('app','tablet','ki','system'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Protokoll sensibler Vorgänge (keine IP-Adressen)';

-- ---------------------------------------------------------------------
--  9. Startwerte (Stand 01.11.2026 – vor jedem Jahreswechsel prüfen!)
-- ---------------------------------------------------------------------

INSERT INTO legal_limit (code, valid_from, amount, source_note) VALUES
  ('minijob_grenze',      '2026-01-01', 603.00, 'Minijob-Zentrale/DRV'),
  ('minijob_grenze',      '2027-01-01', 633.00, 'Minijob-Zentrale/DRV'),
  ('mindestlohn',         '2026-01-01',  13.90, 'Mindestlohnanpassung'),
  ('mindestlohn',         '2027-01-01',  14.60, 'Mindestlohnanpassung'),
  ('rv_mindestbemessung', '2013-01-01', 175.00, 'Mindestbemessungsgrundlage RV bei Minijobs – prüfen');

INSERT INTO app_setting (id) VALUES (1);

INSERT INTO contribution_rate (code, valid_from, rate_pct, source_note) VALUES
  ('kv_pauschal',    '2026-01-01',  5.000, 'nur bei gesetzlicher KV der Assistenz'),
  ('rv_arbeitgeber', '2026-01-01',  5.000, 'Privathaushalt'),
  ('rv_gesamt',      '2026-01-01', 18.600, 'Eigenanteil = rv_gesamt − rv_arbeitgeber'),
  ('pauschsteuer',   '2026-01-01',  2.000, NULL),
  ('u1',             '2026-01-01',  0.800, 'Umlage Krankheit'),
  ('u2',             '2026-01-01',  0.220, 'Umlage Mutterschaft'),
  ('unfall',         '2026-01-01',  1.600, 'Unfallversicherung Privathaushalt'),
  ('u1_erstattung',  '2026-01-01', 80.000, 'Erstattung Lohnfortzahlung');
