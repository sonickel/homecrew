<?php

declare(strict_types=1);

namespace App\Security\Device;

use App\Entity\Device;
use App\Repository\DeviceRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Reloads the team tablet of a session on every request. An unpaired or deleted device ends the session
 * (the firewall then treats the request as without login; App\Security\Device\DeviceAuthenticator explains why).
 *
 * @implements UserProviderInterface<Device>
 */
final readonly class DeviceUserProvider implements UserProviderInterface
{
    public function __construct(
        private DeviceRepository $deviceRepository,
    ) {
    }

    public function refreshUser(UserInterface $user): Device
    {
        if (!$user instanceof Device) {
            throw new UnsupportedUserException(\sprintf('Unsupported user "%s".', $user::class));
        }

        return $this->load($user->getId());
    }

    public function supportsClass(string $class): bool
    {
        return Device::class === $class || is_subclass_of($class, Device::class);
    }

    public function loadUserByIdentifier(string $identifier): Device
    {
        return $this->load(str_starts_with($identifier, 'geraet-') ? (int) substr($identifier, 7) : null);
    }

    private function load(?int $id): Device
    {
        $device = null === $id ? null : $this->deviceRepository->find($id);
        if (null === $device || $device->isRevoked()) {
            $exception = new UserNotFoundException('The device is not paired.');
            $exception->setUserIdentifier('geraet-'.$id);

            throw $exception;
        }

        return $device;
    }
}
