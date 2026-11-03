<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Who may see a task, plan or appointment (konzept-2 §5.3); decided by App\Service\Visibility\VisibilityRules.
 */
enum Visibility: string
{
    case Alle = 'alle';
    case Zustaendig = 'zustaendig';
    case Admins = 'admins';
    case Privat = 'privat';

    public function label(): string
    {
        return match ($this) {
            self::Alle => 'Alle, auch am Team-Tablet',
            self::Zustaendig => 'Nur wer zuständig ist (und Admins)',
            self::Admins => 'Nur Admins',
            self::Privat => 'Nur ich (privat)',
        };
    }
}
