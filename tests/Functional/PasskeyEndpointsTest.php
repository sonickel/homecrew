<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\WebauthnCredential;
use App\Enum\UserRole;
use App\Security\Passkey\PasskeyLoginHandler;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The passkey endpoints answer what passkey_controller.js expects. The ceremony itself (browser + authenticator)
 * is checked in the browser with a virtual authenticator.
 */
final class PasskeyEndpointsTest extends WebTestCase
{
    use PeopleTrait;

    public function testLoginOptionsRequireUserVerificationWithoutRevealingAccounts(): void
    {
        $client = $this->startClient();

        $client->jsonRequest('POST', '/anmelden/passkey/optionen', []);

        self::assertResponseIsSuccessful();
        $options = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($options);
        // Without WEBAUTHN_RP_ID the browser uses the host of the page (locally: localhost)
        self::assertNull($options['rpId'] ?? null);
        self::assertSame('required', $options['userVerification']);
        self::assertGreaterThanOrEqual(43, \strlen($options['challenge']));
        self::assertEmpty($options['allowCredentials'] ?? []);
    }

    public function testFailedPasskeyLoginAnswersInPlainWords(): void
    {
        $client = $this->startClient();
        $client->jsonRequest('POST', '/anmelden/passkey/optionen', []);

        $client->jsonRequest('POST', '/anmelden/passkey', ['id' => 'abc', 'rawId' => 'abc', 'type' => 'public-key', 'response' => []]);

        self::assertResponseStatusCodeSame(401);
        self::assertSame(['status' => 'error', 'message' => PasskeyLoginHandler::FAILED_MESSAGE], json_decode((string) $client->getResponse()->getContent(), true));
    }

    public function testRegistrationOptionsUseThePublicIdAndRequireAResidentKey(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max');
        $this->entityManager()->persist(new WebauthnCredential($max, 'bestehend', 'key', 0, null, ['internal']));
        $this->entityManager()->flush();
        $client->loginUser($max);
        $this->confirmIdentity($client);

        $client->jsonRequest('POST', '/konto/passkey/optionen', []);

        self::assertResponseIsSuccessful();
        $options = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($options);
        self::assertNull($options['rp']['id'] ?? null);
        self::assertSame('max', $options['user']['name']);
        self::assertSame('Max', $options['user']['displayName']);
        self::assertSame(rtrim(strtr(base64_encode($max->getPublicId()->toBinary()), '+/', '-_'), '='), $options['user']['id']);
        self::assertSame('required', $options['authenticatorSelection']['residentKey']);
        self::assertSame('required', $options['authenticatorSelection']['userVerification']);
        self::assertSame('none', $options['attestation'] ?? 'none');
        // An existing passkey is not created twice on the same device
        self::assertSame(rtrim(strtr(base64_encode('bestehend'), '+/', '-_'), '='), $options['excludeCredentials'][0]['id']);
    }

    public function testNoPasskeyCanBeAddedOnceThereIsASecondFactor(): void
    {
        $client = $this->startClient();
        $max = $this->createPerson(UserRole::Manager, 'max');
        $this->enableAuthenticatorApp($max);
        $this->giveRecoveryCodes($max);
        $client->loginUser($max);

        $client->jsonRequest('POST', '/konto/passkey/optionen', []);
        self::assertResponseStatusCodeSame(403);
        $client->jsonRequest('POST', '/konto/passkey', []);
        self::assertResponseStatusCodeSame(403);
    }

    public function testOverAnIpAddressPasskeysAreHiddenAndTheNamedAddressIsOffered(): void
    {
        $client = $this->startClient();

        $client->request('GET', 'https://192.168.1.20/anmelden');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-controller="passkey"]');
        self::assertSelectorTextContains('.hc-alert', 'Passkeys gehen hier nicht, weil HomeCrew über eine IP-Adresse geöffnet ist.');
        self::assertSelectorExists('.hc-alert a[href="https://localhost:8000/anmelden"]');
    }

    public function testOverAnIpAddressTheSetupOffersOnlyTheApp(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Manager, 'max'));
        $this->confirmIdentity($client, 'https://127.0.0.1:8000');

        $client->request('GET', 'https://127.0.0.1:8000/konto/zwei-faktor');
        self::assertSelectorExists('a.hc-choice[href="/konto/zwei-faktor/app"]');
        self::assertSelectorNotExists('a.hc-choice[href="/konto/zwei-faktor/passkey"]');
        self::assertSelectorExists('.hc-alert a[href="https://localhost:8000/konto/zwei-faktor"]');

        $client->request('GET', 'https://127.0.0.1:8000/konto/zwei-faktor/passkey');
        self::assertSelectorNotExists('[data-controller="passkey"]');
        self::assertSelectorExists('a.hc-button[href="/konto/zwei-faktor"]');
    }

    public function testRegistrationNeedsALogin(): void
    {
        $client = $this->startClient();

        $client->jsonRequest('POST', '/konto/passkey/optionen', []);

        self::assertResponseRedirects('http://localhost/anmelden');
    }

    public function testSetupPageShowsTheFormOnlyWithPasskeySupport(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Manager, 'max'));
        $this->confirmIdentity($client);

        $client->request('GET', '/konto/zwei-faktor/passkey');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="passkey"][data-passkey-options-url-value="/konto/passkey/optionen"][data-passkey-result-url-value="/konto/passkey"]');
        self::assertSelectorExists('[data-passkey-target="form"][hidden]');
        self::assertSelectorExists('[data-passkey-target="unsupported"][hidden]');
    }
}
