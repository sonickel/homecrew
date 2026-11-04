<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\AppUser;
use App\Entity\IssueReport;
use App\Entity\Task;
use App\Enum\NotificationEvent;
use App\Enum\ReportKind;
use App\Enum\ReportStatus;
use App\Enum\ReportVisibility;
use App\Enum\ShoppingSource;
use App\Enum\TaskCategory;
use App\Enum\Visibility;
use App\Form\Report\ReportData;
use App\Form\Shopping\ShoppingItemData;
use App\Form\Task\TaskData;
use App\Service\Notification\NotificationRecipients;
use App\Service\Notification\Notifier;
use App\Service\Shopping\ShoppingItems;
use App\Service\Task\TaskActions;
use App\Service\Viewer\Viewer;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Reports (konzept-13): "Melden", "Nicht möglich" at a task, "Auch bemerkt" – and for the admins handing on and
 * closing. A report goes to the admins at once (bell); with a follow-up – an entry on a list or a task – it is
 * "in Arbeit" and closes on its own (ReportClosing). The callers check the rights (App\Security\Voter\ReportVoter).
 */
final readonly class IssueReports
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TaskActions $taskActions,
        private ShoppingItems $shoppingItems,
        private ReportClosing $reportClosing,
        private Notifier $notifier,
        private NotificationRecipients $recipients,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
    ) {
    }

    public function report(ReportKind $kind, ReportData $data, Viewer $viewer): IssueReport
    {
        return $this->entityManager->wrapInTransaction(function () use ($kind, $data, $viewer): IssueReport {
            $report = $this->create($kind, $data, $viewer, null);
            $this->followUp($report, $data, $viewer, null);
            $this->announce($report, $viewer);

            return $report;
        });
    }

    /**
     * "Nicht möglich": closes the task and creates exactly one report for it – null when the task was closed already
     * (a second tap, another device).
     */
    public function reportUnable(Task $task, ReportData $data, Viewer $viewer): ?IssueReport
    {
        $report = null;
        $closed = $this->taskActions->closeUnable($task, $viewer, trim((string) $data->title), function () use ($task, $data, $viewer, &$report): void {
            $report = $this->create(ReportKind::NichtMoeglich, $data, $viewer, $task);
            $this->followUp($report, $data, $viewer, $task);
            $this->announce($report, $viewer);
        });

        return $closed ? $report : null;
    }

    /**
     * "Auch bemerkt": counts instead of a second report; false when the report was closed meanwhile.
     */
    public function confirm(IssueReport $report): bool
    {
        return $this->whileOpen($report, fn () => $report->confirm($this->clock->now()));
    }

    /**
     * Admins hand a report on to a list or a task – it replaces an earlier follow-up. False when it is closed.
     */
    public function forward(IssueReport $report, ReportData $data, Viewer $viewer): bool
    {
        return $this->whileOpen($report, fn () => $this->followUp($report, $data, $viewer, null));
    }

    /**
     * Admins close a report as done or discard it, with an optional note. False when it is closed already.
     */
    public function close(IssueReport $report, ReportStatus $status, ?string $note, AppUser $admin): bool
    {
        $note = trim((string) $note);

        return $this->whileOpen($report, fn () => $report->close($status, $this->clock->now(), $admin, '' === $note ? null : mb_substr($note, 0, IssueReport::CLOSE_NOTE_MAX), $this->reportClosing->deleteAfter()));
    }

    /**
     * Runs a change under a row lock and only while the report is open (it may close on its own meanwhile).
     * Refusals come back as false: an exception would close the entity manager (wrapInTransaction).
     */
    private function whileOpen(IssueReport $report, \Closure $change): bool
    {
        return $this->entityManager->wrapInTransaction(function () use ($report, $change): bool {
            $this->entityManager->lock($report, LockMode::PESSIMISTIC_WRITE);
            $this->entityManager->refresh($report);
            if (!$report->isOpen()) {
                return false;
            }
            $change();

            return true;
        });
    }

    private function create(ReportKind $kind, ReportData $data, Viewer $viewer, ?Task $task): IssueReport
    {
        $report = new IssueReport($kind, trim((string) $data->title), $viewer->actor(), $task);
        $note = trim((string) $data->note);
        $report->describe('' === $note ? null : $note, $data->urgent, $data->visibility());
        $this->entityManager->persist($report);

        return $report;
    }

    private function followUp(IssueReport $report, ReportData $data, Viewer $viewer, ?Task $sourceTask): void
    {
        if (ReportData::FOLLOW_LIST === $data->followUp && null !== $data->list) {
            $item = new ShoppingItemData();
            $item->name = $data->itemForList();
            $report->followUpWith($this->shoppingItems->add($data->list, $item, $viewer, ShoppingSource::Meldung, $sourceTask));
        }
        if (ReportData::FOLLOW_TASK === $data->followUp) {
            $report->followUpWith($this->taskActions->create($this->taskData($report, $data), $viewer));
        }
        $this->entityManager->flush();
    }

    /**
     * The follow-up task: the report's title for whoever was chosen; a report for admins only gives a task that only
     * the responsible and the admins see.
     */
    private function taskData(IssueReport $report, ReportData $data): TaskData
    {
        $task = new TaskData($data->earliestDueDate);
        $task->title = $report->getTitle();
        $task->description = $report->getNote();
        $task->category = TaskCategory::Sonstiges;
        $task->dueDate = $data->dueDate;
        $task->assignee = $data->assignee;
        $task->visibility = ReportVisibility::Admins === $report->getVisibility() ? Visibility::Zustaendig : Visibility::Alle;

        return $task;
    }

    /**
     * A report for admins only keeps its title out of the notification – it may show on a shared screen.
     */
    private function title(IssueReport $report): string
    {
        if ($report->isForAdminsOnly()) {
            return $this->translator->trans($report->isUrgent() ? 'Dringende Meldung (nur für Admins)' : 'Neue Meldung (nur für Admins)');
        }

        return $this->translator->trans($report->isUrgent() ? 'Dringende Meldung: %title%' : 'Neue Meldung: %title%', ['%title%' => $report->getTitle()]);
    }

    private function announce(IssueReport $report, Viewer $viewer): void
    {
        $this->notifier->notify(
            NotificationEvent::Meldung,
            $this->recipients->admins(),
            $this->title($report),
            $this->urlGenerator->generate('app_report_show', ['publicId' => $report->getPublicId()->toRfc4122()]),
            $viewer->actor(),
            $report->isUrgent(),
        );
    }
}
