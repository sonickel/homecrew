<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Attributes for is_granted()/denyAccessUnlessGranted(); every decision is made by a voter in App\Security\Voter.
 */
final class Permission
{
    /** Administration ("Verwaltung"): admin right, second factor set up, full personal login. */
    public const string ADMIN = 'ADMIN';
}
