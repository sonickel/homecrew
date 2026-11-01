<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * How minijobs are registered (app_setting.minijob_procedure).
 */
enum MinijobProcedure: string
{
    case Haushaltsscheck = 'haushaltsscheck';
    case Gewerblich = 'gewerblich';
}
