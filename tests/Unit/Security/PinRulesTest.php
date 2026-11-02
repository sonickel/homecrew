<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\Pin\PinRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PinRulesTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function goodPins(): iterable
    {
        yield 'gemischt' => ['482913'];
        yield 'mit Null vorne' => ['048213'];
        yield 'Folge mit Lücke' => ['135790'];
    }

    #[DataProvider('goodPins')]
    public function testGoodPinIsAccepted(string $pin): void
    {
        self::assertNull(PinRules::problem($pin));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badPins(): iterable
    {
        yield 'zu kurz' => ['4829', 'genau 6 Ziffern'];
        yield 'zu lang' => ['4829135', 'genau 6 Ziffern'];
        yield 'Buchstaben' => ['48a913', 'genau 6 Ziffern'];
        yield 'Leerzeichen' => ['482 91', 'genau 6 Ziffern'];
        yield 'gleiche Ziffern' => ['777777', 'gleichen Ziffern'];
        yield 'aufsteigend' => ['123456', 'Zahlenfolge'];
        yield 'aufsteigend ab Null' => ['012345', 'Zahlenfolge'];
        yield 'absteigend' => ['987654', 'Zahlenfolge'];
    }

    #[DataProvider('badPins')]
    public function testBadPinIsRejectedWithReason(string $pin, string $reason): void
    {
        self::assertStringContainsString($reason, (string) PinRules::problem($pin));
    }
}
