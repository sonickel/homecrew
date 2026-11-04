<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\AppUser;
use App\Entity\Device;
use App\Entity\Task;
use App\Enum\NotificationEvent;
use App\Service\Notification\NotificationRecipients;
use App\Service\Notification\Notifier;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * "Aufgabe für dich" (konzept-13 §7): whoever becomes responsible for a task – created, handed on or coming from a
 * report – is told in the app; a person, everybody with the role, for "wer gerade da ist" the assistants
 * (decision 04.11.). Occurrences of plans are not announced, they come every time.
 */
final readonly class TaskNotifier
{
    public function __construct(
        private Notifier $notifier,
        private NotificationRecipients $recipients,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
    ) {
    }

    public function assigned(Task $task, AppUser|Device $actor): void
    {
        $this->notifier->notify(
            NotificationEvent::Aufgabe,
            $this->recipients->responsibleForTask($task),
            $this->translator->trans('Aufgabe für dich: %title%', ['%title%' => $task->getTitle()]),
            $this->urlGenerator->generate('app_task_show', ['id' => $task->getId()]),
            $actor,
        );
    }
}
