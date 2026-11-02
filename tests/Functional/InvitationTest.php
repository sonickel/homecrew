<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Service\Account\InvitationManager;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Redeeming an invitation (konzept-1 §4, plan M3): code → own password → logged in; used, expired and
 * unknown codes are rejected in plain words.
 */
final class InvitationTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;

    public function testInvitationIsRedeemedOnce(): void
    {
        $client = $this->startClient();
        $code = $this->invite('papa');

        // Typed by hand, with spaces and in lower case
        $this->enterCode($client, strtolower(implode(' ', str_split($code, 4))));
        self::assertResponseRedirects('/einladung/passwort', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Hallo Papa');
        self::assertSelectorTextContains('main', 'Dein Benutzername: papa');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));

        $client->submitForm('Passwort speichern', ['change_password[password][first]' => 'papa-Gartenzaun-7', 'change_password[password][second]' => 'papa-Gartenzaun-7']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-field__errors', 'Benutzernamen nicht enthalten');
        $client->submitForm('Passwort speichern', ['change_password[password][first]' => 'Gartenzaun-Leuchtturm-7', 'change_password[password][second]' => 'Gartenzaun-Leuchtturm-7']);

        // Logged in; the privacy notice is the next step
        self::assertResponseRedirects('/konto/datenschutz', 303);
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame(['einladung.erstellen', 'einladung.einloesen', 'anmeldung.erfolg'], $this->loggedActions());

        // A second time: already used
        $client->restart();
        $this->enterCode($client, $code);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-alert', 'schon benutzt');
    }

    public function testExpiredAndUnknownCodesAreExplained(): void
    {
        self::mockTime('2026-11-02 10:00:00 UTC');
        $client = $this->startClient();
        $code = $this->invite('papa');

        self::mockTime('2026-11-05 10:00:01 UTC');
        $this->enterCode($client, $code);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-alert', 'abgelaufen');

        $this->enterCode($client, 'ABCD EFGH');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-alert', 'gibt es nicht');
    }

    public function testPersonWithSecondFactorLogsInNormallyAfterwards(): void
    {
        $client = $this->startClient();
        $code = $this->invite('papa', password: 'Altes-Passwort-123');
        $this->enableAuthenticatorApp($this->person('papa'));

        $this->enterCode($client, $code);
        $client->followRedirect();
        $client->submitForm('Passwort speichern', ['change_password[password][first]' => 'Gartenzaun-Leuchtturm-7', 'change_password[password][second]' => 'Gartenzaun-Leuchtturm-7']);

        self::assertResponseRedirects('/anmelden', 303);
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/anmelden');
    }

    public function testTooManyAttemptsAreStopped(): void
    {
        $client = $this->startClient();

        for ($i = 0; $i < 10; ++$i) {
            $this->enterCode($client, 'FALSCH'.$i);
            self::assertSelectorTextContains('.hc-alert', 'gibt es nicht');
        }
        $this->enterCode($client, 'FALSCH');
        self::assertSelectorTextContains('.hc-alert', 'Zu viele Versuche');
    }

    public function testCodeFromTheQrCodeIsTakenFromBehindTheHash(): void
    {
        $client = $this->startClient();

        $client->request('GET', '/einladung');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[data-controller="fragment-code"] input[name="code"][data-fragment-code-target="code"]');
    }

    public function testLoggedInPersonsAreAskedToLogOutFirst(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'schwester'));

        $client->request('GET', '/einladung');

        self::assertSelectorTextContains('.hc-alert', 'schon angemeldet');
        self::assertSelectorNotExists('input[name="code"]');
    }

    private function invite(string $username, ?string $password = null): string
    {
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $person = $this->createPerson(UserRole::Angehoerige, $username, password: $password, privacyRead: false);

        return static::getContainer()->get(InvitationManager::class)->create($person, $admin)['code'];
    }

    private function enterCode(KernelBrowser $client, string $code): void
    {
        $client->request('GET', '/einladung');
        $client->submitForm('Weiter', ['code' => $code]);
    }

    private function person(string $username): AppUser
    {
        return $this->entityManager()->getRepository(AppUser::class)->findOneBy(['username' => $username]) ?? throw new \LogicException('missing '.$username);
    }
}
