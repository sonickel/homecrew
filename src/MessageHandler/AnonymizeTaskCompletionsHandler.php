<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\AnonymizeTaskCompletions;
use App\Repository\AppSettingRepository;
use App\Repository\TaskCompletionRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class AnonymizeTaskCompletionsHandler
{
    public function __construct(
        private AppSettingRepository $settingRepository,
        private TaskCompletionRepository $completionRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(AnonymizeTaskCompletions $message): int
    {
        $days = $this->settingRepository->get()->getRetentionCompletionDays();
        $now = $this->clock->now();

        return $this->completionRepository->anonymizeBefore($now->modify(\sprintf('-%d days', $days)), $now);
    }
}
