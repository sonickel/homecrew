<?php

declare(strict_types=1);

namespace App\Tests\Database;

use App\Entity\AppUser;
use App\Entity\AuditLog;
use App\Entity\Device;
use App\Entity\EmergencyContact;
use App\Entity\Poi;
use App\Entity\WebauthnCredential;
use App\Enum\AuditVia;
use App\Enum\DeviceMode;
use App\Enum\EmergencyCategory;
use App\Enum\EmergencyVisibility;
use App\Enum\FontSize;
use App\Enum\TwoFactorMethod;
use App\Enum\UserRole;
use App\Repository\AppSettingRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Uid\UuidV7;

/**
 * Entities, Doctrine types and the connection work together with the migrated schema.
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

    public function testPasskeyDeviceAndLogEntrySurviveTheRoundTrip(): void
    {
        $entityManager = $this->entityManager();
        $max = new AppUser(UserRole::Manager, 'manager', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        $publicKey = random_bytes(77);
        $passkey = new WebauthnCredential($max, "\x00\xffcred", $publicKey, 3, str_repeat("\x01", 16), ['internal', 'hybrid']);
        $device = new Device('Tablet Flur', hash('sha256', 'token'), $max);
        $entry = new AuditLog('anmeldung.erfolg', AuditVia::App, $max, null, 'app_user', null, ['methode' => 'passkey']);
        foreach ([$max, $passkey, $device, $entry] as $entity) {
            $entityManager->persist($entity);
        }
        $entityManager->flush();
        $entityManager->clear();

        $loadedPasskey = $entityManager->find(WebauthnCredential::class, $passkey->getId());
        self::assertNotNull($loadedPasskey);
        self::assertSame("\x00\xffcred", $loadedPasskey->getCredentialId());
        self::assertSame($publicKey, $loadedPasskey->getPublicKey());
        self::assertSame(3, $loadedPasskey->getSignCount());
        self::assertSame(['internal', 'hybrid'], $loadedPasskey->getTransports());
        self::assertSame('Max', $loadedPasskey->getUser()->getDisplayName());

        $loadedDevice = $entityManager->find(Device::class, $device->getId());
        self::assertNotNull($loadedDevice);
        self::assertSame(DeviceMode::Team, $loadedDevice->getMode());
        self::assertSame(FontSize::Normal, $loadedDevice->getFontSize());
        self::assertFalse($loadedDevice->isRevoked());

        $loadedEntry = $entityManager->find(AuditLog::class, $entry->getId());
        self::assertNotNull($loadedEntry);
        self::assertSame(AuditVia::App, $loadedEntry->getVia());
        self::assertSame(['methode' => 'passkey'], $loadedEntry->getDetails());
        self::assertEqualsWithDelta(time(), $loadedEntry->getCreatedAt()->getTimestamp(), 60);
    }

    public function testEmergencyContactAndPlaceSurviveTheRoundTrip(): void
    {
        $entityManager = $this->entityManager();
        $contact = new EmergencyContact();
        $contact->setCategory(EmergencyCategory::Arzt);
        $contact->setName('Hausarztpraxis');
        $contact->setPhone(' 0931 / 1111111 ');
        $contact->setWhenToCall('werktags 8–18 Uhr');
        $contact->setRelation('  ');
        $contact->setVisibility(EmergencyVisibility::Admins);
        $contact->setShowOnLockscreen(false);
        $contact->setSort(3);
        $place = new Poi('Hausarztpraxis', 'arzt');
        $entityManager->persist($contact);
        $entityManager->persist($place);
        $entityManager->flush();
        $entityManager->clear();

        $loaded = $entityManager->find(EmergencyContact::class, $contact->getId());
        self::assertNotNull($loaded);
        self::assertSame(EmergencyCategory::Arzt, $loaded->getCategory());
        self::assertSame('0931 / 1111111', $loaded->getPhone());
        self::assertSame('09311111111', EmergencyContact::dialable($loaded->getPhone()));
        self::assertNull($loaded->getRelation());
        self::assertTrue($loaded->isAdminOnly());
        self::assertFalse($loaded->isShowOnLockscreen());
        self::assertSame(3, $loaded->getSort());

        $loadedPlace = $entityManager->find(Poi::class, $place->getId());
        self::assertNotNull($loadedPlace);
        self::assertTrue($place->getPublicId()->equals($loadedPlace->getPublicId()));
        self::assertSame('arzt', $loadedPlace->getCategory());
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
