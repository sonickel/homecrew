<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppSetting;
use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\DeviceMode;
use App\Enum\UserRole;
use App\Service\Account\PersonAccess;
use App\Service\Tablet\DeviceCookie;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Name tiles and PIN at the tablet (konzept-1 §5, konzept-12 §2): only persons with PIN login, three tries,
 * then 5 minutes pause, never admin rights; "Mein Konto" only with PIN, display and privacy notice.
 */
final class PinLoginTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;
    use TabletTrait;

    private const string PIN = '482913';

    public function testNameTilesShowOnlyPersonsWithPinLogin(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Beides);
        $this->givePin($this->createPerson(UserRole::Assistenz, 'anna', password: null), self::PIN);
        $this->createPerson(UserRole::Assistenz, 'ben', password: null);
        $left = $this->createPerson(UserRole::Assistenz, 'carla', password: null);
        $this->givePin($left, self::PIN);
        $left->leave(new \DateTimeImmutable('2026-01-31'));
        $left->removeAccess();
        $this->entityManager()->flush();

        $crawler = $client->request('GET', '/tablet/personen');

        self::assertResponseIsSuccessful();
        self::assertSame(['Anna'], $crawler->filter('.hc-name-tile')->each(static fn ($tile): string => $tile->text()));
        self::assertSelectorExists('.hc-name-tile.hc-color-neutral');
    }

    public function testCorrectPinLogsInWithoutAdminRights(): void
    {
        $client = $this->startClient();
        $device = $this->pairTablet($client, DeviceMode::Persoenlich);
        $sister = $this->createPerson(UserRole::Angehoerige, 'schwester', admin: true);
        $this->enableAuthenticatorApp($sister);
        $this->giveRecoveryCodes($sister);
        $this->givePin($sister, self::PIN);

        $this->loginWithPin($client, $sister, self::PIN);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Hallo Schwester');
        // No second factor for a PIN login – and no admin rights either
        self::assertSelectorNotExists('a[href="/verwaltung"]');
        $client->request('GET', '/verwaltung');
        self::assertResponseStatusCodeSame(403);
        self::assertContains('anmeldung.erfolg', $this->loggedActions());
        self::assertSame($device->getId(), (int) $this->entityManager()->getConnection()->fetchOne("SELECT device_id FROM audit_log WHERE action = 'anmeldung.erfolg'"));
        self::assertSame('tablet', $this->entityManager()->getConnection()->fetchOne("SELECT via FROM audit_log WHERE action = 'anmeldung.erfolg'"));
    }

    public function testWrongPinCountsDownThenPauses(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, self::PIN);

        $this->loginWithPin($client, $anna, '111222');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Die PIN stimmt nicht. Noch 2 Versuche, dann 5 Minuten Pause.');
        $this->loginWithPin($client, $anna, '111222');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Die PIN stimmt nicht. Noch 1 Versuch, dann 5 Minuten Pause.');
        $this->loginWithPin($client, $anna, '111222');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Die PIN stimmt nicht. Bitte warte 5 Minuten');

        // During the pause even the right PIN does not work
        $this->loginWithPin($client, $anna, self::PIN);
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Zu viele falsche Versuche.');
        $client->request('GET', '/konto');
        self::assertResponseStatusCodeSame(403);
        self::assertSame(['anmeldung.fehlschlag', 'anmeldung.fehlschlag', 'anmeldung.gesperrt'], $this->loggedActions());
    }

    public function testAfterThePauseThePinWorksAgain(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, self::PIN);
        self::mockTime('2026-11-02 10:00:00 UTC');
        foreach (range(1, 3) as $try) {
            $this->loginWithPin($client, $anna, '111222');
        }

        self::mockTime('2026-11-02 10:05:01 UTC');
        $this->loginWithPin($client, $anna, self::PIN);

        self::assertResponseRedirects('/');
    }

    public function testStartPinMustBeReplacedFirst(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null, privacyRead: false);
        $this->givePin($anna, self::PIN, mustChange: true);

        $this->loginWithPin($client, $anna, self::PIN);
        $client->followRedirect();
        self::assertResponseRedirects('/konto/pin');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Neue PIN');
        self::assertSelectorNotExists('input[name="change_pin[current]"]');

        $client->submitForm('PIN speichern', ['change_pin[pin][first]' => '123456', 'change_pin[pin][second]' => '123456']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Bitte keine einfache Zahlenfolge');
        $client->submitForm('PIN speichern', ['change_pin[pin][first]' => self::PIN, 'change_pin[pin][second]' => self::PIN]);
        self::assertSelectorTextContains('main', 'Die neue PIN muss sich von der bisherigen unterscheiden.');

        $client->submitForm('PIN speichern', ['change_pin[pin][first]' => '730514', 'change_pin[pin][second]' => '730514']);
        // Then the privacy notice, then "Heute" – still logged in with the new PIN
        self::assertResponseRedirects('/konto/datenschutz');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertContains('zugang.pin_aendern', $this->loggedActions());
        $this->entityManager()->clear();
        self::assertFalse($this->entityManager()->find(AppUser::class, $anna->getId())?->isPinMustChange());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function loginSettingPages(): iterable
    {
        yield 'Passwort' => ['/konto/passwort'];
        yield 'Bestätigen' => ['/konto/bestaetigen'];
        yield 'Zweiter Schritt' => ['/konto/zwei-faktor'];
        yield 'Authenticator-App' => ['/konto/zwei-faktor/app'];
        yield 'Passkey hinzufügen' => ['/konto/passkeys/neu'];
        yield 'Wiederherstellungscodes' => ['/konto/zwei-faktor/codes'];
    }

    #[DataProvider('loginSettingPages')]
    public function testPinLoginCannotChangeLoginData(string $path): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $this->givePin($papa, self::PIN);
        $this->loginWithPin($client, $papa, self::PIN);

        $client->request('GET', $path);

        self::assertResponseStatusCodeSame(403);
    }

    public function testMyAccountAfterPinLoginOffersPinDisplayAndPrivacyOnly(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $this->givePin($papa, self::PIN);
        $this->loginWithPin($client, $papa, self::PIN);

        $client->request('GET', '/konto');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main a[href="/konto/pin"]');
        self::assertSelectorExists('main a[href="/konto/datenschutz"]');
        self::assertSelectorTextContains('main', 'Darstellung');
        self::assertSelectorNotExists('main a[href="/konto/bestaetigen"]');
        self::assertSelectorNotExists('main a[href="/konto/passkeys/neu"]');

        $client->request('GET', '/konto/pin');
        $client->submitForm('PIN speichern', [
            'change_pin[current]' => '000999',
            'change_pin[pin][first]' => '730514',
            'change_pin[pin][second]' => '730514',
        ]);
        self::assertSelectorTextContains('main', 'Die PIN stimmt nicht. Noch 2 Versuche');
        $client->submitForm('PIN speichern', [
            'change_pin[current]' => self::PIN,
            'change_pin[pin][first]' => '730514',
            'change_pin[pin][second]' => '730514',
        ]);
        self::assertResponseRedirects('/konto');
        // The own new PIN keeps the login
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testFullLoginCannotUseThePinPage(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));

        $client->request('GET', '/konto/pin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testNoPinLoginInModeTeam(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, self::PIN);

        $client->request('GET', '/tablet/personen/'.$anna->getPublicId());
        self::assertResponseStatusCodeSame(403);
    }

    public function testNoPinLoginWithoutPairedTablet(): void
    {
        $client = $this->startClient();
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, self::PIN);

        $client->request('GET', '/tablet/personen');
        self::assertResponseRedirects('http://localhost/anmelden');
        $client->request('POST', '/tablet/personen/'.$anna->getPublicId(), ['pin' => self::PIN]);
        self::assertResponseRedirects('/tablet/personen/'.$anna->getPublicId());
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/anmelden');
    }

    public function testOutsideTheHomeNetworkOnlyWhenTheSwitchIsOff(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Beides);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, self::PIN);
        self::fromOutside($client);

        $client->request('GET', '/tablet/personen');
        self::assertResponseRedirects('http://localhost/anmelden');

        $settings = $this->entityManager()->find(AppSetting::class, AppSetting::ID);
        $settings?->setPinLanOnly(false);
        $this->entityManager()->flush();

        $client->request('GET', '/anmelden');
        self::assertSelectorExists('main a[href="/tablet/personen"]');
        $this->loginWithPin($client, $anna, self::PIN);
        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Hallo Anna');
    }

    public function testPinLoginEndsWhenTheTabletIsUnpaired(): void
    {
        $client = $this->startClient();
        $device = $this->pairTablet($client, DeviceMode::Persoenlich);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, self::PIN);
        $this->loginWithPin($client, $anna, self::PIN);

        $this->entityManager()->find(Device::class, $device->getId())?->revoke(new \DateTimeImmutable());
        $this->entityManager()->flush();
        $client->request('GET', '/konto');

        self::assertResponseRedirects('/');
    }

    public function testPinLoginEndsWithoutTheTabletCookie(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, self::PIN);
        $this->loginWithPin($client, $anna, self::PIN);

        // E.g. the session cookie copied to another browser
        $client->getCookieJar()->expire(DeviceCookie::NAME, '/', 'localhost');
        $client->request('GET', '/konto');

        self::assertResponseRedirects('/');
        $client->request('GET', '/konto');
        self::assertResponseRedirects('http://localhost/anmelden');
    }

    public function testPinLoginEndsOutsideTheHomeNetwork(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, self::PIN);
        $this->loginWithPin($client, $anna, self::PIN);

        self::fromOutside($client);
        $client->request('GET', '/konto');

        self::assertResponseRedirects('/anmelden');
        $client->request('GET', '/konto');
        self::assertResponseRedirects('http://localhost/anmelden');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function adminChangesDuringAPinLogin(): iterable
    {
        // E.g. because the old PIN became known: whoever holds the running login must not set a PIN of their own
        yield 'Neue Start-PIN' => ['start-pin'];
        yield 'PIN-Anmeldung aus' => ['pin-aus'];
        yield 'Tablet auf Team' => ['team'];
    }

    #[DataProvider('adminChangesDuringAPinLogin')]
    public function testRunningPinLoginEndsWhenAnAdminTakesAwayItsBasis(string $change): void
    {
        $client = $this->startClient();
        $device = $this->pairTablet($client, DeviceMode::Beides);
        $manager = $this->createPerson(UserRole::Manager, 'max');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, self::PIN);
        $this->loginWithPin($client, $anna, self::PIN);
        $client->followRedirect();
        self::assertSelectorTextContains('main', 'Hallo Anna');

        $this->entityManager()->clear();
        $anna = $this->entityManager()->find(AppUser::class, $anna->getId());
        $manager = $this->entityManager()->find(AppUser::class, $manager->getId());
        self::assertNotNull($anna);
        self::assertNotNull($manager);
        $personAccess = static::getContainer()->get(PersonAccess::class);
        switch ($change) {
            case 'start-pin':
                $personAccess->setStartPin($anna, $manager);
                break;
            case 'pin-aus':
                $personAccess->setPinLogin($anna, false, $manager);
                break;
            default:
                // Directly: the audit listener for devices would use the person of the last request as actor
                $this->entityManager()->getConnection()->update('device', ['mode' => DeviceMode::Team->value], ['id' => $device->getId()]);
        }
        $this->entityManager()->flush();

        $client->request('GET', '/konto/pin');

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextNotContains('main', 'Hallo Anna');
    }

    public function testForeignFormCannotUseUpTheTries(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, self::PIN);

        $client->request('POST', '/tablet/personen/'.$anna->getPublicId(), ['pin' => '111222', '_token' => 'falsch']);

        self::assertResponseRedirects('/tablet/personen/'.$anna->getPublicId());
        self::assertSame([], $this->loggedActions());
    }
}
