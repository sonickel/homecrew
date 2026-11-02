<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Retention job: delete audit log entries older than app_setting.retention_audit_days (konzept-5 §4.3).
 */
final readonly class PurgeAuditLog
{
}
