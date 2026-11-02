<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\DeviceMode;
use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Personal logins at a paired tablet end early and lead back to the tablet's start page (konzept-12 §5):
 * full login ("Admin-Anmeldung") after 5 minutes, PIN login after the "Auto-Sperre" setting (default 3 minutes).
 */
final class TabletIdleTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;
    use TabletTrait;

    protected function tearDown(): void
    {
        $this->restoreTwoFactorSwitch();
        parent::tearDown();
    }

    public function testAdminLoginAtTheTabletEndsAfterFiveMinutes(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $this->requireTwoFactorForAdmins(false);
        $admin = $this->createPerson(UserRole::Angehoerige, 'schwester', admin: true);
        self::mockTime('2026-11-02 10:00:00 UTC');
        $this->loginWithPassword($client, $admin->getUsername());
        $client->request('GET', '/verwaltung');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('script[data-hc-tablet-logout="300"]');

        self::mockTime('2026-11-02 10:04:59 UTC');
        $client->request('GET', '/');
        self::assertSelectorTextContains('main', 'Hallo Schwester');

        self::mockTime('2026-11-02 10:10:00 UTC');
        $client->request('GET', '/');
        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Team-Tablet „Flur“');
    }

    public function testPinLoginEndsAfterTheAutoLockTime(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Beides);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, '482913');
        self::mockTime('2026-11-02 10:00:00 UTC');
        $this->loginWithPin($client, $anna, '482913');
        $client->followRedirect();
        self::assertSelectorExists('body[data-controller="tablet-session"][data-tablet-session-seconds-value="180"]');

        self::mockTime('2026-11-02 10:03:01 UTC');
        $client->request('GET', '/konto');

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Team-Tablet „Flur“');
    }

    public function testLogoutAtTheTabletLeadsBackToTheTeamView(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $this->loginWithPassword($client, $this->createPerson(UserRole::Angehoerige, 'papa')->getUsername());
        $client->followRedirect();

        $client->submitForm('Abmelden');

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Team-Tablet „Flur“');
    }

    public function testOnThePhoneTheEightHoursStay(): void
    {
        $client = $this->startClient();
        self::mockTime('2026-11-02 10:00:00 UTC');
        $this->loginWithPassword($client, $this->createPerson(UserRole::Angehoerige, 'papa')->getUsername());
        $client->followRedirect();
        self::assertSelectorNotExists('body[data-controller]');

        self::mockTime('2026-11-02 11:00:00 UTC');
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Hallo Papa');
    }
}
