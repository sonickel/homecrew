<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Enum\TwoFactorMethod;
use App\Enum\UserRole;
use App\Security\TwoFactor\RecoveryCodeManager;
use App\Service\Security\SecretBox;
use App\Tests\Support\PeopleTrait;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * First login of the manager (konzept-1 §5, plan M2): change the start password → set up the second factor →
 * recovery codes → "Heute". Before that no other page can be reached. Runs with ADMIN_2FA_REQUIRED=1.
 */
final class FirstLoginTest extends WebTestCase
{
    use PeopleTrait;

    protected function tearDown(): void
    {
        $this->restoreTwoFactorSwitch();
        parent::tearDown();
    }

    public function testFirstLoginGoesThroughAllStepsInOrder(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max', mustChange: true);

        $this->loginWithPassword($client, 'max');
        $client->followRedirect();
        self::assertResponseRedirects('/konto/passwort');

        // Step 1: start password
        $this->assertOnlyStepIsReachable($client, '/konto/passwort');
        $client->request('GET', '/konto/passwort');
        self::assertSelectorTextContains('.hc-page-header__intro', 'Start-Passwort');
        $client->submitForm('Passwort speichern', ['change_password[password][first]' => 'kurz', 'change_password[password][second]' => 'kurz']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-field__errors', 'mindestens 10 Zeichen');
        self::assertSelectorExists('input[aria-describedby="change_password_password_first_help change_password_password_first_errors"]');
        $client->submitForm('Passwort speichern', ['change_password[password][first]' => self::PASSWORD, 'change_password[password][second]' => self::PASSWORD]);
        self::assertSelectorTextContains('.hc-field__errors', 'anderes Passwort als das bisherige');
        $client->submitForm('Passwort speichern', ['change_password[password][first]' => 'Gartenzaun-Leuchtturm-7', 'change_password[password][second]' => 'Gartenzaun-Leuchtturm-7']);
        self::assertResponseRedirects('/konto/zwei-faktor', 303);

        // Step 2: second factor (admin) – still logged in after the password change
        $this->assertOnlyStepIsReachable($client, '/konto/zwei-faktor');
        $client->request('GET', '/konto/zwei-faktor');
        self::assertSelectorExists('a.hc-choice[href="/konto/zwei-faktor/app"]');
        self::assertSelectorExists('a.hc-choice[href="/konto/zwei-faktor/passkey"]');
        $crawler = $client->clickLink('Authenticator-App');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertStringStartsWith('data:image/svg+xml;base64,', (string) $crawler->filter('img.hc-qr')->attr('src'));
        $secret = str_replace(' ', '', $crawler->filter('.hc-secret')->text());
        self::assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $secret);

        $client->submitForm('Code prüfen', ['authenticator_code[code]' => '000000' === self::currentCode($secret) ? '111111' : '000000']);
        self::assertSelectorTextContains('.hc-field__errors', 'Der Code stimmt nicht.');
        $client->submitForm('Code prüfen', ['authenticator_code[code]' => self::currentCode($secret)]);
        self::assertResponseRedirects('/konto/zwei-faktor/codes', 303);

        // Step 3: recovery codes, shown once
        $this->assertOnlyStepIsReachable($client, '/konto/zwei-faktor/codes');
        $client->request('GET', '/konto/zwei-faktor/codes');
        $crawler = $client->submitForm('Codes anzeigen');
        self::assertResponseIsSuccessful();
        $codes = $crawler->filter('.hc-codes li')->each(static fn ($node): string => $node->text());
        self::assertCount(10, $codes);
        self::assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{5}-[A-HJ-NP-Z2-9]{5}$/', $codes[0]);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Heute');
        self::assertSelectorTextContains('.hc-page-header__intro', 'Hallo Max');
        self::assertSame(['anmeldung.erfolg', 'passwort.aendern', 'zweifaktor.einrichten', 'zweifaktor.codes'], $this->loggedActions());

        // The secret is stored encrypted only
        $stored = $this->entityManager()->getConnection()->fetchAssociative('SELECT twofa_method, totp_secret_enc, password_must_change, twofa_backup_codes FROM app_user WHERE id = ?', [$max->getId()]);
        self::assertIsArray($stored);
        self::assertSame(TwoFactorMethod::Totp->value, $stored['twofa_method']);
        self::assertStringNotContainsString($secret, (string) $stored['totp_secret_enc']);
        self::assertSame($secret, static::getContainer()->get(SecretBox::class)->decrypt((string) $stored['totp_secret_enc']));
        self::assertSame(0, (int) $stored['password_must_change']);
        self::assertStringNotContainsString($codes[0], (string) $stored['twofa_backup_codes']);
    }

    public function testOnlyTheCurrentAppCodeConfirmsTheSetup(): void
    {
        $client = $this->startClient();
        $this->createPerson(UserRole::Manager, 'max');
        $this->loginWithPassword($client, 'max');

        $crawler = $client->request('GET', '/konto/zwei-faktor/app');
        $secret = str_replace(' ', '', $crawler->filter('.hc-secret')->text());
        // Reloading keeps the secret until it is confirmed
        $crawler = $client->request('GET', '/konto/zwei-faktor/app');
        self::assertSame($secret, str_replace(' ', '', $crawler->filter('.hc-secret')->text()));

        \assert('' !== $secret);
        $oldCode = TOTP::createFromSecret($secret)->at(max(0, time() - 300));
        $client->submitForm('Code prüfen', ['authenticator_code[code]' => $oldCode]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(TwoFactorMethod::Keine, $this->reload('max')->getTwofaMethod());
    }

    public function testWithoutTheSwitchAdminsMayUseTheAppWithoutSecondFactor(): void
    {
        $this->requireTwoFactorForAdmins(false);
        $client = $this->startClient();
        $this->createPerson(UserRole::Manager, 'max');

        $this->loginWithPassword($client, 'max');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Heute');
        // The second factor stays recommended and can be set up voluntarily
        self::assertSelectorExists('a.hc-button[href="/konto/zwei-faktor"]');
        $client->request('GET', '/verwaltung');
        self::assertResponseIsSuccessful();
    }

    public function testNonAdminsAreNotForcedToSetUpASecondFactor(): void
    {
        $client = $this->startClient();
        $this->createPerson(UserRole::Angehoerige, 'papa');

        $this->loginWithPassword($client, 'papa');
        $client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a.hc-button[href="/konto/zwei-faktor"]');
    }

    public function testPasswordPageIsOnlyForTheStartPassword(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));

        $client->request('GET', '/konto/passwort');
        self::assertResponseRedirects('/');
        $client->request('POST', '/konto/passwort', ['change_password' => ['password' => ['first' => 'Gartenzaun-Leuchtturm-7', 'second' => 'Gartenzaun-Leuchtturm-7']]]);
        self::assertResponseRedirects('/');
        self::assertSame([], $this->loggedActions());
    }

    public function testExistingRecoveryCodesCannotBeReplacedQuietly(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max');
        $this->enableAuthenticatorApp($max);
        $codes = $this->giveRecoveryCodes($max);
        $client->loginUser($max);

        $client->request('GET', '/konto/zwei-faktor/codes');
        self::assertResponseRedirects('/');
        $client->request('POST', '/konto/zwei-faktor/codes', ['_token' => 'egal']);
        self::assertResponseRedirects('/');

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(AppUser::class, $max->getId());
        self::assertNotNull($reloaded);
        self::assertTrue(static::getContainer()->get(RecoveryCodeManager::class)->isBackupCode($reloaded, $codes[0]));
    }

    private function assertOnlyStepIsReachable(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $step): void
    {
        foreach (['/', '/verwaltung', '/verwaltung/protokoll'] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects($step, 303, $path);
        }
    }

    private function reload(string $username): AppUser
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(AppUser::class)->findOneBy(['username' => $username]) ?? throw new \LogicException('missing');
    }
}
