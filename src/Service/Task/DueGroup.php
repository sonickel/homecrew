<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\Task;

/**
 * Where an open task stands on "Heute" (decision M5, konzept-2 §2): overdue after its due date, due on it,
 * "possible today" while its window runs, "when there is time" while it already shows but is due later.
 * "Später" only appears in the list of open tasks (it does not show yet).
 */
enum DueGroup: string
{
    case Ueberfaellig = 'ueberfaellig';
    case HeuteFaellig = 'heute_faellig';
    case HeuteMoeglich = 'heute_moeglich';
    case WennZeitIst = 'wenn_zeit_ist';
    case Spaeter = 'spaeter';

    /**
     * @param string $today household date Y-m-d
     */
    public static function of(Task $task, string $today): self
    {
        $due = $task->getDueDate()->format('Y-m-d');

        return match (true) {
            $due < $today => self::Ueberfaellig,
            $due === $today => self::HeuteFaellig,
            null !== $task->getWindowStart() && $task->getWindowStart()->format('Y-m-d') <= $today => self::HeuteMoeglich,
            null === $task->getVisibleFrom() || $task->getVisibleFrom()->format('Y-m-d') <= $today => self::WennZeitIst,
            default => self::Spaeter,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Ueberfaellig => 'Überfällig',
            self::HeuteFaellig => 'Heute fällig',
            self::HeuteMoeglich => 'Heute möglich',
            self::WennZeitIst => 'Wenn Zeit ist',
            self::Spaeter => 'Später',
        };
    }
}
