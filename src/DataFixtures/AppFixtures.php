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
 * Logins (dev only), all with the password "homecrew-test-2026":
 * - manager (Max): start password – the first login asks for a new password (and a second factor when ADMIN_2FA_REQUIRED=1)
 * - schwester (Schwester, admin): password set, no second factor yet
 * - papa (Papa, no admin): password set – "Verwaltung" is forbidden
 * Anna (tablet PIN) and Ben (no login) get their access with M3/M4.
 */
final class AppFixtures extends Fixture
{
    public const string PASSWORD = 'homecrew-test-2026';

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

        foreach ([$max, $sister, $father] as $person) {
            $manager->persist($person);
        }
        $manager->persist(new AppUser(UserRole::Assistenz, 'anna', 'Anna', $start));
        $manager->persist(new AppUser(UserRole::Assistenz, 'ben', 'Ben', $start));

        $manager->flush();
    }

    private function setPassword(AppUser $person, bool $mustChange = false): void
    {
        $person->changePassword($this->passwordHasher->hashPassword($person, self::PASSWORD), $mustChange);
    }
}
