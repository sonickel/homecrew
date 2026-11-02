<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Enum\Feature;
use App\Enum\UserRole;
use App\Security\UserChecker;
use App\Service\Feature\FeatureAvailability;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class UserCheckerAndFeaturesTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testPersonsWhoLeftCannotLogInFromTheNextDay(): void
    {
        $checker = static::getContainer()->get(UserChecker::class);
        $anna = new AppUser(UserRole::Assistenz, 'anna', 'Anna', new \DateTimeImmutable('2026-01-01'));
        (new \ReflectionProperty(AppUser::class, 'activeUntil'))->setValue($anna, new \DateTimeImmutable('2026-11-30'));

        // 30 November, 23:30 in Germany = 22:30 UTC: still her last day
        self::mockTime('2026-11-30 22:30:00 UTC');
        $checker->checkPreAuth($anna);

        self::mockTime('2026-11-30 23:30:00 UTC');
        static::getContainer()->get('services_resetter')->reset();
        $this->expectException(CustomUserMessageAccountStatusException::class);
        $this->expectExceptionMessage(UserChecker::BLOCKED_MESSAGE);
        $checker->checkPreAuth($anna);
    }

    public function testExtensionsAreInvisibleUntilSetUpAndWorking(): void
    {
        $features = static::getContainer()->get(FeatureAvailability::class);
        self::assertFalse($features->isAvailable(Feature::Email));
        self::assertFalse($features->isAvailable('ki'));

        $connection = static::getContainer()->get(Connection::class);
        $connection->update('app_setting', ['mail_enabled' => 1, 'smtp_host' => 'mail.example.org', 'smtp_port' => 587, 'mail_from_address' => 'homecrew@example.org', 'mail_status' => 'fehler'], ['id' => 1]);
        static::getContainer()->get('doctrine')->getManager()->clear();
        self::assertFalse($features->isAvailable(Feature::Email), 'Fehler = unsichtbar');

        $connection->update('app_setting', ['mail_status' => 'ok'], ['id' => 1]);
        static::getContainer()->get('doctrine')->getManager()->clear();
        self::assertTrue($features->isAvailable(Feature::Email));
    }
}
