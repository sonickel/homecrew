<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\IssueReport;
use App\Entity\Notification;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Entity\Task;
use App\Entity\TaskTemplate;
use App\Enum\AssigneeRole;
use App\Enum\CompletionAction;
use App\Enum\RecurrenceType;
use App\Enum\ReportFollowUp;
use App\Enum\ReportKind;
use App\Enum\ReportStatus;
use App\Enum\ShoppingSource;
use App\Enum\TaskCategory;
use App\Enum\TaskStatus;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Form\Report\ReportData;
use App\Repository\TaskCompletionRepository;
use App\Repository\TaskRepository;
use App\Service\Report\IssueReports;
use App\Service\Report\ReportClosing;
use App\Service\Shopping\ShoppingItems;
use App\Service\Task\Assignment;
use App\Service\Task\PlanSync;
use App\Service\Task\TaskActions;
use App\Service\Viewer\Viewer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Reports (konzept-13): exactly one per "nicht möglich", notifications for the admins and whoever takes care of the
 * follow-up, closing on their own and opening again, "Auch bemerkt", handing on and closing by admins.
 */
final class IssueReportsTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private const string TODAY = '2026-11-04';

    private AppUser $max;
    private AppUser $sister;
    private AppUser $papa;
    private AppUser $anna;
    private Device $tablet;
    private ShoppingList $family;

    protected function setUp(): void
    {
        self::mockTime(self::TODAY.' 09:00:00 UTC');
        $this->max = $this->person(UserRole::Manager, 'max', admin: true);
        $this->sister = $this->person(UserRole::Angehoerige, 'schwester', admin: true);
        $this->papa = $this->person(UserRole::Angehoerige, 'papa');
        $this->anna = $this->person(UserRole::Assistenz, 'anna');
        $this->tablet = new Device('Flur', hash('sha256', 'reports'));
        $this->family = new ShoppingList('Besorgt die Familie');
        $this->family->setAssigneeUser($this->sister);
        $this->entityManager()->persist($this->tablet);
        $this->entityManager()->persist($this->family);
        $this->entityManager()->flush();
    }

    public function testUnableCreatesExactlyOneReportAlsoWhenSentTwice(): void
    {
        $task = $this->soapOccurrence();
        $viewer = Viewer::teamTablet($this->tablet);

        $report = $this->reports()->reportUnable($task, $this->onList('Seife leer', 'Flüssigseife'), $viewer);
        $second = $this->reports()->reportUnable($task, $this->onList('Seife leer', 'Flüssigseife'), $viewer);

        self::assertNotNull($report);
        self::assertNull($second);
        self::assertSame(1, $this->entityManager()->getRepository(IssueReport::class)->count([]));
        self::assertSame([ReportKind::NichtMoeglich, ReportStatus::InArbeit, ReportFollowUp::Liste], [$report->getKind(), $report->getStatus(), $report->getFollowUp()]);
        self::assertSame($task, $report->getTask());
        self::assertNull($report->getCreatedBy());
        self::assertSame($this->tablet, $report->getCreatedByDevice());
        self::assertSame(TaskStatus::NichtMoeglich, $task->getStatus());
        self::assertSame('Seife leer', static::getContainer()->get(TaskCompletionRepository::class)->findForTask($task)?->getComment());

        $item = $report->getFollowUpItem();
        self::assertNotNull($item);
        self::assertSame(['Flüssigseife', ShoppingSource::Meldung, $task, $this->tablet], [$item->getName(), $item->getSource(), $item->getSourceTask(), $item->getCreatedByDevice()]);
    }

    public function testUnableCannotBeUndone(): void
    {
        $task = $this->soapOccurrence();
        $viewer = Viewer::person($this->anna, false);
        $this->reports()->reportUnable($task, $this->onList('Seife leer', 'Flüssigseife'), $viewer);

        self::assertFalse($this->actions()->undo($task, $viewer));
        self::assertSame(TaskStatus::NichtMoeglich, $task->getStatus());
    }

    public function testAdminsAreToldButNeverTheReporterAndNobodyElse(): void
    {
        $data = $this->data('Ladegerät lädt nicht');
        $data->urgent = true;

        $report = $this->reports()->report(ReportKind::Kaputt, $data, Viewer::person($this->sister, true));

        self::assertSame(ReportStatus::Offen, $report->getStatus());
        self::assertSame(['max' => ['Dringende Meldung: Ladegerät lädt nicht', true]], $this->notifications());
    }

    public function testWhoTakesCareOfTheListIsToldOncePerUnreadNotification(): void
    {
        $viewer = Viewer::teamTablet($this->tablet);
        $this->reports()->report(ReportKind::Leer, $this->onList('Seife leer', 'Flüssigseife'), $viewer);
        $this->reports()->report(ReportKind::Kaputt, $this->onList('Glühbirne Flur', 'Glühbirne E27'), $viewer);

        /** @var list<Notification> $forSister */
        $forSister = $this->entityManager()->getRepository(Notification::class)->findBy(['user' => $this->sister, 'link' => '/listen/'.$this->family->getId()]);
        self::assertCount(1, $forSister);
        self::assertSame('Neu auf deiner Liste „Besorgt die Familie“', $forSister[0]->getTitle());
        self::assertSame('Flüssigseife, Glühbirne E27', $forSister[0]->getBody());
    }

    public function testTickingOffClosesTheReportAndTakingItBackOpensIt(): void
    {
        $report = $this->reports()->report(ReportKind::Leer, $this->onList('Seife leer', 'Flüssigseife'), Viewer::person($this->anna, false));
        $item = $report->getFollowUpItem();
        self::assertNotNull($item);

        $this->items()->check($item);

        self::assertSame(ReportStatus::Erledigt, $report->getStatus());
        self::assertTrue($report->isClosedAutomatically());
        self::assertSame(ReportClosing::NOTE_ITEM_CHECKED, $report->getCloseNote());
        self::assertSame('2027-11-04', $report->getDeleteAfter()?->format('Y-m-d'));

        $this->items()->uncheck($item);

        self::assertSame(ReportStatus::InArbeit, $report->getStatus());
        self::assertNull($report->getClosedAt());
        self::assertNull($report->getDeleteAfter());
    }

    public function testRemovingTheEntryLeavesTheReportOpenForTheAdmins(): void
    {
        $report = $this->reports()->report(ReportKind::Leer, $this->onList('Seife leer', 'Flüssigseife'), Viewer::person($this->anna, false));
        $item = $report->getFollowUpItem();
        self::assertNotNull($item);

        $this->items()->remove($item);

        self::assertSame(ReportStatus::Offen, $report->getStatus());
        self::assertNull($report->getFollowUp());
    }

    public function testAnOpenEntryOfThatNameIsLinkedInsteadOfAddedTwice(): void
    {
        $viewer = Viewer::person($this->anna, false);
        $first = $this->reports()->report(ReportKind::Leer, $this->onList('Seife leer', 'Flüssigseife'), $viewer);
        $second = $this->reports()->report(ReportKind::Fehlt, $this->onList('Keine Seife im Bad', 'flüssigseife'), $viewer);

        self::assertSame($first->getFollowUpItem(), $second->getFollowUpItem());
        self::assertSame(1, $this->entityManager()->getRepository(ShoppingItem::class)->count([]));
    }

    public function testTheFollowUpTaskClosesItsReportAndTellsItsPerson(): void
    {
        $data = $this->data('Glühbirne Bad');
        $data->followUp = ReportData::FOLLOW_TASK;
        $data->assignee = Assignment::person($this->papa);
        $data->adminsOnly = true;

        $report = $this->reports()->report(ReportKind::Kaputt, $data, Viewer::teamTablet($this->tablet));
        $task = $report->getFollowUpTask();

        self::assertNotNull($task);
        self::assertSame(['Glühbirne Bad', $this->papa, Visibility::Zustaendig, TaskCategory::Sonstiges], [$task->getTitle(), $task->getAssigneeUser(), $task->getVisibility(), $task->getCategory()]);
        self::assertSame(ReportStatus::InArbeit, $report->getStatus());
        self::assertSame(['Aufgabe für dich: Glühbirne Bad', false], $this->notifications()['papa']);

        $papa = Viewer::person($this->papa, false);
        $this->actions()->close($task, CompletionAction::Erledigt, $papa);
        self::assertSame(ReportStatus::Erledigt, $report->getStatus());
        self::assertSame(ReportClosing::NOTE_TASK_DONE, $report->getCloseNote());

        $this->actions()->undo($task, $papa);
        self::assertSame(ReportStatus::InArbeit, $report->getStatus());
    }

    public function testAFailedFollowUpOpensTheReportAgainForTheAdmins(): void
    {
        $data = $this->data('Glühbirne Bad');
        $data->followUp = ReportData::FOLLOW_TASK;
        $data->assignee = Assignment::person($this->papa);
        $report = $this->reports()->report(ReportKind::Kaputt, $data, Viewer::person($this->anna, false));
        $task = $report->getFollowUpTask();
        self::assertNotNull($task);
        $papa = Viewer::person($this->papa, false);

        $this->actions()->close($task, CompletionAction::Uebersprungen, $papa);
        self::assertSame([ReportStatus::Offen, $task], [$report->getStatus(), $report->getFollowUpTask()]);

        $this->actions()->undo($task, $papa);
        self::assertSame(ReportStatus::InArbeit, $report->getStatus());

        $listed = $this->reports()->report(ReportKind::Leer, $this->onList('Seife leer', 'Flüssigseife'), Viewer::person($this->anna, false));
        $this->family->setActive(false);
        static::getContainer()->get(ReportClosing::class)->listSwitchedOff($this->family);
        self::assertSame(ReportStatus::Offen, $listed->getStatus());
        self::assertNotNull($listed->getFollowUpItem());
    }

    public function testAlsoNoticedCountsUntilTheReportIsClosed(): void
    {
        $report = $this->reports()->report(ReportKind::Leer, $this->data('Seife leer'), Viewer::person($this->anna, false));

        self::assertTrue($this->reports()->confirm($report));
        self::assertTrue($this->reports()->confirm($report));
        self::assertSame(2, $report->getConfirmCount());

        self::assertTrue($this->reports()->close($report, ReportStatus::Verworfen, ' Doppelt ', $this->max));
        self::assertFalse($this->reports()->confirm($report));
        self::assertSame([ReportStatus::Verworfen, $this->max, 'Doppelt', '2027-11-04'], [$report->getStatus(), $report->getClosedBy(), $report->getCloseNote(), $report->getDeleteAfter()?->format('Y-m-d')]);
        self::assertFalse($this->reports()->close($report, ReportStatus::Erledigt, null, $this->max));
    }

    public function testAdminsHandAReportOn(): void
    {
        $report = $this->reports()->report(ReportKind::Leer, $this->data('Waschmittel leer'), Viewer::person($this->anna, false));
        $data = $this->onList('Waschmittel leer', '');

        self::assertTrue($this->reports()->forward($report, $data, Viewer::person($this->max, true)));

        self::assertSame(ReportStatus::InArbeit, $report->getStatus());
        self::assertSame('Waschmittel leer', $report->getFollowUpItem()?->getName());
        self::assertSame($this->max, $report->getFollowUpItem()->getCreatedBy());
    }

    private function soapOccurrence(): Task
    {
        $plan = new TaskTemplate('Seifenspender auffüllen', TaskCategory::Haushalt, RecurrenceType::Kalender, 'FREQ=DAILY', new \DateTimeImmutable(self::TODAY), $this->max);
        $plan->assign(null, AssigneeRole::Assistenz);
        $plan->suggestWhenUnable($this->family, 'Flüssigseife');
        $this->entityManager()->persist($plan);
        $this->entityManager()->flush();
        static::getContainer()->get(PlanSync::class)->sync($plan);

        return static::getContainer()->get(TaskRepository::class)->findFirstOpenOccurrence($plan) ?? throw new \LogicException('No occurrence.');
    }

    private function data(string $title): ReportData
    {
        $data = new ReportData(self::TODAY);
        $data->title = $title;

        return $data;
    }

    private function onList(string $title, string $item): ReportData
    {
        $data = $this->data($title);
        $data->followUp = ReportData::FOLLOW_LIST;
        $data->list = $this->family;
        $data->itemName = $item;

        return $data;
    }

    /**
     * @return array<string, array{string, bool}> username => [title, urgent] of their notification
     */
    private function notifications(): array
    {
        $result = [];
        foreach ($this->entityManager()->getRepository(Notification::class)->findAll() as $notification) {
            $result[$notification->getUser()->getUsername()] = [$notification->getTitle(), $notification->isUrgent()];
        }

        return $result;
    }

    private function person(UserRole $role, string $username, bool $admin = false): AppUser
    {
        $person = new AppUser($role, $username, ucfirst($username), new \DateTimeImmutable('2026-01-01'), $admin);
        $person->changePassword('not-a-real-hash');
        $this->entityManager()->persist($person);

        return $person;
    }

    private function reports(): IssueReports
    {
        return static::getContainer()->get(IssueReports::class);
    }

    private function items(): ShoppingItems
    {
        return static::getContainer()->get(ShoppingItems::class);
    }

    private function actions(): TaskActions
    {
        return static::getContainer()->get(TaskActions::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
