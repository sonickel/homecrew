<?php

declare(strict_types=1);

namespace App\Tests\Database;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Rules of app_setting from docs/datenbank/smoke_test.sql (B87–B92) and the remaining CHECKs of schema.sql.
 */
final class AppSettingRulesTest extends DatabaseTestCase
{
    public function testMigrationCreatesTheSettingsRowWithDefaults(): void
    {
        $row = $this->connection()->fetchAssociative('SELECT * FROM app_setting');

        self::assertIsArray($row);
        self::assertSame(1, (int) $row['id']);
        self::assertSame('Mein Haushalt', $row['household_name']);
        self::assertSame('Europe/Berlin', $row['timezone']);
        self::assertSame(0, (int) $row['ai_enabled']);
        self::assertSame('fehlt', $row['ai_key_status']);
        self::assertSame(0, (int) $row['mail_enabled']);
        self::assertSame('fehlt', $row['mail_status']);
        self::assertSame('18:00:00', $row['daily_digest_time']);
    }

    public function testThereIsExactlyOneSettingsRow(): void
    {
        $this->assertRejected('INSERT INTO app_setting (id) VALUES (2)', self::CHECK_VIOLATED, 'ck_setting_single');
        $this->assertRejected('INSERT INTO app_setting (id) VALUES (1)', self::DUPLICATE, 'PRIMARY');
        $this->assertRejected('INSERT INTO app_setting () VALUES ()', self::DUPLICATE, 'PRIMARY');
    }

    public function testConfiguredExtensionsAreAccepted(): void
    {
        $this->configureExtensions();

        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT ai_enabled = 1 AND ai_key_status = \'ok\' AND mail_enabled = 1 FROM app_setting'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedChanges(): iterable
    {
        yield 'B87 KI einschalten ohne Schlüssel' => ['UPDATE app_setting SET ai_api_key_enc = NULL, ai_api_key_hint = NULL, ai_key_status = \'fehlt\'', 'ck_setting_ai_on'];
        yield 'B88 Schlüssel-Status „ok“ ohne Schlüssel' => ['UPDATE app_setting SET ai_enabled = 0, ai_api_key_enc = NULL, ai_api_key_hint = NULL', 'ck_setting_ai_key'];
        yield 'B89 Unbekannter Schlüssel-Status' => ['UPDATE app_setting SET ai_key_status = \'super\'', 'ck_setting_ai_stat'];
        yield 'B90 Unbekannter KI-Anbieter' => ['UPDATE app_setting SET ai_provider = \'xyz\'', 'ck_setting_ai_prov'];
        yield 'B91 E-Mail eingeschaltet ohne Absenderadresse' => ['UPDATE app_setting SET mail_from_address = NULL', 'ck_setting_mail_on'];
        yield 'B92 SMTP mit unbekannter Verschlüsselung' => ['UPDATE app_setting SET smtp_encryption = \'rot13\'', 'ck_setting_mail_enc'];
        // Further rules of schema.sql that the smoke test does not cover
        yield 'Schlüssel-Hinweis ohne Schlüssel' => ['UPDATE app_setting SET ai_enabled = 0, ai_api_key_enc = NULL, ai_key_status = \'fehlt\'', 'ck_setting_ai_key'];
        yield 'E-Mail eingeschaltet ohne Port' => ['UPDATE app_setting SET smtp_port = NULL', 'ck_setting_mail_on'];
        yield 'E-Mail eingeschaltet mit Port 0' => ['UPDATE app_setting SET smtp_port = 0', 'ck_setting_mail_on'];
        yield 'Unbekannter E-Mail-Status' => ['UPDATE app_setting SET mail_status = \'super\'', 'ck_setting_mail_st'];
        yield 'Unbekanntes Minijob-Verfahren' => ['UPDATE app_setting SET minijob_procedure = \'schwarz\'', 'ck_setting_minijob'];
        yield 'Unbekannter Freigabe-Modus' => ['UPDATE app_setting SET guide_publish_mode = \'irgendwie\'', 'ck_setting_publish'];
        yield 'Unbekannte Spracherkennung' => ['UPDATE app_setting SET speech_recognition = \'cloud\'', 'ck_setting_speech'];
        yield 'Ansichtszeitraum 0 Monate' => ['UPDATE app_setting SET time_view_months = 0', 'ck_setting_months'];
        yield 'Ansichtszeitraum über 24 Monate' => ['UPDATE app_setting SET time_view_months = 25', 'ck_setting_months'];
        yield 'Negatives KI-Budget' => ['UPDATE app_setting SET ai_monthly_budget_eur = -1', 'ck_setting_budget'];
    }

    #[DataProvider('rejectedChanges')]
    public function testRuleIsEnforcedByTheDatabase(string $sql, string $constraint): void
    {
        // Like smoke_test.sql: start from a household with AI and e-mail set up
        $this->configureExtensions();

        $this->assertRejected($sql, self::CHECK_VIOLATED, $constraint);
    }

    private function configureExtensions(): void
    {
        $this->connection()->executeStatement('UPDATE app_setting SET household_name = \'Test-Haushalt\',
            ai_api_key_enc = \'chiffrat\', ai_api_key_hint = \'a1b2\', ai_key_status = \'ok\', ai_key_checked_at = NOW(), ai_enabled = 1,
            mail_enabled = 1, smtp_host = \'smtp.example.org\', smtp_port = 587, smtp_username = \'homecrew@example.org\',
            smtp_password_enc = \'chiffrat\', mail_from_address = \'homecrew@example.org\', mail_from_name = \'HomeCrew\', mail_status = \'ok\'');
    }
}
