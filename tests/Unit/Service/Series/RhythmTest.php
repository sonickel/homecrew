<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Series;

use App\Service\Series\Rhythm;
use App\Service\Series\RhythmText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

/**
 * The rhythms the forms offer: their RRULE, reading it back and the text.
 */
final class RhythmTest extends TestCase
{
    /**
     * @return iterable<string, array{Rhythm, string, string}>
     */
    public static function rhythms(): iterable
    {
        yield 'täglich' => [Rhythm::daily(), 'FREQ=DAILY', 'Täglich'];
        yield 'alle 3 Tage' => [Rhythm::daily(3), 'FREQ=DAILY;INTERVAL=3', 'Alle 3 Tage'];
        yield 'jeden Montag' => [Rhythm::weekly(['MO']), 'FREQ=WEEKLY;BYDAY=MO', 'Jeden Montag'];
        yield 'zwei Tage, sortiert' => [Rhythm::weekly(['TH', 'MO']), 'FREQ=WEEKLY;BYDAY=MO,TH', 'Montags und donnerstags'];
        yield 'drei Tage' => [Rhythm::weekly(['MO', 'WE', 'FR']), 'FREQ=WEEKLY;BYDAY=MO,WE,FR', 'Montags, mittwochs und freitags'];
        yield 'werktags' => [Rhythm::weekly(['MO', 'TU', 'WE', 'TH', 'FR']), 'FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR', 'Werktags'];
        yield 'alle 2 Wochen' => [Rhythm::weekly(['TU'], 2), 'FREQ=WEEKLY;INTERVAL=2;BYDAY=TU', 'Alle 2 Wochen dienstags'];
        yield 'monatlich am 15.' => [Rhythm::monthly(15), 'FREQ=MONTHLY;BYMONTHDAY=15', 'Monatlich am 15.'];
        yield 'alle 3 Monate am 1.' => [Rhythm::monthly(1, 3), 'FREQ=MONTHLY;INTERVAL=3;BYMONTHDAY=1', 'Alle 3 Monate am 1.'];
        yield 'monatlich am 30.' => [Rhythm::monthly(30), 'FREQ=MONTHLY;BYMONTHDAY=28,29,30;BYSETPOS=-1', 'Monatlich am 30. (sonst am letzten Tag)'];
        yield 'Monatsende' => [Rhythm::monthly(31), 'FREQ=MONTHLY;BYMONTHDAY=28,29,30,31;BYSETPOS=-1', 'Monatlich am letzten Tag'];
        yield 'jährlich' => [Rhythm::yearly(1, 3), 'FREQ=YEARLY;BYMONTH=3;BYMONTHDAY=1', 'Jährlich am 1. März'];
        yield 'jährlich am 31.' => [Rhythm::yearly(31, 12), 'FREQ=YEARLY;BYMONTH=12;BYMONTHDAY=31', 'Jährlich am 31. Dezember'];
        yield 'jährlich am 29. Februar' => [Rhythm::yearly(29, 2), 'FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=28,29;BYSETPOS=-1', 'Jährlich am 29. Februar (sonst am 28.)'];
    }

    #[DataProvider('rhythms')]
    public function testRruleReadingBackAndText(Rhythm $rhythm, string $rrule, string $text): void
    {
        self::assertSame($rrule, $rhythm->rrule());
        self::assertEquals($rhythm, Rhythm::fromRrule($rrule));
        self::assertSame($text, (new RhythmText(new IdentityTranslator()))->describe($rhythm));
    }

    public function testRulesTheFormsDoNotWriteAreNotReadBack(): void
    {
        self::assertNull(Rhythm::fromRrule('FREQ=MONTHLY;BYDAY=1MO'));
        self::assertNull(Rhythm::fromRrule('FREQ=WEEKLY'));
        self::assertNull(Rhythm::fromRrule('FREQ=HOURLY'));
        self::assertNull(Rhythm::fromRrule('FREQ=YEARLY;BYMONTH=2;BYMONTHDAY=30'));
    }

    public function testInvalidRhythmsAreRefused(): void
    {
        $this->expectException(\DomainException::class);

        Rhythm::weekly([]);
    }

    public function testTextAfterCompletion(): void
    {
        $text = new RhythmText(new IdentityTranslator());

        self::assertSame('Alle 14 Tage nach der letzten Erledigung', $text->afterCompletion(14));
        self::assertSame('Jeden Tag nach der letzten Erledigung', $text->afterCompletion(1));
    }
}
