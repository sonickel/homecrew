<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Retention job: delete closed reports after app_setting.retention_report_days (konzept-13 §8); open ones stay.
 */
final readonly class PurgeIssueReports
{
}
