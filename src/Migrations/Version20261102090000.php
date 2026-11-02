<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M3: invitations by QR code.
 *
 * The CREATE TABLE statement is copied from docs/datenbank/schema.sql (without SQL comments),
 * see konzept-6 §6. `composer schema:check` proves the result is identical.
 */
final class Version20261102090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Einladungen (invitation)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE invitation (
              id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id             BIGINT UNSIGNED NOT NULL,
              token_hash          CHAR(64)        NOT NULL,
              expires_at          DATETIME        NOT NULL,
              used_at             DATETIME        NULL,
              created_by_user_id  BIGINT UNSIGNED NULL,
              created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (id),
              UNIQUE KEY uq_invitation_token (token_hash),
              CONSTRAINT fk_invitation_member  FOREIGN KEY (user_id)            REFERENCES app_user (id) ON DELETE CASCADE,
              CONSTRAINT fk_invitation_creator FOREIGN KEY (created_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Einladungscode/QR (einmalig, 72 h)'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE invitation');
    }

    /**
     * MariaDB commits DDL implicitly, a surrounding transaction would only pretend safety.
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
