<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Preferred calendar view of a person (app_user.calendar_view).
 */
enum CalendarView: string
{
    case Agenda = 'agenda';
    case Woche = 'woche';
    case Monat = 'monat';
}
