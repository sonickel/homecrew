<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Enum\DeviceMode;
use App\Service\Tablet\DeviceCookie;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Paired tablets for functional tests: a device row plus its cookie in the test browser.
 * Test requests come from 127.0.0.1, which HOME_NETWORKS (.env) counts as home network.
 * Use together with PeopleTrait (entityManager()).
 */
trait TabletTrait
{
    protected const string TABLET_TOKEN = 'test-tablet-token-0123456789';

    protected function pairTablet(KernelBrowser $client, DeviceMode $mode = DeviceMode::Team, string $token = self::TABLET_TOKEN): Device
    {
        $device = new Device('Flur', DeviceCookie::hash($token));
        $device->setMode($mode);
        $this->entityManager()->persist($device);
        $this->entityManager()->flush();
        $client->getCookieJar()->set(new Cookie(DeviceCookie::NAME, $token, null, '/', 'localhost'));

        return $device;
    }

    /**
     * A request from outside the home network (another address or through the tunnel).
     *
     * @param array<string, string> $server extra server parameters, e.g. ['HTTP_CF_CONNECTING_IP' => '…']
     */
    protected static function fromOutside(KernelBrowser $client, array $server = ['REMOTE_ADDR' => '203.0.113.5']): void
    {
        $client->setServerParameters($server);
    }

    protected function givePin(AppUser $person, string $pin, bool $mustChange = false): void
    {
        $person->changePin(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($person, $pin), $mustChange);
        $person->enablePinLogin(true);
        $this->entityManager()->flush();
    }

    protected function loginWithPin(KernelBrowser $client, AppUser $person, string $pin): void
    {
        $client->request('GET', '/tablet/personen/'.$person->getPublicId());
        $client->submitForm('Anmelden', ['pin' => $pin]);
    }
}
