<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Task;

use App\Entity\Task;
use App\Enum\TaskCategory;
use App\Service\Task\DueGroup;
use App\Service\Task\TaskGroups;
use PHPUnit\Framework\TestCase;

/**
 * The groups on "Heute" (decision M5) and the order within each group.
 */
final class TaskGroupsTest extends TestCase
{
    private const string TODAY = '2026-11-03';

    private static function task(string $title, string $due, ?string $time = null, ?string $windowStart = null, ?string $visibleFrom = null, bool $important = false): Task
    {
        $task = new Task($title, TaskCategory::Haushalt, new \DateTimeImmutable($due));
        $task->schedule(
            new \DateTimeImmutable($due),
            null === $time ? null : new \DateTimeImmutable($time),
            null === $windowStart ? null : new \DateTimeImmutable($windowStart),
            null === $visibleFrom ? null : new \DateTimeImmutable($visibleFrom),
        );
        $task->describe($title, null, TaskCategory::Haushalt, null, $important, false);

        return $task;
    }

    public function testGroupOfATask(): void
    {
        self::assertSame(DueGroup::Ueberfaellig, DueGroup::of(self::task('a', '2026-11-02'), self::TODAY));
        self::assertSame(DueGroup::HeuteFaellig, DueGroup::of(self::task('a', '2026-11-03', visibleFrom: '2026-11-03'), self::TODAY));
        self::assertSame(DueGroup::HeuteMoeglich, DueGroup::of(self::task('a', '2026-11-06', windowStart: '2026-11-03', visibleFrom: '2026-11-03'), self::TODAY));
        self::assertSame(DueGroup::WennZeitIst, DueGroup::of(self::task('a', '2026-11-30'), self::TODAY));
        self::assertSame(DueGroup::Spaeter, DueGroup::of(self::task('a', '2026-11-05', visibleFrom: '2026-11-05'), self::TODAY));
        self::assertSame(DueGroup::Spaeter, DueGroup::of(self::task('a', '2026-11-09', windowStart: '2026-11-04', visibleFrom: '2026-11-04'), self::TODAY));
    }

    public function testOrderWithinAGroup(): void
    {
        $groups = TaskGroups::sort([
            self::task('Wäsche', self::TODAY),
            self::task('Bettwäsche', self::TODAY, '10:00'),
            self::task('Müll', self::TODAY, '08:30'),
            self::task('Arzt anrufen', self::TODAY, important: true),
            self::task('Fenster', '2026-11-30'),
            self::task('Post', '2026-11-01'),
            self::task('Keller', '2026-10-30'),
        ], self::TODAY);

        $titles = static fn (DueGroup $group): array => array_map(static fn (Task $task): string => $task->getTitle(), $groups->get($group));
        self::assertSame(['Keller', 'Post'], $titles(DueGroup::Ueberfaellig));
        self::assertSame(['Müll', 'Bettwäsche', 'Arzt anrufen', 'Wäsche'], $titles(DueGroup::HeuteFaellig));
        self::assertSame(['Fenster'], $titles(DueGroup::WennZeitIst));
        self::assertSame([DueGroup::Ueberfaellig, DueGroup::HeuteFaellig, DueGroup::WennZeitIst], array_column($groups->nonEmpty(), 'group'));
        self::assertSame(7, $groups->count());
    }

    public function testPostponingMovesWindowAndShowDate(): void
    {
        $task = self::task('Geschenk', '2026-11-06', windowStart: '2026-11-03', visibleFrom: '2026-11-03');

        $task->postpone(new \DateTimeImmutable('2026-11-10'));

        self::assertSame(['2026-11-10', '2026-11-07', '2026-11-07', 1], [
            $task->getDueDate()->format('Y-m-d'),
            $task->getWindowStart()?->format('Y-m-d'),
            $task->getVisibleFrom()?->format('Y-m-d'),
            $task->getPostponedCount(),
        ]);
    }

    public function testWindowMayNotStartAfterTheDueDate(): void
    {
        $this->expectException(\DomainException::class);

        self::task('a', '2026-11-03', windowStart: '2026-11-04');
    }
}
