<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Security\Permission;
use App\Security\TwoFactor\TwoFactorPolicy;
use App\Security\Voter\AdminVoter;
use App\Security\Voter\PersonVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManager;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class PersonVoterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string, int}>
     */
    public static function cases(): iterable
    {
        // [who, attribute, about whom, expected]
        yield 'Manager bearbeitet Anna' => ['max', Permission::PERSON_EDIT, 'anna', VoterInterface::ACCESS_GRANTED];
        yield 'Manager verwaltet Annas Zugang' => ['max', Permission::PERSON_ACCESS, 'anna', VoterInterface::ACCESS_GRANTED];
        yield 'Manager bearbeitet sich selbst' => ['max', Permission::PERSON_EDIT, 'max', VoterInterface::ACCESS_GRANTED];
        yield 'Manager verwaltet den eigenen Zugang nicht hier' => ['max', Permission::PERSON_ACCESS, 'max', VoterInterface::ACCESS_DENIED];
        yield 'Schwester (Admin) bearbeitet Anna' => ['schwester', Permission::PERSON_EDIT, 'anna', VoterInterface::ACCESS_GRANTED];
        yield 'Schwester (Admin) verwaltet Papas Zugang' => ['schwester', Permission::PERSON_ACCESS, 'papa', VoterInterface::ACCESS_GRANTED];
        yield 'Schwester ändert den Manager nicht' => ['schwester', Permission::PERSON_EDIT, 'max', VoterInterface::ACCESS_DENIED];
        yield 'Schwester setzt den Zugang des Managers nicht zurück' => ['schwester', Permission::PERSON_ACCESS, 'max', VoterInterface::ACCESS_DENIED];
        yield 'Schwester sperrt sich nicht selbst' => ['schwester', Permission::PERSON_ACCESS, 'schwester', VoterInterface::ACCESS_DENIED];
        yield 'Papa (kein Admin) bearbeitet niemanden' => ['papa', Permission::PERSON_EDIT, 'anna', VoterInterface::ACCESS_DENIED];
        yield 'Papa (kein Admin) bearbeitet sich nicht' => ['papa', Permission::PERSON_EDIT, 'papa', VoterInterface::ACCESS_DENIED];
        yield 'Anna (Assistenz) verwaltet keinen Zugang' => ['anna', Permission::PERSON_ACCESS, 'papa', VoterInterface::ACCESS_DENIED];
    }

    #[DataProvider('cases')]
    public function testPermission(string $who, string $attribute, string $aboutWhom, int $expected): void
    {
        $people = self::people();
        $voter = new PersonVoter(new AccessDecisionManager([new AdminVoter(new TwoFactorPolicy(false))]));

        self::assertSame($expected, $voter->vote(new UsernamePasswordToken($people[$who], 'main'), $people[$aboutWhom], [$attribute]));
    }

    public function testAdminLockedOutByTheTwoFactorRuleManagesNobody(): void
    {
        $people = self::people();
        $voter = new PersonVoter(new AccessDecisionManager([new AdminVoter(new TwoFactorPolicy(true))]));

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new UsernamePasswordToken($people['max'], 'main'), $people['anna'], [Permission::PERSON_EDIT]));
    }

    public function testOtherSubjectsAreLeftToOtherVoters(): void
    {
        $people = self::people();
        $voter = new PersonVoter(new AccessDecisionManager([new AdminVoter(new TwoFactorPolicy(false))]));

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote(new UsernamePasswordToken($people['max'], 'main'), null, [Permission::PERSON_EDIT]));
    }

    /**
     * @return array<string, AppUser>
     */
    private static function people(): array
    {
        $start = new \DateTimeImmutable('2026-01-01');
        $people = [
            'max' => new AppUser(UserRole::Manager, 'max', 'Max', $start, isAdmin: true),
            'schwester' => new AppUser(UserRole::Angehoerige, 'schwester', 'Schwester', $start, isAdmin: true),
            'papa' => new AppUser(UserRole::Angehoerige, 'papa', 'Papa', $start),
            'anna' => new AppUser(UserRole::Assistenz, 'anna', 'Anna', $start),
        ];
        $id = 0;
        foreach ($people as $person) {
            (new \ReflectionProperty(AppUser::class, 'id'))->setValue($person, ++$id);
        }

        return $people;
    }
}
