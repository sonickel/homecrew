<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\AssigneeRole;
use App\Enum\CompletionAction;
use App\Enum\TaskStatus;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Form\Task\TaskData;
use App\Repository\TaskCompletionRepository;
use App\Service\Task\Assignment;
use App\Service\Task\TaskActions;
use App\Service\Viewer\Viewer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * Creating, closing, undoing, postponing and handing on tasks – with the person or the team tablet as actor.
 */
final class TaskActionsTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private AppUser $anna;
    private AppUser $ben;
    private AppUser $papa;
    private Device $tablet;

    protected function setUp(): void
    {
        self::mockTime('2026-11-03 09:00:00 UTC');
        $this->anna = new AppUser(UserRole::Assistenz, 'anna', 'Anna', new \DateTimeImmutable('2026-01-01'));
        $this->ben = new AppUser(UserRole::Assistenz, 'ben', 'Ben', new \DateTimeImmutable('2026-01-01'));
        $this->papa = new AppUser(UserRole::Angehoerige, 'papa', 'Papa', new \DateTimeImmutable('2026-01-01'));
        $this->tablet = new Device('Flur', hash('sha256', 'actions'));
        foreach ([$this->anna, $this->ben, $this->papa, $this->tablet] as $entity) {
            $this->entityManager()->persist($entity);
        }
        $this->entityManager()->flush();
    }

    public function testTheTabletCreatesAndCompletesWithoutAPerson(): void
    {
        $viewer = Viewer::teamTablet($this->tablet);
        $task = $this->actions()->create($this->data('Blumen gießen'), $viewer);

        self::assertTrue($this->actions()->close($task, CompletionAction::Erledigt, $viewer));

        $completion = $this->completions()->findForTask($task);
        self::assertNull($task->getCreatedBy());
        self::assertSame(TaskStatus::Erledigt, $task->getStatus());
        self::assertNotNull($completion);
        self::assertNull($completion->getUser());
        self::assertSame($this->tablet, $completion->getDevice());
        self::assertSame('2026-11-03 09:00:00', $completion->getDoneAt()->format('Y-m-d H:i:s'));
    }

    public function testAClosedTaskIsNotClosedTwice(): void
    {
        $task = $this->actions()->create($this->data('Müll rausbringen'), Viewer::person($this->anna, false));

        self::assertTrue($this->actions()->close($task, CompletionAction::Erledigt, Viewer::person($this->anna, false)));
        self::assertFalse($this->actions()->close($task, CompletionAction::Uebersprungen, Viewer::teamTablet($this->tablet)));
        self::assertSame(CompletionAction::Erledigt, $this->completions()->findForTask($task)?->getAction());
    }

    public function testUndoRestoresTheTaskWithin40Seconds(): void
    {
        $viewer = Viewer::teamTablet($this->tablet);
        $task = $this->actions()->create($this->data('Wäsche'), $viewer);
        $this->actions()->close($task, CompletionAction::Uebersprungen, $viewer);

        self::mockTime('2026-11-03 09:00:40 UTC');
        self::assertTrue($this->actions()->undo($task, $viewer));

        self::assertTrue($task->isOpen());
        self::assertNull($this->completions()->findForTask($task));
    }

    public function testUndoIsRefusedLaterOrForSomebodyElse(): void
    {
        $anna = Viewer::person($this->anna, false);
        $task = $this->actions()->create($this->data('Wäsche'), $anna);
        $this->actions()->close($task, CompletionAction::Erledigt, $anna);

        self::assertFalse($this->actions()->undo($task, Viewer::person($this->ben, false)));
        self::assertFalse($this->actions()->undo($task, Viewer::teamTablet($this->tablet)));
        self::mockTime('2026-11-03 09:00:41 UTC');
        self::assertFalse($this->actions()->undo($task, $anna));
        self::assertSame(TaskStatus::Erledigt, $task->getStatus());
    }

    public function testHandingOnNotesThePersonOrTheTablet(): void
    {
        $task = $this->actions()->create($this->data('Fenster putzen'), Viewer::person($this->anna, false));

        $this->actions()->handOver($task, Assignment::person($this->ben), Viewer::person($this->anna, false));
        self::assertSame([$this->ben, null, $this->anna, null], [$task->getAssigneeUser(), $task->getAssigneeRole(), $task->getAssignedBy(), $task->getAssignedByDevice()]);

        self::mockTime('2026-11-03 10:00:00 UTC');
        $this->actions()->handOver($task, Assignment::role(AssigneeRole::Admins), Viewer::teamTablet($this->tablet));
        self::assertSame([null, AssigneeRole::Admins, null, $this->tablet], [$task->getAssigneeUser(), $task->getAssigneeRole(), $task->getAssignedBy(), $task->getAssignedByDevice()]);
        self::assertSame('2026-11-03 10:00:00', $task->getAssignedAt()?->format('Y-m-d H:i:s'));
    }

    public function testChangingTheResponsibilityCountsAsHandingOn(): void
    {
        $papa = Viewer::person($this->papa, false);
        $task = $this->actions()->create($this->data('Rezept abholen'), $papa);
        self::assertNull($task->getAssignedAt());

        $data = TaskData::fromTask($task, '2026-11-03');
        $data->title = 'Rezept in der Apotheke abholen';
        $this->actions()->update($task, $data, $papa);
        self::assertNull($task->getAssignedAt());

        $data->assignee = Assignment::role(AssigneeRole::Assistenz);
        $this->actions()->update($task, $data, $papa);
        self::assertSame([AssigneeRole::Assistenz, $this->papa], [$task->getAssigneeRole(), $task->getAssignedBy()]);
    }

    public function testAPrivateTaskAlwaysBelongsToItsCreator(): void
    {
        $papa = Viewer::person($this->papa, false);
        $data = $this->data('Geschenk für Max');
        $data->visibility = Visibility::Privat;
        $data->assignee = Assignment::role(AssigneeRole::Assistenz);

        $task = $this->actions()->create($data, $papa);

        self::assertSame($this->papa, $task->getAssigneeUser());
        $this->expectException(\DomainException::class);
        $this->actions()->handOver($task, Assignment::person($this->anna), $papa);
    }

    public function testAnAdminDoesNotMakeSomebodyElsesTaskPrivate(): void
    {
        $papa = Viewer::person($this->papa, false);
        $task = $this->actions()->create($this->data('Rezept abholen'), Viewer::person($this->anna, false));
        $data = TaskData::fromTask($task, '2026-11-03');
        $data->visibility = Visibility::Privat;

        $this->expectException(\DomainException::class);
        $this->actions()->update($task, $data, $papa);
    }

    public function testChangesOfAClosedTaskAreRefused(): void
    {
        $anna = Viewer::person($this->anna, false);
        $task = $this->actions()->create($this->data('Fenster putzen'), $anna);
        $this->actions()->close($task, CompletionAction::Erledigt, $anna);

        self::assertFalse($this->actions()->postpone($task, new \DateTimeImmutable('2026-11-05')));
        self::assertFalse($this->actions()->handOver($task, Assignment::person($this->ben), $anna));
        self::assertFalse($this->actions()->update($task, TaskData::fromTask($task, '2026-11-03'), $anna));
        self::assertSame(['2026-11-03', null], [$task->getDueDate()->format('Y-m-d'), $task->getAssigneeUser()]);
    }

    public function testAssistantsKeepNoPrivateTasks(): void
    {
        $data = $this->data('Geheim');
        $data->visibility = Visibility::Privat;

        $this->expectException(\DomainException::class);
        $this->actions()->create($data, Viewer::person($this->anna, false));
    }

    public function testShowFromADaySetsWindowAndShowDate(): void
    {
        $data = $this->data('Geschenk besorgen');
        $data->dueDate = new \DateTimeImmutable('2026-11-06');
        $data->showFrom = TaskData::SHOW_FROM;
        $data->showFromDate = new \DateTimeImmutable('2026-11-04');

        $task = $this->actions()->create($data, Viewer::person($this->papa, false));

        self::assertSame(['2026-11-04', '2026-11-04'], [$task->getVisibleFrom()?->format('Y-m-d'), $task->getWindowStart()?->format('Y-m-d')]);
        self::assertSame(TaskData::SHOW_FROM, TaskData::fromTask($task, '2026-11-03')->showFrom);
    }

    private function data(string $title): TaskData
    {
        $data = new TaskData('2026-11-03');
        $data->title = $title;
        $data->dueDate = new \DateTimeImmutable('2026-11-03');
        $data->assignee = Assignment::present();

        return $data;
    }

    private function actions(): TaskActions
    {
        return static::getContainer()->get(TaskActions::class);
    }

    private function completions(): TaskCompletionRepository
    {
        return static::getContainer()->get(TaskCompletionRepository::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
