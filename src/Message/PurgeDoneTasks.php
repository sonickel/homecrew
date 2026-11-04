<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Retention job: delete closed tasks whose completion is older than app_setting.retention_done_task_days
 * (konzept-5 §4.3) – of every plan the latest app_setting.completion_keep_last stay.
 */
final readonly class PurgeDoneTasks
{
}
