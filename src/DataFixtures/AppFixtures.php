<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AppSetting;
use App\Entity\AppUser;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test household for dev (php bin/console doctrine:fixtures:load).
 *
 * Logins (dev only), all with the password "homecrew-test-2026"; everybody reads the privacy notice first:
 * - manager (Max): start password – the first login asks for a new password (and a second factor when ADMIN_2FA_REQUIRED=1)
 * - schwester (Schwester, admin): password set, no second factor yet
 * - papa (Papa, no admin): password set – "Verwaltung" is forbidden
 * - Anna (assistant): start PIN 246813, PIN login on (the PIN login itself follows in M4)
 * - Ben (assistant): no own login, works at the team tablet only
 */
final class AppFixtures extends Fixture
{
    public const string PASSWORD = 'homecrew-test-2026';
    public const string ANNA_PIN = '246813';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Loading fixtures empties every mapped table, the settings row included
        $manager->persist(new AppSetting());

        $start = new \DateTimeImmutable('2026-01-01');
        $max = new AppUser(UserRole::Manager, 'manager', 'Max', $start, isAdmin: true);
        $this->setPassword($max, mustChange: true);
        $sister = new AppUser(UserRole::Angehoerige, 'schwester', 'Schwester', $start, isAdmin: true);
        $this->setPassword($sister);
        $father = new AppUser(UserRole::Angehoerige, 'papa', 'Papa', $start);
        $this->setPassword($father);
        $anna = new AppUser(UserRole::Assistenz, 'anna', 'Anna', $start);
        $anna->setColor('#2e7d4f');
        $anna->changePin($this->passwordHasher->hashPassword($anna, self::ANNA_PIN), mustChange: true);
        $anna->enablePinLogin(true);
        $ben = new AppUser(UserRole::Assistenz, 'ben', 'Ben', $start);
        $ben->setColor('#8a5300');

        foreach ([$max, $sister, $father, $anna, $ben] as $person) {
            $manager->persist($person);
        }

        $manager->flush();
    }

    private function setPassword(AppUser $person, bool $mustChange = false): void
    {
        $person->changePassword($this->passwordHasher->hashPassword($person, self::PASSWORD), $mustChange);
    }
}
