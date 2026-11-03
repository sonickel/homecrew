<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Who is responsible when no single person is (assignee_role of tasks, plans and lists, konzept-2 §5.2):
 * the admins or everybody with a role. No person and no role means "wer gerade da ist".
 */
enum AssigneeRole: string
{
    case Admins = 'admins';
    case Manager = 'manager';
    case Assistenz = 'assistenz';
    case Angehoerige = 'angehoerige';

    public function label(): string
    {
        return match ($this) {
            self::Admins => 'Admins',
            self::Manager => 'Manager',
            self::Assistenz => 'Assistenz (alle)',
            self::Angehoerige => 'Angehörige (alle)',
        };
    }
}
