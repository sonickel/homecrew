<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Security\TwoFactor\RecoveryCodeGenerator;
use App\Security\TwoFactor\RecoveryCodeManager;
use App\Service\Security\SecretBox;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class RecoveryCodesTest extends TestCase
{
    public function testTenDistinctReadableCodes(): void
    {
        $codes = (new RecoveryCodeGenerator())->generate();

        self::assertCount(10, array_unique($codes));
        foreach ($codes as $code) {
            self::assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}$/', $code);
        }
    }

    public function testOnlyKeyedHashesAreStoredAndEachCodeWorksOnce(): void
    {
        $manager = $this->manager('unit-test-master-key-0123456789abcdef');
        $person = new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        $manager->replace($person, ['ABCDE-FGHJK', 'LMNPQ-RSTUV']);

        $stored = (array) (new \ReflectionProperty(AppUser::class, 'twofaBackupCodes'))->getValue($person);
        self::assertNotContains(hash('sha256', 'ABCDEFGHJK'), $stored, 'no plain hash without key');

        // Case, spaces and dash do not matter
        self::assertTrue($manager->isBackupCode($person, 'abcde fghjk'));
        $manager->invalidateBackupCode($person, 'abcde fghjk');
        self::assertFalse($manager->isBackupCode($person, 'ABCDE-FGHJK'));
        self::assertTrue($manager->isBackupCode($person, 'LMNPQRSTUV'));
        self::assertSame(1, $person->countRecoveryCodes());

        // Another server key does not recognise the codes
        self::assertFalse($this->manager('another-master-key-0123456789abcdefgh')->isBackupCode($person, 'LMNPQ-RSTUV'));
    }

    private function manager(string $masterKey): RecoveryCodeManager
    {
        return new RecoveryCodeManager(new SecretBox($masterKey), $this->createStub(EntityManagerInterface::class));
    }
}
