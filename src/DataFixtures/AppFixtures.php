<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AppSetting;
use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\EmergencyContact;
use App\Enum\DeviceMode;
use App\Enum\EmergencyCategory;
use App\Enum\EmergencyVisibility;
use App\Enum\PersonColor;
use App\Enum\UserRole;
use App\Service\Tablet\DeviceCookie;
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
 * - Anna (assistant): start PIN 246813, PIN login on – at the tablet "Persönlich anmelden" → Anna
 * - Ben (assistant): no own login, works at the team tablet only
 *
 * Team tablet "Flur" (mode "Beides"): pair a second browser profile via Verwaltung → Geräte → "Tablet koppeln",
 * or set the cookie "hc_device" to TABLET_TOKEN by hand (developer tools). Emergency contacts with fake numbers.
 */
final class AppFixtures extends Fixture
{
    public const string PASSWORD = 'homecrew-test-2026';
    public const string ANNA_PIN = '246813';
    public const string TABLET_TOKEN = 'dev-tablet-flur-only-for-fixtures';

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
        $anna->setColor(PersonColor::Gruen->value);
        $anna->changePin($this->passwordHasher->hashPassword($anna, self::ANNA_PIN), mustChange: true);
        $anna->enablePinLogin(true);
        $ben = new AppUser(UserRole::Assistenz, 'ben', 'Ben', $start);
        $ben->setColor(PersonColor::Ocker->value);

        foreach ([$max, $sister, $father, $anna, $ben] as $person) {
            $manager->persist($person);
        }

        $tablet = new Device('Flur', DeviceCookie::hash(self::TABLET_TOKEN), $sister);
        $tablet->setMode(DeviceMode::Beides);
        $manager->persist($tablet);

        $contacts = [
            [EmergencyCategory::Angehoerige, 'Schwester', 'Familie', '+49 931 0000001', 'immer zuerst', EmergencyVisibility::Alle, true],
            [EmergencyCategory::Arzt, 'Hausarztpraxis', null, '0931 0000002', 'werktags 8–18 Uhr', EmergencyVisibility::Alle, false],
            [EmergencyCategory::Notdienst, 'Sanitätshaus-Notdienst', 'Rollstuhl', '0800 0000003', 'wenn der Rollstuhl nicht fährt', EmergencyVisibility::Alle, true],
            [EmergencyCategory::Dienstleister, 'Hausverwaltung', null, '0931 0000004', 'Wasserschaden, Tür klemmt', EmergencyVisibility::Admins, false],
        ];
        foreach ($contacts as [$category, $name, $relation, $phone, $whenToCall, $visibility, $lockScreen]) {
            $contact = new EmergencyContact();
            $contact->setCategory($category);
            $contact->setName($name);
            $contact->setRelation($relation);
            $contact->setPhone($phone);
            $contact->setWhenToCall($whenToCall);
            $contact->setVisibility($visibility);
            $contact->setShowOnLockscreen($lockScreen);
            $manager->persist($contact);
        }

        $manager->flush();
    }

    private function setPassword(AppUser $person, bool $mustChange = false): void
    {
        $person->changePassword($this->passwordHasher->hashPassword($person, self::PASSWORD), $mustChange);
    }
}
