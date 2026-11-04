<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Status of a report (issue_report.status, konzept-13 §4): offen → in Arbeit (follow-up exists) → erledigt or verworfen.
 */
enum ReportStatus: string
{
    case Offen = 'offen';
    case InArbeit = 'in_arbeit';
    case Erledigt = 'erledigt';
    case Verworfen = 'verworfen';

    /**
     * Not closed yet: "offen" or "in Arbeit".
     */
    public function isOpen(): bool
    {
        return self::Offen === $this || self::InArbeit === $this;
    }

    public function label(): string
    {
        return match ($this) {
            self::Offen => 'Offen',
            self::InArbeit => 'In Arbeit',
            self::Erledigt => 'Erledigt',
            self::Verworfen => 'Verworfen',
        };
    }

    /**
     * @return list<self>
     */
    public static function open(): array
    {
        return [self::Offen, self::InArbeit];
    }
}
