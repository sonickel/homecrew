<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Verwaltung → Personen (konzept-11 §2, plan M3): list, create, edit with the rights rules.
 */
final class PersonAdminTest extends WebTestCase
{
    use PeopleTrait;

    public function testOnlyAdminsReachThePersons(): void
    {
        $client = $this->startClient();
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');

        $client->request('GET', '/verwaltung/personen');
        self::assertResponseRedirects('http://localhost/anmelden');

        foreach ([$papa, $anna] as $person) {
            $client->loginUser($person);
            foreach (['/verwaltung/personen', '/verwaltung/personen/new', '/verwaltung/personen/'.$anna->getId().'/zugang'] as $path) {
                $client->request('GET', $path);
                self::assertResponseStatusCodeSame(403, $person->getUsername().' '.$path);
            }
        }
    }

    public function testListShowsRoleAccessAndStatusInWords(): void
    {
        $client = $this->startClient();
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $this->createPerson(UserRole::Assistenz, 'ben', password: null);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/verwaltung/personen');

        self::assertResponseIsSuccessful();
        $benRow = $crawler->filter('tr')->reduce(static fn ($row): bool => str_contains($row->text(), 'ben'));
        self::assertStringContainsString('Assistenz', $benRow->text());
        self::assertStringContainsString('Kein eigener Zugang (Team-Tablet)', $benRow->text());
        self::assertStringContainsString('Aktiv', $benRow->text());
        self::assertSelectorExists('a[href$="/zugang"]');
    }

    public function testPersonWithoutAccessCannotLogInAnywhere(): void
    {
        $client = $this->startClient();
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $client->loginUser($admin);

        $client->request('GET', '/verwaltung/personen/new');
        $client->submitForm('Anlegen', [
            'AppUser[displayName]' => 'Ben',
            'AppUser[username]' => 'Ben',
            'AppUser[role]' => 'assistenz',
            'AppUser[activeFrom]' => '2026-11-01',
        ]);

        $ben = $this->reload('ben');
        self::assertResponseRedirects('/verwaltung/personen/'.$ben->getId().'/zugang');
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-info', 'arbeitet nur am Team-Tablet');
        self::assertSame('2026-11-01', $ben->getActiveFrom()->format('Y-m-d'));
        self::assertFalse($ben->hasPassword());
        self::assertSame(['person.anlegen'], $this->loggedActions());

        // Nobody can log in as Ben: no password, no passkey, no PIN
        $client->restart();
        $this->loginWithPassword($client, 'ben', '');
        self::assertResponseRedirects('/anmelden');
        $this->loginWithPassword($client, 'ben', 'irgendwas-langes-123');
        self::assertResponseRedirects('/anmelden');
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/anmelden');
    }

    public function testAssistantCannotBecomeAdminAndManagerIsNoChoice(): void
    {
        $client = $this->startClient();
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $client->loginUser($admin);

        $client->request('GET', '/verwaltung/personen/'.$anna->getId().'/edit');
        $client->submitForm('Speichern', ['AppUser[admin]' => '1']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.invalid-feedback', 'Assistenzkräfte können keine Admins sein.');
        self::assertFalse($this->reload('anna')->isAdmin());

        $client->request('GET', '/verwaltung/personen/new');
        $form = $client->getCrawler()->selectButton('Anlegen')->form();
        $values = $form->getPhpValues();
        $values['AppUser']['displayName'] = 'Zweiter';
        $values['AppUser']['username'] = 'zweiter';
        $values['AppUser']['role'] = 'manager';
        $client->request('POST', $form->getUri(), $values);
        self::assertResponseStatusCodeSame(422);
        self::assertNull($this->entityManager()->getRepository(AppUser::class)->findOneBy(['username' => 'zweiter']));
    }

    public function testOtherAdminsCannotChangeTheManager(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max');
        $sister = $this->createAdminWithTwoFactor(UserRole::Angehoerige, 'schwester');
        $client->loginUser($sister);

        $client->request('GET', '/verwaltung/personen/'.$max->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/verwaltung/personen/'.$max->getId().'/zugang');
        self::assertResponseStatusCodeSame(403);
        $client->request('POST', '/verwaltung/personen/'.$max->getId().'/zugang/zwei-faktor-zuruecksetzen');
        self::assertResponseStatusCodeSame(403);

        // The list shows the manager without "Bearbeiten" and "Zugang"
        $crawler = $client->request('GET', '/verwaltung/personen');
        self::assertCount(0, $crawler->filter('a[href="/verwaltung/personen/'.$max->getId().'/edit"]'));
        self::assertCount(0, $crawler->filter('a[href="/verwaltung/personen/'.$max->getId().'/zugang"]'));
    }

    public function testOwnRoleAndAdminRightAreFixed(): void
    {
        $client = $this->startClient();
        $sister = $this->createAdminWithTwoFactor(UserRole::Angehoerige, 'schwester');
        $client->loginUser($sister);

        $crawler = $client->request('GET', '/verwaltung/personen/'.$sister->getId().'/edit');
        self::assertSame('disabled', $crawler->filter('select[name="AppUser[role]"]')->attr('disabled'));
        self::assertSame('disabled', $crawler->filter('input[name="AppUser[admin]"]')->attr('disabled'));

        // A forged request cannot take the own admin right away either
        $form = $crawler->selectButton('Speichern')->form();
        $values = $form->getPhpValues();
        $values['AppUser']['role'] = 'assistenz';
        unset($values['AppUser']['admin']);
        $client->request('POST', $form->getUri(), $values);
        $reloaded = $this->reload('schwester');
        self::assertTrue($reloaded->isAdmin());
        self::assertSame(UserRole::Angehoerige, $reloaded->getRole());

        // Own access is managed in "Mein Konto"
        $client->request('GET', '/verwaltung/personen/'.$sister->getId().'/zugang');
        self::assertResponseStatusCodeSame(403);
    }

    public function testChangesAreLoggedWithOldAndNewValue(): void
    {
        $client = $this->startClient();
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $client->loginUser($admin);

        $client->request('GET', '/verwaltung/personen/'.$papa->getId().'/edit');
        $client->submitForm('Speichern', ['AppUser[displayName]' => 'Vati', 'AppUser[admin]' => '1']);
        self::assertResponseRedirects('/verwaltung/personen');

        $crawler = $client->request('GET', '/verwaltung/protokoll');
        $text = $crawler->filter('table')->text();
        self::assertStringContainsString('Person geändert', $text);
        self::assertStringContainsString('Name: Papa → Vati', $text);
        self::assertStringContainsString('Admin-Recht: nein → ja', $text);
        self::assertStringContainsString('Person: Vati', $text);
    }

    private function reload(string $username): AppUser
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(AppUser::class)->findOneBy(['username' => $username]) ?? throw new \LogicException('missing '.$username);
    }
}
