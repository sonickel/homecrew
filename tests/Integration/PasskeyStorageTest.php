<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\WebauthnCredential;
use App\Enum\TwoFactorMethod;
use App\Enum\UserRole;
use App\Security\Passkey\PasskeyCredentialRepository;
use App\Security\Passkey\PasskeyRegisteredHandler;
use App\Security\Passkey\PasskeyUserRepository;
use App\Service\Account\PrivacyNotice;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Uid\Uuid;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

/**
 * Passkeys between the WebAuthn library and webauthn_credential / app_user.
 */
final class PasskeyStorageTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private AppUser $max;

    protected function setUp(): void
    {
        $this->max = new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        $this->max->acknowledgePrivacyNotice(PrivacyNotice::VERSION, new \DateTimeImmutable('2026-01-01'));
        $this->entityManager()->persist($this->max);
        $this->entityManager()->flush();
    }

    public function testUserHandleIsThePublicId(): void
    {
        $users = static::getContainer()->get(PasskeyUserRepository::class);

        $entity = $users->findOneByUsername('max');

        self::assertNotNull($entity);
        self::assertSame($this->max->getPublicId()->toBinary(), $entity->id);
        self::assertSame('Max', $entity->displayName);
        self::assertSame('max', $users->findOneByUserHandle($entity->id)?->name);
        self::assertNull($users->findOneByUserHandle(pack('J', (int) $this->max->getId())));
    }

    public function testNewPasskeyIsStoredAndLaterLoginsUpdateTheCounter(): void
    {
        $credentials = static::getContainer()->get(PasskeyCredentialRepository::class);
        self::mockTime('2026-11-02 10:00:00 UTC');

        $credentials->saveCredentialRecord($this->record(counter: 0));
        $stored = $credentials->findOneByCredentialId('cred-id-1');
        self::assertNotNull($stored);
        self::assertSame('public-key-bytes', $stored->credentialPublicKey);
        self::assertSame(['internal', 'hybrid'], $stored->transports);
        self::assertSame($this->max->getPublicId()->toBinary(), $stored->userHandle);
        self::assertSame('none', $stored->attestationType);

        $credentials->saveCredentialRecord($this->record(counter: 7));
        $this->entityManager()->clear();
        $row = $this->entityManager()->getRepository(WebauthnCredential::class)->findOneBy(['credentialId' => 'cred-id-1']);
        self::assertNotNull($row);
        self::assertSame(7, $row->getSignCount());
        self::assertSame('2026-11-02 10:00:00', $row->getLastUsedAt()?->format('Y-m-d H:i:s'));

        $userEntity = PasskeyUserRepository::userEntity($this->max);
        self::assertCount(1, $credentials->findAllForUserEntity($userEntity));
    }

    public function testRegisteredPasskeyBecomesTheSecondFactorWithItsName(): void
    {
        static::getContainer()->get(PasskeyCredentialRepository::class)->saveCredentialRecord($this->record(counter: 0));
        static::getContainer()->get(TokenStorageInterface::class)->setToken(new UsernamePasswordToken($this->max, 'main', $this->max->getRoles()));

        $response = static::getContainer()->get(PasskeyRegisteredHandler::class)
            ->onSuccess(Request::create('/konto/passkey?bezeichnung='.rawurlencode("Handy\nvon Max")));

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(['status' => 'ok', 'redirect' => '/konto/zwei-faktor/codes'], json_decode((string) $response->getContent(), true));
        self::assertSame(TwoFactorMethod::Passkey, $this->max->getTwofaMethod());
        $this->entityManager()->clear();
        self::assertSame('Handy von Max', $this->entityManager()->getRepository(WebauthnCredential::class)->findOneBy([])?->getLabel());
        self::assertSame(
            ['passkey.hinzufuegen', 'zweifaktor.einrichten'],
            $this->entityManager()->getConnection()->fetchFirstColumn('SELECT action FROM audit_log ORDER BY id'),
        );
    }

    private function record(int $counter): CredentialRecord
    {
        return CredentialRecord::create(
            'cred-id-1',
            'public-key',
            ['internal', 'hybrid'],
            'none',
            EmptyTrustPath::create(),
            Uuid::v4(),
            'public-key-bytes',
            $this->max->getPublicId()->toBinary(),
            $counter,
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
