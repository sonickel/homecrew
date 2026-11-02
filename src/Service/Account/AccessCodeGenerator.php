<?php

declare(strict_types=1);

namespace App\Service\Account;

/**
 * Start values an admin hands over on the access card (konzept-1 §4): generated, never typed by the admin,
 * so no weak start values exist. Characters that are easy to confuse (0/O, 1/l/I) are left out.
 */
final readonly class AccessCodeGenerator
{
    /** 31 lower-case characters: about 4.95 bits each, 59 bits for a start password. */
    private const string PASSWORD_ALPHABET = 'abcdefghjkmnpqrstuvwxyz23456789';
    /** 31 upper-case characters: 24 of them give about 119 bits for an invitation code. */
    private const string INVITATION_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const int INVITATION_LENGTH = 24;
    public const int PIN_LENGTH = 6;

    /**
     * Start password like "k7mp-x3qa-z9tf"; the person must replace it at the first login.
     */
    public function startPassword(): string
    {
        return implode('-', str_split(self::random(self::PASSWORD_ALPHABET, 12), 4));
    }

    /**
     * Start PIN with 6 digits; the person must replace it at the first PIN login (M4).
     */
    public function startPin(): string
    {
        return str_pad((string) random_int(0, 10 ** self::PIN_LENGTH - 1), self::PIN_LENGTH, '0', \STR_PAD_LEFT);
    }

    /**
     * Invitation code without separators; shown in groups of 4 and normalized with normalizeInvitationCode().
     */
    public function invitationCode(): string
    {
        return self::random(self::INVITATION_ALPHABET, self::INVITATION_LENGTH);
    }

    /**
     * Typed codes: case, spaces and dashes do not matter.
     */
    public static function normalizeInvitationCode(string $code): string
    {
        return strtoupper((string) preg_replace('/[^a-z0-9]/i', '', $code));
    }

    private static function random(string $alphabet, int $length): string
    {
        $result = '';
        $max = \strlen($alphabet) - 1;
        for ($i = 0; $i < $length; ++$i) {
            $result .= $alphabet[random_int(0, $max)];
        }

        return $result;
    }
}
