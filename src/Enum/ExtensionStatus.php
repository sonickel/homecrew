<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * State of an extension secret or connection (app_setting.ai_key_status, app_setting.mail_status).
 */
enum ExtensionStatus: string
{
    case Fehlt = 'fehlt';
    case Ungeprueft = 'ungeprueft';
    case Ok = 'ok';
    case Fehler = 'fehler';
}
