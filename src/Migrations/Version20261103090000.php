<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M5: tasks and their completions – and, because tasks refer to them, plans (M6), guides (M12), aids (stage 2)
 * and lists (M7), each without pages yet.
 *
 * The CREATE TABLE statements are copied from docs/datenbank/schema.sql (without SQL comments),
 * see konzept-6 §6. `composer schema:check` proves the result is identical.
 */
final class Version20261103090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aufgaben (task, task_completion) mit Wartungsplänen, Anleitungen, Hilfsmitteln und Listen';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE guide (
              id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              public_id           BINARY(16)      NOT NULL,
              guide_type          VARCHAR(15)     NOT NULL,
              category            VARCHAR(50)     NULL,
              is_archived         TINYINT(1)      NOT NULL DEFAULT 0,
              translatable        TINYINT(1)      NOT NULL DEFAULT 1,
              locked_by_user_id   BIGINT UNSIGNED NULL,
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
              COMMENT='Wissensbasis: stabile Identität (für QR/Links), Inhalt in guide_version'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE asset (
              id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              public_id        BINARY(16)      NOT NULL,
              name             VARCHAR(100)    NOT NULL,
              asset_type       VARCHAR(50)     NULL,
              manufacturer     VARCHAR(100)    NULL,
              model            VARCHAR(100)    NULL,
              serial_number    VARCHAR(100)    NULL,
              location_text    VARCHAR(200)    NULL,
              supplier_poi_id  BIGINT UNSIGNED NULL,
              status           VARCHAR(15)     NOT NULL DEFAULT 'in_betrieb',
              note             TEXT            NULL,
              created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_asset_public (public_id),
              CONSTRAINT fk_asset_supplier FOREIGN KEY (supplier_poi_id) REFERENCES poi (id)    ON DELETE SET NULL,
              CONSTRAINT ck_asset_status   CHECK (status IN ('in_betrieb','defekt','in_reparatur','ausgemustert'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Hilfsmittel'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE shopping_list (
              id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
              name              VARCHAR(100)      NOT NULL,
              assignee_user_id  BIGINT UNSIGNED   NULL,
              assignee_role     VARCHAR(12)       NULL,
              is_active         TINYINT(1)        NOT NULL DEFAULT 1,
              sort              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              created_at        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_shopping_list_name (name),
              KEY ix_shopping_list_assignee (assignee_user_id),
              CONSTRAINT fk_shopping_list_assignee FOREIGN KEY (assignee_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
              CONSTRAINT ck_shopping_list_role     CHECK (assignee_role IS NULL OR assignee_role IN ('admins','manager','assistenz','angehoerige'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Mehrere Listen (Einkauf, Besorgen durch Familie …) – Ziel u. a. bei „nicht möglich"'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE task_template (
              id                   BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
              title                VARCHAR(150)      NOT NULL,
              description          TEXT              NULL,
              category             VARCHAR(15)       NOT NULL,
              estimated_minutes    SMALLINT UNSIGNED NULL,
              priority             VARCHAR(10)       NOT NULL DEFAULT 'normal',
              requires_manager     TINYINT(1)        NOT NULL DEFAULT 0,
              proof_type           VARCHAR(10)       NOT NULL DEFAULT 'keiner',
              assignee_user_id     BIGINT UNSIGNED   NULL,
              assignee_role        VARCHAR(12)       NULL,
              visibility           VARCHAR(12)       NOT NULL DEFAULT 'alle',
              guide_id             BIGINT UNSIGNED   NULL,
              asset_id             BIGINT UNSIGNED   NULL,
              poi_id               BIGINT UNSIGNED   NULL,
              recurrence_type      VARCHAR(10)       NOT NULL,
              rrule                VARCHAR(255)      NULL,
              interval_days        SMALLINT UNSIGNED NULL,
              earliest_after_days  SMALLINT UNSIGNED NULL,
              window_days          SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              due_time             TIME              NULL,
              starts_on            DATE              NOT NULL,
              ends_on              DATE              NULL,
              paused_from          DATE              NULL,
              paused_until         DATE              NULL,
              is_active            TINYINT(1)        NOT NULL DEFAULT 1,
              last_done_at         DATETIME          NULL,
              unable_list_id       BIGINT UNSIGNED   NULL,
              unable_item_name     VARCHAR(100)      NULL,
              next_due_on          DATE              NULL,
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
              COMMENT='Wiederkehrende Aufgabe / Wartungsplan'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE task (
              id                   BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
              template_id          BIGINT UNSIGNED   NULL,
              occurrence_date      DATE              NULL,
              title                VARCHAR(150)      NOT NULL,
              description          TEXT              NULL,
              category             VARCHAR(15)       NOT NULL,
              estimated_minutes    SMALLINT UNSIGNED NULL,
              priority             VARCHAR(10)       NOT NULL DEFAULT 'normal',
              requires_manager     TINYINT(1)        NOT NULL DEFAULT 0,
              proof_type           VARCHAR(10)       NOT NULL DEFAULT 'keiner',
              assignee_user_id     BIGINT UNSIGNED   NULL,
              assignee_role        VARCHAR(12)       NULL,
              assigned_by_user_id  BIGINT UNSIGNED   NULL,
              assigned_by_device_id BIGINT UNSIGNED  NULL,
              assigned_at          DATETIME          NULL,
              visibility           VARCHAR(12)       NOT NULL DEFAULT 'alle',
              guide_id             BIGINT UNSIGNED   NULL,
              asset_id             BIGINT UNSIGNED   NULL,
              poi_id               BIGINT UNSIGNED   NULL,
              due_rule             VARCHAR(16)       NOT NULL DEFAULT 'datum',
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
              KEY ix_task_assigned_device (assigned_by_device_id),
              CONSTRAINT fk_task_template FOREIGN KEY (template_id)              REFERENCES task_template (id) ON DELETE SET NULL,
              CONSTRAINT fk_task_assignee FOREIGN KEY (assignee_user_id)   REFERENCES app_user (id)    ON DELETE SET NULL,
              CONSTRAINT fk_task_assigner FOREIGN KEY (assigned_by_user_id) REFERENCES app_user (id)   ON DELETE SET NULL,
              CONSTRAINT fk_task_assigner_device FOREIGN KEY (assigned_by_device_id) REFERENCES device (id) ON DELETE SET NULL,
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
              COMMENT='Aufgabe: einmalig oder Vorkommen einer Vorlage, auch Gesprächspunkte'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE task_completion (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              task_id        BIGINT UNSIGNED NOT NULL,
              action         VARCHAR(15)     NOT NULL,
              user_id        BIGINT UNSIGNED NULL,
              device_id      BIGINT UNSIGNED NULL,
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
              CONSTRAINT ck_completion_action CHECK (action IN ('erledigt','uebersprungen','nicht_moeglich'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Abschluss eines Vorkommens (wer/wann/Kommentar)'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task_completion');
        $this->addSql('DROP TABLE task');
        $this->addSql('DROP TABLE task_template');
        $this->addSql('DROP TABLE shopping_list');
        $this->addSql('DROP TABLE asset');
        $this->addSql('DROP TABLE guide');
    }

    /**
     * MariaDB commits DDL implicitly, a surrounding transaction would only pretend safety.
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
