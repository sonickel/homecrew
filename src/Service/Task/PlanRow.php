<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\Task;
use App\Entity\TaskCompletion;
use App\Entity\TaskTemplate;

/**
 * A plan in "Routinen & Wartung": its open occurrence due first, where it stands and – for admins only – the
 * latest completion with the person or the tablet (konzept-12 §6).
 */
final readonly class PlanRow
{
    public function __construct(
        public TaskTemplate $plan,
        public ?Task $next,
        public PlanState $state,
        public ?TaskCompletion $lastDone,
    ) {
    }
}
