<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\Notification;
use App\Enum\AssigneeRole;
use App\Enum\NotificationEvent;
use App\Enum\UserRole;
use App\Service\Notification\NotificationRecipients;
use App\Service\Notification\Notifier;
use App\Service\Task\Assignment;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Who gets notifications (konzept-13 §7, decisions 04.11.): whoever is responsible – a person, a role, for
 * "wer gerade da ist" the assistants – but only persons who can log in, are not locked and have not left; never the
 * person who caused it. Notifications are deleted after retention_notification_days.
 */
final class NotifierTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testOnlyReachablePersonsAreTold(): void
    {
        self::mockTime('2026-11-04 09:00:00 UTC');
        $anna = $this->person(UserRole::Assistenz, 'anna');
        $this->person(UserRole::Assistenz, 'ben', password: false);
        $locked = $this->person(UserRole::Assistenz, 'carla');
        $locked->lock(new \DateTimeImmutable('2026-11-01 10:00:00'));
        $left = $this->person(UserRole::Assistenz, 'dora');
        $left->leave(new \DateTimeImmutable('2026-11-03'));
        $pinOnly = $this->person(UserRole::Assistenz, 'emil', password: false);
        $pinOnly->changePin('not-a-real-hash', false);
        $pinOnly->enablePinLogin(true);
        $this->entityManager()->flush();

        $recipients = static::getContainer()->get(NotificationRecipients::class);

        self::assertSame(['anna', 'emil'], self::names($recipients->responsibleFor(Assignment::role(AssigneeRole::Assistenz))));
        self::assertSame(['anna', 'emil'], self::names($recipients->responsibleFor(Assignment::present())));
        self::assertSame(['anna'], self::names($recipients->responsibleFor(Assignment::person($anna))));
        self::assertSame([], $recipients->responsibleFor(Assignment::role(AssigneeRole::Angehoerige)));
    }

    public function testTheActorAndPersonsWithoutTheEventAreLeftOut(): void
    {
        self::mockTime('2026-11-04 09:00:00 UTC');
        $this->entityManager()->getConnection()->update('app_setting', ['retention_notification_days' => 30], ['id' => 1]);
        $max = $this->person(UserRole::Manager, 'max', admin: true);
        $sister = $this->person(UserRole::Angehoerige, 'schwester', admin: true);
        $papa = $this->person(UserRole::Angehoerige, 'papa');
        $this->entityManager()->flush();

        $count = static::getContainer()->get(Notifier::class)->notify(NotificationEvent::Meldung, [$max, $sister, $papa, $sister], 'Neue Meldung: Seife leer', '/meldungen', $max, true);

        self::assertSame(1, $count);
        $notifications = $this->entityManager()->getRepository(Notification::class)->findAll();
        self::assertCount(1, $notifications);
        self::assertSame([$sister, true, '2026-12-04'], [$notifications[0]->getUser(), $notifications[0]->isUrgent(), $notifications[0]->getDeleteAfter()->format('Y-m-d')]);
    }

    /**
     * @param list<AppUser> $persons
     *
     * @return list<string>
     */
    private static function names(array $persons): array
    {
        return array_map(static fn (AppUser $person): string => $person->getUsername(), $persons);
    }

    private function person(UserRole $role, string $username, bool $admin = false, bool $password = true): AppUser
    {
        $person = new AppUser($role, $username, ucfirst($username), new \DateTimeImmutable('2026-01-01'), $admin);
        if ($password) {
            $person->changePassword('not-a-real-hash');
        }
        $this->entityManager()->persist($person);

        return $person;
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
