<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\WebauthnCredential;
use App\Enum\TwoFactorMethod;
use App\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * app:setup (first start) and app:manager:reset-2fa (emergency at the server).
 */
final class CommandsTest extends KernelTestCase
{
    public function testSetupCreatesTheManagerOnlyOnce(): void
    {
        $tester = $this->command('app:setup');
        $tester->setInputs(['max', 'Max', 'Gartenzaun-Leuchtturm-7', 'Gartenzaun-Leuchtturm-7']);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $manager = $this->entityManager()->getRepository(AppUser::class)->findOneBy(['username' => 'max']);
        self::assertNotNull($manager);
        self::assertSame(UserRole::Manager, $manager->getRole());
        self::assertTrue($manager->isAdmin());
        self::assertTrue($manager->isPasswordMustChange());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($manager, 'Gartenzaun-Leuchtturm-7'));
        self::assertSame(['einrichtung.manager', 'system'], $this->entityManager()->getConnection()->fetchNumeric('SELECT action, via FROM audit_log'));

        $second = $this->command('app:setup');
        self::assertSame(Command::FAILURE, $second->execute([]));
        self::assertStringContainsString('Es gibt schon einen Manager.', $second->getDisplay());
    }

    public function testSetupRejectsWeakPasswordsAndBadUsernames(): void
    {
        $tester = $this->command('app:setup');
        // Each invalid answer is asked again
        $tester->setInputs(['Max Mustermann', 'max', 'Max', 'kurz', 'Gartenzaun-Leuchtturm-7', 'Gartenzaun-Leuchtturm-7']);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Nur a–z, 0–9, Punkt, Minus und Unterstrich', $tester->getDisplay());
        self::assertStringContainsString('mindestens 10 Zeichen', $tester->getDisplay());
    }

    public function testSetupRefusesWithoutMasterKey(): void
    {
        $original = (string) $_SERVER['APP_ENCRYPTION_KEY'];
        $_SERVER['APP_ENCRYPTION_KEY'] = $_ENV['APP_ENCRYPTION_KEY'] = '';
        try {
            $tester = $this->command('app:setup');

            self::assertSame(Command::FAILURE, $tester->execute([]));
            self::assertStringContainsString('APP_ENCRYPTION_KEY', $tester->getDisplay());
            self::assertNull($this->entityManager()->getRepository(AppUser::class)->findManager());
        } finally {
            $_SERVER['APP_ENCRYPTION_KEY'] = $_ENV['APP_ENCRYPTION_KEY'] = $original;
        }
    }

    public function testSetupStopsWhenThePasswordsDiffer(): void
    {
        $tester = $this->command('app:setup');
        $tester->setInputs(['max', 'Max', 'Gartenzaun-Leuchtturm-7', 'Gartenzaun-Leuchtturm-8']);

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertNull($this->entityManager()->getRepository(AppUser::class)->findOneBy(['username' => 'max']));
    }

    public function testResetRemovesEverySecondFactorOfTheManager(): void
    {
        $manager = new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        $manager->enableAuthenticatorApp('encrypted');
        $manager->replaceRecoveryCodeHashes(['hash']);
        $this->entityManager()->persist($manager);
        $this->entityManager()->persist(new WebauthnCredential($manager, 'cred', 'key', 0, null, []));
        $this->entityManager()->flush();

        $tester = $this->command('app:manager:reset-2fa');
        $tester->setInputs(['ja', 'Gartenzaun-Leuchtturm-7', 'Gartenzaun-Leuchtturm-7']);

        self::assertSame(Command::SUCCESS, $tester->execute(['--passwort' => true]));
        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(AppUser::class, $manager->getId());
        self::assertNotNull($reloaded);
        self::assertSame(TwoFactorMethod::Keine, $reloaded->getTwofaMethod());
        self::assertNull($reloaded->getTotpSecretEnc());
        self::assertSame(0, $reloaded->countRecoveryCodes());
        self::assertTrue($reloaded->isPasswordMustChange());
        self::assertSame(0, $this->entityManager()->getRepository(WebauthnCredential::class)->count([]));
        self::assertSame('zweifaktor.zuruecksetzen', $this->entityManager()->getConnection()->fetchOne('SELECT action FROM audit_log'));
    }

    public function testResetAsksBeforeChangingAnything(): void
    {
        $manager = new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        $manager->enableAuthenticatorApp('encrypted');
        $this->entityManager()->persist($manager);
        $this->entityManager()->flush();

        $tester = $this->command('app:manager:reset-2fa');
        $tester->setInputs(['nein']);

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $this->entityManager()->clear();
        self::assertSame(TwoFactorMethod::Totp, $this->entityManager()->find(AppUser::class, $manager->getId())?->getTwofaMethod());
    }

    private function command(string $name): CommandTester
    {
        return new CommandTester((new Application(self::bootKernel()))->find($name));
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
