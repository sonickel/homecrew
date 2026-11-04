<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Retention job: delete notifications after app_setting.retention_notification_days (konzept-9 §7).
 */
final readonly class PurgeNotifications
{
}
