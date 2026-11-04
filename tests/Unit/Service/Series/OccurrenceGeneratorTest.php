<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Series;

use App\Service\Series\OccurrenceGenerator;
use App\Service\Series\Rhythm;
use App\Service\Series\SeriesRule;
use PHPUnit\Framework\TestCase;

/**
 * The shared series generator (plans M6, appointment and visit series M8, roster M9): end of month, leap year,
 * change to and from summer time in local time, pause, end.
 */
final class OccurrenceGeneratorTest extends TestCase
{
    private OccurrenceGenerator $generator;
    private \DateTimeZone $berlin;

    protected function setUp(): void
    {
        $this->generator = new OccurrenceGenerator();
        $this->berlin = new \DateTimeZone('Europe/Berlin');
    }

    public function testEveryMondayWithinTheRange(): void
    {
        $rule = $this->rule(Rhythm::weekly(['MO']), '2026-11-02');

        self::assertSame(['2026-11-02', '2026-11-09', '2026-11-16'], $this->days($rule, '2026-11-01', '2026-11-17'));
        self::assertSame(['2026-11-09', '2026-11-16'], $this->days($rule, '2026-11-03', '2026-11-17'), 'from the first day of the range on');
    }

    public function testNothingBeforeTheStart(): void
    {
        $rule = $this->rule(Rhythm::daily(), '2026-11-05');

        self::assertSame(['2026-11-05', '2026-11-06'], $this->days($rule, '2026-11-01', '2026-11-06'));
    }

    public function testEveryOtherWeekCountsFromTheStart(): void
    {
        $rule = $this->rule(Rhythm::weekly(['TU', 'FR'], 2), '2026-11-03');

        self::assertSame(['2026-11-03', '2026-11-06', '2026-11-17', '2026-11-20'], $this->days($rule, '2026-11-01', '2026-11-22'));
    }

    public function testMonthlyOnThe31stFallsOnTheLastDayOfShorterMonths(): void
    {
        $rule = $this->rule(Rhythm::monthly(31), '2027-01-31');

        self::assertSame(['2027-01-31', '2027-02-28', '2027-03-31', '2027-04-30'], $this->days($rule, '2027-01-01', '2027-04-30'));
    }

    public function testMonthlyOnThe30thInALeapYear(): void
    {
        $rule = $this->rule(Rhythm::monthly(30), '2028-01-01');

        self::assertSame(['2028-01-30', '2028-02-29', '2028-03-30'], $this->days($rule, '2028-01-01', '2028-03-31'));
    }

    public function testMonthlyOnAnOrdinaryDay(): void
    {
        $rule = $this->rule(Rhythm::monthly(15, 2), '2026-11-15');

        self::assertSame(['2026-11-15', '2027-01-15', '2027-03-15'], $this->days($rule, '2026-11-01', '2027-03-31'));
    }

    public function testYearlyOn29FebruaryFallsOn28FebruaryInOtherYears(): void
    {
        $rule = $this->rule(Rhythm::yearly(29, 2), '2028-02-29');

        self::assertSame(['2028-02-29', '2029-02-28', '2030-02-28', '2031-02-28', '2032-02-29'], $this->days($rule, '2028-01-01', '2032-12-31'));
    }

    public function testLocalTimeStaysAcrossTheChangeOfSummerTime(): void
    {
        $rule = $this->rule(Rhythm::weekly(['TU']), '2026-10-20', time: '10:00');

        $autumn = $this->generator->between($rule, '2026-10-20', '2026-10-27');
        self::assertSame(['2026-10-20 10:00 +02:00', '2026-10-27 10:00 +01:00'], array_map(static fn (\DateTimeImmutable $at): string => $at->format('Y-m-d H:i P'), $autumn));

        $spring = $this->generator->between($rule, '2027-03-23', '2027-03-30');
        self::assertSame(['2027-03-23 10:00 +01:00', '2027-03-30 10:00 +02:00'], array_map(static fn (\DateTimeImmutable $at): string => $at->format('Y-m-d H:i P'), $spring));
        self::assertSame('08:00', $spring[1]->setTimezone(new \DateTimeZone('UTC'))->format('H:i'));
    }

    public function testDailyHasEveryDayOnceAroundTheChangeOfSummerTime(): void
    {
        $rule = $this->rule(Rhythm::daily(), '2026-01-01');

        self::assertSame(['2026-03-28', '2026-03-29', '2026-03-30'], $this->days($rule, '2026-03-28', '2026-03-30'));
        self::assertSame(['2026-10-24', '2026-10-25', '2026-10-26'], $this->days($rule, '2026-10-24', '2026-10-26'));
    }

    public function testNoOccurrencesDuringAPause(): void
    {
        $rule = $this->rule(Rhythm::daily(), '2026-11-01', pausedFrom: '2026-11-04', pausedUntil: '2026-11-06');

        self::assertSame(['2026-11-02', '2026-11-03', '2026-11-07', '2026-11-08'], $this->days($rule, '2026-11-02', '2026-11-08'));
        self::assertSame('2026-11-07', $this->generator->next($rule, '2026-11-04')?->format('Y-m-d'), 'the next one after the pause');
    }

    public function testAPauseWithoutEndStopsTheSeries(): void
    {
        $rule = $this->rule(Rhythm::weekly(['MO']), '2026-11-02', pausedFrom: '2026-11-10');

        self::assertSame(['2026-11-02', '2026-11-09'], $this->days($rule, '2026-11-01', '2027-01-31'));
        self::assertNull($this->generator->next($rule, '2026-11-10'));
    }

    public function testTheEndDayStillCounts(): void
    {
        $rule = $this->rule(Rhythm::weekly(['MO']), '2026-11-02', endsOn: '2026-11-16');

        self::assertSame(['2026-11-09', '2026-11-16'], $this->days($rule, '2026-11-03', '2026-12-31'));
        self::assertSame('2026-11-16', $this->generator->next($rule, '2026-11-10')?->format('Y-m-d'));
        self::assertNull($this->generator->next($rule, '2026-11-17'));
    }

    public function testNextFindsAnOccurrenceFarAhead(): void
    {
        $rule = $this->rule(Rhythm::yearly(1, 3), '2026-03-01');

        self::assertSame('2027-03-01', $this->generator->next($rule, '2026-11-03')?->format('Y-m-d'));
        self::assertSame('2026-11-09', $this->generator->next($this->rule(Rhythm::weekly(['MO']), '2026-11-02'), '2026-11-09')?->format('Y-m-d'), 'the day itself counts');
    }

    private function rule(Rhythm $rhythm, string $startsOn, ?string $endsOn = null, ?string $pausedFrom = null, ?string $pausedUntil = null, ?string $time = null): SeriesRule
    {
        return new SeriesRule($rhythm->rrule(), $startsOn, $this->berlin, $endsOn, $pausedFrom, $pausedUntil, $time);
    }

    /**
     * @return list<string>
     */
    private function days(SeriesRule $rule, string $from, string $to): array
    {
        return array_map(static fn (\DateTimeImmutable $at): string => $at->format('Y-m-d'), $this->generator->between($rule, $from, $to));
    }
}
