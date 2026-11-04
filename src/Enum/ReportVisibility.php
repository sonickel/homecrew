<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Who sees a report (issue_report.visibility, konzept-13 §6): its title for everybody incl. the team tablet
 * (never the sender), or admins only.
 */
enum ReportVisibility: string
{
    case Alle = 'alle';
    case Admins = 'admins';
}
