<?php

declare(strict_types=1);

namespace App\Service\Household;

/**
 * A household date as short German text: "heute", "morgen", "gestern" or "Mo., 2.11." (same year)
 * or "Mo., 2.11.2027". Dates of DATE columns carry no time zone – they are formatted as they are.
 */
final class DayText
{
    /**
     * @param string $today household date Y-m-d
     */
    public static function day(\DateTimeImmutable $day, string $today): string
    {
        $value = $day->format('Y-m-d');
        $todayDate = new \DateTimeImmutable($today);

        return match ($value) {
            $today => 'heute',
            $todayDate->modify('+1 day')->format('Y-m-d') => 'morgen',
            $todayDate->modify('-1 day')->format('Y-m-d') => 'gestern',
            default => self::format($day, $day->format('Y') === $todayDate->format('Y') ? 'EEEEEE, d.M.' : 'EEEEEE, d.M.yyyy'),
        };
    }

    /**
     * "Montag, 2. November" – for headings and details.
     */
    public static function long(\DateTimeImmutable $day): string
    {
        return self::format($day, 'EEEE, d. MMMM yyyy');
    }

    private static function format(\DateTimeImmutable $day, string $pattern): string
    {
        $formatter = new \IntlDateFormatter('de', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, $day->getTimezone(), null, $pattern);

        return (string) $formatter->format($day);
    }
}
