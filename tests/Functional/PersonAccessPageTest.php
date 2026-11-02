<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Service\Account\PersonAccess;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Verwaltung → Personen → Zugang (plan M3): start password, start PIN, invitation, lock, leaving.
 */
final class PersonAccessPageTest extends WebTestCase
{
    use PeopleTrait;

    private AppUser $admin;

    public function testRelativeAdminWithStartPasswordMustChangeItReadThePrivacyNoticeAndSetUpASecondFactor(): void
    {
        $client = $this->adminClient();
        $sister = $this->createPerson(UserRole::Angehoerige, 'schwester', admin: true, password: null, privacyRead: false);

        $crawler = $this->submitAction($client, $sister, 'Start-Passwort erzeugen');
        self::assertResponseIsSuccessful();
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSelectorExists('meta[name="turbo-cache-control"][content="no-cache"]');
        $password = $this->cardValue($crawler, 'Start-Passwort');
        self::assertMatchesRegularExpression('/^[a-z2-9]{4}-[a-z2-9]{4}-[a-z2-9]{4}$/', $password);
        self::assertSelectorTextContains('.hc-access-card', 'schwester');
        self::assertSelectorTextContains('.hc-access-card', 'http://localhost/anmelden');

        // Nothing readable is kept: the log only says that it happened
        self::assertStringNotContainsString($password, $this->wholeLog());

        $client->restart();
        $this->loginWithPassword($client, 'schwester', $password);
        $client->followRedirect();
        self::assertResponseRedirects('/konto/passwort', 303);
        $client->request('GET', '/konto/passwort');
        $client->submitForm('Passwort speichern', ['change_password[password][first]' => 'Gartenzaun-Leuchtturm-7', 'change_password[password][second]' => 'Gartenzaun-Leuchtturm-7']);
        self::assertResponseRedirects('/konto/datenschutz', 303);
        $client->request('GET', '/');
        self::assertResponseRedirects('/konto/datenschutz', 303);
        $client->request('GET', '/konto/datenschutz');
        self::assertSelectorTextContains('h1', 'Datenschutz');
        $client->submitForm('Gelesen, weiter');
        self::assertResponseRedirects('/konto/zwei-faktor', 303);
    }

    public function testStartPinCardAndPinLogin(): void
    {
        $client = $this->adminClient();
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);

        $crawler = $this->submitAction($client, $anna, 'Start-PIN erzeugen');
        $pin = $this->cardValue($crawler, 'Start-PIN');
        self::assertMatchesRegularExpression('/^\d{6}$/', $pin);
        self::assertSelectorTextNotContains('.hc-access-card', 'Benutzername');

        $client->request('GET', '/verwaltung/personen/'.$anna->getId().'/zugang');
        $client->submitForm('PIN-Anmeldung einschalten');
        self::assertResponseRedirects('/verwaltung/personen/'.$anna->getId().'/zugang', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'PIN-Anmeldung: eingeschaltet.');
        self::assertSame(['zugang.startpin', 'zugang.pin_anmeldung'], $this->loggedActions());
        self::assertStringNotContainsString($pin, $this->wholeLog());
    }

    public function testInvitationCardCarriesTheCodeOnlyAfterTheHash(): void
    {
        $client = $this->adminClient();
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa', password: null);

        $crawler = $this->submitAction($client, $papa, 'Einladung (QR-Code) erstellen');

        $code = str_replace(' ', '', $this->cardValue($crawler, 'Einladungscode'));
        self::assertMatchesRegularExpression('/^[A-HJKMNP-Z2-9]{24}$/', $code);
        self::assertStringStartsWith('data:image/svg+xml;base64,', (string) $crawler->filter('img.hc-access-card__qr')->attr('src'));
        self::assertSelectorTextContains('.hc-access-card', 'http://localhost/einladung');
        self::assertStringNotContainsString($code, $this->wholeLog());
        self::assertSame(hash('sha256', $code), $this->entityManager()->getConnection()->fetchOne('SELECT token_hash FROM invitation'));

        $client->request('GET', '/verwaltung/personen/'.$papa->getId().'/zugang');
        self::assertSelectorTextContains('body', 'Einladung offen bis');
    }

    public function testActionsNeedTheTokenAndTheRight(): void
    {
        $client = $this->adminClient();
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');

        $client->request('POST', '/verwaltung/personen/'.$anna->getId().'/zugang/sperren', ['_token' => 'falsch']);
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));
        $client->request('POST', '/verwaltung/personen/'.$anna->getId().'/zugang/start-passwort');
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], $this->loggedActions());
    }

    public function testLockEndsTheRunningSessionAndUnlockAllowsTheLoginAgain(): void
    {
        $client = $this->startClient();
        $this->admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $this->createPerson(UserRole::Assistenz, 'anna');
        $this->loginWithPassword($client, 'anna');
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        // The client booted a new kernel: work with fresh copies
        [$anna, $max] = $this->reloadAll('anna', 'max');
        static::getContainer()->get(PersonAccess::class)->lock($anna, $max);

        $client->request('GET', '/');
        self::assertResponseRedirects('/anmelden?grund=gesperrt');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Dein Zugang ist gesperrt.');
        $this->loginWithPassword($client, 'anna');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Dein Zugang ist gesperrt.');

        [$anna, $max] = $this->reloadAll('anna', 'max');
        static::getContainer()->get(PersonAccess::class)->unlock($anna, $max);
        $this->loginWithPassword($client, 'anna');
        self::assertResponseRedirects('/');
    }

    public function testLeavingRemovesTheAccessAndKeepsThePersonAsLeft(): void
    {
        $client = $this->adminClient();
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');

        $crawler = $client->request('GET', '/verwaltung/personen/'.$anna->getId().'/zugang');
        $form = $crawler->selectButton('Austritt eintragen')->form();
        $client->submit($form, ['letzter_tag' => '2099-01-01']);
        $client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'nicht im Voraus');
        self::assertFalse($this->reload('anna')->hasLeft());

        $client->submit($form, ['letzter_tag' => '2026-10-31']);
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Ausgetreten');
        $reloaded = $this->reload('anna');
        self::assertTrue($reloaded->hasLeft());
        self::assertFalse($reloaded->hasPassword());

        $crawler = $client->request('GET', '/verwaltung/personen');
        $annaRow = $crawler->filter('tr')->reduce(static fn ($row): bool => str_contains($row->text(), 'anna'));
        self::assertStringContainsString('Ausgetreten (letzter Arbeitstag 31.10.2026)', $annaRow->text());

        $client->restart();
        $this->loginWithPassword($client, 'anna');
        self::assertResponseRedirects('/anmelden');
    }

    public function testDisplayNamesAreEscapedInTheMessagesOfTheAdministration(): void
    {
        $client = $this->adminClient();
        $person = $this->createPerson(UserRole::Assistenz, 'fett');
        $person->setDisplayName('<b>Fett</b>');
        $this->entityManager()->flush();

        $client->request('GET', '/verwaltung/personen/'.$person->getId().'/zugang');
        $client->submitForm('Jetzt sperren');
        $client->followRedirect();

        $html = (string) $client->getResponse()->getContent();
        self::assertStringContainsString('&lt;b&gt;Fett&lt;/b&gt; ist gesperrt', $html);
        self::assertStringNotContainsString('<b>Fett</b>', $html);
    }

    public function testImpossibleDatesAreRejected(): void
    {
        $client = $this->adminClient();
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');

        $crawler = $client->request('GET', '/verwaltung/personen/'.$anna->getId().'/zugang');
        $client->submit($crawler->selectButton('Austritt eintragen')->form(), ['letzter_tag' => '2026-02-31']);
        $client->followRedirect();

        self::assertSelectorTextContains('.alert-danger', 'Bitte gib den letzten Arbeitstag an.');
        self::assertFalse($this->reload('anna')->hasLeft());
    }

    public function testLeavingEndsARunningSessionAtOnce(): void
    {
        $client = $this->startClient();
        $this->createPerson(UserRole::Assistenz, 'anna');
        $this->loginWithPassword($client, 'anna');
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        // Last working day today – also a session that has no password (the tablet PIN in M4) must end
        [$anna] = $this->reloadAll('anna');
        $anna->leave(new \DateTimeImmutable('today'));
        $this->entityManager()->flush();

        $client->request('GET', '/');
        self::assertResponseRedirects('/anmelden?grund=gesperrt');
    }

    private function adminClient(): KernelBrowser
    {
        $client = $this->startClient();
        $this->admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $client->loginUser($this->admin);

        return $client;
    }

    private function submitAction(KernelBrowser $client, AppUser $person, string $button): Crawler
    {
        $client->request('GET', '/verwaltung/personen/'.$person->getId().'/zugang');
        self::assertResponseIsSuccessful();

        return $client->submitForm($button);
    }

    private function cardValue(Crawler $crawler, string $label): string
    {
        $value = null;
        $crawler->filter('.hc-access-card__facts dt')->each(static function (Crawler $term) use ($label, &$value): void {
            if ($label === $term->text()) {
                $value = $term->nextAll()->first()->text();
            }
        });

        return $value ?? throw new \LogicException('No "'.$label.'" on the card.');
    }

    private function wholeLog(): string
    {
        return (string) json_encode($this->entityManager()->getConnection()->fetchAllAssociative('SELECT * FROM audit_log'));
    }

    private function reload(string $username): AppUser
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(AppUser::class)->findOneBy(['username' => $username]) ?? throw new \LogicException('missing '.$username);
    }

    /**
     * @return list<AppUser> managed by the same, freshly cleared entity manager
     */
    private function reloadAll(string ...$usernames): array
    {
        $this->entityManager()->clear();
        $repository = $this->entityManager()->getRepository(AppUser::class);

        return array_map(static fn (string $username): AppUser => $repository->findOneBy(['username' => $username]) ?? throw new \LogicException('missing '.$username), array_values($usernames));
    }
}
