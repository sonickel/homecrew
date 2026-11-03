<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Retention job: delete closed one-off tasks whose completion is older than app_setting.retention_done_task_days
 * (konzept-5 §4.3). Tasks of plans follow with the plans (M6: the last completion_keep_last stay).
 */
final readonly class PurgeDoneTasks
{
}
