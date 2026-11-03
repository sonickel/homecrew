<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Visibility;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\Task;
use App\Enum\AssigneeRole;
use App\Enum\TaskCategory;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Service\Viewer\Viewer;
use App\Service\Visibility\VisibilityRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The visibility and responsibility rules with explicit expectations (decisions M5); the matrix test
 * App\Tests\Integration\VisibilityMatrixTest proves the database queries apply the same rules.
 */
final class VisibilityRulesTest extends TestCase
{
    /** @var array<string, AppUser> */
    private static array $people = [];

    private static function person(string $name): AppUser
    {
        return self::$people[$name] ??= match ($name) {
            'max' => new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), true),
            'schwester' => new AppUser(UserRole::Angehoerige, 'schwester', 'Schwester', new \DateTimeImmutable('2026-01-01'), true),
            'papa' => new AppUser(UserRole::Angehoerige, 'papa', 'Papa', new \DateTimeImmutable('2026-01-01')),
            'anna' => new AppUser(UserRole::Assistenz, 'anna', 'Anna', new \DateTimeImmutable('2026-01-01')),
            default => new AppUser(UserRole::Assistenz, 'ben', 'Ben', new \DateTimeImmutable('2026-01-01')),
        };
    }

    private static function viewer(string $name): Viewer
    {
        return match ($name) {
            'tablet' => Viewer::teamTablet(new Device('Flur', hash('sha256', 'x'))),
            // An admin logged in by PIN has no admin rights
            'schwester-pin' => Viewer::person(self::person('schwester'), false),
            default => Viewer::person(self::person($name), self::person($name)->isAdmin()),
        };
    }

    private static function task(Visibility $visibility, string|AssigneeRole|null $assignee, ?string $creator = 'max'): Task
    {
        $task = new Task('Blumen gießen', TaskCategory::Haushalt, new \DateTimeImmutable('2026-11-03'), null === $creator ? null : self::person($creator));
        $task->assign(\is_string($assignee) ? self::person($assignee) : null, $assignee instanceof AssigneeRole ? $assignee : null);
        $task->setVisibility($visibility);

        return $task;
    }

    /**
     * @return iterable<string, array{Task, list<string>}> task, viewers who see it
     */
    public static function visibility(): iterable
    {
        $all = ['max', 'schwester', 'schwester-pin', 'papa', 'anna', 'ben', 'tablet'];

        yield 'A1 alle, Rolle Assistenz – alle, auch das Tablet' => [self::task(Visibility::Alle, AssigneeRole::Assistenz), $all];
        yield 'alle, für die Admins – trotzdem alle' => [self::task(Visibility::Alle, AssigneeRole::Admins), $all];
        yield 'zuständig: wer gerade da ist – Assistenz, Tablet, Admins, Ersteller' => [self::task(Visibility::Zustaendig, null, 'papa'), ['max', 'schwester', 'papa', 'anna', 'ben', 'tablet']];
        yield 'zuständig: Rolle Assistenz – ohne Angehörige ohne Admin-Recht' => [self::task(Visibility::Zustaendig, AssigneeRole::Assistenz), ['max', 'schwester', 'anna', 'ben', 'tablet']];
        yield 'zuständig: Ben – das Tablet ist nicht Ben' => [self::task(Visibility::Zustaendig, 'ben'), ['max', 'schwester', 'ben']];
        yield 'zuständig: Papa – Papa, Admins und die Erstellerin (auch per PIN)' => [self::task(Visibility::Zustaendig, 'papa', 'schwester'), ['max', 'schwester', 'schwester-pin', 'papa']];
        yield 'zuständig: Rolle Angehörige – auch per PIN' => [self::task(Visibility::Zustaendig, AssigneeRole::Angehoerige), ['max', 'schwester', 'schwester-pin', 'papa']];
        yield 'zuständig: Rolle Admins – per PIN nicht' => [self::task(Visibility::Zustaendig, AssigneeRole::Admins, 'anna'), ['max', 'schwester', 'anna']];
        yield 'nur Admins – Ersteller sieht sie weiter, PIN nicht, Tablet nicht' => [self::task(Visibility::Admins, AssigneeRole::Admins, 'anna'), ['max', 'schwester', 'anna']];
        yield 'nur Admins vom Tablet angelegt' => [self::task(Visibility::Admins, AssigneeRole::Admins, null), ['max', 'schwester']];
        yield 'privat – nur der Ersteller, auch kein Admin' => [self::task(Visibility::Privat, 'papa', 'papa'), ['papa']];
        yield 'privat der Schwester – auch per PIN' => [self::task(Visibility::Privat, 'schwester', 'schwester'), ['schwester', 'schwester-pin']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('visibility')]
    public function testWhoSeesATask(Task $task, array $expected): void
    {
        $rules = new VisibilityRules();
        $seeing = array_values(array_filter(
            ['max', 'schwester', 'schwester-pin', 'papa', 'anna', 'ben', 'tablet'],
            static fn (string $name): bool => $rules->canSee(self::viewer($name), $task),
        ));

        self::assertSame($expected, $seeing);
    }

    /**
     * @return iterable<string, array{Task, list<string>}> task, viewers it is meant for
     */
    public static function meantFor(): iterable
    {
        yield 'wer gerade da ist – Assistenz und Tablet' => [self::task(Visibility::Alle, null), ['anna', 'ben', 'tablet']];
        yield 'Rolle Assistenz – Assistenz und Tablet' => [self::task(Visibility::Alle, AssigneeRole::Assistenz), ['anna', 'ben', 'tablet']];
        yield 'Ben – Ben, am Tablet auch (Ben hat evtl. keinen Zugang)' => [self::task(Visibility::Alle, 'ben'), ['ben', 'tablet']];
        yield 'Rolle Admins – keine PIN-Anmeldung' => [self::task(Visibility::Alle, AssigneeRole::Admins), ['max', 'schwester']];
        yield 'Rolle Manager' => [self::task(Visibility::Alle, AssigneeRole::Manager), ['max']];
        yield 'Rolle Angehörige' => [self::task(Visibility::Alle, AssigneeRole::Angehoerige), ['schwester', 'schwester-pin', 'papa']];
        yield 'Papa' => [self::task(Visibility::Alle, 'papa'), ['papa']];
    }

    /**
     * @param list<string> $expected
     */
    #[DataProvider('meantFor')]
    public function testForWhomATaskIsMeant(Task $task, array $expected): void
    {
        $rules = new VisibilityRules();
        $meant = array_values(array_filter(
            ['max', 'schwester', 'schwester-pin', 'papa', 'anna', 'ben', 'tablet'],
            static fn (string $name): bool => $rules->isForViewer(self::viewer($name), $task),
        ));

        self::assertSame($expected, $meant);
    }

    public function testTasksForTheAssistants(): void
    {
        $rules = new VisibilityRules();

        self::assertTrue($rules->isForAssistants(self::task(Visibility::Alle, null)));
        self::assertTrue($rules->isForAssistants(self::task(Visibility::Alle, AssigneeRole::Assistenz)));
        self::assertTrue($rules->isForAssistants(self::task(Visibility::Zustaendig, 'ben')));
        self::assertFalse($rules->isForAssistants(self::task(Visibility::Alle, AssigneeRole::Admins)));
        self::assertFalse($rules->isForAssistants(self::task(Visibility::Alle, 'papa')));
    }

    public function testOnlyManagerAndRelativesKeepPrivateTasks(): void
    {
        $rules = new VisibilityRules();

        self::assertTrue($rules->mayKeepPrivate(self::viewer('max')));
        self::assertTrue($rules->mayKeepPrivate(self::viewer('papa')));
        self::assertFalse($rules->mayKeepPrivate(self::viewer('anna')));
        self::assertFalse($rules->mayKeepPrivate(self::viewer('tablet')));
    }
}
