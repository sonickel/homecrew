<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Retention job: delete expired invitations (konzept-5 §4.3 "Reset-Links nach Ablauf").
 */
final readonly class PurgeInvitations
{
}
