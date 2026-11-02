<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Channel through which a logged action happened (audit_log.via).
 */
enum AuditVia: string
{
    case App = 'app';
    case Tablet = 'tablet';
    case Ki = 'ki';
    case System = 'system';
}
