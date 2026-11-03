<?php

declare(strict_types=1);

namespace App\Service\Household;

/**
 * Whole weeks (Monday to Sunday) of household dates for the small week calendar (e.g. "Verschieben").
 */
final class WeekDays
{
    public const int MAX_OFFSET = 52;

    /**
     * @param string $today  household date Y-m-d
     * @param int    $offset weeks after the current one (0 = this week)
     *
     * @return list<list<\DateTimeImmutable>> $count weeks of seven days
     */
    public static function weeks(string $today, int $offset, int $count = 2): array
    {
        $day = new \DateTimeImmutable($today);
        $monday = $day->modify(\sprintf('-%d days', (int) $day->format('N') - 1))->modify(\sprintf('+%d weeks', max(0, min(self::MAX_OFFSET, $offset))));

        $weeks = [];
        for ($week = 0; $week < $count; ++$week) {
            $days = [];
            for ($weekday = 0; $weekday < 7; ++$weekday) {
                $days[] = $monday->modify(\sprintf('+%d days', 7 * $week + $weekday));
            }
            $weeks[] = $days;
        }

        return $weeks;
    }
}
