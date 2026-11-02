<?php

declare(strict_types=1);

namespace App\Security\Pin;

use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * A PIN login that did not work, with the text the tablet shows (what happened + what to do).
 */
final class PinLoginException extends CustomUserMessageAuthenticationException
{
}
