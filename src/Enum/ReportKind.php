<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What a report is about (issue_report.kind, konzept-13 §2). "Melden" offers leer · kaputt · fehlt · sonstiges;
 * "nicht möglich" comes from a task, "messwert" from a measurement (stage 2).
 */
enum ReportKind: string
{
    case NichtMoeglich = 'nicht_moeglich';
    case Leer = 'leer';
    case Kaputt = 'kaputt';
    case Fehlt = 'fehlt';
    case Messwert = 'messwert';
    case Sonstiges = 'sonstiges';

    /**
     * The kinds the button "Melden" offers, in this order.
     *
     * @return list<self>
     */
    public static function forReporting(): array
    {
        return [self::Leer, self::Kaputt, self::Fehlt, self::Sonstiges];
    }

    public function label(): string
    {
        return match ($this) {
            self::NichtMoeglich => 'Nicht möglich',
            self::Leer => 'Leer',
            self::Kaputt => 'Kaputt',
            self::Fehlt => 'Fehlt',
            self::Messwert => 'Messwert',
            self::Sonstiges => 'Sonstiges',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::NichtMoeglich => 'tabler:circle-x',
            self::Leer => 'tabler:package-off',
            self::Kaputt => 'tabler:tool',
            self::Fehlt => 'tabler:zoom-question',
            self::Messwert => 'tabler:gauge',
            self::Sonstiges => 'tabler:dots',
        };
    }
}
