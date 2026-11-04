<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Nightly run of the plans (App\Service\Task\PlanSync): new occurrences for the next days, missed ones replaced,
 * "nächste Fälligkeit" up to date.
 */
final readonly class GenerateTaskOccurrences
{
}
