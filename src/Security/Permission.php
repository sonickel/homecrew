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

    /** Change a person's master data and rights (subject: AppUser). */
    public const string PERSON_EDIT = 'PERSON_EDIT';

    /** Manage another person's access: start password/PIN, invitation, reset, lock, leaving (subject: AppUser). */
    public const string PERSON_ACCESS = 'PERSON_ACCESS';
}
