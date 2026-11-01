<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Speech recognition for the AI assistant (app_setting.speech_recognition).
 */
enum SpeechRecognition: string
{
    case Lokal = 'lokal';
    case Aus = 'aus';
}
