<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GenerateTaskOccurrences;
use App\Service\Task\PlanSync;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateTaskOccurrencesHandler
{
    public function __construct(
        private PlanSync $planSync,
    ) {
    }

    public function __invoke(GenerateTaskOccurrences $message): int
    {
        return $this->planSync->syncAll();
    }
}
