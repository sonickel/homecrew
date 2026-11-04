<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Notification;

use App\Entity\AppUser;
use App\Enum\NotificationEvent;
use App\Enum\UserRole;
use App\Service\Notification\NotificationDefaults;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Defaults per role from konzept-13 §7.
 */
final class NotificationDefaultsTest extends TestCase
{
    /**
     * @return iterable<string, array{NotificationEvent, bool, bool, bool, bool}>
     */
    public static function table(): iterable
    {
        // event => manager, Angehörige with admin right, Angehörige, Assistenz
        yield 'neue Meldung' => [NotificationEvent::Meldung, true, true, false, false];
        yield 'Meldung erledigt' => [NotificationEvent::MeldungErledigt, false, false, false, false];
        yield 'Aufgabe für dich' => [NotificationEvent::Aufgabe, true, true, true, true];
        yield 'Dienst' => [NotificationEvent::Dienst, false, false, false, true];
        yield 'Wichtige Übergabe' => [NotificationEvent::UebergabeWichtig, true, true, true, true];
        yield 'Wartung überfällig' => [NotificationEvent::Wartung, true, true, false, false];
    }

    #[DataProvider('table')]
    public function testDefaultsFollowTheConcept(NotificationEvent $event, bool $manager, bool $adminRelative, bool $relative, bool $assistant): void
    {
        $defaults = new NotificationDefaults();
        $since = new \DateTimeImmutable('2026-01-01');

        self::assertSame($manager, $defaults->inApp($event, new AppUser(UserRole::Manager, 'max', 'Max', $since, isAdmin: true)));
        self::assertSame($adminRelative, $defaults->inApp($event, new AppUser(UserRole::Angehoerige, 'eva', 'Eva', $since, isAdmin: true)));
        self::assertSame($relative, $defaults->inApp($event, new AppUser(UserRole::Angehoerige, 'papa', 'Papa', $since)));
        self::assertSame($assistant, $defaults->inApp($event, new AppUser(UserRole::Assistenz, 'anna', 'Anna', $since)));
    }

    public function testEveryEventHasDefaults(): void
    {
        $person = new AppUser(UserRole::Assistenz, 'anna', 'Anna', new \DateTimeImmutable('2026-01-01'));
        foreach (NotificationEvent::cases() as $event) {
            (new NotificationDefaults())->inApp($event, $person);
        }

        $this->expectNotToPerformAssertions();
    }
}
