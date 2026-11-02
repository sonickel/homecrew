<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Second login step after the password: code from the app or recovery code; passkey persons need a recovery code.
 */
final class TwoFactorLoginTest extends WebTestCase
{
    use PeopleTrait;

    public function testAppCodeCompletesTheLogin(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max');
        $secret = $this->enableAuthenticatorApp($max);
        $this->giveRecoveryCodes($max);

        $this->loginWithPassword($client, 'max');
        // The password is accepted, the target page then asks for the second step
        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertResponseRedirects('http://localhost/anmelden/code');

        // Without the second step nothing else is reachable
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/anmelden/code');
        $client->request('GET', '/verwaltung');
        self::assertResponseRedirects('http://localhost/anmelden/code');

        $client->request('GET', '/anmelden/code');
        self::assertSelectorTextContains('.hc-page-header__intro', 'Authenticator-App');
        self::assertSelectorNotExists('[data-controller="passkey"]');
        self::assertStringNotContainsString('E-Mail', $client->getCrawler()->filter('main')->text());

        $this->submitSecondStep($client, self::currentCode($secret));
        // Back to the page asked for during the second step
        self::assertResponseRedirects('http://localhost/verwaltung');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        $client->request('GET', '/');
        self::assertSelectorTextSame('h1', 'Heute');
        self::assertSelectorExists('a.hc-topbar__link[href="/verwaltung"]');

        $details = $this->entityManager()->getConnection()->fetchOne('SELECT details FROM audit_log WHERE action = ?', ['anmeldung.erfolg']);
        self::assertSame(['methode' => 'passwort', 'zweiter_schritt' => true], json_decode((string) $details, true));
    }

    public function testWrongCodeIsRejected(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max');
        $secret = $this->enableAuthenticatorApp($max);
        $this->giveRecoveryCodes($max);

        $this->loginWithPassword($client, 'max');
        $this->submitSecondStep($client, '000000' === self::currentCode($secret) ? '111111' : '000000');
        $client->followRedirect();

        self::assertSelectorTextContains('.hc-alert--error', 'Der Code stimmt nicht.');
        self::assertContains('anmeldung.fehlschlag', $this->loggedActions());
        $client->request('GET', '/');
        self::assertResponseRedirects('http://localhost/anmelden/code');
    }

    public function testEveryRecoveryCodeWorksExactlyOnce(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max');
        $this->enableAuthenticatorApp($max);
        $codes = $this->giveRecoveryCodes($max);

        $this->loginWithPassword($client, 'max');
        // Lower case and without dash is fine as well
        $this->submitSecondStep($client, strtolower(str_replace('-', '', $codes[0])));
        self::assertResponseRedirects('/');
        self::assertContains('zweifaktor.ersatzcode', $this->loggedActions());

        $client->followRedirect();
        $client->submitForm('Abmelden');
        $this->loginWithPassword($client, 'max');
        $this->submitSecondStep($client, $codes[0]);
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert--error', 'Der Code stimmt nicht.');

        $this->submitSecondStep($client, $codes[1]);
        self::assertResponseRedirects('/');
        $this->entityManager()->clear();
        self::assertSame(8, $this->entityManager()->find(AppUser::class, $max->getId())?->countRecoveryCodes());
    }

    public function testPasswordAloneIsNotEnoughForPasskeyPersons(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max');
        $max->addPasskeyAsTwoFactor();
        $codes = $this->giveRecoveryCodes($max);

        $this->loginWithPassword($client, 'max');
        $client->followRedirect();
        self::assertResponseRedirects('http://localhost/anmelden/code');
        $client->request('GET', '/anmelden/code');
        self::assertSelectorTextContains('.hc-page-header__intro', 'Melde dich mit deinem Passkey an oder gib einen Wiederherstellungscode ein.');
        self::assertSelectorExists('section.hc-passkey[data-controller="passkey"]');
        self::assertSelectorTextSame('label[for="_auth_code"]', 'Wiederherstellungscode');

        $this->submitSecondStep($client, '123456');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert--error', 'Der Code stimmt nicht.');

        $this->submitSecondStep($client, $codes[3]);
        self::assertResponseRedirects('/');
    }

    public function testTooManyWrongCodesLockTheSecondStep(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max');
        $this->enableAuthenticatorApp($max);
        $codes = $this->giveRecoveryCodes($max);

        $this->loginWithPassword($client, 'max');
        for ($i = 0; $i < 5; ++$i) {
            $this->submitSecondStep($client, 'AAAAA-AAAA'.$i);
        }
        // Even a valid recovery code is refused during the lock
        $this->submitSecondStep($client, $codes[0]);
        $client->followRedirect();

        self::assertContains('anmeldung.gesperrt', $this->loggedActions());
        self::assertMatchesRegularExpression('/^Zu viele Fehlversuche. Bitte warte 1[56] Minuten und versuch es dann noch einmal.$/', $client->getCrawler()->filter('.hc-alert--error')->text());
        $this->entityManager()->clear();
        self::assertSame(10, $this->entityManager()->find(AppUser::class, $max->getId())?->countRecoveryCodes());
    }

    public function testCancelEndsTheHalfLogin(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max');
        $this->enableAuthenticatorApp($max);
        $this->giveRecoveryCodes($max);

        $this->loginWithPassword($client, 'max');
        $client->request('GET', '/anmelden/code');
        $client->submitForm('Abbrechen');
        $client->request('GET', '/');

        self::assertResponseRedirects('http://localhost/anmelden');
    }
}
