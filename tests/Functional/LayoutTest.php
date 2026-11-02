<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Layout rules every page follows, checked on the login page (reachable without login).
 */
final class LayoutTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public function testPageRendersInGerman(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/anmelden');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="de"][data-font="normal"][data-contrast="normal"]');
        self::assertCount(1, $crawler->filter('h1'));
        self::assertSelectorExists('a.hc-skip-link[href="#inhalt"]');
        self::assertSelectorExists('main#inhalt');
        // Not logged in: no account links
        self::assertSelectorNotExists('.hc-topbar__nav');
    }

    public function testTitleAndIntroUseTheHouseholdName(): void
    {
        $client = static::createClient();
        static::getContainer()->get(Connection::class)->update('app_setting', ['household_name' => 'Haus am See'], ['id' => 1]);

        $client->request('GET', '/anmelden');

        self::assertSelectorTextSame('title', 'Anmelden – Haus am See');
        self::assertSelectorTextSame('.hc-page-header__intro', 'Haus am See');
    }

    public function testStrictContentSecurityPolicyIsSent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/anmelden');

        $csp = (string) $client->getResponse()->headers->get('Content-Security-Policy');
        self::assertStringContainsString("default-src 'self'", $csp);
        self::assertStringContainsString("frame-ancestors 'none'", $csp);
        self::assertStringNotContainsString('unsafe-inline', $csp);
        self::assertStringNotContainsString('unsafe-eval', $csp);

        // Inline scripts carry the nonce announced in the header
        $nonce = $crawler->filter('script[type="importmap"]')->attr('nonce');
        self::assertNotEmpty($nonce);
        self::assertStringContainsString("'nonce-".$nonce."'", $csp);
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
    }

    public function testPageReferencesNoExternalResources(): void
    {
        $client = static::createClient();
        $client->request('GET', '/anmelden');

        // Only exception: the SVG namespace of inline icons, which is no request
        $html = str_replace('xmlns="http://www.w3.org/2000/svg"', '', (string) $client->getResponse()->getContent());
        self::assertDoesNotMatchRegularExpression('~https?://~i', $html);
        self::assertDoesNotMatchRegularExpression('~(?:src|href|action)\s*=\s*["\']?//~i', $html);
    }

    public function testImportMapContainsNoDataModules(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/anmelden');

        // CSS imported from JavaScript would appear as "data:" module, which the CSP blocks
        $importMap = $crawler->filter('script[type="importmap"]')->text();
        self::assertStringNotContainsString('data:', $importMap);
        self::assertStringContainsString('@simplewebauthn/browser', $importMap);
        self::assertCount(1, $crawler->filter('link[rel="stylesheet"][href*="/assets/styles/app-"]'));
    }

    public function testDateInTopBarUsesClockAndHouseholdTimeZone(): void
    {
        $client = static::createClient();
        // 23:30 UTC is already the next day in Europe/Berlin
        self::mockTime('2026-11-03 23:30:00 UTC');
        $client->request('GET', '/anmelden');

        self::assertSelectorTextSame('.hc-topbar__date', 'Mittwoch, 4. November');
        self::assertSelectorExists('.hc-topbar__date time[datetime="2026-11-04"]');

        // The time zone comes from the settings
        static::getContainer()->get(Connection::class)->update('app_setting', ['timezone' => 'America/New_York'], ['id' => 1]);
        $client->request('GET', '/anmelden');
        self::assertSelectorTextSame('.hc-topbar__date', 'Dienstag, 3. November');
    }

    public function testFontIsServedLocally(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/anmelden');

        $fontsCss = $crawler->filter('link[rel="stylesheet"][href*="/assets/styles/fonts-"]')->attr('href');
        self::assertNotNull($fontsCss);

        $client->request('GET', $fontsCss);
        self::assertResponseIsSuccessful();
        $css = (string) $client->getInternalResponse()->getContent();
        // AssetMapper rewrites url() to a relative, versioned path below /assets/fonts/
        self::assertMatchesRegularExpression('~url\("\.\./fonts/atkinson-hyperlegible-next-latin-400-normal-[^"]+\.woff2"\)~', $css);
        self::assertStringNotContainsString('http', $css);
    }
}
