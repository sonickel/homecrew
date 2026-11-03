<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PurgeDoneTasks;
use App\Repository\AppSettingRepository;
use App\Repository\TaskRepository;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class PurgeDoneTasksHandler
{
    public function __construct(
        private AppSettingRepository $settingRepository,
        private TaskRepository $taskRepository,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(PurgeDoneTasks $message): int
    {
        $days = $this->settingRepository->get()->getRetentionDoneTaskDays();

        return $this->taskRepository->deleteClosedOneOffBefore($this->clock->now()->modify(\sprintf('-%d days', $days)));
    }
}
