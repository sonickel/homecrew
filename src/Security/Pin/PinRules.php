<?php

declare(strict_types=1);

namespace App\Security\Pin;

/**
 * Rules for a PIN the person chooses (decision 02.11.): exactly 6 digits, not all the same, no simple sequence.
 * That the new PIN differs from the old one is checked by App\Service\Tablet\PinLogin (it needs the hash).
 */
final class PinRules
{
    public const int LENGTH = 6;

    /**
     * @return string|null what is wrong with the PIN, or null when it may be used
     */
    public static function problem(string $pin): ?string
    {
        if (1 !== preg_match('/^\d{'.self::LENGTH.'}$/', $pin)) {
            return \sprintf('Die PIN hat genau %d Ziffern, ohne Leerzeichen.', self::LENGTH);
        }
        if (1 === \count(array_unique(str_split($pin)))) {
            return 'Bitte keine PIN aus lauter gleichen Ziffern – die errät man zu leicht.';
        }
        if (str_contains('0123456789', $pin) || str_contains('9876543210', $pin)) {
            return 'Bitte keine einfache Zahlenfolge wie 123456 – die errät man zu leicht.';
        }

        return null;
    }
}
