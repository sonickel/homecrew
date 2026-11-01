<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Provider of the AI extension (app_setting.ai_provider).
 */
enum AiProvider: string
{
    case Anthropic = 'anthropic';
}
