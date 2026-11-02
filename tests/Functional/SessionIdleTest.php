<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Personal logins end after 8 hours without a request (SESSION_IDLE_MINUTES, decision 01.11.).
 */
final class SessionIdleTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;

    public function testSessionFilesLiveLongerThanTheIdleTime(): void
    {
        static::bootKernel();
        /** @var array{gc_maxlifetime: int} $options */
        $options = static::getContainer()->getParameter('session.storage.options');

        self::assertGreaterThan((int) $_SERVER['SESSION_IDLE_MINUTES'] * 60, $options['gc_maxlifetime']);
    }

    public function testActivityKeepsTheLogin(): void
    {
        $client = $this->startClient();
        self::mockTime('2026-11-02 08:00:00 UTC');
        $this->loginWithPassword($client, $this->createPerson(UserRole::Angehoerige, 'papa')->getUsername());

        foreach (['15:00', '22:59'] as $time) {
            self::mockTime('2026-11-02 '.$time.':00 UTC');
            $client->request('GET', '/');
            self::assertResponseIsSuccessful($time);
        }
    }

    public function testEightHoursWithoutRequestEndTheLogin(): void
    {
        $client = $this->startClient();
        self::mockTime('2026-11-02 08:00:00 UTC');
        $this->loginWithPassword($client, $this->createPerson(UserRole::Angehoerige, 'papa')->getUsername());
        $client->request('GET', '/');

        self::mockTime('2026-11-02 16:00:01 UTC');
        $client->request('GET', '/');

        self::assertResponseRedirects('/anmelden?grund=inaktiv');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Du warst 8 Stunden nicht aktiv und wurdest abgemeldet.');
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/anmelden');
    }
}
