<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\AppSetting;
use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\EmergencyContact;
use App\Entity\Task;
use App\Entity\TaskCompletion;
use App\Entity\TaskTemplate;
use App\Enum\AssigneeRole;
use App\Enum\CompletionAction;
use App\Enum\DeviceMode;
use App\Enum\EmergencyCategory;
use App\Enum\EmergencyVisibility;
use App\Enum\PersonColor;
use App\Enum\RecurrenceType;
use App\Enum\TaskCategory;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Service\Series\Rhythm;
use App\Service\Tablet\DeviceCookie;
use App\Service\Task\PlanSync;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Test household for dev (php bin/console doctrine:fixtures:load).
 *
 * Logins (dev only), all with the password "homecrew-test-2026"; everybody reads the privacy notice first:
 * - manager (Max): start password – the first login asks for a new password (and a second factor when ADMIN_2FA_REQUIRED=1)
 * - schwester (Schwester, admin): password set, no second factor yet
 * - papa (Papa, no admin): password set – "Verwaltung" is forbidden; switch "wiederkehrende Aufgaben" on
 * - Anna (assistant): start PIN 246813, PIN login on – at the tablet "Persönlich anmelden" → Anna
 * - Ben (assistant): no own login, works at the team tablet only
 *
 * Team tablet "Flur" (mode "Beides"): pair a second browser profile via Verwaltung → Geräte → "Tablet koppeln",
 * or set the cookie "hc_device" to TABLET_TOKEN by hand (developer tools). Emergency contacts with fake numbers.
 * Tasks around today: every group of "Heute", for the team, single persons, admins only and one private task.
 * Plans ("Routinen & Wartung"): weekly with checklist, twice a week with a missed one, every 14 days after
 * completion (overdue), monthly with a window, yearly for admins only, one paused.
 */
final class AppFixtures extends Fixture
{
    public const string PASSWORD = 'homecrew-test-2026';
    public const string ANNA_PIN = '246813';
    public const string TABLET_TOKEN = 'dev-tablet-flur-only-for-fixtures';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly PlanSync $planSync,
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
        $father->setCanManageRecurringTasks(true);
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

        $this->loadPlans($manager, $max, $sister, $anna, $tablet);
    }

    /**
     * Tasks relative to today (household time), so that "Heute" shows every group.
     */
    private function loadTasks(ObjectManager $manager, AppUser $max, AppUser $sister, AppUser $ben): void
    {
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
     * Plans with their history: done last by Anna or at the tablet, one overdue, one paused, one for admins only.
     * The generator (PlanSync) creates the occurrences from today on.
     */
    private function loadPlans(ObjectManager $manager, AppUser $max, AppUser $sister, AppUser $anna, Device $tablet): void
    {
        $today = self::today();
        $weekday = (int) $today->format('N');

        $bedding = $this->plan('Bettwäsche wechseln', TaskCategory::Waesche, Rhythm::weekly(['MO']), $max, minutes: 30, important: true, time: '10:00', checklist: ['Bett abziehen', 'Frisch beziehen', 'Wäsche in die Maschine']);
        $rubbish = $this->plan('Müll rausbringen', TaskCategory::Haushalt, Rhythm::weekly(['TU', 'FR']), $max, assignee: null, minutes: 10);
        $tyres = $this->plan('Reifendruck prüfen', TaskCategory::Hilfsmittel, 14, $max, earliestAfterDays: 10, minutes: 15, checklist: ['Reifen vorne links', 'Reifen vorne rechts', 'Reifen hinten links', 'Reifen hinten rechts'], description: 'Solldruck steht auf dem Reifen. Pumpe liegt im Flurschrank.');
        $fridge = $this->plan('Kühlschrank auswischen', TaskCategory::Kueche, Rhythm::monthly(1), $sister, windowDays: 3, minutes: 20);
        $smoke = $this->plan('Rauchmelder testen', TaskCategory::Haushalt, Rhythm::yearly(1, 3), $sister, assignee: AssigneeRole::Admins, visibility: Visibility::Admins, startsOn: new \DateTimeImmutable('2026-03-01'));
        $oven = $this->plan('Backofen reinigen', TaskCategory::Kueche, Rhythm::monthly(15), $max, minutes: 45);
        $oven->pause($today, $today->modify('+14 days'));
        foreach ([$bedding, $rubbish, $tyres, $fridge, $smoke, $oven] as $plan) {
            $manager->persist($plan);
        }

        // History: last Monday by Anna, the tyres 16 days ago at the tablet (3 of 4 points) – overdue since two days
        $lastMonday = $today->modify(\sprintf('-%d days', 1 === $weekday ? 7 : $weekday - 1));
        $done = [
            [$this->pastOccurrence($bedding, $lastMonday), $anna, $lastMonday->format('Y-m-d').' 10:40', null],
            [$this->pastOccurrence($tyres, $today->modify('-16 days')), $tablet, $today->modify('-16 days')->format('Y-m-d').' 16:05', 3],
            [$this->pastOccurrence($fridge, $today->modify('first day of this month')), $anna, $today->modify('first day of this month')->format('Y-m-d').' 11:20', null],
        ];
        // Missed: the last Tuesday or Friday before today is still open (replaced once the next one is due)
        $missed = $today->modify('-1 day');
        while (!\in_array((int) $missed->format('N'), [2, 5], true)) {
            $missed = $missed->modify('-1 day');
        }
        $manager->persist($this->pastOccurrence($rubbish, $missed));
        foreach ($done as [$task]) {
            $manager->persist($task);
        }
        $manager->flush();

        foreach ($done as [$task, $by, $at, $checked]) {
            $task->close(CompletionAction::Erledigt);
            // Household time, stored in UTC like every point in time
            $doneAt = (new \DateTimeImmutable($at, new \DateTimeZone('Europe/Berlin')))->setTimezone(new \DateTimeZone('UTC'));
            $completion = new TaskCompletion($task, CompletionAction::Erledigt, $by, $doneAt);
            $ids = array_map(static fn ($item): int => (int) $item->getId(), [...$task->getItems()]);
            $completion->tickOff(null === $checked ? null : \array_slice($ids, 0, $checked));
            $manager->persist($completion);
        }
        $manager->flush();

        foreach ([$bedding, $rubbish, $tyres, $fridge, $smoke, $oven] as $plan) {
            $this->planSync->sync($plan);
        }
    }

    /**
     * @param Rhythm|int   $rhythm    by calendar, or the number of days after the last completion
     * @param list<string> $checklist
     */
    private function plan(string $title, TaskCategory $category, Rhythm|int $rhythm, AppUser $creator, ?AssigneeRole $assignee = AssigneeRole::Assistenz, Visibility $visibility = Visibility::Alle, ?int $earliestAfterDays = null, int $windowDays = 0, ?int $minutes = null, bool $important = false, ?string $time = null, array $checklist = [], ?string $description = null, ?\DateTimeImmutable $startsOn = null): TaskTemplate
    {
        $startsOn ??= self::today()->modify('-60 days');
        $plan = $rhythm instanceof Rhythm
            ? new TaskTemplate($title, $category, RecurrenceType::Kalender, $rhythm->rrule(), $startsOn, $creator)
            : new TaskTemplate($title, $category, RecurrenceType::Intervall, $rhythm, $startsOn, $creator);
        if ($rhythm instanceof Rhythm) {
            $plan->repeatByCalendar($rhythm->rrule(), $windowDays);
        } else {
            $plan->repeatAfterCompletion($rhythm, $earliestAfterDays);
        }
        $plan->describe($title, $description, $category, $minutes, $important, false);
        $plan->schedule($startsOn, null, null === $time ? null : new \DateTimeImmutable($time));
        $plan->assign(null, $assignee);
        $plan->setVisibility($visibility);
        $plan->replaceChecklist($checklist);

        return $plan;
    }

    /**
     * An occurrence on a past day, as the generator would have created it.
     */
    private function pastOccurrence(TaskTemplate $plan, \DateTimeImmutable $day): Task
    {
        $task = new Task($plan->getTitle(), $plan->getCategory(), $day, $plan->getCreatedBy());
        $task->belongTo($plan, $day);
        $task->describe($plan->getTitle(), $plan->getDescription(), $plan->getCategory(), $plan->getEstimatedMinutes(), $plan->isImportant(), false);
        $task->schedule($day, $plan->getDueTime(), null, $day);
        $task->assign(null, $plan->getAssigneeRole());
        $task->setVisibility($plan->getVisibility());
        $task->copyChecklist($plan);

        return $task;
    }

    private static function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable((new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format('Y-m-d'));
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
