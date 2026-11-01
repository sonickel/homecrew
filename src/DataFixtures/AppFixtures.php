<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AppSetting;
use App\Entity\AppUser;
use App\Enum\UserRole;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Test household for dev (php bin/console doctrine:fixtures:load).
 * Login data (password, PIN, team tablet) follows with the milestones that build the login (M2–M4).
 */
final class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Loading fixtures empties every mapped table, the settings row included
        $manager->persist(new AppSetting());

        $start = new \DateTimeImmutable('2026-01-01');
        $manager->persist(new AppUser(UserRole::Manager, 'manager', 'Max', $start, isAdmin: true));
        $manager->persist(new AppUser(UserRole::Angehoerige, 'schwester', 'Schwester', $start, isAdmin: true));
        $manager->persist(new AppUser(UserRole::Angehoerige, 'papa', 'Papa', $start));
        $manager->persist(new AppUser(UserRole::Assistenz, 'anna', 'Anna', $start));
        $manager->persist(new AppUser(UserRole::Assistenz, 'ben', 'Ben', $start));

        $manager->flush();
    }
}
