<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\DeviceMode;
use App\Enum\FontSize;
use App\Enum\UserRole;
use App\Service\Tablet\DeviceCookie;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Verwaltung → Geräte (konzept-11 §2): pair a tablet by code, change it, unpair it – each step in the log.
 */
final class PairingTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;
    use TabletTrait;

    private ?AppUser $admin = null;

    public function testAdminPairsATabletWithTheCode(): void
    {
        $adminClient = $this->loggedInAdmin();
        $code = $this->pairingCode($adminClient, 'Küche');
        self::assertSelectorExists('img.hc-admin-qr[src^="data:image/svg+xml"]');
        self::assertStringContainsString('no-store', (string) $adminClient->getResponse()->headers->get('Cache-Control'));

        $tablet = $this->tabletBrowser();
        $tablet->request('GET', '/koppeln');
        $tablet->submitForm('Koppeln', ['code' => strtolower($code)]);

        self::assertResponseRedirects('/');
        $cookie = $tablet->getCookieJar()->get(DeviceCookie::NAME);
        self::assertNotNull($cookie);
        self::assertTrue($cookie->isHttpOnly());
        $tablet->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Dieses Tablet ist jetzt gekoppelt: „Küche“.');
        self::assertSelectorTextContains('main', 'Team-Tablet „Küche“');

        $device = $this->entityManager()->getRepository(Device::class)->findOneBy(['name' => 'Küche']);
        self::assertNotNull($device);
        self::assertTrue($device->hasTokenHash(DeviceCookie::hash($cookie->getValue())));
        self::assertSame('max', $device->getPairedBy()?->getUsername());
        self::assertSame(['geraet.kopplungscode', 'geraet.koppeln'], $this->loggedActions());
        // Neither the code nor the token are written anywhere
        self::assertStringNotContainsString($code, (string) $this->entityManager()->getConnection()->fetchOne('SELECT GROUP_CONCAT(COALESCE(details, \'\')) FROM audit_log'));

        // Pairing again from the same browser is refused
        $tablet->request('GET', '/koppeln');
        self::assertSelectorTextContains('main', 'Dieses Tablet ist schon gekoppelt: „Küche“.');
    }

    public function testCodeWorksOnlyOnce(): void
    {
        $code = $this->pairingCode($this->loggedInAdmin(), 'Küche');
        $first = $this->tabletBrowser();
        $first->request('GET', '/koppeln');
        $first->submitForm('Koppeln', ['code' => $code]);
        self::assertResponseRedirects('/');

        $first->getCookieJar()->clear();
        $first->request('GET', '/koppeln');
        $first->submitForm('Koppeln', ['code' => $code]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-alert', 'Diesen Code kennt HomeCrew nicht');
    }

    public function testCodeExpiresAfterTenMinutes(): void
    {
        self::mockTime('2026-11-02 10:00:00 UTC');
        $code = $this->pairingCode($this->loggedInAdmin(), 'Küche');
        self::assertSelectorTextContains('body', 'Gilt bis 11:10 Uhr');

        $tablet = $this->tabletBrowser();
        self::mockTime('2026-11-02 10:10:01 UTC');
        $tablet->request('GET', '/koppeln');
        $tablet->submitForm('Koppeln', ['code' => $code]);

        self::assertResponseStatusCodeSame(422);
        self::assertNull($tablet->getCookieJar()->get(DeviceCookie::NAME));
    }

    public function testPairingOnlyInTheHomeNetwork(): void
    {
        $code = $this->pairingCode($this->loggedInAdmin(), 'Küche');
        $tablet = $this->tabletBrowser();
        self::fromOutside($tablet, ['HTTP_CF_CONNECTING_IP' => '192.168.1.20']);

        $tablet->request('GET', '/koppeln');
        $tablet->submitForm('Koppeln', ['code' => $code]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-alert', 'Koppeln geht nur im WLAN zu Hause.');
    }

    public function testNonAdminCannotCreateACode(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));

        $client->request('GET', '/verwaltung/geraete/koppeln');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminChangesAndUnpairsATablet(): void
    {
        $client = $this->loggedInAdmin();
        $device = $this->pairTablet($client);

        $crawler = $client->request('GET', '/verwaltung/geraete');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'Flur');
        self::assertSelectorTextContains('table', 'gekoppelt');

        $client->request('GET', '/verwaltung/geraete/'.$device->getId().'/edit');
        $client->submitForm('Speichern', [
            'Device[name]' => 'Flur unten',
            'Device[mode]' => DeviceMode::Beides->value,
            'Device[dimAfterMinutes]' => '10',
            'Device[fontSize]' => FontSize::Gross->value,
        ]);
        self::assertResponseRedirects();

        $client->request('GET', '/verwaltung/geraete/'.$device->getId().'/entkoppeln');
        self::assertSelectorTextContains('body', 'Das Tablet zeigt danach sofort nichts mehr an');
        $client->submitForm('Jetzt entkoppeln');
        self::assertResponseRedirects('/verwaltung/geraete');

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(Device::class, $device->getId());
        self::assertNotNull($reloaded);
        self::assertSame('Flur unten', $reloaded->getName());
        self::assertSame(DeviceMode::Beides, $reloaded->getMode());
        self::assertSame(600, $reloaded->getDimAfterSeconds());
        self::assertSame(FontSize::Gross, $reloaded->getFontSize());
        self::assertTrue($reloaded->isRevoked());
        self::assertSame(['geraet.aendern', 'geraet.entkoppeln'], $this->loggedActions());
        $details = (string) $this->entityManager()->getConnection()->fetchOne("SELECT details FROM audit_log WHERE action = 'geraet.aendern'");
        self::assertStringContainsString('"mode":{"alt":"team","neu":"beides"}', $details);
    }

    /**
     * A second browser for the tablet – without emptying the cache, where the pairing code lives.
     */
    private function tabletBrowser(): KernelBrowser
    {
        self::ensureKernelShutdown();

        return static::createClient();
    }

    private function loggedInAdmin(): KernelBrowser
    {
        $client = $this->startClient();
        $this->admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $client->loginUser($this->admin);

        return $client;
    }

    /**
     * @return string the code as shown, e.g. "ABCD EFGH JKMN"
     */
    private function pairingCode(KernelBrowser $client, string $name): string
    {
        $client->request('GET', '/verwaltung/geraete/koppeln');
        $client->submitForm('Kopplungscode zeigen', ['name' => $name]);
        self::assertResponseIsSuccessful();

        return $client->getCrawler()->filter('.hc-admin-code')->text();
    }
}
