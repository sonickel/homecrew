<?php

declare(strict_types=1);

namespace App\Security\TwoFactor;

/**
 * Recovery codes for a lost phone or passkey: 10 codes "ABCDE-FGHJK", each usable once.
 */
final readonly class RecoveryCodeGenerator
{
    public const int COUNT = 10;
    /** 32 characters without the easily confused 0/O and 1/I – 5 bits per character, 50 bits per code. */
    private const string ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @return list<string>
     */
    public function generate(): array
    {
        $codes = [];
        while (\count($codes) < self::COUNT) {
            $code = $this->block().'-'.$this->block();
            $codes[$code] = $code;
        }

        return array_values($codes);
    }

    private function block(): string
    {
        $block = '';
        for ($i = 0; $i < 5; ++$i) {
            $block .= self::ALPHABET[random_int(0, \strlen(self::ALPHABET) - 1)];
        }

        return $block;
    }
}
