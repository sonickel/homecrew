<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Transport encryption of the SMTP connection (app_setting.smtp_encryption).
 */
enum SmtpEncryption: string
{
    case Starttls = 'starttls';
    case Smtps = 'smtps';
}
