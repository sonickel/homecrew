<?php

declare(strict_types=1);

namespace App\Service\Report;

/**
 * "Auch bemerkt" instead of a second report (konzept-13 §2): two titles are similar when they share a word that
 * matters – the same, one inside the other ("Seife" in "Flüssigseife") or one letter apart ("Glübirne").
 * Words like "leer", "kaputt" or "ist" do not count; case, umlauts and punctuation are ignored.
 */
final class TitleSimilarity
{
    private const int MIN_LENGTH = 3;
    private const int FUZZY_LENGTH = 4;

    private const array IGNORED = [
        'leer', 'kaputt', 'defekt', 'fehlt', 'fehlen', 'weg', 'alle', 'fast', 'mehr', 'kein', 'keine', 'nicht', 'geht',
        'ist', 'sind', 'der', 'die', 'das', 'den', 'dem', 'des', 'ein', 'eine', 'einer', 'und', 'oder', 'mit', 'von',
        'vom', 'zum', 'zur', 'auf', 'aus', 'bei', 'beim', 'noch', 'schon', 'bitte', 'neu', 'neue', 'neuer', 'nichts',
    ];

    public static function isSimilar(string $one, string $other): bool
    {
        $words = self::words($one);
        $others = self::words($other);
        if ([] === $words || [] === $others) {
            return self::normalize($one) === self::normalize($other);
        }
        foreach ($words as $word) {
            foreach ($others as $otherWord) {
                if (self::wordsMatch($word, $otherWord)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public static function words(string $title): array
    {
        $words = preg_split('/\s+/', self::normalize($title), -1, \PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            $words,
            static fn (string $word): bool => \strlen($word) >= self::MIN_LENGTH && !\in_array($word, self::IGNORED, true),
        ));
    }

    private static function normalize(string $title): string
    {
        $text = strtr(mb_strtolower($title), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss']);

        return trim((string) preg_replace('/[^a-z0-9]+/', ' ', $text));
    }

    private static function wordsMatch(string $word, string $other): bool
    {
        if ($word === $other) {
            return true;
        }
        if (min(\strlen($word), \strlen($other)) < self::FUZZY_LENGTH) {
            return false;
        }

        return str_contains($word, $other) || str_contains($other, $word) || levenshtein($word, $other) <= 1;
    }
}
