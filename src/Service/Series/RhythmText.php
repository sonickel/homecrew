<?php

declare(strict_types=1);

namespace App\Service\Series;

use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A rhythm in plain words: "Jeden Montag", "Montags und donnerstags", "Alle 2 Wochen dienstags", "Werktags",
 * "Monatlich am 15.", "Monatlich am letzten Tag", "Jährlich am 1. März". The texts come translated.
 */
final readonly class RhythmText
{
    private const array DAYS = ['MO' => 'Montag', 'TU' => 'Dienstag', 'WE' => 'Mittwoch', 'TH' => 'Donnerstag', 'FR' => 'Freitag', 'SA' => 'Samstag', 'SU' => 'Sonntag'];
    private const array ON_DAYS = ['MO' => 'montags', 'TU' => 'dienstags', 'WE' => 'mittwochs', 'TH' => 'donnerstags', 'FR' => 'freitags', 'SA' => 'samstags', 'SU' => 'sonntags'];

    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function describe(Rhythm $rhythm): string
    {
        $count = ['%count%' => $rhythm->every];

        return match ($rhythm->frequency) {
            Rhythm::DAILY => 1 === $rhythm->every ? $this->trans('Täglich') : $this->trans('Alle %count% Tage', $count),
            Rhythm::WEEKLY => $this->weekly($rhythm),
            Rhythm::MONTHLY => $this->monthly($rhythm),
            default => $this->yearly($rhythm),
        };
    }

    /**
     * "Alle 14 Tage nach der letzten Erledigung".
     */
    public function afterCompletion(int $days): string
    {
        return 1 === $days ? $this->trans('Jeden Tag nach der letzten Erledigung') : $this->trans('Alle %count% Tage nach der letzten Erledigung', ['%count%' => $days]);
    }

    private function weekly(Rhythm $rhythm): string
    {
        if ($rhythm->isWorkdays()) {
            return $this->trans('Werktags');
        }
        if (1 === $rhythm->every && 7 === \count($rhythm->weekdays)) {
            return $this->trans('Täglich');
        }
        if (1 === $rhythm->every && 1 === \count($rhythm->weekdays)) {
            return $this->trans('Jeden %day%', ['%day%' => $this->trans(self::DAYS[$rhythm->weekdays[0]])]);
        }

        $days = array_map(fn (string $day): string => $this->trans(self::ON_DAYS[$day]), $rhythm->weekdays);
        $last = array_pop($days) ?? '';
        $list = [] === $days ? $last : $this->trans('%days% und %last%', ['%days%' => implode(', ', $days), '%last%' => $last]);

        return 1 === $rhythm->every ? mb_ucfirst($list) : $this->trans('Alle %count% Wochen %days%', ['%count%' => $rhythm->every, '%days%' => $list]);
    }

    private function monthly(Rhythm $rhythm): string
    {
        $every = $rhythm->every;
        if (31 === $rhythm->monthDay) {
            return 1 === $every ? $this->trans('Monatlich am letzten Tag') : $this->trans('Alle %count% Monate am letzten Tag', ['%count%' => $every]);
        }
        $text = 1 === $every
            ? $this->trans('Monatlich am %day%.', ['%day%' => $rhythm->monthDay])
            : $this->trans('Alle %count% Monate am %day%.', ['%count%' => $every, '%day%' => $rhythm->monthDay]);

        return $rhythm->fallsBackToLastDay() ? $this->trans('%rhythm% (sonst am letzten Tag)', ['%rhythm%' => $text]) : $text;
    }

    private function yearly(Rhythm $rhythm): string
    {
        // 2028 has a 29 February
        $date = new \DateTimeImmutable(\sprintf('2028-%02d-%02d', $rhythm->month, $rhythm->monthDay));
        $formatter = new \IntlDateFormatter('de', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, 'UTC', null, 'd. MMMM');
        $text = $this->trans('Jährlich am %date%', ['%date%' => (string) $formatter->format($date)]);

        return $rhythm->fallsBackToLastDay() ? $this->trans('%rhythm% (sonst am 28.)', ['%rhythm%' => $text]) : $text;
    }

    /**
     * @param array<string, string|int|null> $parameters
     */
    private function trans(string $text, array $parameters = []): string
    {
        return $this->translator->trans($text, $parameters);
    }
}
