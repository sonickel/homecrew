<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M4: emergency contacts and – because they may link a place – the places table (pages for places in M12).
 *
 * The CREATE TABLE statements are copied from docs/datenbank/schema.sql (without SQL comments),
 * see konzept-6 §6. `composer schema:check` proves the result is identical.
 */
final class Version20261102140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notfallkontakte (emergency_contact) und Orte (poi)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE poi (
              id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              public_id       BINARY(16)      NOT NULL,
              name            VARCHAR(100)    NOT NULL,
              category        VARCHAR(30)     NOT NULL,
              street          VARCHAR(150)    NULL,
              postal_code     VARCHAR(10)     NULL,
              city            VARCHAR(100)    NULL,
              latitude        DECIMAL(9,6)    NULL,
              longitude       DECIMAL(9,6)    NULL,
              phone           VARCHAR(30)     NULL,
              website         VARCHAR(255)    NULL,
              opening_hours   TEXT            NULL,
              note            TEXT            NULL,
              preferences     TEXT            NULL,
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
              COMMENT='Orte mit Barrierefreiheit und Navigations-Link'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE emergency_contact (
              id                  BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
              category            VARCHAR(15)       NOT NULL DEFAULT 'angehoerige',
              name                VARCHAR(100)      NOT NULL,
              relation            VARCHAR(50)       NULL,
              phone               VARCHAR(30)       NOT NULL,
              phone_alt           VARCHAR(30)       NULL,
              when_to_call        VARCHAR(255)      NULL,
              poi_id              BIGINT UNSIGNED   NULL,
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
              COMMENT='Notfall-Infos: Kontakte und wichtige Nummern (Sperrbildschirm ohne PIN, wenn show_on_lockscreen)'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE emergency_contact');
        $this->addSql('DROP TABLE poi');
    }

    /**
     * MariaDB commits DDL implicitly, a surrounding transaction would only pretend safety.
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
