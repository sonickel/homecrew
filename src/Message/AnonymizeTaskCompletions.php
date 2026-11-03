<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Retention job: remove the person from completions older than app_setting.retention_completion_days
 * (konzept-5 §4.3) – the tablet stays, it is no person.
 */
final readonly class AnonymizeTaskCompletions
{
}
