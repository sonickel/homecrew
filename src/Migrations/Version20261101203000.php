<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M2: passkeys and the audit log; device comes along because audit_log refers to it (pairing follows in M4).
 *
 * The CREATE TABLE statements are copied from docs/datenbank/schema.sql (without SQL comments),
 * see konzept-6 §6. `composer schema:check` proves the result is identical.
 */
final class Version20261101203000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Geräte (device), Passkeys (webauthn_credential) und Protokoll (audit_log)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE device (
              id                 BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              name               VARCHAR(50)     NOT NULL,
              token_hash         CHAR(64)        NOT NULL,
              paired_by_user_id  BIGINT UNSIGNED NULL,
              mode               VARCHAR(12)     NOT NULL DEFAULT 'team',
              dim_after_seconds  SMALLINT UNSIGNED NOT NULL DEFAULT 300,
              font_size          VARCHAR(12)     NOT NULL DEFAULT 'normal',
              paired_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              last_seen_at       DATETIME        NULL,
              revoked_at         DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_device_token (token_hash),
              CONSTRAINT fk_device_paired FOREIGN KEY (paired_by_user_id) REFERENCES app_user (id) ON DELETE SET NULL,
              CONSTRAINT ck_device_mode   CHECK (mode IN ('team','persoenlich','beides')),
              CONSTRAINT ck_device_font   CHECK (font_size IN ('normal','gross','sehr_gross'))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Gekoppeltes Tablet: Team-Tablet (sieht alles für die Rolle Assistenz, keine Person) und/oder persönliche PIN-Anmeldung'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE webauthn_credential (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              user_id        BIGINT UNSIGNED NOT NULL,
              credential_id  VARBINARY(255)  NOT NULL,
              public_key     BLOB            NOT NULL,
              sign_count     INT UNSIGNED    NOT NULL DEFAULT 0,
              aaguid         BINARY(16)      NULL,
              transports     VARCHAR(100)    NULL,
              label          VARCHAR(100)    NULL,
              created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
              last_used_at   DATETIME        NULL,
              PRIMARY KEY (id),
              UNIQUE KEY uq_webauthn_cred (credential_id),
              CONSTRAINT fk_webauthn_user FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
              COMMENT='Passkeys'
            SQL);

        $this->addSql(<<<'SQL'
            CREATE TABLE audit_log (
              id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
              actor_user_id  BIGINT UNSIGNED NULL,
              device_id      BIGINT UNSIGNED NULL,
              via            VARCHAR(10)     NOT NULL DEFAULT 'app',
              action         VARCHAR(50)     NOT NULL,
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
              COMMENT='Protokoll sensibler Vorgänge (keine IP-Adressen)'
            SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_log');
        $this->addSql('DROP TABLE webauthn_credential');
        $this->addSql('DROP TABLE device');
    }

    /**
     * MariaDB commits DDL implicitly, a surrounding transaction would only pretend safety.
     */
    public function isTransactional(): bool
    {
        return false;
    }
}
