<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\Task;
use App\Entity\TaskTemplate;
use App\Enum\AssigneeRole;
use App\Enum\CompletionAction;
use App\Enum\DeviceMode;
use App\Enum\RecurrenceType;
use App\Enum\TaskCategory;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Security\Device\DeviceToken;
use App\Security\Permission;
use App\Security\Pin\PinLoginToken;
use App\Security\Voter\TaskVoter;
use App\Service\Viewer\ViewerProvider;
use App\Service\Visibility\VisibilityRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorToken;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Who may create, act on, hand on and change tasks (konzept-1 §3, decisions M5 and M6).
 */
final class TaskVoterTest extends TestCase
{
    /** @var array<string, AppUser> */
    private static array $people = [];

    private static function person(string $name): AppUser
    {
        return self::$people[$name] ??= match ($name) {
            'max' => new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), true),
            'papa' => new AppUser(UserRole::Angehoerige, 'papa', 'Papa', new \DateTimeImmutable('2026-01-01')),
            'anna' => new AppUser(UserRole::Assistenz, 'anna', 'Anna', new \DateTimeImmutable('2026-01-01')),
            'carla' => self::withSwitch(new AppUser(UserRole::Assistenz, 'carla', 'Carla', new \DateTimeImmutable('2026-01-01'))),
            default => new AppUser(UserRole::Assistenz, 'ben', 'Ben', new \DateTimeImmutable('2026-01-01')),
        };
    }

    /**
     * Switch "wiederkehrende Aufgaben".
     */
    private static function withSwitch(AppUser $person): AppUser
    {
        $person->setCanManageRecurringTasks(true);

        return $person;
    }

    private static function occurrence(): Task
    {
        $plan = new TaskTemplate('Bettwäsche wechseln', TaskCategory::Waesche, RecurrenceType::Kalender, 'FREQ=WEEKLY;BYDAY=MO', new \DateTimeImmutable('2026-11-02'), self::person('max'));
        $task = self::task(AssigneeRole::Assistenz, creator: 'max');
        $task->belongTo($plan, new \DateTimeImmutable('2026-11-09'));

        return $task;
    }

    private static function token(string $who): TokenInterface
    {
        return match ($who) {
            'tablet' => self::tabletToken(DeviceMode::Team),
            'tablet-persönlich' => self::tabletToken(DeviceMode::Persoenlich),
            'max-pin' => new PinLoginToken(self::person('max'), 'main', ['ROLE_USER'], 1),
            'max-zweiter-schritt' => new TwoFactorToken(new UsernamePasswordToken(self::person('max'), 'main'), null, 'main', ['app']),
            default => new UsernamePasswordToken(self::person($who), 'main', ['ROLE_USER']),
        };
    }

    private static function tabletToken(DeviceMode $mode): DeviceToken
    {
        $device = new Device('Flur', hash('sha256', 'x'));
        $device->setMode($mode);

        return new DeviceToken($device, 'main', $device->getRoles());
    }

    private static function task(string|AssigneeRole|null $assignee, Visibility $visibility = Visibility::Alle, ?string $creator = 'papa'): Task
    {
        $task = new Task('Blumen gießen', TaskCategory::Haushalt, new \DateTimeImmutable('2026-11-03'), null === $creator ? null : self::person($creator));
        $task->assign(\is_string($assignee) ? self::person($assignee) : null, $assignee instanceof AssigneeRole ? $assignee : null);
        $task->setVisibility($visibility);

        return $task;
    }

    private static function voter(): TaskVoter
    {
        // Stands in for the AdminVoter and TabletVoter: admin only by full login, team view only in mode Team/Beides
        $decisions = new class implements AccessDecisionManagerInterface {
            /**
             * @param array<mixed> $attributes
             */
            public function decide(TokenInterface $token, array $attributes, mixed $object = null, mixed $accessDecision = null, bool $allowMultipleAttributes = false): bool
            {
                $user = $token->getUser();

                return match ($attributes[0] ?? null) {
                    Permission::ADMIN => $user instanceof AppUser && $user->isAdmin() && !$token instanceof PinLoginToken,
                    Permission::TEAM_VIEW => $user instanceof Device && $user->getMode()->showsTeamView(),
                    default => false,
                };
            }
        };

        return new TaskVoter(new ViewerProvider(new Security(new Container()), $decisions), new VisibilityRules());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function creators(): iterable
    {
        yield 'Manager' => ['max', VoterInterface::ACCESS_GRANTED];
        yield 'Assistenz' => ['anna', VoterInterface::ACCESS_GRANTED];
        yield 'Admin per PIN' => ['max-pin', VoterInterface::ACCESS_GRANTED];
        yield 'Team-Tablet' => ['tablet', VoterInterface::ACCESS_GRANTED];
        yield 'Tablet im Modus Persönlich (ohne PIN)' => ['tablet-persönlich', VoterInterface::ACCESS_DENIED];
        yield 'zweiter Schritt offen' => ['max-zweiter-schritt', VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('creators')]
    public function testWhoCreatesTasks(string $who, int $expected): void
    {
        self::assertSame($expected, self::voter()->vote(self::token($who), null, [Permission::TASK_CREATE]));
    }

    /**
     * @return iterable<string, array{string, Task, int}>
     */
    public static function handOvers(): iterable
    {
        $granted = VoterInterface::ACCESS_GRANTED;
        $denied = VoterInterface::ACCESS_DENIED;

        yield 'A10 Admin: Aufgabe für die Admins' => ['max', self::task(AssigneeRole::Admins), $granted];
        yield 'Admin: Aufgabe einer anderen Rolle' => ['max', self::task('papa'), $granted];
        yield 'Admin per PIN: Aufgabe für die Admins' => ['max-pin', self::task(AssigneeRole::Admins), $denied];
        yield 'Angehörige: eigene' => ['papa', self::task('papa'), $granted];
        yield 'Angehörige: selbst angelegt, aber an die Assistenz gegeben' => ['papa', self::task(AssigneeRole::Assistenz), $denied];
        yield 'Assistenz: Rolle Assistenz' => ['anna', self::task(AssigneeRole::Assistenz), $granted];
        yield 'Assistenz: wer gerade da ist' => ['anna', self::task(null), $granted];
        yield 'Assistenz: Aufgabe für Ben' => ['anna', self::task('ben'), $denied];
        yield 'Assistenz: Aufgabe für Papa' => ['anna', self::task('papa'), $denied];
        yield 'Team-Tablet: Rolle Assistenz' => ['tablet', self::task(AssigneeRole::Assistenz), $granted];
        yield 'Team-Tablet: Aufgabe für Ben' => ['tablet', self::task('ben'), $granted];
        yield 'Team-Tablet: Aufgabe für die Admins' => ['tablet', self::task(AssigneeRole::Admins), $denied];
        yield 'privat: auch nicht vom Ersteller' => ['papa', self::task('papa', Visibility::Privat), $denied];
        yield 'unsichtbar: nur Admins' => ['anna', self::task(AssigneeRole::Assistenz, Visibility::Admins), $denied];
    }

    #[DataProvider('handOvers')]
    public function testWhoHandsTasksOn(string $who, Task $task, int $expected): void
    {
        self::assertSame($expected, self::voter()->vote(self::token($who), $task, [Permission::TASK_HANDOVER]));
    }

    /**
     * @return iterable<string, array{string, Task, int}>
     */
    public static function edits(): iterable
    {
        yield 'Admin' => ['max', self::task(AssigneeRole::Assistenz, creator: 'anna'), VoterInterface::ACCESS_GRANTED];
        yield 'Ersteller' => ['anna', self::task('papa', creator: 'anna'), VoterInterface::ACCESS_GRANTED];
        yield 'zuständig, aber nicht Ersteller' => ['anna', self::task(AssigneeRole::Assistenz), VoterInterface::ACCESS_DENIED];
        yield 'Admin per PIN, nicht Ersteller' => ['max-pin', self::task(AssigneeRole::Assistenz), VoterInterface::ACCESS_DENIED];
        yield 'Team-Tablet' => ['tablet', self::task(AssigneeRole::Assistenz, creator: null), VoterInterface::ACCESS_DENIED];
        yield 'Schalter: Vorkommen eines Plans' => ['carla', self::occurrence(), VoterInterface::ACCESS_GRANTED];
        yield 'Schalter: fremde einmalige Aufgabe' => ['carla', self::task(AssigneeRole::Assistenz), VoterInterface::ACCESS_DENIED];
        yield 'ohne Schalter: Vorkommen eines Plans' => ['anna', self::occurrence(), VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('edits')]
    public function testWhoChangesTasks(string $who, Task $task, int $expected): void
    {
        self::assertSame($expected, self::voter()->vote(self::token($who), $task, [Permission::TASK_EDIT]));
    }

    public function testWhoeverSeesAnOpenTaskActsOnIt(): void
    {
        $voter = self::voter();
        $open = self::task('papa');
        $closed = self::task(AssigneeRole::Assistenz);
        $closed->close(CompletionAction::Erledigt);
        $adminsOnly = self::task(AssigneeRole::Admins, Visibility::Admins);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(self::token('tablet'), $open, [Permission::TASK_ACT]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(self::token('tablet'), $closed, [Permission::TASK_ACT]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(self::token('tablet'), $closed, [Permission::TASK_VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(self::token('tablet'), $adminsOnly, [Permission::TASK_VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote(self::token('max'), $adminsOnly, [Permission::TASK_ACT]));
    }
}
