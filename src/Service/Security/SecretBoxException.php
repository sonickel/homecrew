<?php

declare(strict_types=1);

namespace App\Service\Security;

/**
 * An encrypted value cannot be read: missing or wrong master key, or the value was altered.
 */
final class SecretBoxException extends \RuntimeException
{
}
