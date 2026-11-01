<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * When a person gets notifications by e-mail (app_user.notify_email).
 */
enum EmailNotification: string
{
    case Aus = 'aus';
    case Sofort = 'sofort';
    case Taeglich = 'taeglich';
}
