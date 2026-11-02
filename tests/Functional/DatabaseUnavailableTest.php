<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Service\Tablet\DeviceCookie;
use DAMA\DoctrineTestBundle\Doctrine\DBAL\StaticDriver;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

/**
 * If the database is down (e.g. MariaDB restarting on the Pi), visitors still get German pages
 * instead of a blank one – so neither the layout nor the error pages may need the database.
 */
final class DatabaseUnavailableTest extends WebTestCase
{
    private string $databaseUrl;

    protected function setUp(): void
    {
        $this->databaseUrl = (string) $_SERVER['DATABASE_URL'];
        // Nothing listens on port 1
        $unreachable = (string) preg_replace('~@[^/]+/~', '@127.0.0.1:1/', $this->databaseUrl, 1);
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $unreachable;
        // dama keeps one open connection per connection name and would reuse it despite the new URL
        StaticDriver::setKeepStaticConnections(false);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        StaticDriver::setKeepStaticConnections(true);
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $this->databaseUrl;
    }

    public function testLoginPageRendersWithFallbackName(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/anmelden');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('title', 'Anmelden – HomeCrew');
    }

    public function testPageThatNeedsTheDatabaseShowsTheGermanErrorPage(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/anmelden');
        // Checking the password needs the database
        $client->submitForm('Anmelden', ['_username' => 'max', '_password' => 'egal-egal-egal']);

        self::assertResponseStatusCodeSame(500);
        self::assertSelectorTextContains('h1', 'Etwas ist schiefgelaufen');
        self::assertSelectorExists('a.hc-button[href="/"]');
    }

    public function testTabletWithoutDatabaseGetsGermanPages(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->getCookieJar()->set(new Cookie(DeviceCookie::NAME, 'token-eines-tablets', null, '/', 'localhost'));

        $client->request('GET', '/anmelden');
        self::assertResponseIsSuccessful();
        // A restart of the database (e.g. an update on the Pi) must not unpair the tablet
        self::assertSelectorTextNotContains('main', 'nicht mehr gekoppelt');
        self::assertNotNull($client->getCookieJar()->get(DeviceCookie::NAME));
        $client->request('GET', '/gibt-es-nicht');
        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Seite nicht gefunden');
    }

    public function testNotFoundPageRendersWithoutDatabase(): void
    {
        $client = static::createClient(['debug' => false]);
        $client->request('GET', '/gibt-es-nicht');

        self::assertResponseStatusCodeSame(404);
        self::assertSelectorTextContains('h1', 'Seite nicht gefunden');
    }
}
