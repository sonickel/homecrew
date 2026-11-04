<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M6: checklists of plans and occurrences with their results – and, because checklist points may refer to them,
 * parts of aids (stage 2, without pages yet). Occurrences changed one by one are marked (task.is_modified,
 * added to the reference schema 03.11.).
 *
 * The CREATE TABLE statements are copied from docs/datenbank/schema.sql (without SQL comments),
 * see konzept-6 §6. `composer schema:check` proves the result is identical.
 */
final class Version20261103180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Checklisten (task_template_item, task_item, task_item_result), Bauteile (asset_component), task.is_modified';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            ALTER TABLE task
              ADD COLUMN is_modified TINYINT(1) NOT NULL DEFAULT 0 AFTER occurrence_date,
              ADD CONSTRAINT ck_task_modified CHECK (occurrence_date IS NOT NULL OR is_modified = 0)
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE asset_component (
              id              BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
              asset_id        BIGINT UNSIGNED   NOT NULL,
              name            VARCHAR(100)      NOT NULL,
              component_type  VARCHAR(30)       NULL,
              sort            SMALLINT UNSIGNED NOT NULL DEFAULT 0,
              PRIMARY KEY (id),
              KEY ix_component_asset (asset_id, sort),
              CONSTRAINT fk_component_asset  FOREIGN KEY (asset_id)  REFERENCES asset (id)  ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Bauteile eines Hilfsmittels'
            SQL);

        $this->addSql(<<<'SQL'
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
              COMMENT='Checklistenpunkte einer Vorlage (zusätzlich zu den automatisch erzeugten Sollwert-Punkten)'
            SQL);

        $this->addSql(<<<'SQL'
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
              COMMENT='Checkliste eines Vorkommens (inkl. Sollwerte aus Hilfsmittel-Bauteilen)'
            SQL);

        $this->addSql(<<<'SQL'
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
              COMMENT='Ergebnis je Checklistenpunkt (abgehakt, Messwert)'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE task_item_result');
        $this->addSql('DROP TABLE task_item');
        $this->addSql('DROP TABLE task_template_item');
        $this->addSql('DROP TABLE asset_component');
        $this->addSql('ALTER TABLE task DROP CONSTRAINT ck_task_modified, DROP COLUMN is_modified');
    }

    /**
     * MariaDB commits DDL implicitly, a surrounding transaction would only pretend safety.
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
