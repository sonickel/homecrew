<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Entity\AppUser;

/**
 * Privacy notice (konzept-5 §4): every person with an own login confirms the current version once.
 * A new version (e.g. when an extension with a new processor is set up, M13) asks everybody again.
 */
final readonly class PrivacyNotice
{
    public const string VERSION = '2026-11-02';

    public function needsAcknowledgement(AppUser $user): bool
    {
        return self::VERSION !== $user->getPrivacyNoticeVersion();
    }
}
