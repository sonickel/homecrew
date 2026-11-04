<?php

declare(strict_types=1);

namespace App\Service\Series;

/**
 * When a series has occurrences: an RRULE (RFC 5545) from its first day on, optionally until an end day,
 * without the days of a pause (no end = until further notice) and at a local time of day.
 * All dates are household dates (Y-m-d), the time is household time (H:i) in the household's time zone.
 */
final readonly class SeriesRule
{
    public function __construct(
        public string $rrule,
        public string $startsOn,
        public \DateTimeZone $timezone,
        public ?string $endsOn = null,
        public ?string $pausedFrom = null,
        public ?string $pausedUntil = null,
        public ?string $time = null,
    ) {
    }

    /**
     * @param string $day household date Y-m-d
     */
    public function isPausedOn(string $day): bool
    {
        return null !== $this->pausedFrom && $this->pausedFrom <= $day && (null === $this->pausedUntil || $this->pausedUntil >= $day);
    }

    /**
     * @param string $day household date Y-m-d
     */
    public function hasEndedBy(string $day): bool
    {
        return null !== $this->endsOn && $day > $this->endsOn;
    }
}
