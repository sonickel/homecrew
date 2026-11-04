<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\IssueReport;
use App\Entity\Notification;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Enum\DeviceMode;
use App\Enum\NotificationKind;
use App\Enum\ReportKind;
use App\Enum\ReportStatus;
use App\Enum\ReportVisibility;
use App\Enum\UserRole;
use App\Security\Device\DeviceToken;
use App\Security\Permission;
use App\Security\Pin\PinLoginToken;
use App\Security\Voter\NotificationVoter;
use App\Security\Voter\ReportVoter;
use App\Security\Voter\ShoppingVoter;
use App\Service\Viewer\ViewerProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Lists, reports and notifications (konzept-1 §3, konzept-13 §6): everybody incl. the team tablet uses lists and
 * reports; entries are changed by admins and whoever added them; reports for admins only and the report log are for
 * admins (never by PIN); notifications only for their person, never at the tablet.
 */
final class ReportVotersTest extends TestCase
{
    private const int GRANTED = VoterInterface::ACCESS_GRANTED;
    private const int DENIED = VoterInterface::ACCESS_DENIED;

    /** @var array<string, AppUser> */
    private static array $people = [];

    private static ?Device $tablet = null;

    private static function person(string $name): AppUser
    {
        return self::$people[$name] ??= match ($name) {
            'max' => new AppUser(UserRole::Manager, 'max', 'Max', new \DateTimeImmutable('2026-01-01'), true),
            'papa' => new AppUser(UserRole::Angehoerige, 'papa', 'Papa', new \DateTimeImmutable('2026-01-01')),
            default => new AppUser(UserRole::Assistenz, $name, ucfirst($name), new \DateTimeImmutable('2026-01-01')),
        };
    }

    private static function tablet(): Device
    {
        if (null === self::$tablet) {
            self::$tablet = new Device('Flur', hash('sha256', 'x'));
            self::$tablet->setMode(DeviceMode::Team);
        }

        return self::$tablet;
    }

    private static function token(string $who): TokenInterface
    {
        return match ($who) {
            'tablet' => new DeviceToken(self::tablet(), 'main', self::tablet()->getRoles()),
            'max-pin' => new PinLoginToken(self::person('max'), 'main', ['ROLE_USER'], 1),
            default => new UsernamePasswordToken(self::person($who), 'main', ['ROLE_USER']),
        };
    }

    private static function viewerProvider(): ViewerProvider
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

        return new ViewerProvider(new Security(new Container()), $decisions);
    }

    private static function report(ReportVisibility $visibility = ReportVisibility::Alle, bool $open = true): IssueReport
    {
        $report = new IssueReport(ReportKind::Leer, 'Seife leer', self::person('anna'));
        $report->describe(null, false, $visibility);
        if (!$open) {
            $report->close(ReportStatus::Erledigt, new \DateTimeImmutable(), null, null, new \DateTimeImmutable('2027-11-05'));
        }

        return $report;
    }

    /**
     * @return iterable<string, array{string, int, int, int, int}>
     */
    public static function reports(): iterable
    {
        // who => create, see for everybody, see for admins only, report log
        yield 'Manager' => ['max', self::GRANTED, self::GRANTED, self::GRANTED, self::GRANTED];
        yield 'Manager per PIN' => ['max-pin', self::GRANTED, self::GRANTED, self::DENIED, self::DENIED];
        yield 'Angehörige' => ['papa', self::GRANTED, self::GRANTED, self::DENIED, self::DENIED];
        yield 'Assistenz, die gemeldet hat' => ['anna', self::GRANTED, self::GRANTED, self::DENIED, self::DENIED];
        yield 'Team-Tablet' => ['tablet', self::GRANTED, self::GRANTED, self::DENIED, self::DENIED];
    }

    #[DataProvider('reports')]
    public function testWhoReportsAndSeesReports(string $who, int $create, int $forAll, int $forAdmins, int $manage): void
    {
        $voter = new ReportVoter(self::viewerProvider());
        $token = self::token($who);

        self::assertSame($create, $voter->vote($token, null, [Permission::REPORT_CREATE]));
        self::assertSame($forAll, $voter->vote($token, self::report(), [Permission::REPORT_VIEW]));
        self::assertSame($forAll, $voter->vote($token, self::report(), [Permission::REPORT_CONFIRM]));
        self::assertSame($forAdmins, $voter->vote($token, self::report(ReportVisibility::Admins), [Permission::REPORT_VIEW]));
        self::assertSame($manage, $voter->vote($token, null, [Permission::REPORT_MANAGE]));
        self::assertSame($manage, $voter->vote($token, self::report(), [Permission::REPORT_MANAGE]));
    }

    public function testClosedReportsAreNotNoticedAgain(): void
    {
        $voter = new ReportVoter(self::viewerProvider());

        self::assertSame(self::GRANTED, $voter->vote(self::token('anna'), self::report(open: false), [Permission::REPORT_VIEW]));
        self::assertSame(self::DENIED, $voter->vote(self::token('anna'), self::report(open: false), [Permission::REPORT_CONFIRM]));
    }

    /**
     * @return iterable<string, array{string, int, int, int}>
     */
    public static function lists(): iterable
    {
        // who => use lists, change Anna's entry, change the tablet's entry
        yield 'Manager' => ['max', self::GRANTED, self::GRANTED, self::GRANTED];
        yield 'Manager per PIN' => ['max-pin', self::GRANTED, self::DENIED, self::DENIED];
        yield 'Anna (hat angelegt)' => ['anna', self::GRANTED, self::GRANTED, self::DENIED];
        yield 'Ben' => ['ben', self::GRANTED, self::DENIED, self::DENIED];
        yield 'Team-Tablet' => ['tablet', self::GRANTED, self::DENIED, self::GRANTED];
    }

    #[DataProvider('lists')]
    public function testWhoUsesListsAndChangesEntries(string $who, int $use, int $annasEntry, int $tabletsEntry): void
    {
        $voter = new ShoppingVoter(self::viewerProvider());
        $list = new ShoppingList('Einkauf');
        $token = self::token($who);

        self::assertSame($use, $voter->vote($token, null, [Permission::LIST_USE]));
        self::assertSame($annasEntry, $voter->vote($token, new ShoppingItem($list, 'Milch', self::person('anna')), [Permission::LIST_ITEM_EDIT]));
        self::assertSame($tabletsEntry, $voter->vote($token, new ShoppingItem($list, 'Brot', self::tablet()), [Permission::LIST_ITEM_EDIT]));
    }

    public function testNotificationsOnlyForTheirPersonNeverAtTheTablet(): void
    {
        $voter = new NotificationVoter(self::viewerProvider());
        $notification = new Notification(self::person('max'), NotificationKind::Meldung, 'Neue Meldung: Seife leer', '/meldungen', false, new \DateTimeImmutable('2026-12-05'));

        self::assertSame(self::GRANTED, $voter->vote(self::token('max'), null, [Permission::NOTIFICATION_VIEW]));
        self::assertSame(self::GRANTED, $voter->vote(self::token('max'), $notification, [Permission::NOTIFICATION_VIEW]));
        self::assertSame(self::GRANTED, $voter->vote(self::token('anna'), null, [Permission::NOTIFICATION_VIEW]));
        self::assertSame(self::DENIED, $voter->vote(self::token('anna'), $notification, [Permission::NOTIFICATION_VIEW]));
        self::assertSame(self::DENIED, $voter->vote(self::token('tablet'), null, [Permission::NOTIFICATION_VIEW]));
    }
}
