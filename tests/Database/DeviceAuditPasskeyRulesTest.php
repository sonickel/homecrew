<?php

declare(strict_types=1);

namespace App\Tests\Database;

use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Rules of device, webauthn_credential and audit_log from docs/datenbank/smoke_test.sql (B59, B93)
 * and the remaining constraints and delete rules of schema.sql.
 */
final class DeviceAuditPasskeyRulesTest extends DatabaseTestCase
{
    private int $managerId;
    private int $deviceId;

    protected function setUp(): void
    {
        $this->managerId = $this->insertUser('manager', 'manager', ['password_hash' => 'pw-hash']);
        $this->connection()->insert('device', ['name' => 'Tablet Flur', 'token_hash' => hash('sha256', 'geraet'), 'paired_by_user_id' => $this->managerId]);
        $this->deviceId = (int) $this->connection()->lastInsertId();
        $this->connection()->insert('webauthn_credential', ['user_id' => $this->managerId, 'credential_id' => 'cred-1', 'public_key' => 'key']);
    }

    public function testValidRowsGetTheirDefaults(): void
    {
        $device = $this->connection()->fetchAssociative('SELECT mode, dim_after_seconds, font_size, paired_at FROM device WHERE id = ?', [$this->deviceId]);
        self::assertIsArray($device);
        self::assertSame('team', $device['mode']);
        self::assertSame(300, (int) $device['dim_after_seconds']);
        self::assertSame('normal', $device['font_size']);
        self::assertNotNull($device['paired_at']);

        $this->connection()->insert('audit_log', ['actor_user_id' => $this->managerId, 'action' => 'anmeldung.erfolg', 'details' => '{"methode":"passwort"}']);
        self::assertSame('app', $this->connection()->fetchOne('SELECT via FROM audit_log'));
        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT sign_count FROM webauthn_credential'));
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function rejectedChanges(): iterable
    {
        yield 'B59 Tablet mit unbekanntem Modus' => ['UPDATE device SET mode = \'kiosk\' WHERE id = :device', self::CHECK_VIOLATED, 'ck_device_mode'];
        yield 'B93 Tablet mit unbekannter Schriftgröße' => ['UPDATE device SET font_size = \'riesig\' WHERE id = :device', self::CHECK_VIOLATED, 'ck_device_font'];
        // Further rules of schema.sql that the smoke test does not cover
        yield 'Zweites Tablet mit demselben Token' => ['INSERT INTO device (name, token_hash) VALUES (\'Tablet Küche\', SHA2(\'geraet\', 256))', self::DUPLICATE, 'uq_device_token'];
        yield 'Passkey doppelt' => ['INSERT INTO webauthn_credential (user_id, credential_id, public_key) VALUES (:manager, \'cred-1\', \'key\')', self::DUPLICATE, 'uq_webauthn_cred'];
        yield 'Protokoll mit unbekanntem Weg' => ['INSERT INTO audit_log (action, via) VALUES (\'x\', \'fax\')', self::CHECK_VIOLATED, 'ck_audit_via'];
    }

    #[DataProvider('rejectedChanges')]
    public function testRuleIsEnforcedByTheDatabase(string $sql, int $expectedErrorNumber, string $constraint): void
    {
        $params = array_filter(
            ['manager' => $this->managerId, 'device' => $this->deviceId],
            static fn (string $name): bool => str_contains($sql, ':'.$name),
            \ARRAY_FILTER_USE_KEY,
        );

        $this->assertRejected($sql, $expectedErrorNumber, $constraint, $params);
    }

    public function testPasskeysDisappearWithThePersonWhileLogAndDeviceStay(): void
    {
        $this->connection()->insert('audit_log', ['actor_user_id' => $this->managerId, 'device_id' => $this->deviceId, 'via' => 'tablet', 'action' => 'geraet.koppeln']);

        $this->connection()->delete('app_user', ['id' => $this->managerId]);

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM webauthn_credential'));
        self::assertSame([null, $this->deviceId], array_map(
            static fn (mixed $value): ?int => null === $value ? null : (int) $value,
            array_values((array) $this->connection()->fetchAssociative('SELECT actor_user_id, device_id FROM audit_log')),
        ));
        self::assertNull($this->connection()->fetchOne('SELECT paired_by_user_id FROM device WHERE id = ?', [$this->deviceId]));
    }

    public function testLogEntryStaysWhenTheDeviceIsRemoved(): void
    {
        $this->connection()->insert('audit_log', ['device_id' => $this->deviceId, 'via' => 'tablet', 'action' => 'aufgabe.erledigen']);

        $this->connection()->delete('device', ['id' => $this->deviceId]);

        self::assertNull($this->connection()->fetchOne('SELECT device_id FROM audit_log'));
    }
}
