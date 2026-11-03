<?php

declare(strict_types=1);

namespace App\Enum;

enum TaskPriority: string
{
    case Normal = 'normal';
    case Wichtig = 'wichtig';
}
