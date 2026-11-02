<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Device;
use App\Enum\DeviceMode;
use App\Enum\FontSize;
use App\Enum\UserRole;
use App\Service\Tablet\DeviceCookie;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The team tablet (konzept-12): a paired device sees its team view without login – only in the home network,
 * only while paired, never "Verwaltung" or "Mein Konto".
 */
final class TeamTabletTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;
    use TabletTrait;

    public function testPairedTabletShowsTheTeamViewWithoutLogin(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Heute');
        self::assertSelectorTextContains('main', 'Team-Tablet „Flur“');
        self::assertSelectorExists('a.hc-emergency-button[href="/notfall"]');
        self::assertSelectorTextContains('main', 'Admin-Anmeldung');
        // Mode "Team": no personal login offered; no account links in the top bar
        self::assertSelectorTextNotContains('main', 'Persönlich anmelden');
        self::assertSelectorNotExists('a[href="/konto"]');
        self::assertSelectorNotExists('form[action="/abmelden"]');
        self::assertSelectorExists('body[data-controller="screensaver"][data-screensaver-seconds-value="300"]');
    }

    public function testWithoutCookieNothingIsShown(): void
    {
        $client = $this->startClient();

        $client->request('GET', '/');

        self::assertResponseRedirects('http://localhost/anmelden');
    }

    public function testUnpairedTabletIsToldAtOnce(): void
    {
        $client = $this->startClient();
        $device = $this->pairTablet($client);
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $this->entityManager()->find(Device::class, $device->getId())?->revoke(new \DateTimeImmutable());
        $this->entityManager()->flush();
        $client->request('GET', '/');

        self::assertResponseRedirects('http://localhost/anmelden');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Dieses Tablet ist nicht mehr gekoppelt.');
        self::assertNull($client->getCookieJar()->get(DeviceCookie::NAME), 'The worthless cookie is removed.');
    }

    public function testUnknownTokenIsRemoved(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, token: 'erster-token');
        $client->getCookieJar()->set(new Cookie(DeviceCookie::NAME, 'falscher-token', null, '/', 'localhost'));

        $client->request('GET', '/');

        self::assertResponseRedirects('http://localhost/anmelden');
        self::assertNull($client->getCookieJar()->get(DeviceCookie::NAME));
    }

    /**
     * @return iterable<string, array{array<string, string>}>
     */
    public static function outsideRequests(): iterable
    {
        yield 'andere IP-Adresse' => [['REMOTE_ADDR' => '203.0.113.5']];
        yield 'Tunnel (Cloudflare)' => [['HTTP_CF_CONNECTING_IP' => '192.168.1.5', 'HTTP_CF_RAY' => '8a1b2c3d4e5f-FRA']];
        yield 'Weiterleitung von außen' => [['HTTP_X_FORWARDED_FOR' => '203.0.113.5']];
    }

    /**
     * @param array<string, string> $server
     */
    #[DataProvider('outsideRequests')]
    public function testTabletTokenFromOutsideIsRejected(array $server): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        self::fromOutside($client, $server);

        $client->request('GET', '/');

        self::assertResponseRedirects('http://localhost/anmelden');
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Die Team-Ansicht gibt es aber nur im WLAN zu Hause.');
        self::assertNotNull($client->getCookieJar()->get(DeviceCookie::NAME), 'The cookie stays: at home the tablet works again.');
    }

    public function testRunningTabletSessionEndsWhenTheRequestComesFromOutside(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        self::fromOutside($client);
        $client->request('GET', '/');

        self::assertResponseRedirects('/anmelden');
    }

    public function testTabletSessionWithoutItsCookieEnds(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        // E.g. the session cookie copied to another browser
        $client->getCookieJar()->expire(DeviceCookie::NAME, '/', 'localhost');
        $client->request('GET', '/');

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseRedirects('http://localhost/anmelden');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function personalPages(): iterable
    {
        yield 'Verwaltung' => ['/verwaltung'];
        yield 'Geräte' => ['/verwaltung/geraete'];
        yield 'Mein Konto' => ['/konto'];
        yield 'Passwort' => ['/konto/passwort'];
        yield 'Zweiter Schritt' => ['/konto/zwei-faktor'];
        yield 'Datenschutz' => ['/konto/datenschutz'];
        yield 'PIN ändern' => ['/konto/pin'];
    }

    #[DataProvider('personalPages')]
    public function testTeamTabletReachesNoPersonalOrAdminPage(string $path): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);

        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(403);
    }

    public function testModePersoenlichStartsWithTheNameTiles(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);

        $client->request('GET', '/');

        self::assertResponseRedirects('/tablet/personen');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Wer bist du?');
        self::assertSelectorTextNotContains('main', 'Zur Team-Ansicht');
    }

    public function testModeBeidesOffersThePersonalLogin(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Beides);

        $client->request('GET', '/');

        self::assertSelectorExists('main a[href="/tablet/personen"]');
    }

    public function testModeTeamOffersNoNameTiles(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);

        $client->request('GET', '/tablet/personen');

        self::assertResponseStatusCodeSame(403);
    }

    public function testFontSizeOfTheTabletIsUsed(): void
    {
        $client = $this->startClient();
        $device = $this->pairTablet($client);
        $device->setFontSize(FontSize::SehrGross);
        $this->entityManager()->flush();

        $client->request('GET', '/');

        self::assertSelectorExists('html[data-font="sehr_gross"]');
    }

    public function testLastSeenIsNotedAtMostEveryFiveMinutes(): void
    {
        $client = $this->startClient();
        $device = $this->pairTablet($client);
        self::mockTime('2026-11-02 10:00:00 UTC');
        $client->request('GET', '/');
        self::mockTime('2026-11-02 10:03:00 UTC');
        $client->request('GET', '/');

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(Device::class, $device->getId());
        self::assertSame('2026-11-02 10:00', $reloaded?->getLastSeenAt()?->format('Y-m-d H:i'));
        $cookie = $client->getResponse()->headers->getCookies();
        self::assertSame([], $cookie, 'The cookie is only renewed together with "last seen".');
    }

    public function testManyWrongPasswordsAtTheTabletDoNotUnpairIt(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        // 25 wrong logins from one address block further logins from it for 15 minutes …
        foreach (range(1, 26) as $try) {
            $client->request('POST', '/anmelden', ['_username' => 'person'.$try, '_password' => 'falsch', '_csrf_token' => 'falsch']);
        }
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Zu viele');

        // … but not the tablet itself, also in a new session (e.g. after a PIN login has ended)
        $client->getCookieJar()->clear();
        $client->getCookieJar()->set(new Cookie(DeviceCookie::NAME, self::TABLET_TOKEN, null, '/', 'localhost'));
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Team-Tablet „Flur“');
        self::assertNotNull($client->getCookieJar()->get(DeviceCookie::NAME));
    }

    public function testPasswordTriesAtTheTabletStillCountOncePerTry(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $this->createPerson(UserRole::Angehoerige, 'papa');

        // Each try in a new session: the tablet logs itself in on the way – the password try counts all the same
        foreach (range(1, 6) as $try) {
            $client->getCookieJar()->clear();
            $client->getCookieJar()->set(new Cookie(DeviceCookie::NAME, self::TABLET_TOKEN, null, '/', 'localhost'));
            $client->request('POST', '/anmelden', ['_username' => 'papa', '_password' => 'falsch', '_csrf_token' => 'falsch']);
            $client->followRedirect();
            self::assertSame(6 === $try, str_contains($client->getCrawler()->filter('main')->text(), 'Zu viele'), \sprintf('Try %d', $try));
        }
    }

    public function testLoginFormAtTheTabletStartsEmpty(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);

        // The first request of the tablet's session logs the tablet in on the way – its name must not show up here
        $crawler = $client->request('GET', '/anmelden');

        self::assertSame('', $crawler->filter('#username')->attr('value'));
    }

    public function testAdminLoginAtTheTabletIsPossible(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $this->createPerson(UserRole::Angehoerige, 'papa');

        $client->request('GET', '/anmelden');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Admin-Anmeldung am Tablet');
        self::assertSelectorTextContains('main', 'Bitte das Passwort nicht im Browser speichern.');
        $client->submitForm('Anmelden', ['_username' => 'papa', '_password' => self::PASSWORD]);
        $client->followRedirect();

        self::assertSelectorTextContains('h1', 'Heute');
        self::assertSelectorTextContains('main', 'Hallo Papa');
        self::assertSelectorExists('body[data-controller="tablet-session"][data-tablet-session-seconds-value="300"]');
    }
}
