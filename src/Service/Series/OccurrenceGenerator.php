<?php

declare(strict_types=1);

namespace App\Service\Series;

use RRule\RRule;

/**
 * The days (and times) a series has occurrences on – one generator for plans (M6), appointment and visit series (M8)
 * and the roster (M9), based on php-rrule. It counts in the household's time zone, so "10:00" stays 10:00 local
 * time across the change to and from summer time. Which occurrences exist already is up to the caller.
 */
final readonly class OccurrenceGenerator
{
    /** Gives up looking for the next occurrence after this many pauses (a series with nothing left). */
    private const int MAX_STEPS = 50;

    /**
     * Occurrences from one day to another (both included), in order.
     *
     * @param string $from household date Y-m-d
     * @param string $to   household date Y-m-d
     *
     * @return list<\DateTimeImmutable> local date and time
     */
    public function between(SeriesRule $rule, string $from, string $to): array
    {
        $from = max($from, $rule->startsOn);
        $to = null === $rule->endsOn ? $to : min($to, $rule->endsOn);
        if ($from > $to) {
            return [];
        }

        $occurrences = [];
        foreach (self::rrule($rule)->getOccurrencesBetween(self::at($rule, $from, '00:00:00'), self::at($rule, $to, '23:59:59')) as $occurrence) {
            $occurrence = \DateTimeImmutable::createFromInterface($occurrence);
            if (!$rule->isPausedOn($occurrence->format('Y-m-d'))) {
                $occurrences[] = $occurrence;
            }
        }

        return $occurrences;
    }

    /**
     * The first occurrence on or after a day – also far ahead (e.g. yearly); null when the series has none left.
     *
     * @param string $from household date Y-m-d
     */
    public function next(SeriesRule $rule, string $from): ?\DateTimeImmutable
    {
        $rrule = self::rrule($rule);
        $day = max($from, $rule->startsOn);
        for ($step = 0; $step < self::MAX_STEPS; ++$step) {
            $found = $rrule->getOccurrencesAfter(self::at($rule, $day, '00:00:00'), true, 1)[0] ?? null;
            if (null === $found) {
                return null;
            }
            $occurrence = \DateTimeImmutable::createFromInterface($found);
            $date = $occurrence->format('Y-m-d');
            if ($rule->hasEndedBy($date)) {
                return null;
            }
            if (!$rule->isPausedOn($date)) {
                return $occurrence;
            }
            if (null === $rule->pausedUntil) {
                return null;
            }
            $day = (new \DateTimeImmutable($rule->pausedUntil))->modify('+1 day')->format('Y-m-d');
        }

        return null;
    }

    /**
     * @return RRule&iterable<int, \DateTimeInterface>
     */
    private static function rrule(SeriesRule $rule): RRule
    {
        return new RRule($rule->rrule, self::at($rule, $rule->startsOn, ($rule->time ?? '00:00').':00'));
    }

    private static function at(SeriesRule $rule, string $day, string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable($day.' '.$time, $rule->timezone);
    }
}
