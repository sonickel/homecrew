<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\WebauthnCredential;
use App\Enum\UserRole;
use App\Security\Passkey\PasskeyConfirmationHandler;
use App\Service\Account\RecentConfirmation;
use App\Tests\Support\PeopleTrait;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

/**
 * Fresh confirmation with a passkey: the signature is checked by the WebAuthn bundle, the handler makes
 * sure the passkey belongs to the person who is logged in.
 */
final class PasskeyConfirmationTest extends WebTestCase
{
    use PeopleTrait;

    private AppUser $papa;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $this->entityManager()->persist(new WebauthnCredential($this->papa, 'cred-papa', 'key', 0, null, []));
        $this->entityManager()->persist(new WebauthnCredential($anna, 'cred-anna', 'key', 0, null, []));
        $this->entityManager()->flush();
        static::getContainer()->get(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($this->papa, 'main', $this->papa->getRoles()));
    }

    public function testOwnPasskeyConfirms(): void
    {
        $response = $this->handle('cred-papa', 'app_account_password');

        self::assertSame(['status' => 'ok', 'redirect' => '/konto/passwort'], json_decode((string) $response->getContent(), true));
        self::assertTrue(static::getContainer()->get(RecentConfirmation::class)->isFresh());
        $this->entityManager()->clear();
        self::assertNotNull($this->entityManager()->getRepository(WebauthnCredential::class)->findOneBy(['credentialId' => 'cred-papa'])?->getLastUsedAt());
    }

    public function testPasskeyOfAnotherPersonIsRefused(): void
    {
        $response = $this->handle('cred-anna', 'app_account');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(PasskeyConfirmationHandler::FOREIGN_MESSAGE, json_decode((string) $response->getContent(), true)['message'] ?? null);
        self::assertFalse(static::getContainer()->get(RecentConfirmation::class)->isFresh());
    }

    public function testUnknownTargetsLeadToTheAccountPage(): void
    {
        $response = $this->handle('cred-papa', 'https://example.org');

        self::assertSame('/konto', json_decode((string) $response->getContent(), true)['redirect'] ?? null);
    }

    private function handle(string $credentialId, string $target): \Symfony\Component\HttpFoundation\Response
    {
        $request = Request::create('/konto/bestaetigen/passkey?weiter='.rawurlencode($target), 'POST', content: (string) json_encode(['rawId' => Base64UrlSafe::encodeUnpadded($credentialId)]));
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get(RequestStack::class)->push($request);

        return static::getContainer()->get(PasskeyConfirmationHandler::class)->onSuccess($request);
    }
}
