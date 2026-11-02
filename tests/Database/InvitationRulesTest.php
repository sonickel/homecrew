<?php

declare(strict_types=1);

namespace App\Tests\Database;

/**
 * Constraints and delete rules of invitation (schema.sql; the smoke test has no rule for it).
 */
final class InvitationRulesTest extends DatabaseTestCase
{
    private int $managerId;
    private int $annaId;

    protected function setUp(): void
    {
        $this->managerId = $this->insertUser('manager', 'manager');
        $this->annaId = $this->insertUser('assistenz', 'anna');
        $this->connection()->insert('invitation', [
            'user_id' => $this->annaId,
            'token_hash' => hash('sha256', 'code-1'),
            'expires_at' => '2026-11-05 12:00:00',
            'created_by_user_id' => $this->managerId,
        ]);
    }

    public function testValidRowGetsItsDefaults(): void
    {
        $row = $this->connection()->fetchAssociative('SELECT used_at, created_at FROM invitation');
        self::assertIsArray($row);
        self::assertNull($row['used_at']);
        self::assertNotNull($row['created_at']);
    }

    public function testSameCodeTwiceIsRejected(): void
    {
        $this->assertRejected(
            'INSERT INTO invitation (user_id, token_hash, expires_at) VALUES (:anna, SHA2(\'code-1\', 256), \'2026-11-06 12:00:00\')',
            self::DUPLICATE,
            'uq_invitation_token',
            ['anna' => $this->annaId],
        );
    }

    public function testInvitationDisappearsWithThePerson(): void
    {
        $this->connection()->delete('app_user', ['id' => $this->annaId]);

        self::assertSame(0, (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM invitation'));
    }

    public function testInvitationStaysWhenTheCreatorIsRemoved(): void
    {
        $sister = $this->insertUser('angehoerige', 'schwester', ['is_admin' => 1]);
        $this->connection()->update('invitation', ['created_by_user_id' => $sister], ['user_id' => $this->annaId]);

        $this->connection()->delete('app_user', ['id' => $sister]);

        self::assertNull($this->connection()->fetchOne('SELECT created_by_user_id FROM invitation'));
    }
}
