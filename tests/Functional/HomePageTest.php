<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

final class HomePageTest extends WebTestCase
{
    use ClockSensitiveTrait;

    public function testHomePageRendersInGerman(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('html[lang="de"][data-font="normal"][data-contrast="normal"]');
        self::assertCount(1, $crawler->filter('h1'));
        self::assertSelectorTextContains('h1', 'Willkommen bei HomeCrew');
        self::assertSelectorExists('a.hc-skip-link[href="#inhalt"]');
        self::assertSelectorExists('main#inhalt');
    }

    public function testStrictContentSecurityPolicyIsSent(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

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
        $client->request('GET', '/');

        // Only exception: the SVG namespace of inline icons, which is no request
        $html = str_replace('xmlns="http://www.w3.org/2000/svg"', '', (string) $client->getResponse()->getContent());
        self::assertDoesNotMatchRegularExpression('~https?://~i', $html);
        self::assertDoesNotMatchRegularExpression('~(?:src|href|action)\s*=\s*["\']?//~i', $html);
    }

    public function testImportMapContainsNoDataModules(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        // CSS imported from JavaScript would appear as "data:" module, which the CSP blocks
        $importMap = $crawler->filter('script[type="importmap"]')->text();
        self::assertStringNotContainsString('data:', $importMap);
        self::assertCount(1, $crawler->filter('link[rel="stylesheet"][href*="/assets/styles/app-"]'));
    }

    public function testDateInTopBarUsesClockAndHouseholdTimeZone(): void
    {
        $client = static::createClient();
        // 23:30 UTC is already the next day in Europe/Berlin
        self::mockTime('2026-11-03 23:30:00 UTC');
        $client->request('GET', '/');

        self::assertSelectorTextSame('.hc-topbar__date', 'Mittwoch, 4. November');
        self::assertSelectorExists('.hc-topbar__date time[datetime="2026-11-04"]');
    }

    public function testFontIsServedLocally(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/');

        $baseCss = $crawler->filter('link[rel="stylesheet"][href*="/assets/styles/base-"]')->attr('href');
        self::assertNotNull($baseCss);

        $client->request('GET', $baseCss);
        self::assertResponseIsSuccessful();
        $css = (string) $client->getInternalResponse()->getContent();
        // AssetMapper rewrites url() to a relative, versioned path below /assets/fonts/
        self::assertMatchesRegularExpression('~url\("\.\./fonts/atkinson-hyperlegible-next-latin-400-normal-[^"]+\.woff2"\)~', $css);
        self::assertStringNotContainsString('http', $css);
    }
}
