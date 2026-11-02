<?php

declare(strict_types=1);

namespace App\Security\Device;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * The device cookie belongs to no paired tablet (unknown token or unpaired) – the only reason to delete the cookie.
 * Other failures (database down, …) leave it in place: the tablet tries again with the next request.
 */
final class DeviceUnpairedException extends CustomUserMessageAuthenticationException
{
    public function __construct()
    {
        parent::__construct(DeviceAuthenticator::UNPAIRED_MESSAGE);
    }
}
