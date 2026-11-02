<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Entity\WebauthnCredential;
use App\Enum\FontSize;
use App\Enum\TwoFactorMethod;
use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * "Mein Konto" (plan M3): display settings, fresh confirmation, password, second factor, passkeys, recovery codes.
 */
final class AccountTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;

    public function testEveryPersonWithOwnLoginHasAnAccountPage(): void
    {
        $client = $this->startClient();
        $client->request('GET', '/konto');
        self::assertResponseRedirects('http://localhost/anmelden');

        foreach ([[UserRole::Assistenz, 'anna'], [UserRole::Angehoerige, 'papa']] as [$role, $username]) {
            $client->loginUser($this->createPerson($role, $username));
            $client->request('GET', '/konto');
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('h1', 'Mein Konto');
        }
        self::assertSelectorExists('a.hc-topbar__link[href="/konto"]');
    }

    public function testFontSizeAndContrastWorkAtOnceAndStay(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Assistenz, 'anna'));

        $client->request('GET', '/konto');
        $client->submitForm('Darstellung speichern', [
            'display_settings[fontSize]' => FontSize::SehrGross->value,
            'display_settings[highContrast]' => '1',
        ]);
        self::assertResponseRedirects('/konto', 303);
        $client->followRedirect();

        self::assertSelectorExists('html[data-font="sehr_gross"][data-contrast="hoch"]');
        $client->request('GET', '/');
        self::assertSelectorExists('html[data-font="sehr_gross"][data-contrast="hoch"]');
        $anna = $this->reload('anna');
        self::assertSame(FontSize::SehrGross, $anna->getFontSize());
        self::assertTrue($anna->isHighContrast());
        self::assertSame([], $this->loggedActions(), 'Darstellung ist nicht protokollpflichtig.');
    }

    public function testChangesNeedAFreshConfirmationWithPasswordAndCode(): void
    {
        $client = $this->startClient();
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $secret = $this->enableAuthenticatorApp($papa);
        $this->giveRecoveryCodes($papa);
        $client->loginUser($papa);

        $client->request('GET', '/konto');
        self::assertSelectorNotExists('a[href="/konto/passwort"]');
        $client->clickLink('Anmeldung ändern');
        self::assertSelectorExists('input[name="code"]');

        $client->submitForm('Bestätigen', ['passwort' => self::PASSWORD, 'code' => '000000' === self::currentCode($secret) ? '111111' : '000000']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-alert', 'Passwort oder Code stimmen nicht');

        $client->submitForm('Bestätigen', ['passwort' => self::PASSWORD, 'code' => self::currentCode($secret)]);
        self::assertResponseRedirects('/konto', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Bestätigt');

        $client->clickLink('Passwort ändern');
        $client->submitForm('Passwort speichern', ['change_password[password][first]' => 'Gartenzaun-Leuchtturm-7', 'change_password[password][second]' => 'Gartenzaun-Leuchtturm-7']);
        self::assertResponseRedirects('/konto', 303);
        self::assertSame(['passwort.aendern'], $this->loggedActions());
    }

    public function testConfirmationEndsAfterFiveMinutes(): void
    {
        self::mockTime('2026-11-02 10:00:00 UTC');
        $client = $this->confirmedClient('papa');

        $client->request('GET', '/konto/passwort');
        self::assertResponseIsSuccessful();

        self::mockTime('2026-11-02 10:05:00 UTC');
        $client->request('GET', '/konto/passwort');
        self::assertResponseRedirects('/konto/bestaetigen?weiter=app_account_password');
    }

    public function testConfirmationIsLimitedToFiveAttempts(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));

        for ($i = 0; $i < 5; ++$i) {
            $client->request('GET', '/konto/bestaetigen');
            $client->submitForm('Bestätigen', ['passwort' => 'falsch-'.$i]);
            self::assertSelectorTextContains('.hc-alert', 'Das Passwort stimmt nicht');
        }
        $client->request('GET', '/konto/bestaetigen');
        $client->submitForm('Bestätigen', ['passwort' => self::PASSWORD]);
        self::assertSelectorTextContains('.hc-alert', 'Zu viele Versuche');
    }

    public function testRecoveryCodeConfirmsOnceAndNewCodesReplaceTheOldOnes(): void
    {
        $client = $this->startClient();
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $this->enableAuthenticatorApp($papa);
        $oldCodes = $this->giveRecoveryCodes($papa);
        $client->loginUser($papa);

        $client->request('GET', '/konto/bestaetigen?weiter=app_account_recovery_codes');
        $client->submitForm('Bestätigen', ['passwort' => self::PASSWORD, 'code' => $oldCodes[0]]);
        self::assertResponseRedirects('/konto/zwei-faktor/codes', 303);
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('main', 'ersetzen alle bisherigen');
        $crawler = $client->submitForm('Neue Codes erzeugen');
        $newCodes = $crawler->filter('.hc-codes li')->each(static fn ($node): string => $node->text());
        self::assertCount(10, $newCodes);
        self::assertSelectorExists('a[href="/konto"]');

        $client->request('GET', '/konto/bestaetigen');
        $client->submitForm('Bestätigen', ['passwort' => self::PASSWORD, 'code' => $oldCodes[1]]);
        self::assertResponseStatusCodeSame(422, 'Alte Codes gelten nicht mehr.');
    }

    public function testAdminCannotRemoveTheLastSecondFactor(): void
    {
        $client = $this->confirmedClient('max', UserRole::Manager, withApp: true);
        $client->request('GET', '/konto');
        self::assertSelectorNotExists('form[action="/konto/zwei-faktor/app/entfernen"]');
        $client->request('POST', '/konto/zwei-faktor/app/entfernen', ['_token' => $this->csrfToken($client, 'two-factor-app-remove')]);
        self::assertResponseRedirects('/konto', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Als Admin brauchst du einen zweiten Schritt');
        self::assertSame(TwoFactorMethod::Totp, $this->reload('max')->getTwofaMethod());
    }

    public function testOthersMayRemoveTheirAuthenticatorApp(): void
    {
        $client = $this->confirmedClient('papa', withApp: true);
        $client->request('GET', '/konto');
        $client->submitForm('App entfernen');
        self::assertResponseRedirects('/konto', 303);
        $papa = $this->reload('papa');
        self::assertSame(TwoFactorMethod::Keine, $papa->getTwofaMethod());
        self::assertSame(0, $papa->countRecoveryCodes());
        self::assertContains('zweifaktor.app_entfernen', $this->loggedActions());
    }

    public function testPasskeysOfOthersCannotBeRemoved(): void
    {
        $client = $this->confirmedClient('papa');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $passkey = new WebauthnCredential($anna, 'cred-anna', 'key', 0, null, []);
        $this->entityManager()->persist($passkey);
        $this->entityManager()->flush();

        $client->request('POST', '/konto/passkeys/'.$passkey->getId().'/entfernen', ['_token' => 'egal']);
        self::assertResponseStatusCodeSame(404);
    }

    public function testRemovingTheLastPasskeyEndsItsRoleAsSecondFactor(): void
    {
        $client = $this->confirmedClient('papa');
        $papa = $this->reload('papa');
        $passkey = new WebauthnCredential($papa, 'cred-papa', 'key', 0, null, []);
        $passkey->setLabel('Handy');
        $this->entityManager()->persist($passkey);
        $papa->addPasskeyAsTwoFactor();
        $this->giveRecoveryCodes($papa);

        $client->request('GET', '/konto');
        self::assertSelectorTextContains('.hc-settings', 'Handy');
        $client->submitForm('Entfernen');
        self::assertResponseRedirects('/konto', 303);
        self::assertSame(TwoFactorMethod::Keine, $this->reload('papa')->getTwofaMethod());
        self::assertSame(0, (int) $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM webauthn_credential'));
    }

    public function testEvenTheFirstSecondFactorNeedsAConfirmation(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));

        // At an open session nobody may make their own phone the key to the account
        $client->request('GET', '/konto/zwei-faktor/app');
        self::assertResponseRedirects('/konto/bestaetigen?weiter=app_account_two_factor_app');
        $client->request('GET', '/konto/zwei-faktor/passkey');
        self::assertResponseRedirects('/konto/bestaetigen?weiter=app_account_two_factor_passkey');
        $client->request('GET', '/konto/passkeys/neu');
        self::assertResponseRedirects('/konto/bestaetigen?weiter=app_account_passkey_add');
        $client->jsonRequest('POST', '/konto/passkey/optionen', []);
        self::assertResponseStatusCodeSame(403);

        $this->confirmIdentity($client);
        $client->request('GET', '/konto/zwei-faktor/app');
        self::assertResponseIsSuccessful();
    }

    public function testConfirmationIsReachableDuringTheMandatorySetup(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Manager, 'max'));

        $client->request('GET', '/konto/zwei-faktor/app');
        self::assertResponseRedirects('/konto/bestaetigen?weiter=app_account_two_factor_app');
        $client->followRedirect();
        self::assertResponseIsSuccessful();
        $client->submitForm('Bestätigen', ['passwort' => self::PASSWORD]);
        self::assertResponseRedirects('/konto/zwei-faktor/app', 303);
        $client->followRedirect();
        self::assertSelectorExists('img.hc-qr');
    }

    public function testAddingAPasskeyWithASecondFactorNeedsAConfirmation(): void
    {
        $client = $this->startClient();
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $this->enableAuthenticatorApp($papa);
        $this->giveRecoveryCodes($papa);
        $client->loginUser($papa);

        $client->request('GET', '/konto/passkeys/neu');
        self::assertResponseRedirects('/konto/bestaetigen?weiter=app_account_passkey_add');
        $client->request('POST', '/konto/passkey/optionen', [], [], ['CONTENT_TYPE' => 'application/json'], '{}');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Logged in and confirmed: with the password, and with $withApp an authenticator app plus its code.
     */
    private function confirmedClient(string $username, UserRole $role = UserRole::Angehoerige, bool $withApp = false): KernelBrowser
    {
        $client = $this->startClient();
        $person = $this->createPerson($role, $username);
        $secret = null;
        if ($withApp) {
            $secret = $this->enableAuthenticatorApp($person);
            $this->giveRecoveryCodes($person);
        }
        $client->loginUser($person);
        $client->request('GET', '/konto/bestaetigen');
        $client->submitForm('Bestätigen', null === $secret ? ['passwort' => self::PASSWORD] : ['passwort' => self::PASSWORD, 'code' => self::currentCode($secret)]);
        self::assertResponseRedirects('/konto', 303);

        return $client;
    }

    /**
     * A CSRF token for a form the page does not show (to prove the server refuses anyway).
     */
    private function csrfToken(KernelBrowser $client, string $id): string
    {
        $client->request('GET', '/konto');
        $request = $client->getRequest();
        $requestStack = static::getContainer()->get('request_stack');
        $requestStack->push($request);
        $token = static::getContainer()->get('security.csrf.token_manager')->getToken($id)->getValue();
        $request->getSession()->save();
        $requestStack->pop();

        return $token;
    }

    private function reload(string $username): AppUser
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(AppUser::class)->findOneBy(['username' => $username]) ?? throw new \LogicException('missing '.$username);
    }
}
