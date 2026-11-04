<?php

declare(strict_types=1);

namespace App\Service\Report;

use App\Entity\IssueReport;
use App\Enum\ReportStatus;
use App\Service\Household\HouseholdContext;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Attribute\AsTwigFunction;

/**
 * Texts about a report: status with symbol and word, how long it is open, who sent and who closed it (the last two
 * only in templates of the admins). The texts come translated.
 */
final readonly class ReportPresenter
{
    public function __construct(
        private HouseholdContext $household,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array{modifier: string, icon: string, text: string} modifier as in App\Service\Task\TaskPresenter::status()
     */
    #[AsTwigFunction('report_status')]
    public function status(IssueReport $report): array
    {
        [$modifier, $icon] = match ($report->getStatus()) {
            ReportStatus::Offen => ['due', 'tabler:alert-circle'],
            ReportStatus::InArbeit => ['possible', 'tabler:progress'],
            ReportStatus::Erledigt => ['ok', 'tabler:circle-check'],
            ReportStatus::Verworfen => ['later', 'tabler:circle-off'],
        };

        return ['modifier' => $modifier, 'icon' => $icon, 'text' => $this->translator->trans($report->getStatus()->label())];
    }

    /**
     * "Seit 3 Tagen offen" (konzept-13 §4) – closed reports: "Gemeldet am 3. November 2026".
     */
    #[AsTwigFunction('report_age')]
    public function age(IssueReport $report): string
    {
        $reported = $this->day($report->getCreatedAt());
        if (!$report->isOpen()) {
            return $this->translator->trans('Gemeldet am %day%', ['%day%' => $this->longDay($reported)]);
        }
        $days = (int) $reported->diff($this->household->today())->format('%r%a');

        return match (true) {
            $days <= 0 => $this->translator->trans('Seit heute offen'),
            1 === $days => $this->translator->trans('Seit gestern offen'),
            default => $this->translator->trans('Seit %days% Tagen offen', ['%days%' => $days]),
        };
    }

    /**
     * Who sent it – only for admins (konzept-13 §6): the person, "Team-Tablet", or nobody any more after the
     * anonymisation.
     */
    #[AsTwigFunction('report_sender')]
    public function sender(IssueReport $report): string
    {
        return match (true) {
            null !== $report->getCreatedBy() => $report->getCreatedBy()->getDisplayName(),
            null !== $report->getCreatedByDevice() => $this->translator->trans('Team-Tablet'),
            null !== $report->getAnonymizedAt() => $this->translator->trans('nicht mehr gespeichert'),
            default => $this->translator->trans('unbekannt'),
        };
    }

    /**
     * "4-mal, zuletzt am 2. November 2026, etwa alle 3 Wochen" for "Was fehlt oft?".
     *
     * @param array{title: string, count: int, first: \DateTimeImmutable, last: \DateTimeImmutable} $row
     */
    #[AsTwigFunction('report_frequency')]
    public function frequency(array $row): string
    {
        $text = $this->translator->trans('%times%-mal, zuletzt am %day%', ['%times%' => $row['count'], '%day%' => $this->longDay($this->day($row['last']))]);
        $days = (int) round(($row['last']->getTimestamp() - $row['first']->getTimestamp()) / 86400 / ($row['count'] - 1));
        if ($days < 1) {
            return $text;
        }

        return $text.', '.($days < 14
            ? $this->translator->trans('etwa alle %days% Tage', ['%days%' => $days])
            : $this->translator->trans('etwa alle %weeks% Wochen', ['%weeks%' => (int) round($days / 7)]));
    }

    /**
     * A point in time (UTC) as household date at midnight.
     */
    private function day(\DateTimeImmutable $at): \DateTimeImmutable
    {
        return $at->setTimezone($this->household->timezone())->setTime(0, 0);
    }

    private function longDay(\DateTimeImmutable $day): string
    {
        return (string) (new \IntlDateFormatter('de', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $day->getTimezone(), null, 'd. MMMM yyyy'))->format($day);
    }
}
