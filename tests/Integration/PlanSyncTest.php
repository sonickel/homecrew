<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\Task;
use App\Entity\TaskTemplate;
use App\Enum\AssigneeRole;
use App\Enum\CompletionAction;
use App\Enum\CreatedVia;
use App\Enum\RecurrenceType;
use App\Enum\TaskCategory;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Repository\TaskCompletionRepository;
use App\Repository\TaskRepository;
use App\Service\Series\Rhythm;
use App\Service\Task\Assignment;
use App\Service\Task\PlanSync;
use App\Service\Task\TaskActions;
use App\Service\Viewer\Viewer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

/**
 * The occurrences of plans (decisions M6): look-ahead without duplicates, occurrences changed one by one stay,
 * n days after completion, missed ones replaced, pause and end – Tuesday 3 November 2026, 10:00 household time.
 */
final class PlanSyncTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    private AppUser $max;
    private AppUser $anna;
    private Device $tablet;

    protected function setUp(): void
    {
        self::mockTime('2026-11-03 09:00:00 UTC');
        $this->max = new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), isAdmin: true);
        $this->anna = new AppUser(UserRole::Assistenz, 'anna', 'Anna', new \DateTimeImmutable('2026-01-01'));
        $this->tablet = new Device('Flur', hash('sha256', 'plans'));
        foreach ([$this->max, $this->anna, $this->tablet] as $entity) {
            $this->entityManager()->persist($entity);
        }
        $this->entityManager()->flush();
    }

    public function testEveryMondayCreatesTheLookAheadAndASecondRunNoDuplicates(): void
    {
        $plan = $this->calendarPlan('Bettwäsche wechseln', Rhythm::weekly(['MO']), '2026-11-02', time: '10:00');

        $this->sync($plan);
        $this->sync($plan);

        self::assertSame(['2026-11-09 → 2026-11-09', '2026-11-16 → 2026-11-16'], $this->openDays($plan));
        $first = $this->open($plan)[0];
        self::assertSame(['10:00', '2026-11-09', CreatedVia::System], [$first->getDueTime()?->format('H:i'), $first->getVisibleFrom()?->format('Y-m-d'), $first->getCreatedVia()]);
        self::assertSame($this->max, $first->getCreatedBy());
        self::assertSame(AssigneeRole::Assistenz, $first->getAssigneeRole());
        self::assertSame('2026-11-09', $plan->getNextDueOn()?->format('Y-m-d'));
    }

    public function testAtLeastTheNextOccurrenceEvenFarAhead(): void
    {
        $plan = $this->calendarPlan('Rauchmelder testen', Rhythm::yearly(1, 3), '2026-03-01');

        $this->sync($plan);

        self::assertSame(['2027-03-01 → 2027-03-01'], $this->openDays($plan));
    }

    public function testTheWindowOpensDaysBefore(): void
    {
        $plan = $this->calendarPlan('Kühlschrank auswischen', Rhythm::monthly(5), '2026-11-01', windowDays: 3);

        $this->sync($plan);

        $task = $this->open($plan)[0];
        self::assertSame(['2026-11-05', '2026-11-02', '2026-11-02'], [$task->getDueDate()->format('Y-m-d'), $task->getWindowStart()?->format('Y-m-d'), $task->getVisibleFrom()?->format('Y-m-d')]);
    }

    public function testAPostponedOccurrenceStaysWhenThePlanChanges(): void
    {
        $plan = $this->calendarPlan('Bettwäsche wechseln', Rhythm::weekly(['MO']), '2026-11-02');
        $this->sync($plan);
        $monday = $this->open($plan)[0];
        $this->actions()->postpone($monday, new \DateTimeImmutable('2026-11-11'));

        $plan->repeatByCalendar(Rhythm::weekly(['TU'])->rrule(), 0);
        $plan->describe('Betten frisch beziehen', null, TaskCategory::Waesche, null, false, false);
        $this->entityManager()->flush();
        $this->sync($plan);

        self::assertSame(['2026-11-03 → 2026-11-03', '2026-11-09 → 2026-11-11', '2026-11-10 → 2026-11-10', '2026-11-17 → 2026-11-17'], $this->openDays($plan));
        $titles = array_map(static fn (Task $task): string => $task->getTitle(), $this->open($plan));
        self::assertSame(['Betten frisch beziehen', 'Bettwäsche wechseln', 'Betten frisch beziehen', 'Betten frisch beziehen'], $titles, 'the postponed one keeps its state');
        self::assertTrue($monday->isModified());
    }

    public function testAHandedOnOccurrenceKeepsItsResponsibility(): void
    {
        $plan = $this->calendarPlan('Müll rausbringen', Rhythm::weekly(['TU', 'FR']), '2026-11-03');
        $this->sync($plan);
        $friday = $this->open($plan)[1];
        $this->actions()->handOver($friday, Assignment::person($this->anna), Viewer::person($this->max, true));

        $plan->assign(null, AssigneeRole::Admins);
        $this->entityManager()->flush();
        $this->sync($plan);

        $roles = array_map(static fn (Task $task): string => $task->getAssigneeUser()?->getDisplayName() ?? (string) $task->getAssigneeRole()?->value, $this->open($plan));
        self::assertSame(['admins', 'Anna', 'admins', 'admins', 'admins'], $roles);
    }

    public function testAMissedOccurrenceIsReplacedWhenTheNextOneIsDue(): void
    {
        $plan = $this->calendarPlan('Müll rausbringen', Rhythm::weekly(['TU', 'FR']), '2026-11-03');
        $this->sync($plan);
        $tuesdayId = $this->open($plan)[0]->getId();
        $this->actions()->postpone($this->open($plan)[1], new \DateTimeImmutable('2026-11-12'));

        self::mockTime('2026-11-05 09:00:00 UTC');
        $this->sync($plan);
        self::assertSame('2026-11-03 → 2026-11-03', $this->openDays($plan)[0], 'overdue until a later one is due');

        self::mockTime('2026-11-10 09:00:00 UTC');
        $this->sync($plan);
        self::assertSame(['2026-11-06 → 2026-11-12', '2026-11-10 → 2026-11-10'], \array_slice($this->openDays($plan), 0, 2), 'Tuesday replaced, the Friday postponed to Thursday still there');
        self::assertNull($this->entityManager()->find(Task::class, $tuesdayId));
    }

    public function testAMissedOccurrenceIsReplacedEvenWhenHandedOn(): void
    {
        $plan = $this->calendarPlan('Müll rausbringen', Rhythm::weekly(['TU', 'FR']), '2026-11-03');
        $this->sync($plan);
        $this->actions()->handOver($this->open($plan)[0], Assignment::person($this->anna), Viewer::person($this->max, true));

        self::mockTime('2026-11-06 09:00:00 UTC');
        $this->sync($plan);

        self::assertSame('2026-11-06 → 2026-11-06', $this->openDays($plan)[0]);
    }

    public function testANarrowerVisibilityReachesEveryOpenOccurrence(): void
    {
        $plan = $this->calendarPlan('Müll rausbringen', Rhythm::weekly(['TU', 'FR']), '2026-11-03');
        $this->sync($plan);
        $this->actions()->postpone($this->open($plan)[1], new \DateTimeImmutable('2026-11-08'));
        self::mockTime('2026-11-04 09:00:00 UTC');

        $plan->setVisibility(Visibility::Admins);
        $plan->describe('Mülltonnen rausstellen', null, TaskCategory::Haushalt, null, false, false);
        $this->entityManager()->flush();
        $this->sync($plan);

        $open = $this->open($plan);
        self::assertSame(['2026-11-03 → 2026-11-03', '2026-11-06 → 2026-11-08'], \array_slice($this->openDays($plan), 0, 2));
        self::assertSame([Visibility::Admins], array_values(array_unique(array_map(static fn (Task $task): Visibility => $task->getVisibility(), $open), \SORT_REGULAR)));
        self::assertSame(['Mülltonnen rausstellen', 'Müll rausbringen'], [$open[0]->getTitle(), $open[1]->getTitle()], 'the overdue one takes over the plan, the postponed one only who sees it');
    }

    public function testAfterAnEarlyCompletionTheNextOccurrenceFollows(): void
    {
        $plan = $this->calendarPlan('Kühlschrank auswischen', Rhythm::monthly(20), '2026-11-01');
        $this->sync($plan);
        self::assertSame(['2026-11-20 → 2026-11-20'], $this->openDays($plan));

        $this->actions()->close($this->open($plan)[0], CompletionAction::Erledigt, Viewer::person($this->anna, false));
        $this->sync($plan);

        self::assertSame(['2026-12-20 → 2026-12-20'], $this->openDays($plan));
        self::assertSame('2026-12-20', $plan->getNextDueOn()?->format('Y-m-d'));
    }

    public function testAfterCompletionCountsFromTheCompletion(): void
    {
        $plan = $this->intervalPlan('Reifendruck prüfen', 14, '2026-11-03', earliestAfterDays: 10);
        $this->sync($plan);
        self::assertSame(['2026-11-03 → 2026-11-03'], $this->openDays($plan));

        self::mockTime('2026-11-05 09:00:00 UTC');
        $this->actions()->close($this->open($plan)[0], CompletionAction::Erledigt, Viewer::teamTablet($this->tablet));

        $next = $this->open($plan)[0];
        self::assertSame(['2026-11-19', '2026-11-15', '2026-11-15'], [$next->getDueDate()->format('Y-m-d'), $next->getWindowStart()?->format('Y-m-d'), $next->getVisibleFrom()?->format('Y-m-d')]);
        self::assertCount(1, $this->open($plan));
        self::assertSame('2026-11-05 09:00', $plan->getLastDoneAt()?->format('Y-m-d H:i'));
        self::assertSame('2026-11-19', $plan->getNextDueOn()?->format('Y-m-d'));
    }

    public function testSkippingCountsAsWellButIsNotDone(): void
    {
        $plan = $this->intervalPlan('Reifendruck prüfen', 14, '2026-11-03');
        $this->sync($plan);

        $this->actions()->close($this->open($plan)[0], CompletionAction::Uebersprungen, Viewer::person($this->anna, false));

        self::assertSame(['2026-11-17 → 2026-11-17'], $this->openDays($plan));
        self::assertNull($plan->getLastDoneAt());
    }

    public function testUndoRestoresTheFormerState(): void
    {
        $plan = $this->intervalPlan('Reifendruck prüfen', 14, '2026-11-03');
        $this->sync($plan);
        $task = $this->open($plan)[0];
        $viewer = Viewer::person($this->anna, false);
        $this->actions()->close($task, CompletionAction::Erledigt, $viewer);
        self::assertSame(['2026-11-17 → 2026-11-17'], $this->openDays($plan));

        self::assertTrue($this->actions()->undo($task, $viewer));

        self::assertSame(['2026-11-03 → 2026-11-03'], $this->openDays($plan));
        self::assertNull($plan->getLastDoneAt());
        self::assertSame('2026-11-03', $plan->getNextDueOn()?->format('Y-m-d'));
    }

    public function testCompletedEarlyTheNextOneNeverCollidesWithItsOriginalDay(): void
    {
        $plan = $this->intervalPlan('Reifendruck prüfen', 7, '2026-11-10');
        $this->sync($plan);

        $this->actions()->close($this->open($plan)[0], CompletionAction::Erledigt, Viewer::person($this->anna, false));
        self::assertSame(['2026-11-11 → 2026-11-10'], $this->openDays($plan), 'due 7 days after today, original day after the closed one');
    }

    public function testAPausedPlanCreatesNothingAndGoesOnAfterwards(): void
    {
        $calendar = $this->calendarPlan('Fenster putzen', Rhythm::weekly(['MO']), '2026-11-02');
        $calendar->pause(new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-11-20'));
        $interval = $this->intervalPlan('Reifendruck prüfen', 14, '2026-11-03');
        $interval->pause(new \DateTimeImmutable('2026-11-01'), new \DateTimeImmutable('2026-11-20'));
        $this->entityManager()->flush();

        $this->sync($calendar);
        $this->sync($interval);

        self::assertSame(['2026-11-23 → 2026-11-23'], $this->openDays($calendar), 'only the next one after the pause');
        self::assertSame(['2026-11-21 → 2026-11-21'], $this->openDays($interval));

        $calendar->pause(new \DateTimeImmutable('2026-11-01'), null);
        $interval->pause(new \DateTimeImmutable('2026-11-01'), null);
        $this->entityManager()->flush();
        $this->sync($calendar);
        $this->sync($interval);
        self::assertSame([], $this->openDays($calendar), 'paused until further notice');
        self::assertSame([], $this->openDays($interval));
        self::assertNull($calendar->getNextDueOn());
    }

    public function testAnEndedPlanKeepsOnlyWhatWasChangedOneByOne(): void
    {
        $plan = $this->calendarPlan('Müll rausbringen', Rhythm::weekly(['TU', 'FR']), '2026-11-03');
        $this->sync($plan);
        $this->actions()->postpone($this->open($plan)[1], new \DateTimeImmutable('2026-11-07'));

        $plan->end();
        $this->entityManager()->flush();
        $this->sync($plan);

        self::assertSame(['2026-11-06 → 2026-11-07'], $this->openDays($plan));

        $plan->resume();
        $this->entityManager()->flush();
        $this->sync($plan);
        self::assertCount(5, $this->openDays($plan));
    }

    public function testTheChecklistIsCopiedAndTickedOff(): void
    {
        $plan = $this->intervalPlan('Reifendruck prüfen', 14, '2026-11-03');
        $plan->replaceChecklist(['Reifen vorne links', 'Reifen vorne rechts', '', 'Reifen hinten links']);
        $this->entityManager()->flush();
        $this->sync($plan);
        $task = $this->open($plan)[0];
        self::assertSame(['Reifen vorne links', 'Reifen vorne rechts', 'Reifen hinten links'], array_map(static fn ($item): string => $item->getLabel(), [...$task->getItems()]));

        $first = $task->getItems()->first();
        \assert(false !== $first && null !== $first->getId());
        $this->actions()->close($task, CompletionAction::Erledigt, Viewer::person($this->anna, false), [$first->getId()]);
        $completion = $this->completions()->findForTask($task);
        self::assertSame([3, 1], [$completion?->getResults()->count(), $completion?->countChecked()]);

        $next = $this->open($plan)[0];
        $this->actions()->close($next, CompletionAction::Erledigt, Viewer::teamTablet($this->tablet));
        self::assertSame(3, $this->completions()->findForTask($next)?->countChecked(), 'one tap ticks off every point');
    }

    public function testAChangedChecklistReachesTheOpenOccurrences(): void
    {
        $plan = $this->calendarPlan('Bettwäsche wechseln', Rhythm::weekly(['MO']), '2026-11-02');
        $plan->replaceChecklist(['Bett abziehen']);
        $this->entityManager()->flush();
        $this->sync($plan);

        $plan->replaceChecklist(['Bett abziehen', 'Frisch beziehen']);
        $this->entityManager()->flush();
        $this->sync($plan);

        foreach ($this->open($plan) as $task) {
            self::assertSame(['Bett abziehen', 'Frisch beziehen'], array_map(static fn ($item): string => $item->getLabel(), [...$task->getItems()]));
        }
    }

    public function testThePrivatePlanOfARelativeStaysTheirs(): void
    {
        $papa = new AppUser(UserRole::Angehoerige, 'papa', 'Papa', new \DateTimeImmutable('2026-01-01'));
        $this->entityManager()->persist($papa);
        $plan = $this->calendarPlan('Blumen für Mama', Rhythm::monthly(10), '2026-11-01', creator: $papa);
        $plan->setVisibility(Visibility::Privat);
        $plan->assign($papa, null);
        $this->entityManager()->flush();

        $this->sync($plan);

        $task = $this->open($plan)[0];
        self::assertSame([Visibility::Privat, $papa, $papa], [$task->getVisibility(), $task->getCreatedBy(), $task->getAssigneeUser()]);
    }

    public function testTheNightlyRunCoversEveryPlan(): void
    {
        $this->calendarPlan('Bettwäsche wechseln', Rhythm::weekly(['MO']), '2026-11-02');
        $this->intervalPlan('Reifendruck prüfen', 14, '2026-11-03');

        self::assertSame(2, $this->planSync()->syncAll());
        self::assertCount(3, $this->tasks()->findBy(['createdVia' => CreatedVia::System]));
    }

    private function calendarPlan(string $title, Rhythm $rhythm, string $startsOn, int $windowDays = 0, ?string $time = null, ?AppUser $creator = null): TaskTemplate
    {
        $plan = new TaskTemplate($title, TaskCategory::Haushalt, RecurrenceType::Kalender, $rhythm->rrule(), new \DateTimeImmutable($startsOn), $creator ?? $this->max);
        $plan->repeatByCalendar($rhythm->rrule(), $windowDays);
        $plan->schedule(new \DateTimeImmutable($startsOn), null, null === $time ? null : new \DateTimeImmutable($time));
        $plan->assign(null, AssigneeRole::Assistenz);
        $this->entityManager()->persist($plan);
        $this->entityManager()->flush();

        return $plan;
    }

    private function intervalPlan(string $title, int $days, string $startsOn, ?int $earliestAfterDays = null): TaskTemplate
    {
        $plan = new TaskTemplate($title, TaskCategory::Hilfsmittel, RecurrenceType::Intervall, $days, new \DateTimeImmutable($startsOn), $this->max);
        $plan->repeatAfterCompletion($days, $earliestAfterDays);
        $plan->assign(null, AssigneeRole::Assistenz);
        $this->entityManager()->persist($plan);
        $this->entityManager()->flush();

        return $plan;
    }

    private function sync(TaskTemplate $plan): void
    {
        $this->planSync()->sync($plan);
    }

    /**
     * @return list<Task>
     */
    private function open(TaskTemplate $plan): array
    {
        return $this->tasks()->findOpenOccurrences($plan);
    }

    /**
     * @return list<string> "original day → due date"
     */
    private function openDays(TaskTemplate $plan): array
    {
        return array_map(static fn (Task $task): string => $task->getOccurrenceDate()?->format('Y-m-d').' → '.$task->getDueDate()->format('Y-m-d'), $this->open($plan));
    }

    private function planSync(): PlanSync
    {
        return static::getContainer()->get(PlanSync::class);
    }

    private function actions(): TaskActions
    {
        return static::getContainer()->get(TaskActions::class);
    }

    private function tasks(): TaskRepository
    {
        return static::getContainer()->get(TaskRepository::class);
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
