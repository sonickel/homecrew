<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\Task;
use App\Entity\TaskCompletion;
use App\Entity\TaskTemplate;
use App\Enum\AssigneeRole;
use App\Enum\CompletionAction;
use App\Enum\RecurrenceType;
use App\Enum\TaskCategory;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Repository\TaskRepository;
use App\Service\Series\Rhythm;
use App\Service\Tablet\DeviceCookie;
use App\Service\Task\PlanSync;
use App\Service\Task\TaskActions;
use App\Service\Viewer\Viewer;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\DomCrawler\Form;

/**
 * "Routinen & Wartung" and the plan pages for every role and the team tablet (konzept-2 §5.6, konzept-12 §6,
 * decisions M6) – Tuesday 3 November 2026.
 */
final class PlanPagesTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;
    use TabletTrait;

    private const string NOW = '2026-11-03 08:00:00 UTC';

    protected function tearDown(): void
    {
        $this->restoreTwoFactorSwitch();
        parent::tearDown();
    }

    public function testEverybodySeesTheOverviewWithWhatTheyMaySee(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $this->plan('Bettwäsche wechseln', creator: $admin);
        $this->plan('Rauchmelder testen', AssigneeRole::Admins, Visibility::Admins, creator: $admin);
        $this->plan('Blumen für Mama', $papa, Visibility::Privat, creator: $papa);

        foreach ([[$admin, ['Bettwäsche wechseln', 'Rauchmelder testen']], [$papa, ['Bettwäsche wechseln', 'Blumen für Mama']], [$anna, ['Bettwäsche wechseln']]] as [$person, $titles]) {
            $client->loginUser($person);
            $client->request('GET', '/routinen');
            self::assertResponseIsSuccessful();
            self::assertSame($titles, $this->titles($client), $person->getUsername());
        }

        $this->tabletClient($client);
        $client->request('GET', '/routinen');
        self::assertResponseIsSuccessful();
        self::assertSame(['Bettwäsche wechseln'], $this->titles($client));
        self::assertSelectorNotExists('a[href="/routinen/neu"]');
    }

    public function testInvisiblePlansAreRefused(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $plan = $this->plan('Rauchmelder testen', AssigneeRole::Admins, Visibility::Admins, creator: $admin);

        $client->loginUser($this->createPerson(UserRole::Assistenz, 'anna'));
        $client->request('GET', '/routinen/'.$plan->getId());
        self::assertResponseStatusCodeSame(403);

        $client->loginUser($admin);
        $client->request('GET', '/routinen/'.$plan->getId());
        self::assertResponseIsSuccessful();
    }

    public function testLastDoneIsOnlyTheDayExceptForAdmins(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $tablet = $this->tabletClient($client);
        $client->getCookieJar()->clear();
        $byAnna = $this->plan('Reifendruck prüfen', interval: 14, startsOn: '2026-10-20', creator: $admin);
        $atTablet = $this->plan('Kühlschrank auswischen', interval: 30, startsOn: '2026-10-20', creator: $admin);
        $this->completeAt($byAnna, Viewer::person($anna, false), '2026-10-20 09:15:00 UTC');
        $this->completeAt($atTablet, Viewer::teamTablet($tablet), '2026-10-21 14:00:00 UTC');

        $client->loginUser($anna);
        $client->request('GET', '/routinen');
        self::assertSelectorTextContains('main', 'Zuletzt erledigt: 20. Oktober 2026');
        self::assertSelectorTextNotContains('main', 'von Anna');
        self::assertSelectorTextNotContains('main', 'am Team-Tablet');
        $client->request('GET', '/routinen/'.$byAnna->getId());
        self::assertSelectorTextContains('.hc-history', '20. Oktober 2026 erledigt');
        self::assertSelectorTextNotContains('.hc-history', 'Uhr');

        $this->tabletClient($client);
        $client->request('GET', '/routinen');
        self::assertSelectorTextContains('main', 'Zuletzt erledigt: 21. Oktober 2026');
        self::assertSelectorTextNotContains('main', 'am Team-Tablet');

        $client->loginUser($admin);
        $client->request('GET', '/routinen');
        self::assertSelectorTextContains('main', 'Zuletzt erledigt: 20. Oktober 2026 von Anna');
        self::assertSelectorTextContains('main', 'Zuletzt erledigt: 21. Oktober 2026 am Team-Tablet');
        $client->request('GET', '/routinen/'.$byAnna->getId());
        self::assertSelectorTextContains('.hc-history', '20. Oktober 2026, 11:15 Uhr erledigt von Anna');
    }

    public function testCreatingPlansNeedsTheSwitch(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');

        foreach ([$anna, $papa] as $person) {
            $client->loginUser($person);
            $client->request('GET', '/routinen');
            self::assertSelectorNotExists('a[href="/routinen/neu"]');
            $client->request('GET', '/routinen/neu');
            self::assertResponseStatusCodeSame(403, $person->getUsername());
            $client->request('POST', '/routinen/neu', ['plan' => ['title' => 'x']]);
            self::assertResponseStatusCodeSame(403, $person->getUsername());
        }

        $this->giveSwitch('anna');
        $client->loginUser($anna);
        $client->request('GET', '/routinen');
        self::assertSelectorExists('a[href="/routinen/neu"]');
        $crawler = $client->request('GET', '/routinen/neu');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('select[name="plan[visibility]"] option[value="privat"]');
        $form = $crawler->selectButton('Plan anlegen')->form([
            'plan[title]' => 'Bettwäsche wechseln',
            'plan[rhythm]' => Rhythm::WEEKLY,
            'plan[startsOn]' => '2026-11-03',
            'plan[dueTime]' => '10:00',
            'plan[checklist]' => "Bett abziehen\n\nFrisch beziehen",
        ]);
        self::chooseWeekdays($form, ['MO', 'TH']);
        $client->submit($form);

        $plan = $this->entityManager()->getRepository(TaskTemplate::class)->findOneBy(['title' => 'Bettwäsche wechseln']);
        self::assertNotNull($plan);
        self::assertResponseRedirects('/routinen/'.$plan->getId());
        self::assertSame(['FREQ=WEEKLY;BYDAY=MO,TH', 'Anna'], [$plan->getRrule(), $plan->getCreatedBy()?->getDisplayName()]);
        self::assertCount(2, $plan->getItems());
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', '„Bettwäsche wechseln“ ist angelegt.');
        self::assertSelectorTextContains('.hc-facts', 'Montags und donnerstags, 10:00 Uhr');
        self::assertCount(4, static::getContainer()->get(TaskRepository::class)->findOpenOccurrences($plan), 'Thu 5., Mon 9., Thu 12., Mon 16.');
    }

    public function testTheTeamTabletCreatesNoPlans(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $this->tabletClient($client);

        $client->request('GET', '/routinen/neu');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAfterCompletionIsCheckedInTheForm(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $client->loginUser($this->createAdminWithTwoFactor(UserRole::Manager, 'max'));
        self::mockTime(self::NOW);

        $client->request('GET', '/routinen/neu');
        $client->submitForm('Plan anlegen', [
            'plan[title]' => 'Reifendruck prüfen',
            'plan[rhythm]' => 'nach_erledigung',
            'plan[afterDays]' => '14',
            'plan[earliestAfterDays]' => '20',
            'plan[startsOn]' => '2026-11-03',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-field--error', 'höchstens 14 Tage');

        $client->submitForm('Plan anlegen', ['plan[earliestAfterDays]' => '10', 'plan[startsOn]' => '2026-11-02']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-field--error', 'Vergangenheit');

        $client->submitForm('Plan anlegen', ['plan[startsOn]' => '2026-11-03']);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-facts', 'Alle 14 Tage nach der letzten Erledigung');
        self::assertSelectorTextContains('.hc-facts', 'Schon nach 10 Tagen möglich');
    }

    public function testOnlyTheNumbersOfTheChosenRhythmCount(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $client->loginUser($this->createAdminWithTwoFactor(UserRole::Manager, 'max'));

        $client->request('GET', '/routinen/neu');
        $client->submitForm('Plan anlegen', [
            'plan[title]' => 'Kühlschrank auswischen',
            'plan[rhythm]' => Rhythm::MONTHLY,
            'plan[monthDay]' => '',
            'plan[everyWeeks]' => '99',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorCount(1, '.hc-field--error');
        self::assertSelectorTextContains('.hc-field--error', 'Bitte eine Zahl von 1 bis 31.');

        $client->submitForm('Plan anlegen', ['plan[monthDay]' => '31']);
        self::assertResponseRedirects();
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-facts', 'Monatlich am letzten Tag');
    }

    public function testAdminsChangeEndAndResumeAPlan(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $plan = $this->plan('Bettwäsche wechseln', creator: $admin);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/routinen/'.$plan->getId().'/bearbeiten');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="plan[weekdays][]"][value="TU"][checked]');
        $form = $crawler->selectButton('Speichern')->form(['plan[pausedFrom]' => '2026-11-04', 'plan[pausedUntil]' => '2026-11-12']);
        self::chooseWeekdays($form, ['WE']);
        $client->submit($form);
        self::assertResponseRedirects('/routinen/'.$plan->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-facts', 'Jeden Mittwoch');
        self::assertSelectorTextContains('.hc-facts', 'Mittwoch, 4. November 2026 bis Donnerstag, 12. November 2026');
        self::assertSame(['2026-11-18'], $this->openDays($plan), 'Tuesday gone, Wednesdays in the pause skipped');

        $client->submitForm('Beenden');
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'ist beendet');
        self::assertSame([], $this->openDays($plan));
        $client->request('GET', '/routinen');
        self::assertSelectorTextContains('.hc-plans-ended summary', 'Beendet (1)');

        $client->request('GET', '/routinen/'.$plan->getId());
        $client->submitForm('Wieder aufnehmen');
        self::assertSame(['2026-11-18'], $this->openDays($plan));
    }

    public function testOnlyWithTheSwitchSomebodyElseChangesAPlan(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $plan = $this->plan('Bettwäsche wechseln', creator: $admin);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $client->loginUser($anna);

        foreach (['GET' => '/bearbeiten', 'POST' => '/beenden'] as $method => $page) {
            $client->request($method, '/routinen/'.$plan->getId().$page);
            self::assertResponseStatusCodeSame(403, $page);
        }

        $this->giveSwitch('anna');
        $client->request('GET', '/routinen/'.$plan->getId().'/bearbeiten');
        self::assertResponseIsSuccessful();
    }

    public function testNowDoneAtTheTabletWithUndo(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $tablet = $this->tabletClient($client);
        $plan = $this->plan('Reifendruck prüfen', interval: 14, startsOn: '2026-11-10');
        self::mockTime('2026-11-03 09:00:00 UTC');

        $crawler = $client->request('GET', '/routinen/'.$plan->getId());
        $client->submit($crawler->selectButton('Jetzt erledigt')->form());
        self::assertResponseRedirects('/routinen/'.$plan->getId());
        $crawler = $client->followRedirect();
        self::assertSelectorTextContains('.hc-toast', '„Reifendruck prüfen“ ist erledigt.');
        self::assertSelectorTextContains('.hc-history', '3. November 2026 erledigt');
        $completion = $this->entityManager()->getRepository(TaskCompletion::class)->findOneBy([]);
        self::assertSame($tablet->getId(), $completion?->getDevice()?->getId());

        $client->submit($crawler->filter('.hc-toast form')->form());
        self::assertResponseRedirects('/routinen/'.$plan->getId());
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'ist wieder offen');
        self::assertSame(['2026-11-10'], $this->openDays($plan));
    }

    public function testATapOnTodayTicksOffTheWholeChecklistTheDetailsSomePoints(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $plan = $this->plan('Reifendruck prüfen', interval: 14, startsOn: '2026-11-03', checklist: ['Reifen vorne links', 'Reifen vorne rechts']);
        $client->loginUser($anna);

        $crawler = $client->request('GET', '/');
        self::assertSelectorTextContains('section[aria-labelledby="aufgaben-mine"]', 'Checkliste: 2 Punkte');
        $first = $this->openTasks($plan)[0];
        $client->submit($crawler->filter('form[action="/aufgaben/'.$first->getId().'/erledigt"]')->form());
        self::assertSame(2, $this->checkedPoints($first));

        $next = $this->openTasks($plan)[0];
        $crawler = $client->request('GET', '/aufgaben/'.$next->getId());
        self::assertSelectorTextContains('#checkliste', 'Checkliste');
        self::assertSelectorNotExists('.hc-button-row form[action="/aufgaben/'.$next->getId().'/erledigt"]');
        $form = $crawler->filter('#checkliste + form')->form();
        $points = $form['punkte'];
        \assert(\is_array($points) && $points[0] instanceof ChoiceFormField);
        $points[0]->tick();
        $client->submit($form);
        self::assertSame(1, $this->checkedPoints($next));

        $client->request('GET', '/routinen/'.$plan->getId());
        self::assertSelectorTextContains('.hc-history', '1 von 2 Punkten');
        $client->request('GET', '/aufgaben/'.$next->getId());
        self::assertSelectorTextContains('#checkliste + .hc-checklist', 'Abgehakt: Reifen vorne links');
        self::assertSelectorTextContains('#checkliste + .hc-checklist', 'Nicht abgehakt: Reifen vorne rechts');
    }

    public function testAnOccurrenceLinksItsPlanAndIsChangedOnlyThisTime(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $plan = $this->plan('Bettwäsche wechseln', creator: $admin);
        $task = $this->openTasks($plan)[0];
        $client->loginUser($admin);

        $client->request('GET', '/aufgaben/'.$task->getId());
        self::assertSelectorTextContains('.hc-facts', 'Gehört zu Bettwäsche wechseln (Jeden Dienstag)');
        $client->request('GET', '/aufgaben/'.$task->getId().'/bearbeiten');
        self::assertSelectorTextContains('.hc-alert', 'Das ändert nur dieses Mal.');
        self::assertSelectorExists('.hc-alert a[href="/routinen/'.$plan->getId().'/bearbeiten"]');
    }

    public function testOpenTasksShowOccurrencesOnlyOnceTheyShow(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $this->plan('Bettwäsche wechseln', rhythm: Rhythm::weekly(['MO']), startsOn: '2026-11-02');
        $this->plan('Müll rausbringen');
        $client->loginUser($this->createPerson(UserRole::Assistenz, 'anna'));

        $client->request('GET', '/aufgaben');

        self::assertSame(['Müll rausbringen'], $client->getCrawler()->filter('.hc-task__title')->each(static fn ($node): string => trim($node->text())));
    }

    public function testOnlyAdminsSeeOverduePlansOnToday(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        self::mockTime('2026-11-02 08:00:00 UTC');
        $this->plan('Bettwäsche wechseln', rhythm: Rhythm::weekly(['MO']), startsOn: '2026-11-02', at: '2026-11-02 08:00:00 UTC');
        self::mockTime(self::NOW);

        $client->loginUser($admin);
        $client->request('GET', '/');
        self::assertSelectorTextContains('.hc-today__notice', 'Routinen & Wartung: 1 überfällig');
        self::assertSelectorExists('.hc-tile[href="/routinen"]');

        $client->loginUser($this->createPerson(UserRole::Assistenz, 'anna'));
        $client->request('GET', '/');
        self::assertSelectorNotExists('.hc-today__notice');
        self::assertSelectorTextContains('section[aria-labelledby="aufgaben-mine"]', 'Bettwäsche wechseln');
    }

    public function testTheSwitchInThePersonsIsLogged(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $client->loginUser($this->createAdminWithTwoFactor(UserRole::Manager, 'max'));
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');

        $client->request('GET', '/verwaltung/personen/'.$anna->getId().'/edit');
        $client->submitForm('Speichern', ['AppUser[canManageRecurringTasks]' => '1']);
        self::assertResponseRedirects('/verwaltung/personen');

        self::assertTrue($this->reload('anna')->canManageRecurringTasks());
        $crawler = $client->request('GET', '/verwaltung/protokoll');
        self::assertStringContainsString('Wiederkehrende Aufgaben: nein → ja', $crawler->filter('table')->text());
    }

    /**
     * A plan with its occurrences, made at the given time (default: Tuesday 3 November 2026).
     *
     * @param list<string> $checklist
     */
    private function plan(string $title, AppUser|AssigneeRole|null $assignee = AssigneeRole::Assistenz, Visibility $visibility = Visibility::Alle, ?AppUser $creator = null, ?Rhythm $rhythm = null, ?int $interval = null, string $startsOn = '2026-11-03', array $checklist = [], string $at = self::NOW): TaskTemplate
    {
        self::mockTime($at);
        $rhythm ??= Rhythm::weekly(['TU']);
        $plan = null === $interval
            ? new TaskTemplate($title, TaskCategory::Haushalt, RecurrenceType::Kalender, $rhythm->rrule(), new \DateTimeImmutable($startsOn), $creator)
            : new TaskTemplate($title, TaskCategory::Hilfsmittel, RecurrenceType::Intervall, $interval, new \DateTimeImmutable($startsOn), $creator);
        $plan->assign($assignee instanceof AppUser ? $assignee : null, $assignee instanceof AssigneeRole ? $assignee : null);
        $plan->setVisibility($visibility);
        $plan->replaceChecklist($checklist);
        $this->entityManager()->persist($plan);
        $this->entityManager()->flush();
        static::getContainer()->get(PlanSync::class)->sync($plan);

        return $plan;
    }

    private function completeAt(TaskTemplate $plan, Viewer $viewer, string $at): void
    {
        self::mockTime($at);
        static::getContainer()->get(TaskActions::class)->close($this->openTasks($plan)[0], CompletionAction::Erledigt, $viewer);
        self::mockTime(self::NOW);
    }

    /**
     * From now on the client is the team tablet (paired once per test).
     */
    private function tabletClient(KernelBrowser $client): Device
    {
        $client->getCookieJar()->clear();
        $device = $this->entityManager()->getRepository(Device::class)->findOneBy(['tokenHash' => DeviceCookie::hash(self::TABLET_TOKEN)]);
        if (null === $device) {
            return $this->pairTablet($client);
        }
        $client->getCookieJar()->set(new Cookie(DeviceCookie::NAME, self::TABLET_TOKEN, null, '/', 'localhost'));

        return $device;
    }

    /**
     * The switch "wiederkehrende Aufgaben" straight in the database – changing a person through the entity manager
     * of the test would be logged with the detached person of the last request.
     */
    private function giveSwitch(string $username): void
    {
        $this->entityManager()->getConnection()->update('app_user', ['can_manage_recurring_tasks' => 1], ['username' => $username]);
    }

    /**
     * The weekday checkboxes share one name – DomCrawler addresses them one by one.
     *
     * @param list<string> $days RFC 5545 weekdays
     */
    private static function chooseWeekdays(Form $form, array $days): void
    {
        $fields = $form['plan[weekdays]'];
        \assert(\is_array($fields));
        foreach ($fields as $field) {
            \assert($field instanceof ChoiceFormField);
            \in_array($field->availableOptionValues()[0], $days, true) ? $field->tick() : $field->untick();
        }
    }

    /**
     * @return list<Task>
     */
    private function openTasks(TaskTemplate $plan): array
    {
        return static::getContainer()->get(TaskRepository::class)->findOpenOccurrences($plan);
    }

    /**
     * @return list<string> the due dates of the open occurrences
     */
    private function openDays(TaskTemplate $plan): array
    {
        return array_map(
            'strval',
            $this->entityManager()->getConnection()->fetchFirstColumn("SELECT due_date FROM task WHERE template_id = ? AND status = 'offen' ORDER BY due_date", [$plan->getId()]),
        );
    }

    private function checkedPoints(Task $task): int
    {
        return (int) $this->entityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM task_item_result r JOIN task_completion c ON c.id = r.completion_id WHERE c.task_id = ? AND r.is_checked = 1',
            [$task->getId()],
        );
    }

    /**
     * @return list<string>
     */
    private function titles(KernelBrowser $client): array
    {
        return $client->getCrawler()->filter('main .hc-task__title')->each(static fn ($node): string => trim($node->text()));
    }

    private function reload(string $username): AppUser
    {
        $this->entityManager()->clear();

        return $this->entityManager()->getRepository(AppUser::class)->findOneBy(['username' => $username]) ?? throw new \LogicException('missing '.$username);
    }
}
