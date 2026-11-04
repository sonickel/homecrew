<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What happens with a report (issue_report.follow_up, konzept-13 §4): an entry on a list or a task – both close the
 * report on their own – or a handover (M10).
 */
enum ReportFollowUp: string
{
    case Liste = 'liste';
    case Aufgabe = 'aufgabe';
    case Uebergabe = 'uebergabe';
}
