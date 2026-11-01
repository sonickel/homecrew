<?php

declare(strict_types=1);

namespace App\Tests\Database;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DriverException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Tests against homecrew_test; every test runs in a transaction that is rolled back (dama/doctrine-test-bundle).
 * The rules mirror docs/datenbank/smoke_test.sql (B…) – the database itself must reject invalid data.
 */
abstract class DatabaseTestCase extends KernelTestCase
{
    /** MariaDB error numbers, as in smoke_test.sql */
    protected const int CHECK_VIOLATED = 4025;
    protected const int DUPLICATE = 1062;

    protected function connection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    /**
     * The statement must fail with this error number because of exactly this constraint or unique key
     * (MariaDB names it in the message), not because of another rule that happens to fail as well.
     *
     * @param array<string, mixed> $params
     */
    protected function assertRejected(string $sql, int $expectedErrorNumber, string $constraint, array $params = []): void
    {
        try {
            $this->connection()->executeStatement($sql, $params);
        } catch (DriverException $exception) {
            self::assertSame($expectedErrorNumber, $exception->getCode(), $exception->getMessage());
            self::assertMatchesRegularExpression('/[`\']'.preg_quote($constraint, '/').'[`\']/', $exception->getMessage());

            return;
        }

        self::fail('The database accepted: '.$sql);
    }

    /**
     * Inserts a person like smoke_test.sql does and returns its id.
     *
     * @param array<string, mixed> $columns additional columns
     */
    protected function insertUser(string $role, string $username, array $columns = []): int
    {
        $this->connection()->insert('app_user', [
            'public_id' => Uuid::v7()->toBinary(),
            'role' => $role,
            'is_admin' => 'manager' === $role ? 1 : 0,
            'username' => $username,
            'display_name' => ucfirst($username),
            'active_from' => '2026-01-01',
            ...$columns,
        ]);

        return (int) $this->connection()->lastInsertId();
    }
}
