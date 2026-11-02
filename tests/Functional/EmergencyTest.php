<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\EmergencyContact;
use App\Enum\DeviceMode;
use App\Enum\EmergencyCategory;
use App\Enum\EmergencyVisibility;
use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Notfall (konzept-3 §2): 112 and contacts for everybody incl. the tablet without PIN; "nur Admins" only with admin
 * rights (never by PIN or at the team tablet); the screensaver shows only contacts for everybody marked for it.
 */
final class EmergencyTest extends WebTestCase
{
    use PeopleTrait;
    use TabletTrait;

    private const string PIN = '482913';

    private function addContacts(): void
    {
        foreach ([
            ['Schwester', EmergencyCategory::Angehoerige, EmergencyVisibility::Alle, true, '+49 931 0000001'],
            ['Hausarztpraxis', EmergencyCategory::Arzt, EmergencyVisibility::Alle, false, '0931 / 0000002'],
            ['Hausverwaltung', EmergencyCategory::Dienstleister, EmergencyVisibility::Admins, true, '0931 0000004'],
        ] as [$name, $category, $visibility, $lockScreen, $phone]) {
            $contact = new EmergencyContact();
            $contact->setName($name);
            $contact->setCategory($category);
            $contact->setVisibility($visibility);
            $contact->setShowOnLockscreen($lockScreen);
            $contact->setPhone($phone);
            $this->entityManager()->persist($contact);
        }
        $this->entityManager()->flush();
    }

    /**
     * @return list<string>
     */
    private static function contactNames(KernelBrowser $client): array
    {
        return $client->getCrawler()->filter('.hc-contact__name')->each(static fn ($node): string => trim((string) preg_replace('/\s*\(.*$|\s*nur für Admins sichtbar/', '', $node->text())));
    }

    public function testAdminSeesAllContactsWithCallLinks(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createAdminWithTwoFactor(UserRole::Manager, 'max'));
        $this->addContacts();

        $client->request('GET', '/notfall');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a.hc-emergency-button[href="tel:112"]');
        self::assertSame(['Schwester', 'Hausarztpraxis', 'Hausverwaltung'], self::contactNames($client));
        self::assertSelectorExists('a[href="tel:+499310000001"]');
        self::assertSelectorExists('a[href="tel:09310000002"]');
        self::assertSelectorTextContains('main', 'nur für Admins sichtbar');
    }

    public function testPersonsWithoutAdminRightsSeeOnlyContactsForEverybody(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));
        $this->addContacts();

        $client->request('GET', '/notfall');

        self::assertSame(['Schwester', 'Hausarztpraxis'], self::contactNames($client));
        self::assertSelectorTextNotContains('main', 'Hausverwaltung');
    }

    public function testAdminLoggedInByPinSeesNoAdminContacts(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $sister = $this->createPerson(UserRole::Angehoerige, 'schwester', admin: true);
        $this->givePin($sister, self::PIN);
        $this->addContacts();
        $this->loginWithPin($client, $sister, self::PIN);

        $client->request('GET', '/notfall');

        self::assertSame(['Schwester', 'Hausarztpraxis'], self::contactNames($client));
    }

    public function testTeamTabletSeesContactsForEverybody(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $this->addContacts();

        $client->request('GET', '/notfall');

        self::assertSame(['Schwester', 'Hausarztpraxis'], self::contactNames($client));
    }

    public function testTabletInModePersoenlichReachesTheEmergencyPageWithoutPin(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);

        $client->request('GET', '/tablet/personen');
        self::assertSelectorExists('a.hc-emergency-button[href="/notfall"]');
        $client->request('GET', '/notfall');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Noch keine Notfallkontakte. Ein Admin trägt sie');
    }

    public function testEmergencyPageIsReachableDuringTheFirstSteps(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null, privacyRead: false);
        $this->givePin($anna, self::PIN, mustChange: true);
        $this->loginWithPin($client, $anna, self::PIN);
        $client->followRedirect();
        self::assertResponseRedirects('/konto/pin');

        $client->request('GET', '/notfall');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Notfall');
    }

    public function testScreensaverShowsOnlyContactsForEverybodyMarkedForIt(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $this->addContacts();

        $client->request('GET', '/');

        $names = $client->getCrawler()->filter('.hc-screensaver .hc-screensaver__name')->each(static fn ($node): string => $node->text());
        self::assertSame(['Schwester'], $names);
        self::assertSelectorExists('.hc-screensaver[hidden] a[href="tel:112"]');
    }

    public function testPersonsSeeNoScreensaver(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));
        $this->addContacts();

        $client->request('GET', '/');

        self::assertSelectorNotExists('.hc-screensaver');
        self::assertSelectorExists('a.hc-emergency-button[href="/notfall"]');
    }

    public function testWithoutLoginNoEmergencyData(): void
    {
        $client = $this->startClient();

        $client->request('GET', '/notfall');
        self::assertResponseRedirects('http://localhost/anmelden');
        $client->followRedirect();
        self::assertSelectorNotExists('a.hc-emergency-button');
    }

    public function testAdminMaintainsContactsInTheAdministration(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createAdminWithTwoFactor(UserRole::Manager, 'max'));

        $client->request('GET', '/verwaltung/notfall/new');
        $client->submitForm('Anlegen', [
            'EmergencyContact[category]' => EmergencyCategory::Notdienst->value,
            'EmergencyContact[name]' => 'Sanitätshaus-Notdienst',
            'EmergencyContact[phone]' => 'bitte anrufen',
            'EmergencyContact[visibility]' => EmergencyVisibility::Alle->value,
            'EmergencyContact[sort]' => '0',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Bitte gib eine Telefonnummer mit Ziffern ein');

        $client->submitForm('Anlegen', [
            'EmergencyContact[category]' => EmergencyCategory::Notdienst->value,
            'EmergencyContact[name]' => 'Sanitätshaus-Notdienst',
            'EmergencyContact[phone]' => '0800 0000003',
            'EmergencyContact[visibility]' => EmergencyVisibility::Alle->value,
            'EmergencyContact[sort]' => '0',
        ]);
        self::assertResponseRedirects();
        $client->request('GET', '/verwaltung/notfall');
        self::assertSelectorTextContains('table', 'Notdienste');
        self::assertSelectorTextContains('table', 'Alle');
    }

    public function testNonAdminCannotMaintainContacts(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));

        $client->request('GET', '/verwaltung/notfall');

        self::assertResponseStatusCodeSame(403);
    }
}
