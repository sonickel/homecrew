<?php

declare(strict_types=1);

namespace App\Security\Device;

use App\Entity\Device;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;

/**
 * Token of the team tablet: the user is the Device, never a person. A class of its own, so that
 * scheb/2fa (which matches token classes exactly) never asks a tablet for a second factor.
 */
final class DeviceToken extends PostAuthenticationToken
{
    public function getDevice(): Device
    {
        $device = $this->getUser();
        \assert($device instanceof Device);

        return $device;
    }
}
