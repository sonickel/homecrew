<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\IssueReport;
use App\Entity\ShoppingList;
use App\Entity\Task;
use App\Entity\TaskTemplate;
use App\Enum\AssigneeRole;
use App\Enum\RecurrenceType;
use App\Enum\ReportKind;
use App\Enum\ReportVisibility;
use App\Enum\TaskCategory;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Repository\TaskRepository;
use App\Service\Tablet\DeviceCookie;
use App\Service\Task\PlanSync;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Uid\Uuid;

/**
 * Reports for every role and the team tablet (konzept-13, plan M7): "Nicht möglich" with the plan's suggestion,
 * "Melden", "Auch bemerkt", the open reports without senders and the report log for admins – Wednesday 4 November 2026.
 */
final class ReportPagesTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use PeopleTrait;
    use TabletTrait;

    private const string NOW = '2026-11-04 08:00:00 UTC';

    protected function tearDown(): void
    {
        $this->restoreTwoFactorSwitch();
        parent::tearDown();
    }

    /**
     * Scenario A4: "Seifenspender auffüllen" → "Nicht möglich" → suggestion with one tap → the admin sees bell and
     * "Meldungen: 1 offen" → the entry is ticked off → the report is done on its own.
     */
    public function testScenarioA4AtTheTeamTablet(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $sister = $this->createPerson(UserRole::Angehoerige, 'schwester');
        $family = $this->list('Besorgt die Familie', $sister);
        $task = $this->soapOccurrence($admin, $family);
        $this->pairTablet($client);

        $crawler = $client->request('GET', '/aufgaben/'.$task->getId());
        $crawler = $client->click($crawler->selectLink('Nicht möglich')->link());
        self::assertResponseIsSuccessful();
        self::assertSame('Flüssigseife fehlt', $crawler->filter('#report_title')->attr('value'));
        self::assertSame('liste', $crawler->filter('input[name="report[followUp]"]:checked')->attr('value'));
        self::assertSame('Flüssigseife', $crawler->filter('#report_itemName')->attr('value'));
        $client->submit($crawler->selectButton('Melden')->form());
        self::assertResponseRedirects('/', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Gemeldet. Die Admins wissen Bescheid.');

        $this->switchTo($client, $admin);
        $client->request('GET', '/');
        self::assertSelectorTextContains('.hc-today__reports', 'Meldungen: 1 offen');
        self::assertSelectorTextContains('.hc-badge', '1 neu');
        self::assertSelectorTextContains('#neu + .hc-notifications', 'Neue Meldung: Flüssigseife fehlt');
        $client->request('GET', '/routinen');
        self::assertSelectorTextContains('.hc-task-list', 'Meldung offen');

        $this->switchTo($client, $sister);
        $crawler = $client->request('GET', '/listen/'.$family->getId());
        self::assertSelectorTextContains('#auf-der-liste + .hc-task-list', 'Aus einer Meldung');
        $client->submit($crawler->filter('button:contains("Abhaken: Flüssigseife")')->form());

        $report = $this->entityManager()->getConnection()->fetchAssociative('SELECT kind, status, closed_by_user_id, close_note, created_by_device_id FROM issue_report');
        self::assertIsArray($report);
        self::assertSame(['nicht_moeglich', 'erledigt', null, 'automatisch: Eintrag abgehakt'], [$report['kind'], $report['status'], $report['closed_by_user_id'], $report['close_note']]);
        self::assertNotNull($report['created_by_device_id']);

        $this->switchTo($client, $admin);
        $client->request('GET', '/');
        self::assertSelectorNotExists('.hc-today__reports');
        $client->request('GET', '/meldungen?status=abgeschlossen');
        self::assertSelectorTextContains('.hc-task-list', 'Flüssigseife fehlt');
        self::assertSelectorTextContains('.hc-task-list', 'Von: Team-Tablet');
    }

    /**
     * Scenario A5: "Melden" → kaputt → "Glühbirne Bad" without a task.
     */
    public function testScenarioA5ReportWithoutTask(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $client->loginUser($anna);

        $crawler = $client->request('GET', '/');
        $crawler = $client->click($crawler->selectLink('Melden')->link());
        $crawler = $client->click($crawler->selectLink('Kaputt')->link());
        self::assertSelectorTextContains('h1', 'Melden: Kaputt');
        $client->submit($crawler->selectButton('Melden')->form(), ['report[title]' => 'Glühbirne Bad']);

        self::assertResponseRedirects('/', 303);
        $report = $this->entityManager()->getConnection()->fetchAssociative('SELECT kind, title, task_id, follow_up, status, visibility, created_by_user_id FROM issue_report');
        self::assertIsArray($report);
        self::assertSame(['kaputt', 'Glühbirne Bad', null, null, 'offen', 'alle', $anna->getId()], [...array_values(\array_slice($report, 0, 6)), (int) $report['created_by_user_id']]);
    }

    public function testASimilarTitleOffersAlsoNoticedInsteadOfASecondReport(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $this->report('Glühbirne Bad', $anna);
        $this->pairTablet($client);

        $client->request('GET', '/melden/aehnlich?titel=Glühbirne');
        self::assertSelectorTextContains('turbo-frame#aehnliche-meldungen', 'Glühbirne Bad');
        $client->request('GET', '/melden/aehnlich?titel=Waschmittel');
        self::assertSelectorNotExists('turbo-frame#aehnliche-meldungen .hc-similar');

        $crawler = $client->request('GET', '/melden/kaputt');
        $crawler = $client->submit($crawler->selectButton('Melden')->form(), ['report[title]' => 'Glühbirne im Bad']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-similar', 'Glühbirne Bad');
        self::assertSame(1, $this->countReports());

        $client->submit($crawler->filter('.hc-similar form')->form());
        self::assertResponseRedirects('/', 303);
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-alert', 'Danke – „Glühbirne Bad“ ist als „auch bemerkt“ vermerkt.');
        self::assertSame([1, 1], [$this->countReports(), (int) $this->entityManager()->getConnection()->fetchOne('SELECT confirm_count FROM issue_report')]);

        // Something else after all: "Trotzdem neu melden" keeps what was typed
        $crawler = $client->request('GET', '/melden/kaputt');
        $crawler = $client->submit($crawler->selectButton('Melden')->form(), ['report[title]' => 'Glühbirne Küche']);
        $client->submit($crawler->selectButton('Trotzdem neu melden')->form());
        self::assertResponseRedirects('/', 303);
        self::assertSame(2, $this->countReports());
    }

    public function testNotPossibleGivesExactlyOneReportAlsoWhenSentTwice(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $task = $this->soapOccurrence($admin, null);
        $client->loginUser($anna);

        $crawler = $client->request('GET', '/aufgaben/'.$task->getId().'/nicht-moeglich');
        self::assertSame('keine', $crawler->filter('input[name="report[followUp]"]:checked')->attr('value'));
        $form = $crawler->selectButton('Melden')->form(['report[title]' => 'Seife leer']);
        $client->submit($form);
        self::assertResponseRedirects('/', 303);
        $client->submit($form);
        self::assertResponseRedirects('/aufgaben/'.$task->getId(), 303);
        $client->followRedirect();
        self::assertAnySelectorTextContains('.hc-alert', '„Seifenspender auffüllen“ ist schon abgeschlossen.');
        self::assertSelectorTextContains('.hc-page-header', 'Nicht möglich');
        self::assertSelectorTextContains('.hc-facts', 'Seife leer (Offen)');

        self::assertSame(1, $this->countReports());
        self::assertSame('nicht_moeglich', $this->entityManager()->getConnection()->fetchOne('SELECT status FROM task WHERE id = ?', [$task->getId()]));
    }

    /**
     * A private task stays private: "nicht möglich" there starts as a report for admins only, and the admins do not
     * see the task's title (konzept-2 §5.3).
     */
    public function testNotPossibleAtAPrivateTaskStaysWithTheAdminsWithoutItsTitle(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $task = new Task('Geschenk für Max besorgen', TaskCategory::Einkauf, new \DateTimeImmutable('2026-11-04'), $papa);
        $task->assign($papa, null);
        $task->setVisibility(Visibility::Privat);
        $this->entityManager()->persist($task);
        $this->entityManager()->flush();
        $client->loginUser($papa);

        $crawler = $client->request('GET', '/aufgaben/'.$task->getId().'/nicht-moeglich');
        self::assertSame('checked', $crawler->filter('#report_adminsOnly')->attr('checked'));
        $client->submit($crawler->selectButton('Melden')->form(), ['report[title]' => 'Laden zu']);
        self::assertResponseRedirects('/', 303);
        self::assertSame('admins', $this->entityManager()->getConnection()->fetchOne('SELECT visibility FROM issue_report'));

        $client->loginUser($admin);
        $publicId = Uuid::fromBinary((string) $this->entityManager()->getConnection()->fetchOne('SELECT public_id FROM issue_report'))->toRfc4122();
        $client->request('GET', '/meldungen/'.$publicId);
        self::assertSelectorTextContains('.hc-facts', 'eine private Aufgabe');
        self::assertSelectorTextNotContains('.hc-facts', 'Geschenk');
        self::assertSame('Neue Meldung (nur für Admins)', $this->entityManager()->getConnection()->fetchOne('SELECT title FROM notification WHERE user_id = ?', [$admin->getId()]));
    }

    public function testTheListEntryFitsAndReportsForAdminsNameItThemselves(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $list = $this->list('Einkauf');
        $client->loginUser($anna);
        $fields = ['report[followUp]' => 'liste', 'report[list]' => (string) $list->getId()];

        $crawler = $client->request('GET', '/melden/leer');
        $client->submit($crawler->selectButton('Melden')->form(), ['report[title]' => str_repeat('Sehr lange Beschreibung ', 5), ...$fields]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-field--error', 'Bitte schreib kürzer, was auf die Liste soll (höchstens 100 Zeichen).');

        $client->submit($crawler->selectButton('Melden')->form(), ['report[title]' => 'Geheimes', 'report[adminsOnly]' => '1', ...$fields]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-field--error', 'Bitte schreib, was auf die Liste soll – den Eintrag sehen alle.');

        $client->submit($crawler->selectButton('Melden')->form(), ['report[title]' => 'Geheimes', 'report[adminsOnly]' => '1', 'report[itemName]' => 'Batterien', ...$fields]);
        self::assertResponseRedirects('/', 303);
        self::assertSame(['Batterien'], $this->entityManager()->getConnection()->fetchFirstColumn('SELECT name FROM shopping_item'));
        self::assertSame(1, $this->countReports());
    }

    public function testOpenReportsShowNoSenderAndReportsForAdminsStayHidden(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $this->report('Seife leer', $anna);
        $this->report('Ladegerät lädt nicht', $papa, ReportVisibility::Admins);

        foreach ([$anna, $papa, null] as $viewer) {
            $this->switchTo($client, $viewer);
            $client->request('GET', '/meldungen');
            self::assertResponseIsSuccessful();
            $list = $client->getCrawler()->filter('.hc-task-list')->text();
            self::assertStringContainsString('Seife leer', $list);
            self::assertStringNotContainsString('Ladegerät', $list);
            self::assertStringNotContainsString('Anna', $list);
            self::assertStringNotContainsString('Von:', $list);
            self::assertSelectorNotExists('.hc-filter');
        }

        $this->switchTo($client, $admin);
        $client->request('GET', '/meldungen');
        $list = $client->getCrawler()->filter('.hc-task-list')->text();
        self::assertStringContainsString('Ladegerät lädt nicht', $list);
        self::assertStringContainsString('Von: Anna', $list);
        self::assertStringContainsString('Von: Papa', $list);
        self::assertSelectorExists('.hc-filter');
    }

    public function testTheReportLogIsForAdminsOnly(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $anna = $this->createPerson(UserRole::Assistenz, 'anna');
        $report = $this->report('Seife leer', $anna);
        $forAdmins = $this->report('Ladegerät lädt nicht', $admin, ReportVisibility::Admins);
        $path = '/meldungen/'.$report->getPublicId()->toRfc4122();

        foreach ([$anna, null] as $viewer) {
            $this->switchTo($client, $viewer);
            foreach (['GET' => [$path, $path.'/weitergeben'], 'POST' => [$path.'/schliessen']] as $method => $paths) {
                foreach ($paths as $page) {
                    $client->request($method, $page, 'POST' === $method ? ['status' => 'verworfen'] : []);
                    self::assertResponseStatusCodeSame(403, $method.' '.$page);
                }
            }
            // A report for admins only does not exist for them – not even for "Auch bemerkt"
            $client->request('POST', '/meldungen/'.$forAdmins->getPublicId()->toRfc4122().'/auch-bemerkt', ['_token' => 'x']);
            self::assertResponseStatusCodeSame(404);
        }

        $this->switchTo($client, $admin);
        $crawler = $client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.hc-history', 'gemeldet von Anna');
        $client->submit($crawler->selectButton('Verwerfen')->form(), ['notiz' => 'Doppelt gemeldet']);
        self::assertResponseRedirects($path, 303);
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-page-header', 'Verworfen');
        self::assertSelectorTextContains('.hc-history', 'verworfen von Max – Doppelt gemeldet');
        self::assertSelectorNotExists('form[action$="/schliessen"]');
    }

    public function testAdminsHandAReportOnAsTask(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $papa = $this->createPerson(UserRole::Angehoerige, 'papa');
        $report = $this->report('Glühbirne Bad', $admin);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/meldungen/'.$report->getPublicId()->toRfc4122().'/weitergeben');
        $client->submit($crawler->selectButton('Weitergeben')->form(), [
            'report[followUp]' => 'aufgabe',
            'report[assignee]' => 'person-'.$papa->getPublicId()->toRfc4122(),
            'report[dueDate]' => '2026-11-06',
        ]);
        $client->followRedirect();

        self::assertSelectorTextContains('.hc-facts', 'Glühbirne Bad (Aufgabe für: Papa)');
        $connection = $this->entityManager()->getConnection();
        self::assertSame(['in_arbeit', 'aufgabe'], array_values((array) $connection->fetchAssociative('SELECT status, follow_up FROM issue_report')));
        self::assertSame(['Glühbirne Bad', '2026-11-06'], array_values((array) $connection->fetchAssociative('SELECT title, due_date FROM task')));
        self::assertSame('Aufgabe für dich: Glühbirne Bad', $connection->fetchOne('SELECT title FROM notification WHERE user_id = ?', [$papa->getId()]));
    }

    public function testThePlanKeepsItsSuggestionForNotPossible(): void
    {
        $client = $this->startClient();
        self::mockTime(self::NOW);
        $admin = $this->createAdminWithTwoFactor(UserRole::Manager, 'max');
        $family = $this->list('Besorgt die Familie');
        $task = $this->soapOccurrence($admin, null);
        $plan = $task->getTemplate();
        self::assertNotNull($plan);
        $client->loginUser($admin);

        $crawler = $client->request('GET', '/routinen/'.$plan->getId().'/bearbeiten');
        $client->submit($crawler->selectButton('Speichern')->form(), ['plan[unableItemName]' => 'Flüssigseife']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.hc-field--error', 'Bitte wähle die Liste dazu.');

        $client->submit($crawler->selectButton('Speichern')->form(), ['plan[unableList]' => (string) $family->getId(), 'plan[unableItemName]' => 'Flüssigseife']);
        $client->followRedirect();
        self::assertSelectorTextContains('.hc-facts', '„Flüssigseife“ auf die Liste „Besorgt die Familie“');
    }

    private function countReports(): int
    {
        return (int) $this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM issue_report');
    }

    /**
     * A person – or with null the team tablet (paired once per test).
     */
    private function switchTo(KernelBrowser $client, ?AppUser $person): void
    {
        $client->getCookieJar()->clear();
        if (null !== $person) {
            $client->loginUser($person);

            return;
        }
        $device = $this->entityManager()->getRepository(Device::class)->findOneBy(['tokenHash' => DeviceCookie::hash(self::TABLET_TOKEN)]);
        if (null === $device) {
            $this->pairTablet($client);

            return;
        }
        $client->getCookieJar()->set(new Cookie(DeviceCookie::NAME, self::TABLET_TOKEN, null, '/', 'localhost'));
    }

    private function list(string $name, ?AppUser $assignee = null): ShoppingList
    {
        $list = new ShoppingList($name);
        $list->setAssigneeUser($assignee);
        $this->entityManager()->persist($list);
        $this->entityManager()->flush();

        return $list;
    }

    /**
     * Today's occurrence of the daily plan "Seifenspender auffüllen" – with the suggestion "Flüssigseife" on the list.
     */
    private function soapOccurrence(AppUser $creator, ?ShoppingList $list): Task
    {
        $plan = new TaskTemplate('Seifenspender auffüllen', TaskCategory::Haushalt, RecurrenceType::Kalender, 'FREQ=DAILY', new \DateTimeImmutable('2026-11-04'), $creator);
        $plan->assign(null, AssigneeRole::Assistenz);
        $plan->suggestWhenUnable($list, null === $list ? null : 'Flüssigseife');
        $this->entityManager()->persist($plan);
        $this->entityManager()->flush();
        static::getContainer()->get(PlanSync::class)->sync($plan);

        return static::getContainer()->get(TaskRepository::class)->findFirstOpenOccurrence($plan) ?? throw new \LogicException('No occurrence.');
    }

    private function report(string $title, AppUser $by, ReportVisibility $visibility = ReportVisibility::Alle): IssueReport
    {
        $report = new IssueReport(ReportKind::Kaputt, $title, $by);
        $report->describe(null, false, $visibility);
        $this->entityManager()->persist($report);
        $this->entityManager()->flush();

        return $report;
    }
}
