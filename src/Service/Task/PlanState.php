<?php

declare(strict_types=1);

namespace App\Service\Task;

use App\Entity\Task;
use App\Entity\TaskTemplate;

/**
 * Where a plan stands in "Routinen & Wartung" – in the order of urgency: by its open occurrence due first
 * (as on "Heute"), else paused, without a further day, or ended.
 */
enum PlanState: string
{
    case Ueberfaellig = 'ueberfaellig';
    case HeuteFaellig = 'heute_faellig';
    case HeuteMoeglich = 'heute_moeglich';
    case Geplant = 'geplant';
    case Pausiert = 'pausiert';
    case NichtsGeplant = 'nichts_geplant';
    case Beendet = 'beendet';

    /**
     * @param string $today household date Y-m-d
     */
    public static function of(TaskTemplate $plan, ?Task $next, string $today): self
    {
        if (!$plan->isActive() || (null === $next && !$plan->runsOn($today) && !$plan->isPausedOn($today))) {
            return self::Beendet;
        }
        if ($plan->isPausedOn($today)) {
            return self::Pausiert;
        }

        return match (null === $next ? null : DueGroup::of($next, $today)) {
            null => self::NichtsGeplant,
            DueGroup::Ueberfaellig => self::Ueberfaellig,
            DueGroup::HeuteFaellig => self::HeuteFaellig,
            DueGroup::HeuteMoeglich => self::HeuteMoeglich,
            default => self::Geplant,
        };
    }

    public function rank(): int
    {
        return (int) array_search($this, self::cases(), true);
    }
}
