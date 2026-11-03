<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Entity\Task;
use App\Entity\TaskCompletion;
use App\Enum\AssigneeRole;
use App\Enum\DeviceMode;
use App\Enum\TaskCategory;
use App\Enum\TaskStatus;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * "Heute" and the task pages for every role and the team tablet (konzept-1 §3, konzept-12 §3, decisions M5).
 */
final class TaskPagesTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;
    use TabletTrait;

    protected function tearDown(): void
    {
        $this->restoreTwoFactorSwitch();
        parent::tearDown();
    }

    public function testA1TheTeamTabletShowsTheTasksForTheTeamAtOnce(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $max = $this->createPerson(UserRole::Manager, 'max');
        $ben = $this->createPerson(UserRole::Assistenz, 'ben', password: null);
        $this->task('Blumen gießen', AssigneeRole::Assistenz, creator: $max);
        $this->task('Müll rausbringen', null, creator: $max);
        $this->task('Fahrrad aufpumpen', $ben, creator: $max);
        $this->task('Ben: Gespräch vorbereiten', $ben, Visibility::Zustaendig, creator: $max);
        $this->task('Rechnung prüfen', AssigneeRole::Admins, Visibility::Admins, creator: $max);
        $this->task('Geschenk für Papa', $max, Visibility::Privat, creator: $max);
        $this->task('Rezept abholen', AssigneeRole::Angehoerige, creator: $max);

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(['Blumen gießen', 'Fahrrad aufpumpen', 'Müll rausbringen'], $this->taskTitles($client, '#aufgaben-mine'));
        self::assertSelectorNotExists('#aufgaben-assistenz');
        self::assertSelectorExists('[data-controller="auto-refresh"]');
        self::assertSelectorTextContains('main', 'Zuständig: Ben');
    }

    public function testAPrivateTaskIsOnlySeenByItsCreator(): void
    {
        $client = $this->startClient();
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $private = $this->task('Geschenk für Max', $papa, Visibility::Privat, creator: $papa);

        $client->loginUser($papa);
        $client->request('GET', '/aufgaben/'.$private->getId());
        self::assertResponseIsSuccessful();

        $client->loginUser($admin);
        $client->request('GET', '/aufgaben/'.$private->getId());
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/aufgaben');
        self::assertSelectorTextNotContains('main', 'Geschenk für Max');
    }

    public function testAResponsibleTaskIsSeenByTheResponsiblePersonAndAdmins(): void
    {
        $client = $this->startClient();
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $ben = $this->createPerson(UserRole::Assistenz, 'ben');
        $admin = $this->createAdminWithTwoFactor(UserRole::Angehoerige, 'schwester');
        $task = $this->task('Gespräch über Urlaub', $anna, Visibility::Zustaendig, creator: $papa);

        foreach ([[$anna, 200], [$admin, 200], [$papa, 200], [$ben, 403]] as [$person, $status]) {
            $client->loginUser($person);
            $client->request('GET', '/aufgaben/'.$task->getId());
            self::assertResponseStatusCodeSame($status, $person->getUsername());
        }
    }

    public function testAdminsSeeTheAssistantsTasksSeparately(): void
    {
        $client = $this->startClient();
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $this->task('Rechnung prüfen', AssigneeRole::Admins, creator: $papa);
        $this->task('Bettwäsche wechseln', null, Visibility::Zustaendig, creator: $papa);

        $client->loginUser($admin);
        $client->request('GET', '/');
        self::assertSame(['Rechnung prüfen'], $this->taskTitles($client, '#aufgaben-mine'));
        self::assertSame(['Bettwäsche wechseln'], $this->taskTitles($client, '#aufgaben-assistenz'));

        $client->loginUser($papa);
        $client->request('GET', '/');
        self::assertSelectorNotExists('#aufgaben-assistenz');
        self::assertSelectorTextContains('.hc-empty-state', 'Für heute ist alles erledigt.');
    }

    public function testCompletedAtTheTabletStoresTheTabletAndUndoRestoresTheTask(): void
    {
        $client = $this->startClient();
        $tablet = $this->pairTablet($client);
        $task = $this->task('Blumen gießen', AssigneeRole::Assistenz);
        self::mockTime('2026-11-03 09:00:00 UTC');

        $crawler = $client->request('GET', '/');
        $client->submit($crawler->filter('form[action="/aufgaben/'.$task->getId().'/erledigt"]')->form());
        self::assertResponseRedirects('/');
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.hc-toast', '„Blumen gießen“ ist erledigt.');
        self::assertSelectorTextContains('.hc-empty-state', 'Für heute ist alles erledigt.');

        $completion = $this->entityManager()->getRepository(TaskCompletion::class)->findOneBy(['task' => $task->getId()]);
        self::assertNotNull($completion);
        self::assertNull($completion->getUser());
        self::assertSame($tablet->getId(), $completion->getDevice()?->getId());

        self::mockTime('2026-11-03 09:00:29 UTC');
        $client->submit($crawler->filter('.hc-toast form')->form());
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', '„Blumen gießen“ ist wieder offen.');
        self::assertSame(['Blumen gießen'], $this->taskTitles($client, '#aufgaben-mine'));
        self::assertSame(0, (int) $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM task_completion'));
    }

    public function testASecondTapFromAnOldPageGivesAHint(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Assistenz, 'anna'));
        $task = $this->task('Blumen gießen', AssigneeRole::Assistenz);
        $crawler = $client->request('GET', '/');
        $form = $crawler->filter('form[action="/aufgaben/'.$task->getId().'/erledigt"]')->form();

        $client->submit($form);
        $client->submit($form);
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', '„Blumen gießen“ ist schon abgeschlossen.');

        foreach (['bearbeiten', 'verschieben', 'weitergeben'] as $page) {
            $client->request('GET', '/aufgaben/'.$task->getId().'/'.$page);
            self::assertResponseRedirects('/aufgaben/'.$task->getId(), 303, $page);
        }
    }

    public function testOnlyAdminsSeeWhoCompletedATaskAndWhen(): void
    {
        $client = $this->startClient();
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $task = $this->task('Blumen gießen', AssigneeRole::Assistenz);
        $task->close(\App\Enum\CompletionAction::Erledigt);
        $this->entityManager()->persist(new TaskCompletion($task, \App\Enum\CompletionAction::Erledigt, $anna, new \DateTimeImmutable('2026-11-03 09:15:00')));
        $this->entityManager()->flush();

        $client->loginUser($anna);
        $client->request('GET', '/aufgaben/'.$task->getId());
        self::assertSelectorTextContains('.hc-facts', '3. November 2026');
        self::assertSelectorTextNotContains('.hc-facts', 'Uhr');
        self::assertSelectorTextNotContains('.hc-facts', 'von Anna');

        $client->loginUser($admin);
        $client->request('GET', '/aufgaben/'.$task->getId());
        self::assertSelectorTextContains('.hc-facts', '10:15 Uhr');
        self::assertSelectorTextContains('.hc-facts', 'von Anna');
    }

    public function testClosingNeedsAToken(): void
    {
        $client = $this->startClient();
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $task = $this->task('Blumen gießen', AssigneeRole::Assistenz);
        $client->loginUser($anna);

        $client->request('POST', '/aufgaben/'.$task->getId().'/erledigt', ['_token' => 'falsch']);

        self::assertResponseStatusCodeSame(403);
        self::assertSame(TaskStatus::Offen->value, $this->entityManager()->getConnection()->fetchOne('SELECT status FROM task'));
    }

    public function testA10HandingOn(): void
    {
        $client = $this->startClient();
        $admin = $this->createAdminWithTwoFactor(UserRole::Angehoerige, 'schwester');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $forAdmins = $this->task('Rezept abholen', AssigneeRole::Admins, creator: $papa);
        $forPapa = $this->task('Brief einwerfen', $papa, creator: $admin);
        $forAssistants = $this->task('Fenster putzen', AssigneeRole::Assistenz, creator: $admin);

        // Admins hand on every task they see
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/aufgaben/'.$forAdmins->getId().'/weitergeben');
        $client->submit($crawler->selectButton('Weitergeben')->form(), ['hand_over[assignee]' => 'rolle-assistenz']);
        self::assertResponseRedirects('/');
        $this->entityManager()->clear();
        $handedOn = $this->entityManager()->find(Task::class, $forAdmins->getId());
        self::assertSame([AssigneeRole::Assistenz, $admin->getId()], [$handedOn?->getAssigneeRole(), $handedOn?->getAssignedBy()?->getId()]);
        $client->request('GET', '/aufgaben/'.$forPapa->getId().'/weitergeben');
        self::assertResponseIsSuccessful();

        // Everybody else what they are responsible for
        $client->loginUser($papa);
        $client->request('GET', '/aufgaben/'.$forPapa->getId().'/weitergeben');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/aufgaben/'.$forAssistants->getId().'/weitergeben');
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($anna);
        $client->request('GET', '/aufgaben/'.$forAssistants->getId().'/weitergeben');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/aufgaben/'.$forPapa->getId().'/weitergeben');
        self::assertResponseStatusCodeSame(403);
    }

    public function testTheTeamTabletHandsOnItsTasks(): void
    {
        $client = $this->startClient();
        $tablet = $this->pairTablet($client);
        $forAssistants = $this->task('Fenster putzen', AssigneeRole::Assistenz);
        $forAdmins = $this->task('Rechnung prüfen', AssigneeRole::Admins);

        $crawler = $client->request('GET', '/aufgaben/'.$forAssistants->getId().'/weitergeben');
        $client->submit($crawler->selectButton('Weitergeben')->form(), ['hand_over[assignee]' => 'rolle-admins']);
        self::assertResponseRedirects('/');
        $client->request('GET', '/aufgaben/'.$forAdmins->getId().'/weitergeben');
        self::assertResponseStatusCodeSame(403);

        $row = $this->entityManager()->getConnection()->fetchAssociative('SELECT assignee_role, assigned_by_user_id, assigned_by_device_id FROM task WHERE id = ?', [$forAssistants->getId()]);
        self::assertSame(['assignee_role' => 'admins', 'assigned_by_user_id' => null, 'assigned_by_device_id' => $tablet->getId()], [
            'assignee_role' => $row['assignee_role'] ?? null,
            'assigned_by_user_id' => $row['assigned_by_user_id'] ?? null,
            'assigned_by_device_id' => (int) ($row['assigned_by_device_id'] ?? 0),
        ]);
    }

    public function testTheTeamTabletCreatesTasksWithoutAPersonAndWithoutPrivate(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        self::mockTime('2026-11-03 09:00:00 UTC');

        $crawler = $client->request('GET', '/aufgaben/neu');
        self::assertSelectorNotExists('input[value="privat"]');
        self::assertSelectorExists('select[name="task[assignee]"] option[value="da"][selected]');
        $client->submit($crawler->selectButton('Aufgabe anlegen')->form(), ['task[title]' => 'Spülmaschine ausräumen']);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', '„Spülmaschine ausräumen“ ist angelegt.');
        self::assertSame(['Spülmaschine ausräumen'], $this->taskTitles($client, '#aufgaben-mine'));
        self::assertNull($this->entityManager()->getConnection()->fetchOne('SELECT created_by_user_id FROM task'));
    }

    public function testRelativesKeepPrivateTasksAndPastDaysAreRefused(): void
    {
        $client = $this->startClient();
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        self::mockTime('2026-11-03 09:00:00 UTC');
        $client->loginUser($papa);

        $crawler = $client->request('GET', '/aufgaben/neu');
        $client->submit($crawler->selectButton('Aufgabe anlegen')->form(), ['task[title]' => 'Geschenk', 'task[dueDate]' => '2026-11-02']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-field__errors', 'Der Tag liegt in der Vergangenheit.');

        $client->submit($crawler->selectButton('Aufgabe anlegen')->form(), ['task[title]' => 'Geschenk', 'task[visibility]' => 'privat']);
        self::assertResponseRedirects('/');
        $row = $this->entityManager()->getConnection()->fetchAssociative('SELECT visibility, assignee_user_id FROM task');
        self::assertSame(['privat', $papa->getId()], [$row['visibility'] ?? null, (int) ($row['assignee_user_id'] ?? 0)]);
    }

    public function testAssistantsCannotKeepPrivateTasks(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Assistenz, 'anna'));

        $client->request('GET', '/aufgaben/neu');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('input[value="privat"]');
    }

    public function testOnlyAdminsAndTheCreatorChangeATask(): void
    {
        $client = $this->startClient();
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $ben = $this->createPerson(UserRole::Assistenz, 'ben');
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $task = $this->task('Blumen gießen', AssigneeRole::Assistenz, creator: $anna);

        foreach ([[$anna, 200], [$admin, 200], [$ben, 403]] as [$person, $status]) {
            $client->loginUser($person);
            $client->request('GET', '/aufgaben/'.$task->getId().'/bearbeiten');
            self::assertResponseStatusCodeSame($status, $person->getUsername());
        }
    }

    public function testPostponeWithOneTapInTheWeekCalendar(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Assistenz, 'anna'));
        $task = $this->task('Fenster putzen', AssigneeRole::Assistenz);
        self::mockTime('2026-11-03 09:00:00 UTC');

        // This and next week (Monday 2.11. to Sunday 15.11.): yesterday and the current due date are no buttons
        $crawler = $client->request('GET', '/aufgaben/'.$task->getId().'/verschieben');
        self::assertCount(14, $crawler->filter('.hc-week-picker__day'));
        self::assertSelectorNotExists('button[value="2026-11-02"]');
        self::assertSelectorNotExists('button[value="2026-11-03"]');
        self::assertSelectorExists('span[aria-current="date"]');
        self::assertSelectorExists('button[value="2026-11-15"]');

        $client->submit($crawler->filter('button[value="2026-11-04"]')->form());

        self::assertResponseRedirects('/');
        $row = $this->entityManager()->getConnection()->fetchAssociative('SELECT due_date, postponed_count FROM task');
        self::assertSame(['2026-11-04', 1], [$row['due_date'] ?? null, (int) ($row['postponed_count'] ?? 0)]);

        $client->request('GET', '/aufgaben/'.$task->getId().'/verschieben?woche=2');
        self::assertSelectorExists('button[value="2026-11-16"]');
        self::assertSelectorExists('button[value="2026-11-29"]');
    }

    public function testAPastDayIsRefusedEvenWhenSentByHand(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Assistenz, 'anna'));
        $task = $this->task('Fenster putzen', AssigneeRole::Assistenz);
        self::mockTime('2026-11-03 09:00:00 UTC');

        $crawler = $client->request('GET', '/aufgaben/'.$task->getId().'/verschieben');
        $form = $crawler->filter('button[value="2026-11-04"]')->form();
        $client->submit($form, ['postpone[dueDate]' => '2026-11-01']);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-field__errors', 'Der Tag liegt in der Vergangenheit.');
    }

    public function testATabletInModePersoenlichShowsNoTasks(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Persoenlich);
        $task = $this->task('Blumen gießen', AssigneeRole::Assistenz);

        $client->request('GET', '/');
        self::assertResponseRedirects('/tablet/personen');
        foreach (['/aufgaben', '/aufgaben/neu', '/aufgaben/'.$task->getId()] as $path) {
            $client->request('GET', $path);
            self::assertResponseStatusCodeSame(403, $path);
        }
    }

    public function testAnAdminByPinSeesNoAdminTasks(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client, DeviceMode::Beides);
        $admin = $this->createPerson(UserRole::Angehoerige, 'schwester', admin: true);
        $this->givePin($admin, '482913');
        $this->task('Rechnung prüfen', AssigneeRole::Admins, Visibility::Admins);
        $this->task('Rezept abholen', AssigneeRole::Angehoerige);

        $this->loginWithPin($client, $admin, '482913');
        $client->request('GET', '/');

        self::assertSame(['Rezept abholen'], $this->taskTitles($client, '#aufgaben-mine'));
        self::assertSelectorNotExists('#aufgaben-assistenz');
    }

    public function testAFullAdminLoginAtTheTabletWorksLikeOnThePc(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);
        $this->requireTwoFactorForAdmins(true);
        $admin = $this->createPerson(UserRole::Angehoerige, 'schwester', admin: true);
        $secret = $this->enableAuthenticatorApp($admin);
        $this->giveRecoveryCodes($admin);
        $this->task('Rechnung prüfen', AssigneeRole::Admins, Visibility::Admins);

        $this->loginWithPassword($client, 'schwester');
        // The task helper fixed the clock: the code must fit the app's clock
        $this->submitSecondStep($client, self::codeAt($secret, '2026-11-03 08:00:00 UTC'));
        $client->request('GET', '/');
        self::assertSame(['Rechnung prüfen'], $this->taskTitles($client, '#aufgaben-mine'));
        self::assertSelectorExists('#aufgaben-assistenz');
        $client->request('GET', '/verwaltung');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/aufgaben/neu');
        self::assertSelectorExists('input[value="admins"]');
    }

    public function testTheTopBarLeadsBackToToday(): void
    {
        $client = $this->startClient();
        $client->loginUser($this->createPerson(UserRole::Angehoerige, 'papa'));

        $client->request('GET', '/aufgaben');
        self::assertSelectorExists('.hc-topbar__nav a.hc-topbar__link[href="/"]');
        $client->request('GET', '/');
        self::assertSelectorNotExists('.hc-topbar__nav a.hc-topbar__link[href="/"]');
    }

    public function testTheTopBarOfTheTeamTabletLeadsBackToToday(): void
    {
        $client = $this->startClient();
        $this->pairTablet($client);

        $client->request('GET', '/notfall');

        self::assertSelectorExists('.hc-topbar__nav a.hc-topbar__link[href="/"]');
        self::assertSelectorNotExists('.hc-topbar__nav a[href="/konto"]');
    }

    private function task(string $title, AppUser|AssigneeRole|null $assignee, Visibility $visibility = Visibility::Alle, ?AppUser $creator = null, string $due = '2026-11-03'): Task
    {
        self::mockTime('2026-11-03 08:00:00 UTC');
        $task = new Task($title, TaskCategory::Haushalt, new \DateTimeImmutable($due), $creator);
        $task->assign($assignee instanceof AppUser ? $assignee : null, $assignee instanceof AssigneeRole ? $assignee : null);
        $task->setVisibility($visibility);
        $this->entityManager()->persist($task);
        $this->entityManager()->flush();

        return $task;
    }

    /**
     * @return list<string>
     */
    private function taskTitles(KernelBrowser $client, string $section): array
    {
        $crawler = $client->getCrawler()->filter('section[aria-labelledby="'.ltrim($section, '#').'"] .hc-task__title');

        return $crawler->each(static fn ($node): string => trim($node->text()));
    }
}
