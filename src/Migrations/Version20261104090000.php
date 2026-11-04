<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M7: lists with entries and favourites, reports and in-app notifications – and, because a report may be followed up
 * by a handover, the handover book (pages in M10). List entries and favourites have a link (added to the reference
 * schema 04.11.).
 *
 * The CREATE TABLE statements are copied from docs/datenbank/schema.sql (without SQL comments),
 * see konzept-6 §6. `composer schema:check` proves the result is identical.
 */
final class Version20261104090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Listen (shopping_item, shopping_favorite), Meldungen (issue_report), Benachrichtigungen (notification), Übergabebuch (handover_note)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE handover_note (
              id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              parent_id         BIGINT UNSIGNED NULL,
              author_user_id    BIGINT UNSIGNED NULL,
              device_id         BIGINT UNSIGNED NULL,
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
              COMMENT='Übergabebuch'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE shopping_favorite (
              id                BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
              list_id           BIGINT UNSIGNED   NULL,
              name              VARCHAR(100)      NOT NULL,
              default_quantity  VARCHAR(30)       NULL,
              link              VARCHAR(500)      NULL,
              poi_id            BIGINT UNSIGNED   NULL,
              category          VARCHAR(30)       NULL,
              sort              SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              PRIMARY KEY (id),
              KEY ix_favorite (sort),
              KEY ix_favorite_list (list_id),
              CONSTRAINT fk_favorite_poi    FOREIGN KEY (poi_id)    REFERENCES poi (id)    ON DELETE SET NULL,
              CONSTRAINT fk_favorite_list   FOREIGN KEY (list_id)   REFERENCES shopping_list (id) ON DELETE SET NULL,
              CONSTRAINT ck_favorite_link   CHECK (link IS NULL OR link LIKE 'https://%')
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Lieblingsartikel zum schnellen Hinzufügen'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE shopping_item (
              id                    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              list_id               BIGINT UNSIGNED NOT NULL,
              name                  VARCHAR(100)    NOT NULL,
              quantity              VARCHAR(30)     NULL,
              poi_id                BIGINT UNSIGNED NULL,
              category              VARCHAR(30)     NULL,
              note                  VARCHAR(255)    NULL,
              link                  VARCHAR(500)    NULL,
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
              CONSTRAINT ck_shopping_done    CHECK ((is_done = 0 AND done_at IS NULL) OR (is_done = 1 AND done_at IS NOT NULL)),
              CONSTRAINT ck_shopping_link    CHECK (link IS NULL OR link LIKE 'https://%')
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Einkaufsliste (Warnungen über profile_keyword)'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE issue_report (
              id                          BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
              public_id                   BINARY(16)        NOT NULL,
              kind                        VARCHAR(15)       NOT NULL,
              title                       VARCHAR(150)      NOT NULL,
              note                        VARCHAR(1000)     NULL,
              is_urgent                   TINYINT(1)        NOT NULL DEFAULT 0,
              visibility                  VARCHAR(10)       NOT NULL DEFAULT 'alle',
              task_id                     BIGINT UNSIGNED   NULL,
              asset_id                    BIGINT UNSIGNED   NULL,
              poi_id                      BIGINT UNSIGNED   NULL,
              created_by_user_id          BIGINT UNSIGNED   NULL,
              created_by_device_id        BIGINT UNSIGNED   NULL,
              created_via                 VARCHAR(10)       NOT NULL DEFAULT 'app',
              created_at                  DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
              confirm_count               SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              last_confirmed_at           DATETIME          NULL,
              status                      VARCHAR(12)       NOT NULL DEFAULT 'offen',
              follow_up                   VARCHAR(10)       NULL,
              follow_up_shopping_item_id  BIGINT UNSIGNED   NULL,
              follow_up_task_id           BIGINT UNSIGNED   NULL,
              follow_up_handover_id       BIGINT UNSIGNED   NULL,
              closed_at                   DATETIME          NULL,
              closed_by_user_id           BIGINT UNSIGNED   NULL,
              close_note                  VARCHAR(255)      NULL,
              anonymized_at               DATETIME          NULL,
              delete_after                DATE              NULL,
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
              COMMENT='Meldungen – erscheinen bei den Admins (Heute, Glocke, optional E-Mail) und im Meldungs-Log'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE notification (
              id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id       BIGINT UNSIGNED NOT NULL,
              kind          VARCHAR(12)     NOT NULL,
              title         VARCHAR(150)    NOT NULL,
              body          VARCHAR(500)    NULL,
              link          VARCHAR(255)    NULL,
              is_urgent     TINYINT(1)      NOT NULL DEFAULT 0,
              created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              read_at       DATETIME        NULL,
              pushed_at     DATETIME        NULL,
              mailed_at     DATETIME        NULL,
              delete_after  DATE            NOT NULL,
              PRIMARY KEY (id),
              KEY ix_notification_inbox (user_id, read_at, created_at),
              KEY ix_notification_cleanup (delete_after),
              CONSTRAINT fk_notification_member FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE,
              CONSTRAINT ck_notification_kind   CHECK (kind IN ('nachricht','meldung','dienst','urlaub','aufgabe','termin','uebergabe','erinnerung','checkliste','system'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Benachrichtigungen in der App (Glocke, „seit deinem letzten Besuch") – Push nur optional'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE issue_report');
        $this->addSql('DROP TABLE shopping_item');
        $this->addSql('DROP TABLE shopping_favorite');
        $this->addSql('DROP TABLE handover_note');
    }

    /**
     * MariaDB commits DDL implicitly, a surrounding transaction would only pretend safety.
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
