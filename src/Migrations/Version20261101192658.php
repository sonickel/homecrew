<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M1: settings (exactly one row) and persons.
 *
 * The CREATE TABLE statements are copied from docs/datenbank/schema.sql (without SQL comments),
 * because Doctrine knows neither CHECK constraints, generated columns nor ON UPDATE, and writes
 * booleans as TINYINT instead of TINYINT(1). `composer schema:check` proves the result is identical.
 */
final class Version20261101192658 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Einstellungen (app_setting mit Startzeile) und Personen (app_user)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE app_setting (
              id                           TINYINT UNSIGNED  NOT NULL DEFAULT 1,
              household_name               VARCHAR(100)      NOT NULL DEFAULT 'Mein Haushalt',
              timezone                     VARCHAR(40)       NOT NULL DEFAULT 'Europe/Berlin',
              holiday_region               VARCHAR(20)       NOT NULL DEFAULT 'BY',
              minijob_procedure            VARCHAR(20)       NOT NULL DEFAULT 'haushaltsscheck',
              guide_publish_mode           VARCHAR(20)       NOT NULL DEFAULT 'freigabe',
              tablet_lock_seconds          SMALLINT UNSIGNED NOT NULL DEFAULT 180,
              pin_lan_only                 TINYINT(1)        NOT NULL DEFAULT 1,
              max_shift_minutes            SMALLINT UNSIGNED NOT NULL DEFAULT 600,
              time_view_months             TINYINT UNSIGNED  NOT NULL DEFAULT 3,
              video_max_seconds            SMALLINT UNSIGNED NOT NULL DEFAULT 90,
              voice_messages_enabled       TINYINT(1)        NOT NULL DEFAULT 1,
              assistant_direct_chat        TINYINT(1)        NOT NULL DEFAULT 0,
              push_allowed                 TINYINT(1)        NOT NULL DEFAULT 0,
              daily_digest_time            TIME              NOT NULL DEFAULT '18:00:00',
              ai_enabled                   TINYINT(1)        NOT NULL DEFAULT 0,
              ai_provider                  VARCHAR(20)       NOT NULL DEFAULT 'anthropic',
              ai_model                     VARCHAR(60)       NOT NULL DEFAULT 'claude-haiku-5-5',
              ai_api_key_enc               VARBINARY(512)    NULL,
              ai_api_key_hint              CHAR(4)           NULL,
              ai_key_status                VARCHAR(12)       NOT NULL DEFAULT 'fehlt',
              ai_key_checked_at            DATETIME          NULL,
              ai_key_error                 VARCHAR(255)      NULL,
              ai_feature_assistant         TINYINT(1)        NOT NULL DEFAULT 1,
              ai_feature_writing           TINYINT(1)        NOT NULL DEFAULT 1,
              ai_feature_translation       TINYINT(1)        NOT NULL DEFAULT 0,
              ai_team_device               TINYINT(1)        NOT NULL DEFAULT 1,
              ai_monthly_budget_eur        DECIMAL(8,2)      NULL,
              speech_recognition           VARCHAR(10)       NOT NULL DEFAULT 'lokal',
              mail_enabled                 TINYINT(1)        NOT NULL DEFAULT 0,
              smtp_host                    VARCHAR(190)      NULL,
              smtp_port                    SMALLINT UNSIGNED NULL,
              smtp_encryption              VARCHAR(10)       NOT NULL DEFAULT 'starttls',
              smtp_username                VARCHAR(190)      NULL,
              smtp_password_enc            VARBINARY(512)    NULL,
              mail_from_address            VARCHAR(190)      NULL,
              mail_from_name               VARCHAR(100)      NULL,
              mail_status                  VARCHAR(12)       NOT NULL DEFAULT 'fehlt',
              mail_checked_at              DATETIME          NULL,
              retention_handover_days      SMALLINT UNSIGNED NOT NULL DEFAULT 30,
              retention_completion_days    SMALLINT UNSIGNED NOT NULL DEFAULT 90,
              completion_keep_last         TINYINT UNSIGNED  NOT NULL DEFAULT 5,
              retention_done_task_days     SMALLINT UNSIGNED NOT NULL DEFAULT 365,
              retention_chat_days          SMALLINT UNSIGNED NOT NULL DEFAULT 365,
              retention_notification_days  SMALLINT UNSIGNED NOT NULL DEFAULT 30,
              retention_report_days        SMALLINT UNSIGNED NOT NULL DEFAULT 365,
              retention_audit_days         SMALLINT UNSIGNED NOT NULL DEFAULT 365,
              retention_ai_days            SMALLINT UNSIGNED NOT NULL DEFAULT 7,
              retention_hr_years           TINYINT UNSIGNED  NULL,
              updated_at                   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              CONSTRAINT ck_setting_single   CHECK (id = 1),
              CONSTRAINT ck_setting_minijob  CHECK (minijob_procedure IN ('haushaltsscheck','gewerblich')),
              CONSTRAINT ck_setting_publish  CHECK (guide_publish_mode IN ('freigabe','direkt')),
              CONSTRAINT ck_setting_speech   CHECK (speech_recognition IN ('lokal','aus')),
              CONSTRAINT ck_setting_months   CHECK (time_view_months BETWEEN 1 AND 24),
              CONSTRAINT ck_setting_budget   CHECK (ai_monthly_budget_eur IS NULL OR ai_monthly_budget_eur >= 0),
              CONSTRAINT ck_setting_ai_prov  CHECK (ai_provider IN ('anthropic')),
              CONSTRAINT ck_setting_ai_stat  CHECK (ai_key_status IN ('fehlt','ungeprueft','ok','fehler')),
              CONSTRAINT ck_setting_ai_key   CHECK ((ai_api_key_enc IS NULL AND ai_api_key_hint IS NULL AND ai_key_status = 'fehlt')
                                                 OR (ai_api_key_enc IS NOT NULL AND ai_key_status <> 'fehlt')),
              CONSTRAINT ck_setting_ai_on    CHECK (ai_enabled = 0 OR ai_api_key_enc IS NOT NULL),
              CONSTRAINT ck_setting_mail_enc CHECK (smtp_encryption IN ('starttls','smtps')),
              CONSTRAINT ck_setting_mail_st  CHECK (mail_status IN ('fehlt','ungeprueft','ok','fehler')),
              CONSTRAINT ck_setting_mail_on  CHECK (mail_enabled = 0 OR (smtp_host IS NOT NULL AND smtp_port IS NOT NULL AND smtp_port > 0
                                                                        AND mail_from_address IS NOT NULL))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Globale Einstellungen des Haushalts (genau eine Zeile, Admin-Menü)'
            SQL);
        $this->addSql('INSERT INTO app_setting (id) VALUES (1)');

        $this->addSql(<<<'SQL'
            CREATE TABLE app_user (
              id                            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
              public_id                     BINARY(16)       NOT NULL,
              role                          VARCHAR(12)      NOT NULL,
              is_admin                      TINYINT(1)       NOT NULL DEFAULT 0,
              username                      VARCHAR(50)      NOT NULL,
              display_name                  VARCHAR(50)      NOT NULL,
              color                         CHAR(7)          NULL,
              active_from                   DATE             NOT NULL,
              active_until                  DATE             NULL,
              disabled_at                   DATETIME         NULL,
              anonymized_at                 DATETIME         NULL,
              email                         VARCHAR(190)     NULL,
              email_verified_at             DATETIME         NULL,
              password_hash                 VARCHAR(255)     NULL,
              password_must_change          TINYINT(1)       NOT NULL DEFAULT 0,
              twofa_method                  VARCHAR(10)      NOT NULL DEFAULT 'keine',
              totp_secret_enc               VARBINARY(255)   NULL,
              email_code_hash               CHAR(64)         NULL,
              email_code_expires_at         DATETIME         NULL,
              email_code_attempts           TINYINT UNSIGNED NOT NULL DEFAULT 0,
              twofa_backup_codes            JSON             NULL,
              last_login_at                 DATETIME         NULL,
              pin_hash                      VARCHAR(255)     NULL,
              pin_login_enabled             TINYINT(1)       NOT NULL DEFAULT 0,
              pin_must_change               TINYINT(1)       NOT NULL DEFAULT 1,
              pin_failed_count              TINYINT UNSIGNED NOT NULL DEFAULT 0,
              pin_locked_until              DATETIME         NULL,
              can_publish_guides            TINYINT(1)       NOT NULL DEFAULT 0,
              can_manage_recurring_tasks    TINYINT(1)       NOT NULL DEFAULT 0,
              can_create_team_appointments  TINYINT(1)      NOT NULL DEFAULT 0,
              can_use_ai                    TINYINT(1)       NOT NULL DEFAULT 1,
              ai_voice_confirm              TINYINT(1)       NOT NULL DEFAULT 0,
              font_size                     VARCHAR(12)      NOT NULL DEFAULT 'normal',
              high_contrast                 TINYINT(1)       NOT NULL DEFAULT 0,
              speech_output                 TINYINT(1)       NOT NULL DEFAULT 0,
              calendar_view                 VARCHAR(10)      NOT NULL DEFAULT 'agenda',
              preferred_language            VARCHAR(10)      NOT NULL DEFAULT 'de',
              notify_email                  VARCHAR(10)      NOT NULL DEFAULT 'aus',
              notify_push                   TINYINT(1)       NOT NULL DEFAULT 0,
              notify_mail_details           TINYINT(1)       NOT NULL DEFAULT 0,
              quiet_from                    TIME             NULL,
              quiet_until                   TIME             NULL,
              show_read_receipts            TINYINT(1)       NOT NULL DEFAULT 1,
              privacy_notice_version        VARCHAR(20)      NULL,
              privacy_notice_ack_at         DATETIME         NULL,
              created_at                    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at                    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              manager_marker                TINYINT AS (IF(role = 'manager', 1, NULL)) STORED,
              PRIMARY KEY (id),
              UNIQUE KEY uq_user_public (public_id),
              UNIQUE KEY uq_user_username (username),
              UNIQUE KEY uq_user_display (display_name),
              UNIQUE KEY uq_user_email (email),
              UNIQUE KEY uq_user_manager (manager_marker),
              CONSTRAINT ck_user_role         CHECK (role IN ('manager','assistenz','angehoerige')),
              CONSTRAINT ck_user_manager      CHECK (role <> 'manager' OR is_admin = 1),
              CONSTRAINT ck_user_assist_admin CHECK (role <> 'assistenz' OR is_admin = 0),
              CONSTRAINT ck_user_dates        CHECK (active_until IS NULL OR active_until >= active_from),
              CONSTRAINT ck_user_pin          CHECK (pin_login_enabled = 0 OR pin_hash IS NOT NULL),
              CONSTRAINT ck_user_2fa          CHECK (twofa_method IN ('keine','passkey','totp','email')),
              CONSTRAINT ck_user_2fa_mail     CHECK (twofa_method <> 'email' OR email IS NOT NULL),
              CONSTRAINT ck_user_2fa_totp     CHECK (twofa_method <> 'totp' OR totp_secret_enc IS NOT NULL),
              CONSTRAINT ck_user_font         CHECK (font_size IN ('normal','gross','sehr_gross')),
              CONSTRAINT ck_user_calview      CHECK (calendar_view IN ('agenda','woche','monat')),
              CONSTRAINT ck_user_mail         CHECK (notify_email IN ('aus','sofort','taeglich'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Person im Haushalt: Rolle, Admin-Recht, Anmeldung (Passwort/Passkey/2FA und/oder Tablet-PIN), Einstellungen'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE app_setting');
    }

    /**
     * MariaDB commits DDL implicitly, a surrounding transaction would only pretend safety.
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
