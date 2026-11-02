<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Security\TwoFactor\AuthenticatorApp;
use App\Security\TwoFactor\RecoveryCodeGenerator;
use App\Security\TwoFactor\RecoveryCodeManager;
use Doctrine\ORM\EntityManagerInterface;
use OTPHP\TOTP;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Persons with login data for functional tests (each test runs in a rolled-back transaction).
 * Requires a booted kernel (createClient() first).
 */
trait PeopleTrait
{
    protected const string PASSWORD = 'Sonnenblume-Fahrrad-42';

    private ?string $originalTwoFactorSwitch = null;

    /**
     * Client with fresh login limits: rate limiters and the used-code cache live in the filesystem cache
     * and would otherwise carry failed attempts from one test into the next.
     */
    protected function startClient(): KernelBrowser
    {
        $client = static::createClient();
        $clearer = static::getContainer()->get('cache.global_clearer');
        $clearer->clearPool('cache.rate_limiter');
        $clearer->clearPool('cache.app');

        return $client;
    }

    protected function createPerson(UserRole $role, string $username, bool $admin = false, ?string $password = self::PASSWORD, bool $mustChange = false): AppUser
    {
        $person = new AppUser($role, $username, ucfirst($username), new \DateTimeImmutable('2026-01-01'), $admin || UserRole::Manager === $role);
        if (null !== $password) {
            $person->changePassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($person, $password), $mustChange);
        }
        $this->entityManager()->persist($person);
        $this->entityManager()->flush();

        return $person;
    }

    /**
     * @return string the plain secret, to compute codes like the app on the phone
     */
    protected function enableAuthenticatorApp(AppUser $person): string
    {
        $authenticatorApp = static::getContainer()->get(AuthenticatorApp::class);
        $secret = $authenticatorApp->generateSecret();
        $person->enableAuthenticatorApp($authenticatorApp->encryptSecret($secret));
        $this->entityManager()->flush();

        return $secret;
    }

    /**
     * @return list<string>
     */
    protected function giveRecoveryCodes(AppUser $person): array
    {
        $codes = (new RecoveryCodeGenerator())->generate();
        static::getContainer()->get(RecoveryCodeManager::class)->replace($person, $codes);
        $this->entityManager()->flush();

        return $codes;
    }

    protected static function currentCode(string $secret): string
    {
        \assert('' !== $secret);

        return TOTP::createFromSecret($secret)->now();
    }

    protected function loginWithPassword(KernelBrowser $client, string $username, string $password = self::PASSWORD): void
    {
        $client->request('GET', '/anmelden');
        $client->submitForm('Anmelden', ['_username' => $username, '_password' => $password]);
    }

    protected function submitSecondStep(KernelBrowser $client, string $code): void
    {
        $client->request('GET', '/anmelden/code');
        $client->submitForm('Anmelden', ['_auth_code' => $code]);
    }

    /**
     * ADMIN_2FA_REQUIRED for the kernels booted afterwards (.env.test switches it on).
     */
    protected function requireTwoFactorForAdmins(bool $required): void
    {
        $this->originalTwoFactorSwitch ??= (string) $_SERVER['ADMIN_2FA_REQUIRED'];
        $_SERVER['ADMIN_2FA_REQUIRED'] = $_ENV['ADMIN_2FA_REQUIRED'] = $required ? '1' : '0';
    }

    protected function restoreTwoFactorSwitch(): void
    {
        if (null !== $this->originalTwoFactorSwitch) {
            $_SERVER['ADMIN_2FA_REQUIRED'] = $_ENV['ADMIN_2FA_REQUIRED'] = $this->originalTwoFactorSwitch;
            $this->originalTwoFactorSwitch = null;
        }
    }

    protected function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * @return list<string> actions of the audit log, oldest first
     */
    protected function loggedActions(): array
    {
        return array_map(
            'strval',
            $this->entityManager()->getConnection()->fetchFirstColumn('SELECT action FROM audit_log ORDER BY id'),
        );
    }
}
