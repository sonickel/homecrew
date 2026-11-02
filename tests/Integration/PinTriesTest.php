<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Device;
use App\Enum\UserRole;
use App\Security\Pin\PinLoginException;
use App\Service\Tablet\DeviceCookie;
use App\Service\Tablet\PinLogin;
use App\Tests\Support\PeopleTrait;
use App\Tests\Support\TabletTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Wrong PINs count per person even when requests run at the same time (App\Service\Tablet\PinLogin):
 * each try reads the counter under a row lock instead of trusting the person loaded at the start of the request.
 */
final class PinTriesTest extends WebTestCase
{
    use PeopleTrait;
    use TabletTrait;

    public function testATryCountsWhatAParallelRequestSavedMeanwhile(): void
    {
        $this->startClient();
        $device = new Device('Flur', DeviceCookie::hash('token'));
        $this->entityManager()->persist($device);
        $anna = $this->createPerson(UserRole::Assistenz, 'anna', password: null);
        $this->givePin($anna, '482913');

        // Two parallel requests counted their wrong PINs after this request had loaded Anna
        $this->entityManager()->getConnection()->executeStatement('UPDATE app_user SET pin_failed_count = 2 WHERE id = ?', [$anna->getId()]);

        try {
            static::getContainer()->get(PinLogin::class)->verify($anna, '111222', $device);
            self::fail('A wrong PIN must not work.');
        } catch (PinLoginException $exception) {
            self::assertStringStartsWith('Die PIN stimmt nicht. Bitte warte 5 Minuten', $exception->getMessageKey());
        }
        self::assertTrue($anna->isPinLockedAt(new \DateTimeImmutable()));
        self::assertSame(['anmeldung.gesperrt'], $this->loggedActions());
    }
}
