<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\AppUser;
use App\Enum\UserRole;
use App\Tests\Support\PeopleTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Form rules of a person (konzept-1 §2): the database repeats the rights rules (tests/Database/AppUserRulesTest).
 */
final class PersonValidationTest extends WebTestCase
{
    use PeopleTrait;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->createPerson(UserRole::Manager, 'max');
    }

    public function testValidPerson(): void
    {
        self::assertSame([], $this->violations(self::person(UserRole::Assistenz, 'anna', 'Anna')));
    }

    public function testAssistantIsNeverAdmin(): void
    {
        $anna = self::person(UserRole::Assistenz, 'anna', 'Anna');
        $anna->setAdmin(true);

        self::assertSame(['admin' => 'Assistenzkräfte können keine Admins sein.'], $this->violations($anna));
    }

    public function testManagerIsAlwaysAdmin(): void
    {
        $manager = $this->entityManager()->getRepository(AppUser::class)->findOneBy(['username' => 'max']);
        self::assertNotNull($manager);
        $manager->setAdmin(false);

        self::assertSame(['admin' => 'Der Manager ist immer Admin.'], $this->violations($manager));
    }

    public function testUsernameAndNameAreUnique(): void
    {
        $person = self::person(UserRole::Angehoerige, ' MAX ', 'Max');

        self::assertSame('max', $person->getUsername());
        self::assertSame([
            'username' => 'Diesen Benutzernamen gibt es schon.',
            'displayName' => 'Diesen Namen gibt es schon. Nimm z. B. einen Spitznamen oder hänge einen Buchstaben an.',
        ], $this->violations($person));
    }

    public function testUsernameFormat(): void
    {
        foreach (['an', 'anna müller', '-anna', 'anna@haus'] as $username) {
            self::assertArrayHasKey('username', $this->violations(self::person(UserRole::Assistenz, $username, 'Anna')), $username);
        }
        foreach (['anna', 'anna.m', 'anna_2', 'a-b'] as $username) {
            self::assertSame([], $this->violations(self::person(UserRole::Assistenz, $username, 'Anna')), $username);
        }
    }

    private static function person(UserRole $role, string $username, string $displayName): AppUser
    {
        $person = new AppUser($role, '', '', new \DateTimeImmutable('2026-01-01'));
        $person->setUsername($username);
        $person->setDisplayName($displayName);

        return $person;
    }

    /**
     * @return array<string, string> first message per field
     */
    private function violations(AppUser $person): array
    {
        $messages = [];
        foreach (static::getContainer()->get(ValidatorInterface::class)->validate($person) as $violation) {
            $messages[$violation->getPropertyPath()] ??= (string) $violation->getMessage();
        }

        return $messages;
    }
}
