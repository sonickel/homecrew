<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AppSetting;
use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\EmergencyContact;
use App\Entity\Task;
use App\Enum\AssigneeRole;
use App\Enum\DeviceMode;
use App\Enum\EmergencyCategory;
use App\Enum\EmergencyVisibility;
use App\Enum\PersonColor;
use App\Enum\TaskCategory;
use App\Enum\UserRole;
use App\Enum\Visibility;
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
 * Tasks around today: every group of "Heute", for the team, single persons, admins only and one private task.
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

        $this->loadTasks($manager, $max, $sister, $ben);

        $manager->flush();
    }

    /**
     * Tasks relative to today (household time), so that "Heute" shows every group.
     */
    private function loadTasks(ObjectManager $manager, AppUser $max, AppUser $sister, AppUser $ben): void
    {
        $manager->persist($this->task('Müll rausbringen', TaskCategory::Haushalt, null, -1, $max, minutes: 10));
        $manager->persist($this->task('Bettwäsche wechseln', TaskCategory::Waesche, AssigneeRole::Assistenz, 0, $max, time: '10:00', minutes: 30, important: true));
        $manager->persist($this->task('Blumen gießen', TaskCategory::Haushalt, AssigneeRole::Assistenz, 0, $sister, minutes: 10));
        $manager->persist($this->task('Fahrrad aufpumpen', TaskCategory::Hilfsmittel, $ben, 0, $max, minutes: 15));
        $manager->persist($this->task('Wäsche zusammenlegen', TaskCategory::Waesche, null, 2, $sister, from: -1, minutes: 20));
        $manager->persist($this->task('Fenster putzen', TaskCategory::Haushalt, AssigneeRole::Assistenz, 5, $max, minutes: 60));
        $manager->persist($this->task('Mit Max einkaufen gehen', TaskCategory::Einkauf, null, 1, $max, time: '15:00', onDueDate: true, minutes: 90, withManager: true));
        $manager->persist($this->task('Rezept in der Apotheke abholen', TaskCategory::Sonstiges, $sister, 0, $max, visibility: Visibility::Zustaendig));
        $manager->persist($this->task('Rechnung der Physio prüfen', TaskCategory::Sonstiges, AssigneeRole::Admins, 0, $max, visibility: Visibility::Admins, important: true));
        $manager->persist($this->task('Geschenk für Papa besorgen', TaskCategory::Einkauf, $sister, 3, $sister, Visibility::Privat));
    }

    /**
     * @param int      $due  days from today
     * @param int|null $from "Heute möglich" from this day on (days from today)
     */
    private function task(string $title, TaskCategory $category, AppUser|AssigneeRole|null $assignee, int $due, AppUser $creator, Visibility $visibility = Visibility::Alle, ?string $time = null, ?int $from = null, bool $onDueDate = false, ?int $minutes = null, bool $important = false, bool $withManager = false): Task
    {
        $today = new \DateTimeImmutable((new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d'));
        $dueDate = $today->modify(\sprintf('%+d days', $due));
        $fromDate = null === $from ? null : $today->modify(\sprintf('%+d days', $from));

        $task = new Task($title, $category, $dueDate, $creator);
        $task->describe($title, null, $category, $minutes, $important, $withManager);
        $task->schedule($dueDate, null === $time ? null : new \DateTimeImmutable($time), $fromDate, $onDueDate ? $dueDate : $fromDate);
        $task->assign($assignee instanceof AppUser ? $assignee : null, $assignee instanceof AssigneeRole ? $assignee : null);
        $task->setVisibility($visibility);

        return $task;
    }

    private function setPassword(AppUser $person, bool $mustChange = false): void
    {
        $person->changePassword($this->passwordHasher->hashPassword($person, self::PASSWORD), $mustChange);
    }
}
