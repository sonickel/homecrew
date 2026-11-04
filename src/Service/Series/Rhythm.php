<?php

declare(strict_types=1);

namespace App\Service\Series;

/**
 * A rhythm by calendar as the forms offer it – daily, on weekdays, monthly on a day, yearly on a date – and its
 * RRULE (RFC 5545). Days 29 to 31 fall on the last day of shorter months (BYSETPOS=-1 over the possible days),
 * 29 February on the 28th in other years. fromRrule() reads back only what rrule() writes.
 */
final readonly class Rhythm
{
    public const string DAILY = 'taeglich';
    public const string WEEKLY = 'woechentlich';
    public const string MONTHLY = 'monatlich';
    public const string YEARLY = 'jaehrlich';

    /** RFC 5545 weekdays in the order of the week (ISO: Monday first). */
    public const array WEEKDAYS = ['MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU'];

    private const array FREQUENCIES = [self::DAILY => 'DAILY', self::WEEKLY => 'WEEKLY', self::MONTHLY => 'MONTHLY', self::YEARLY => 'YEARLY'];

    /**
     * @param list<string> $weekdays RFC 5545 weekdays in the order of the week
     */
    private function __construct(
        public string $frequency,
        public int $every,
        public array $weekdays,
        public ?int $monthDay,
        public ?int $month,
    ) {
    }

    public static function daily(int $every = 1): self
    {
        return new self(self::DAILY, max(1, $every), [], null, null);
    }

    /**
     * @param list<string> $weekdays RFC 5545 weekdays ("MO" …), at least one
     */
    public static function weekly(array $weekdays, int $every = 1): self
    {
        $weekdays = array_values(array_intersect(self::WEEKDAYS, $weekdays));
        if ([] === $weekdays) {
            throw new \DomainException('A weekly rhythm needs at least one weekday.');
        }

        return new self(self::WEEKLY, max(1, $every), $weekdays, null, null);
    }

    public static function monthly(int $day, int $every = 1): self
    {
        return new self(self::MONTHLY, max(1, $every), [], self::monthDay($day), null);
    }

    public static function yearly(int $day, int $month): self
    {
        if ($month < 1 || $month > 12 || $day > (int) (new \DateTimeImmutable(\sprintf('2028-%02d-01', $month)))->format('t')) {
            throw new \DomainException('No such date.');
        }

        return new self(self::YEARLY, 1, [], self::monthDay($day), $month);
    }

    /**
     * Null for rules the forms do not write.
     */
    public static function fromRrule(string $rrule): ?self
    {
        $parts = [];
        foreach (explode(';', strtoupper($rrule)) as $part) {
            [$key, $value] = array_pad(explode('=', $part, 2), 2, '');
            $parts[$key] = $value;
        }
        $frequency = array_search($parts['FREQ'] ?? '', self::FREQUENCIES, true);
        $every = (int) ($parts['INTERVAL'] ?? 1);
        $monthDays = isset($parts['BYMONTHDAY']) ? array_map(intval(...), explode(',', $parts['BYMONTHDAY'])) : [];
        $monthDay = [] === $monthDays ? null : max($monthDays);

        try {
            $rhythm = match ($frequency) {
                self::DAILY => self::daily($every),
                self::WEEKLY => self::weekly(explode(',', $parts['BYDAY'] ?? ''), $every),
                self::MONTHLY => null === $monthDay ? null : self::monthly($monthDay, $every),
                self::YEARLY => null === $monthDay ? null : self::yearly($monthDay, (int) ($parts['BYMONTH'] ?? 0)),
                default => null,
            };
        } catch (\DomainException) {
            return null;
        }

        return null !== $rhythm && $rhythm->rrule() === strtoupper($rrule) ? $rhythm : null;
    }

    public function rrule(): string
    {
        $parts = ['FREQ='.self::FREQUENCIES[$this->frequency]];
        if ($this->every > 1) {
            $parts[] = 'INTERVAL='.$this->every;
        }
        if (self::WEEKLY === $this->frequency) {
            $parts[] = 'BYDAY='.implode(',', $this->weekdays);
        }
        if (null !== $this->month) {
            $parts[] = 'BYMONTH='.$this->month;
        }
        if (null !== $this->monthDay) {
            $lastDayOfShorterMonths = $this->monthDay > 28 && (null === $this->month || $this->monthDay > self::shortestLength($this->month));
            $parts[] = $lastDayOfShorterMonths
                ? 'BYMONTHDAY='.implode(',', range(28, $this->monthDay)).';BYSETPOS=-1'
                : 'BYMONTHDAY='.$this->monthDay;
        }

        return implode(';', $parts);
    }

    /**
     * Monday to Friday, every week.
     */
    public function isWorkdays(): bool
    {
        return self::WEEKLY === $this->frequency && 1 === $this->every && ['MO', 'TU', 'WE', 'TH', 'FR'] === $this->weekdays;
    }

    /**
     * True when the day does not exist in every month (or year) – then the last day of the month counts.
     */
    public function fallsBackToLastDay(): bool
    {
        return null !== $this->monthDay && $this->monthDay > (null === $this->month ? 28 : self::shortestLength($this->month));
    }

    private static function monthDay(int $day): int
    {
        if ($day < 1 || $day > 31) {
            throw new \DomainException('A day of the month lies between 1 and 31.');
        }

        return $day;
    }

    /**
     * Days of the month in a year without 29 February.
     */
    private static function shortestLength(int $month): int
    {
        return (int) (new \DateTimeImmutable(\sprintf('2027-%02d-01', $month)))->format('t');
    }
}
