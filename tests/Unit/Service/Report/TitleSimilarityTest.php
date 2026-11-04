<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Report;

use App\Service\Report\TitleSimilarity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * "Auch bemerkt" instead of a second report: which titles count as the same thing (konzept-13 §2).
 */
final class TitleSimilarityTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function titles(): iterable
    {
        yield 'gleicher Titel' => ['Seife leer', 'Seife leer', true];
        yield 'Groß/klein und Satzzeichen' => ['Seife leer!', 'seife LEER', true];
        yield 'Wort im anderen Wort' => ['Seife leer', 'Flüssigseife fehlt', true];
        yield 'Umlaute geschrieben oder umschrieben' => ['Glühbirne Bad', 'Gluehbirne kaputt', true];
        yield 'ein Tippfehler' => ['Glübirne Bad', 'Glühbirne Flur', true];
        yield 'nur Füllwörter gemeinsam' => ['Seife ist leer', 'Milch ist leer', false];
        yield 'ganz anderes' => ['Glühbirne Bad', 'Waschmittel', false];
        yield 'kurze Wörter nur gleich' => ['Tee', 'Teelicht', false];
        yield 'nur Art-Wörter' => ['kaputt', 'kaputt', true];
    }

    #[DataProvider('titles')]
    public function testSimilarTitles(string $one, string $other, bool $similar): void
    {
        self::assertSame($similar, TitleSimilarity::isSimilar($one, $other));
        self::assertSame($similar, TitleSimilarity::isSimilar($other, $one));
    }

    public function testWordsLeaveOutWhatDoesNotMatter(): void
    {
        self::assertSame(['gluehbirne', 'bad'], TitleSimilarity::words('Die Glühbirne im Bad ist kaputt.'));
    }
}
