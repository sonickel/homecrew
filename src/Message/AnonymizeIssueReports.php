<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Retention job: remove the person from reports sent longer ago than app_setting.retention_completion_days
 * (konzept-13 §8) – the tablet stays, it is no person.
 */
final readonly class AnonymizeIssueReports
{
}
