<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Whether guides need approval before they are published (app_setting.guide_publish_mode).
 */
enum GuidePublishMode: string
{
    case Freigabe = 'freigabe';
    case Direkt = 'direkt';
}
