<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Login with username + password (konzept-1 §5) – without e-mail functions (M13).
 */
final class LoginTest extends WebTestCase
{
    use PeopleTrait;

    public function testPagesNeedALogin(): void
    {
        $client = $this->startClient();

        foreach (['/', '/verwaltung', '/konto/passwort', '/konto/zwei-faktor'] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('http://localhost/anmelden', 302, $path);
        }
    }

    public function testLoginPageOffersNoEmailFunctions(): void
    {
        $client = $this->startClient();
        $crawler = $client->request('GET', '/anmelden');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Anmelden');
        self::assertSelectorExists('input[name="_username"][autocomplete="username webauthn"]');
        self::assertSelectorExists('input[name="_password"][type="password"]');
        // Passkey login stays hidden until the browser proves support
        self::assertSelectorExists('section.hc-passkey[hidden][data-controller="passkey"]');
        $text = $crawler->filter('main')->text();
        self::assertStringNotContainsString('vergessen', $text);
        self::assertStringNotContainsString('E-Mail', $text);
        self::assertStringContainsString("'nonce-", (string) $client->getResponse()->headers->get('Content-Security-Policy'));
    }

    public function testCorrectPasswordLeadsToToday(): void
    {
        $client = $this->startClient();
        $this->createPerson(UserRole::Angehoerige, 'papa');

        $this->loginWithPassword($client, 'papa');

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextSame('h1', 'Heute');
        self::assertSame(['anmeldung.erfolg'], $this->loggedActions());
    }

    public function testWrongPasswordIsExplainedAndLoggedForKnownPersonsOnly(): void
    {
        $client = $this->startClient();
        $this->createPerson(UserRole::Angehoerige, 'papa');

        $this->loginWithPassword($client, 'papa', 'falsch-falsch-falsch');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert--error', 'Benutzername oder Passwort stimmt nicht.');
        // No template source on the page (a broken edit once left "|trans(…)" visible after the message)
        self::assertStringNotContainsString('|trans', (string) $client->getCrawler()->filter('main')->text());
        self::assertInputValueSame('_username', 'papa');

        // An unknown username is not logged – it could be a password typed into the wrong field
        $this->loginWithPassword($client, 'Sonnenblume-Fahrrad-42');
        self::assertSame(['anmeldung.fehlschlag'], $this->loggedActions());
    }

    public function testRepeatedFailuresLockTheLoginWithWaitingTime(): void
    {
        $client = $this->startClient();
        $this->createPerson(UserRole::Angehoerige, 'papa');

        for ($i = 0; $i < 5; ++$i) {
            $this->loginWithPassword($client, 'papa', 'falsch-'.$i);
        }
        $this->loginWithPassword($client, 'papa');
        $client->followRedirect();

        // The sliding window rounds up: 15 or 16 minutes
        self::assertMatchesRegularExpression('/^Zu viele Fehlversuche. Bitte warte 1[56] Minuten und versuch es dann noch einmal.$/', $client->getCrawler()->filter('.hc-alert--error')->text());
        self::assertContains('anmeldung.gesperrt', $this->loggedActions());
    }

    public function testBlockedPersonCannotLogIn(): void
    {
        $client = $this->startClient();
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $this->entityManager()->getConnection()->update('app_user', ['disabled_at' => '2026-10-01 00:00:00'], ['id' => $papa->getId()]);

        $this->loginWithPassword($client, 'papa');
        $client->followRedirect();

        self::assertSelectorTextContains('.hc-alert--error', 'Dein Zugang ist gesperrt. Bitte wende dich an einen Admin.');
    }

    public function testPersonWithoutPasswordCannotLogIn(): void
    {
        $client = $this->startClient();
        $this->createPerson(UserRole::Assistenz, 'ben', password: null);

        $this->loginWithPassword($client, 'ben', '');
        $client->followRedirect();

        self::assertSelectorExists('.hc-alert--error');
        self::assertSame('/anmelden', parse_url((string) $client->getRequest()->getUri(), \PHP_URL_PATH));
    }

    public function testLogoutNeedsPostWithToken(): void
    {
        $client = $this->startClient();
        $this->createPerson(UserRole::Angehoerige, 'papa');
        $this->loginWithPassword($client, 'papa');
        $client->followRedirect();

        $client->submitForm('Abmelden');
        self::assertResponseRedirects('http://localhost/anmelden');

        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/anmelden');
    }
}
