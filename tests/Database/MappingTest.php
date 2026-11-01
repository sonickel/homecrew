<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Entity\AppUser;
use App\Enum\FontSize;
use App\Enum\TwoFactorMethod;
use App\Enum\UserRole;
use App\Repository\AppSettingRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Uid\UuidV7;

/**
 * Entities, Doctrine types and the connection work together with the schema of the first migration.
 */
final class MappingTest extends DatabaseTestCase
{
    public function testPersonSurvivesTheRoundTrip(): void
    {
        $entityManager = $this->entityManager();
        $anna = new AppUser(UserRole::Assistenz, 'anna', 'Anna', new \DateTimeImmutable('2026-10-01'));
        $entityManager->persist($anna);
        $entityManager->flush();
        $entityManager->clear();

        $loaded = $entityManager->find(AppUser::class, $anna->getId());

        self::assertNotNull($loaded);
        self::assertInstanceOf(UuidV7::class, $loaded->getPublicId());
        self::assertTrue($anna->getPublicId()->equals($loaded->getPublicId()));
        self::assertSame(16, \strlen((string) $this->connection()->fetchOne('SELECT public_id FROM app_user WHERE id = ?', [$anna->getId()])));
        self::assertSame(UserRole::Assistenz, $loaded->getRole());
        self::assertFalse($loaded->isAdmin());
        self::assertSame('anna', $loaded->getUsername());
        self::assertSame('Anna', $loaded->getDisplayName());
        self::assertSame('2026-10-01', $loaded->getActiveFrom()->format('Y-m-d'));
        self::assertSame(TwoFactorMethod::Keine, $loaded->getTwofaMethod());
        self::assertSame(FontSize::Normal, $loaded->getFontSize());
        self::assertFalse($loaded->isHighContrast());
    }

    public function testDatabaseSetsCreatedAtInUtc(): void
    {
        $entityManager = $this->entityManager();
        $ben = new AppUser(UserRole::Assistenz, 'ben', 'Ben', new \DateTimeImmutable('2026-10-01'));
        $entityManager->persist($ben);
        $entityManager->flush();

        // Fetched back by Doctrine after the insert; DATETIME columns hold UTC
        self::assertEqualsWithDelta(time(), $ben->getCreatedAt()->getTimestamp(), 60);
    }

    public function testSecondManagerIsRejectedThroughTheOrm(): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist(new AppUser(UserRole::Manager, 'manager', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true));
        $entityManager->flush();

        $entityManager->persist(new AppUser(UserRole::Manager, 'zweiter', 'Zweiter', new \DateTimeImmutable('2026-01-01'), isAdmin: true));

        $this->expectException(UniqueConstraintViolationException::class);
        $entityManager->flush();
    }

    public function testSettingsRowIsAvailable(): void
    {
        $settings = static::getContainer()->get(AppSettingRepository::class)->get();

        self::assertSame(1, $settings->getId());
        self::assertSame('Mein Haushalt', $settings->getHouseholdName());
        self::assertSame('Europe/Berlin', $settings->getTimezone());
    }

    public function testConnectionUsesUtc(): void
    {
        self::assertSame('+00:00', $this->connection()->fetchOne('SELECT @@session.time_zone'));
        self::assertSame(1, (int) $this->connection()->fetchOne('SELECT NOW() = UTC_TIMESTAMP()'));
    }

    public function testMappingMatchesTheMigratedSchema(): void
    {
        // Same comparison as doctrine:schema:validate and doctrine:migrations:diff: no change may be left,
        // e.g. TINYINT UNSIGNED must not be read back as boolean (App\Doctrine\Schema\MariaDbSchemaManager)
        $entityManager = $this->entityManager();
        $schemaTool = new SchemaTool($entityManager);

        $changes = $schemaTool->getUpdateSchemaSql($entityManager->getMetadataFactory()->getAllMetadata());

        // The migration bundle hides its own table only while a migration command runs
        self::assertSame([], array_values(array_filter($changes, static fn (string $sql): bool => 'DROP TABLE doctrine_migration_versions' !== $sql)));
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
