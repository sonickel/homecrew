<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * Rules for every password a person or the setup command chooses: at least 10 characters, not too easy to guess.
 */
final class PasswordRules
{
    public const int MIN_LENGTH = 10;

    /**
     * @return list<Constraint>
     */
    public static function constraints(): array
    {
        return [
            new NotBlank(message: 'Bitte gib ein Passwort ein.'),
            new Length(min: self::MIN_LENGTH, max: 4096, minMessage: 'Das Passwort braucht mindestens {{ limit }} Zeichen.'),
            new PasswordStrength(minScore: PasswordStrength::STRENGTH_MEDIUM, message: 'Das Passwort ist zu leicht zu erraten. Nimm ein längeres oder mehrere Wörter.'),
        ];
    }
}
