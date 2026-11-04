<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\IssueReport;
use App\Entity\ShoppingItem;
use App\Entity\ShoppingList;
use App\Entity\Task;
use App\Enum\NotificationEvent;
use App\Enum\ReportStatus;
use App\Repository\AppSettingRepository;
use App\Repository\IssueReportRepository;
use App\Service\Household\HouseholdContext;
use App\Service\Notification\NotificationRecipients;
use App\Service\Notification\Notifier;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Automatic closing (konzept-13 §4): a report is done as soon as its list entry is ticked off or its task is done –
 * without a person (closed_by stays empty). Taking the entry back or "Rückgängig" opens it again. When the follow-up
 * does not work out – the entry is removed, its list switched off, the task skipped or not possible – the report is
 * "offen" again, so the admins notice it. Called by App\Service\Shopping\ShoppingItems and App\Service\Task\TaskActions
 * inside their transactions; they flush.
 */
final readonly class ReportClosing
{
    public const string NOTE_ITEM_CHECKED = 'automatisch: Eintrag abgehakt';
    public const string NOTE_TASK_DONE = 'automatisch: Aufgabe erledigt';

    public function __construct(
        private IssueReportRepository $reportRepository,
        private AppSettingRepository $settingRepository,
        private HouseholdContext $household,
        private Notifier $notifier,
        private NotificationRecipients $recipients,
        private UrlGeneratorInterface $urlGenerator,
        private TranslatorInterface $translator,
        private ClockInterface $clock,
    ) {
    }

    public function itemChecked(ShoppingItem $item): void
    {
        foreach ($this->reportRepository->findByFollowUpItem($item) as $report) {
            $this->closeDone($report, self::NOTE_ITEM_CHECKED);
        }
    }

    public function itemUnchecked(ShoppingItem $item): void
    {
        foreach ($this->reportRepository->findByFollowUpItem($item) as $report) {
            if ($report->isClosedAutomatically()) {
                $report->reopen();
            }
        }
    }

    /**
     * Before an entry is removed from its list: its reports are open again (nothing would close them).
     */
    public function itemRemoved(ShoppingItem $item): void
    {
        foreach ($this->reportRepository->findByFollowUpItem($item) as $report) {
            if ($report->isOpen()) {
                $report->dropFollowUp();
            }
        }
    }

    /**
     * A list was switched off: its open entries will not be ticked off any more.
     */
    public function listSwitchedOff(ShoppingList $list): void
    {
        foreach ($this->reportRepository->findOpenWithOpenEntryOn($list) as $report) {
            $report->followUpFailed();
        }
    }

    public function taskDone(Task $task): void
    {
        foreach ($this->reportRepository->findByFollowUpTask($task) as $report) {
            $this->closeDone($report, self::NOTE_TASK_DONE);
        }
    }

    /**
     * The follow-up task was skipped or was not possible.
     */
    public function taskDropped(Task $task): void
    {
        foreach ($this->reportRepository->findByFollowUpTask($task) as $report) {
            $report->followUpFailed();
        }
    }

    public function taskReopened(Task $task): void
    {
        foreach ($this->reportRepository->findByFollowUpTask($task) as $report) {
            if ($report->isClosedAutomatically()) {
                $report->reopen();
            } else {
                $report->resumeFollowUp();
            }
        }
    }

    /**
     * Delete date of a report closed today (retention_report_days).
     */
    public function deleteAfter(): \DateTimeImmutable
    {
        $days = $this->settingRepository->get()->getRetentionReportDays();

        return new \DateTimeImmutable($this->household->today()->modify(\sprintf('+%d days', $days))->format('Y-m-d'));
    }

    private function closeDone(IssueReport $report, string $note): void
    {
        if (!$report->isOpen()) {
            return;
        }
        $report->close(ReportStatus::Erledigt, $this->clock->now(), null, $note, $this->deleteAfter());
        $this->notifier->notify(
            NotificationEvent::MeldungErledigt,
            $this->recipients->admins(),
            $report->isForAdminsOnly() ? $this->translator->trans('Meldung erledigt (nur für Admins)') : $this->translator->trans('Meldung erledigt: %title%', ['%title%' => $report->getTitle()]),
            $this->urlGenerator->generate('app_report_show', ['publicId' => $report->getPublicId()->toRfc4122()]),
            null,
        );
    }
}
