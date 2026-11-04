<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\TaskTemplate;
use App\Enum\AssigneeRole;
use App\Enum\DeviceMode;
use App\Enum\RecurrenceType;
use App\Enum\TaskCategory;
use App\Enum\UserRole;
use App\Enum\Visibility;
use App\Security\Device\DeviceToken;
use App\Security\Permission;
use App\Security\Pin\PinLoginToken;
use App\Security\Voter\PlanVoter;
use App\Service\Viewer\ViewerProvider;
use App\Service\Visibility\VisibilityRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Who sees, creates and changes plans (konzept-1 §3, decisions M6): the switch "wiederkehrende Aufgaben"
 * counts like the admin right, never for the team tablet.
 */
final class PlanVoterTest extends TestCase
{
    /** @var array<string, AppUser> */
    private static array $people = [];

    private static function person(string $name): AppUser
    {
        return self::$people[$name] ??= match ($name) {
            'max' => new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), true),
            'papa' => new AppUser(UserRole::Angehoerige, 'papa', 'Papa', new \DateTimeImmutable('2026-01-01')),
            'mama' => self::withSwitch(new AppUser(UserRole::Angehoerige, 'mama', 'Mama', new \DateTimeImmutable('2026-01-01'))),
            'anna' => new AppUser(UserRole::Assistenz, 'anna', 'Anna', new \DateTimeImmutable('2026-01-01')),
            default => self::withSwitch(new AppUser(UserRole::Assistenz, 'ben', 'Ben', new \DateTimeImmutable('2026-01-01'))),
        };
    }

    private static function withSwitch(AppUser $person): AppUser
    {
        $person->setCanManageRecurringTasks(true);

        return $person;
    }

    private static function token(string $who): TokenInterface
    {
        return match ($who) {
            'tablet' => self::tabletToken(),
            'ben-pin' => new PinLoginToken(self::person('ben'), 'main', ['ROLE_USER'], 1),
            'max-pin' => new PinLoginToken(self::person('max'), 'main', ['ROLE_USER'], 1),
            default => new UsernamePasswordToken(self::person($who), 'main', ['ROLE_USER']),
        };
    }

    private static function tabletToken(): DeviceToken
    {
        $device = new Device('Flur', hash('sha256', 'x'));
        $device->setMode(DeviceMode::Team);

        return new DeviceToken($device, 'main', $device->getRoles());
    }

    private static function plan(Visibility $visibility = Visibility::Alle, string $creator = 'max'): TaskTemplate
    {
        $plan = new TaskTemplate('Bettwäsche wechseln', TaskCategory::Waesche, RecurrenceType::Kalender, 'FREQ=WEEKLY;BYDAY=MO', new \DateTimeImmutable('2026-11-02'), self::person($creator));
        $plan->assign(null, AssigneeRole::Assistenz);
        $plan->setVisibility($visibility);

        return $plan;
    }

    private static function voter(): PlanVoter
    {
        // Stands in for the AdminVoter and TabletVoter: admin only by full login, team view in mode Team
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

        return new PlanVoter(new ViewerProvider(new Security(new Container()), $decisions), new VisibilityRules());
    }

    /**
     * @return iterable<string, array{string, int, int}>
     */
    public static function overview(): iterable
    {
        $granted = VoterInterface::ACCESS_GRANTED;
        $denied = VoterInterface::ACCESS_DENIED;

        yield 'Manager' => ['max', $granted, $granted];
        yield 'Angehörige ohne Schalter' => ['papa', $granted, $denied];
        yield 'Angehörige mit Schalter' => ['mama', $granted, $granted];
        yield 'Assistenz ohne Schalter' => ['anna', $granted, $denied];
        yield 'Assistenz mit Schalter' => ['ben', $granted, $granted];
        yield 'Assistenz mit Schalter per PIN' => ['ben-pin', $granted, $granted];
        yield 'Admin per PIN (kein Schalter)' => ['max-pin', $granted, $denied];
        yield 'Team-Tablet' => ['tablet', $granted, $denied];
    }

    #[DataProvider('overview')]
    public function testWhoSeesTheOverviewAndCreatesPlans(string $who, int $view, int $create): void
    {
        self::assertSame($view, self::voter()->vote(self::token($who), null, [Permission::PLAN_VIEW]));
        self::assertSame($create, self::voter()->vote(self::token($who), null, [Permission::PLAN_MANAGE]));
    }

    /**
     * @return iterable<string, array{string, TaskTemplate, int, int}>
     */
    public static function plans(): iterable
    {
        $granted = VoterInterface::ACCESS_GRANTED;
        $denied = VoterInterface::ACCESS_DENIED;

        yield 'Admin: jeder sichtbare Plan' => ['max', self::plan(creator: 'papa'), $granted, $granted];
        yield 'mit Schalter: auch fremde Pläne' => ['ben', self::plan(), $granted, $granted];
        yield 'ohne Schalter: nur ansehen' => ['anna', self::plan(), $granted, $denied];
        yield 'Team-Tablet: nur ansehen' => ['tablet', self::plan(), $granted, $denied];
        yield 'mit Schalter: Plan nur für Admins unsichtbar' => ['ben', self::plan(Visibility::Admins), $denied, $denied];
        yield 'Team-Tablet: Plan nur für Admins unsichtbar' => ['tablet', self::plan(Visibility::Admins), $denied, $denied];
        yield 'privat: nur wer ihn angelegt hat' => ['mama', self::plan(Visibility::Privat, 'mama'), $granted, $granted];
        yield 'privat: Admins sehen fremde nicht' => ['max', self::plan(Visibility::Privat, 'mama'), $denied, $denied];
    }

    #[DataProvider('plans')]
    public function testWhoSeesAndChangesAPlan(string $who, TaskTemplate $plan, int $view, int $manage): void
    {
        self::assertSame($view, self::voter()->vote(self::token($who), $plan, [Permission::PLAN_VIEW]));
        self::assertSame($manage, self::voter()->vote(self::token($who), $plan, [Permission::PLAN_MANAGE]));
    }
}
