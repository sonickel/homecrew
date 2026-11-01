<?php

declare(strict_types=1);

namespace App\Tests\Database;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Rules of app_user from docs/datenbank/smoke_test.sql (B43–B46, B50–B52) and the remaining CHECKs of schema.sql.
 */
final class AppUserRulesTest extends DatabaseTestCase
{
    private int $managerId;
    private int $assistantId;
    private int $relativeId;

    protected function setUp(): void
    {
        $this->managerId = $this->insertUser('manager', 'manager', ['password_hash' => 'pw-hash', 'twofa_method' => 'passkey']);
        $this->assistantId = $this->insertUser('assistenz', 'martin', ['pin_hash' => 'argon2-hash']);
        $this->relativeId = $this->insertUser('angehoerige', 'schwester', ['is_admin' => 1]);
    }

    public function testValidHouseholdIsAccepted(): void
    {
        // Assistant without any login (team tablet only) and relative without admin right are fine
        $this->insertUser('assistenz', 'ben');
        $this->insertUser('angehoerige', 'papa');

        self::assertSame(5, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM app_user'));
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM app_user WHERE manager_marker = 1'));
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function rejectedChanges(): iterable
    {
        $insert = 'INSERT INTO app_user (public_id, role, is_admin, username, display_name, active_from%s) VALUES (UNHEX(REPLACE(UUID(),\'-\',\'\')), %s)';

        yield 'B43 PIN-Anmeldung aktiviert, aber keine PIN gesetzt' => [sprintf($insert, ', pin_login_enabled', "'assistenz', 0, 'neu', 'Neu', '2026-11-01', 1"), self::CHECK_VIOLATED, 'ck_user_pin'];
        yield 'B44 2FA per E-Mail ohne E-Mail-Adresse' => ['UPDATE app_user SET twofa_method = \'email\' WHERE id = :relative', self::CHECK_VIOLATED, 'ck_user_2fa_mail'];
        yield 'B45 Zweite Person mit Rolle „Manager"' => [sprintf($insert, '', "'manager', 1, 'mgr2', 'Mgr2', '2026-11-01'"), self::DUPLICATE, 'uq_user_manager'];
        yield 'B46 Manager ohne Admin-Recht' => ['UPDATE app_user SET is_admin = 0 WHERE id = :manager', self::CHECK_VIOLATED, 'ck_user_manager'];
        yield 'B50 Unbekannte E-Mail-Einstellung' => ['UPDATE app_user SET notify_email = \'stuendlich\' WHERE id = :assistant', self::CHECK_VIOLATED, 'ck_user_mail'];
        yield 'B51 Assistenz mit Admin-Recht' => ['UPDATE app_user SET is_admin = 1 WHERE id = :assistant', self::CHECK_VIOLATED, 'ck_user_assist_admin'];
        yield 'B52 Benutzername doppelt' => [sprintf($insert, '', "'assistenz', 0, 'martin', 'Martin B.', '2026-11-01'"), self::DUPLICATE, 'uq_user_username'];
        // Further rules of schema.sql that the smoke test does not cover
        yield 'Anzeigename doppelt' => [sprintf($insert, '', "'assistenz', 0, 'martin2', 'Martin', '2026-11-01'"), self::DUPLICATE, 'uq_user_display'];
        yield 'Unbekannte Rolle' => ['UPDATE app_user SET role = \'chef\' WHERE id = :assistant', self::CHECK_VIOLATED, 'ck_user_role'];
        yield 'Austritt vor Eintritt' => ['UPDATE app_user SET active_until = \'2025-12-31\' WHERE id = :assistant', self::CHECK_VIOLATED, 'ck_user_dates'];
        yield 'Unbekannte 2FA-Methode' => ['UPDATE app_user SET twofa_method = \'sms\' WHERE id = :relative', self::CHECK_VIOLATED, 'ck_user_2fa'];
        yield 'Authenticator-App ohne Geheimnis' => ['UPDATE app_user SET twofa_method = \'totp\' WHERE id = :relative', self::CHECK_VIOLATED, 'ck_user_2fa_totp'];
        yield 'Unbekannte Schriftgröße' => ['UPDATE app_user SET font_size = \'riesig\' WHERE id = :assistant', self::CHECK_VIOLATED, 'ck_user_font'];
        yield 'Unbekannte Kalenderansicht' => ['UPDATE app_user SET calendar_view = \'jahr\' WHERE id = :assistant', self::CHECK_VIOLATED, 'ck_user_calview'];
    }

    #[DataProvider('rejectedChanges')]
    public function testRuleIsEnforcedByTheDatabase(string $sql, int $expectedErrorNumber, string $constraint): void
    {
        $params = array_filter(
            ['manager' => $this->managerId, 'assistant' => $this->assistantId, 'relative' => $this->relativeId],
            static fn (string $name): bool => str_contains($sql, ':'.$name),
            \ARRAY_FILTER_USE_KEY,
        );

        $this->assertRejected($sql, $expectedErrorNumber, $constraint, $params);
    }

    public function testEmailIsUniqueButOptional(): void
    {
        $this->connection()->update('app_user', ['email' => 'a@example.org'], ['id' => $this->assistantId]);

        $this->assertRejected('UPDATE app_user SET email = \'a@example.org\' WHERE id = :relative', self::DUPLICATE, 'uq_user_email', ['relative' => $this->relativeId]);
        self::assertSame(2, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM app_user WHERE email IS NULL'));
    }
}
